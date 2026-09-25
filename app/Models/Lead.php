<?php

namespace App\Models;

use App\Services\CompanyWhatsappConnection;
use App\Services\LeadWhatsappService;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'user_id',
    'product',
    'company_name',
    'address',
    'contact_name',
    'industry',
    'category',
    'city',
    'state',
    'phone',
    'whatsapp',
    'email',
    'website',
    'has_website',
    'site_status',
    'can_improve',
    'opportunity',
    'maps_url',
    'rating',
    'reviews',
    'captured_at',
    'instagram',
    'source',
    'status',
    'lost_reason',
    'next_follow_up_at',
    'last_contacted_at',
    'notes',
])]
class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = [
        'lead_score',
        'priority',
        'follow_up_state',
    ];

    public const STATUSES = [
        'new' => 'Novo',
        'contacted' => 'Contatado',
        'interested' => 'Interessado',
        'meeting' => 'Reunião',
        'converted' => 'Convertido',
        'lost' => 'Perdido',
    ];

    /** Estágios encerrados: nunca contam como follow-up pendente, mesmo com data gravada. */
    public const CLOSED_STATUSES = ['converted', 'lost'];

    /**
     * Situação do próximo contato de um prospect aberto (atributo follow_up_state e filtro follow_up).
     * overdue = o horário já passou; today = ainda hoje; upcoming = depois de hoje.
     */
    public const FOLLOW_UP_STATES = [
        'overdue' => 'Atrasado',
        'today' => 'Hoje',
        'upcoming' => 'Próximo',
        'none' => 'Sem follow-up',
    ];

    /**
     * Relógio da equipe comercial. O app grava em UTC; "hoje" e as datas digitadas sem fuso
     * (formulários, CSV) seguem este fuso.
     */
    public const FOLLOW_UP_TIMEZONE = 'America/Sao_Paulo';

    /** Horário assumido quando o próximo contato chega só com a data (CSV, integrações antigas). */
    public const FOLLOW_UP_DEFAULT_TIME = '09:00';

    public const LOST_REASONS = [
        'price' => 'Preço',
        'no_interest' => 'Sem interesse',
        'competitor' => 'Fechou com concorrente',
        'no_response' => 'Sem resposta',
        'bad_fit' => 'Fora do perfil',
        'other' => 'Outro',
    ];

    public const PRODUCTS = [
        'vetoros' => 'VetorOS',
        'vetorpet' => 'VetorPet',
        'site' => 'Site institucional',
        'landing_page' => 'Landing Page',
        'sistema_sob_medida' => 'Sistema sob medida',
        'aplicativo' => 'Aplicativo',
        'integracao' => 'Integração / API',
        'outro' => 'Outro',
    ];

    protected function casts(): array
    {
        return [
            'has_website' => 'boolean',
            'can_improve' => 'boolean',
            'rating' => 'decimal:2',
            'reviews' => 'integer',
            'captured_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'last_contacted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // `account_id` is a leftover column from migrations that ran on the
        // local/production database but were never committed to the repo —
        // it doesn't exist on a fresh schema (e.g. the sqlite test database
        // built from the committed migrations only). Where it *does* exist
        // and is NOT NULL, default it to the current user's account, or the
        // sole existing account for unauthenticated writes (e.g. the public
        // contact form), since this app currently only has a single account.
        static::creating(function (self $lead): void {
            if ($lead->account_id || ! Schema::hasColumn($lead->getTable(), 'account_id')) {
                return;
            }

            $lead->account_id = auth()->user()?->account_id ?? Account::query()->value('id');
        });

        // Toda mudança do próximo contato, venha de qualquer tela, fica no histórico como nota:
        // não é mensagem WhatsApp, então não aparece na conversa nem conta como não lida.
        static::updated(function (self $lead): void {
            if (! $lead->wasChanged('next_follow_up_at')) {
                return;
            }

            $lead->activities()->create([
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => self::followUpChangeDescription(
                    $lead->getOriginal('next_follow_up_at'),
                    $lead->next_follow_up_at,
                ),
            ]);
        });
    }

    /**
     * Grava sempre em UTC, sem segundos. Texto sem fuso (datetime-local do formulário, CSV) é
     * horário de Brasília; só a data vale às 09:00. Com fuso (ISO da conversa), vale o instante.
     */
    public function setNextFollowUpAtAttribute(mixed $value): void
    {
        $this->attributes['next_follow_up_at'] = self::normalizeFollowUpAt($value);
    }

    public static function normalizeFollowUpAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $date = Carbon::instance($value);
        } else {
            $value = trim((string) $value);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $value .= ' '.self::FOLLOW_UP_DEFAULT_TIME;
            }

            $hasTimezone = preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $value) === 1;
            $date = Carbon::parse($value, $hasTimezone ? null : self::FOLLOW_UP_TIMEZONE);
        }

        return $date->setTimezone(config('app.timezone'))->startOfMinute()->format('Y-m-d H:i:s');
    }

    /** Fim do dia comercial de hoje, no fuso do app (para comparar com o banco). */
    public static function followUpTodayEndsAt(): CarbonInterface
    {
        return now(self::FOLLOW_UP_TIMEZONE)->endOfDay()->setTimezone(config('app.timezone'));
    }

    public static function formatFollowUpAt(DateTimeInterface $value): string
    {
        $date = Carbon::instance($value)->setTimezone(self::FOLLOW_UP_TIMEZONE);

        return $date->format('d/m/Y').' às '.$date->format('H:i');
    }

    private static function followUpChangeDescription(?DateTimeInterface $from, ?DateTimeInterface $to): string
    {
        if ($to === null) {
            return 'Próximo contato removido'.($from ? ' (estava em '.self::formatFollowUpAt($from).').' : '.');
        }

        if ($from === null) {
            return 'Próximo contato definido para '.self::formatFollowUpAt($to).'.';
        }

        return 'Próximo contato alterado de '.self::formatFollowUpAt($from).' para '.self::formatFollowUpAt($to).'.';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /** Situação do próximo contato (chave de FOLLOW_UP_STATES); nula para Convertido/Perdido. */
    public function getFollowUpStateAttribute(): ?string
    {
        if ($this->isClosed()) {
            return null;
        }

        if ($this->next_follow_up_at === null) {
            return 'none';
        }

        if ($this->next_follow_up_at->lt(now())) {
            return 'overdue';
        }

        return $this->next_follow_up_at->lte(self::followUpTodayEndsAt()) ? 'today' : 'upcoming';
    }

    /**
     * Prospects abertos na situação de follow-up informada. Mesma regra de follow_up_state.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function followUpState(Builder $query, string $state): void
    {
        if (! array_key_exists($state, self::FOLLOW_UP_STATES)) {
            return;
        }

        $query->whereNotIn('status', self::CLOSED_STATUSES);

        match ($state) {
            'overdue' => $query->where('next_follow_up_at', '<', now()),
            'today' => $query->whereBetween('next_follow_up_at', [now(), self::followUpTodayEndsAt()]),
            'upcoming' => $query->where('next_follow_up_at', '>', self::followUpTodayEndsAt()),
            'none' => $query->whereNull('next_follow_up_at'),
        };
    }

    public function getLeadScoreAttribute(): int
    {
        if ($this->status === 'converted') {
            return 100;
        }

        if ($this->status === 'lost') {
            return 0;
        }

        $score = [
            'new' => 10,
            'contacted' => 25,
            'interested' => 45,
            'meeting' => 60,
        ][$this->status] ?? 10;

        $score += [
            'overdue' => 25,
            'today' => 18,
            'upcoming' => 8,
        ][$this->follow_up_state] ?? 0;

        if ($this->whatsapp) {
            $score += 8;
        }

        if ($this->email) {
            $score += 4;
        }

        if ($this->contact_name) {
            $score += 4;
        }

        if ($this->website || $this->instagram) {
            $score += 3;
        }

        if ($this->notes) {
            $score += 3;
        }

        return min($score, 100);
    }

    public function getPriorityAttribute(): string
    {
        if ($this->lead_score >= 70) {
            return 'high';
        }

        if ($this->lead_score >= 40) {
            return 'medium';
        }

        return 'low';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    /**
     * Mensagens WhatsApp recebidas ainda não vistas no CRM. Na listagem, use
     * withCount('unreadWhatsappMessages') para contar sem N+1.
     *
     * @return HasMany<LeadActivity, $this>
     */
    public function unreadWhatsappMessages(): HasMany
    {
        return $this->activities()->unreadInbound();
    }

    /**
     * Lead cujo WhatsApp corresponde ao número recebido (só dígitos), com a mesma equivalência
     * usada no WhatsApp da empresa: com ou sem 55 e com ou sem o nono dígito. Com mais de um
     * lead no mesmo número, fica o contatado mais recentemente.
     */
    public static function findByWhatsappNumber(string $number): ?self
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (strlen($digits) < 8) {
            return null;
        }

        // Os 8 últimos dígitos existem em todas as variações do número, mesmo gravado com máscara
        // (a importação do n8n não normaliza). O LIKE só pré-filtra; a comparação é feita abaixo.
        $pattern = '%'.implode('%', str_split(substr($digits, -8)));
        $target = LeadWhatsappService::normalizeNumber($digits) ?? $digits;

        return self::query()
            ->where('whatsapp', 'like', $pattern)
            ->orderByDesc('last_contacted_at')
            ->orderByDesc('id')
            ->get()
            ->first(fn (self $lead) => CompanyWhatsappConnection::sameNumber(
                LeadWhatsappService::normalizeNumber($lead->whatsapp) ?? $lead->whatsapp,
                $target,
            ));
    }

    /**
     * Shared dedupe check (same e-mail, WhatsApp, or company+city+state)
     * used both by the admin lead form and the public contact form.
     *
     * @param  array<string, mixed>  $data
     */
    public static function isDuplicate(array $data, ?self $except = null): bool
    {
        return self::findDuplicate($data, $except) !== null;
    }

    /**
     * Find the existing lead matching the shared deduplication rules.
     *
     * @param  array<string, mixed>  $data
     */
    public static function findDuplicate(array $data, ?self $except = null): ?self
    {
        $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $whatsapp = isset($data['whatsapp']) ? preg_replace('/\D+/', '', (string) $data['whatsapp']) : null;
        $company = trim((string) ($data['company_name'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $state = strtoupper(trim((string) ($data['state'] ?? '')));
        $hasCompanyLocation = $company !== '' && $city !== '' && $state !== '';

        if (! $email && ! $whatsapp && ! $hasCompanyLocation) {
            return null;
        }

        return self::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->where(function ($query) use ($email, $whatsapp, $company, $city, $state, $hasCompanyLocation) {
                if ($email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }

                if ($whatsapp) {
                    $query->orWhere('whatsapp', $whatsapp);
                }

                if ($hasCompanyLocation) {
                    $query->orWhere(function ($query) use ($company, $city, $state) {
                        $query->whereRaw('LOWER(company_name) = ?', [strtolower($company)])
                            ->whereRaw('LOWER(city) = ?', [strtolower($city)])
                            ->where('state', $state);
                    });
                }
            })
            ->first();
    }
}
