<?php

namespace App\Models;

use App\Services\CompanyWhatsappConnection;
use App\Services\LeadWhatsappService;
use App\Services\WhatsappBusinessHours;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Fillable([
    'user_id',
    'product',
    'prospect_product_id',
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
    'trial_started_at',
    'trial_ends_at',
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
        'trial_state',
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
     * Situação do período de teste para as automações (atributo trial_state, scope trialState e
     * automation-state). trial_ends_at é o instante do vencimento. none = sem início ou prospect
     * encerrado; active = iniciado, sem vencimento ou vencimento ainda por vir; expired =
     * vencimento já alcançado (trial_ends_at <= agora).
     */
    public const TRIAL_STATES = [
        'none' => 'Sem teste',
        'active' => 'Em teste',
        'expired' => 'Teste vencido',
    ];

    /**
     * Situação do período de teste na tela do lead (CRM-WA-23), calculada das datas e do
     * produto, sem olhar o status comercial. none = teste nunca iniciado e produto sem prazo
     * (ou lead sem produto); not_started = produto com prazo e teste não iniciado; today = vence
     * ainda hoje em São Paulo; expired = vencimento já alcançado.
     */
    public const TRIAL_PERIOD_STATUSES = [
        'none' => 'Sem teste',
        'not_started' => 'Não iniciado',
        'active' => 'Em teste',
        'today' => 'Vence hoje',
        'expired' => 'Expirado',
    ];

    /**
     * Relógio da equipe comercial. O app grava em UTC; "hoje" e as datas digitadas sem fuso
     * (formulários, CSV) seguem este fuso.
     */
    public const FOLLOW_UP_TIMEZONE = 'America/Sao_Paulo';

    /** Horário assumido quando o próximo contato chega só com a data (CSV, integrações antigas). */
    public const FOLLOW_UP_DEFAULT_TIME = '09:00';

    /**
     * SLA da fila "Aguardando resposta": cada nível começa quando a espera atinge from_minutes,
     * inclusive (15:00 já é Atenção). A espera é o tempo de atendimento desde awaiting_reply_since
     * (WhatsappBusinessHours): fora do expediente o relógio fica parado. Fonte única dos limites:
     * filtro sla, contador de críticos e o badge da fila (a página recebe esta lista).
     * Não é prioridade comercial: só mede há quanto tempo o cliente espera resposta.
     */
    public const WHATSAPP_REPLY_SLA = [
        'normal' => ['label' => 'Normal', 'from_minutes' => 0],
        'attention' => ['label' => 'Atenção', 'from_minutes' => 15],
        'urgent' => ['label' => 'Urgente', 'from_minutes' => 30],
        'critical' => ['label' => 'Crítico', 'from_minutes' => 60],
    ];

    /**
     * Nível seguinte de WHATSAPP_REPLY_SLA para uma espera de $businessSeconds de atendimento
     * (o primeiro limite ainda não atingido), ou null no último nível (Crítico).
     */
    public static function nextWhatsappReplySla(int $businessSeconds): ?string
    {
        foreach (self::WHATSAPP_REPLY_SLA as $level => ['from_minutes' => $fromMinutes]) {
            if ($fromMinutes * 60 > $businessSeconds) {
                return $level;
            }
        }

        return null;
    }

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
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
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

        // Produto/Serviço (CRM-WA-21): o vínculo com prospect_products é a fonte preferida e
        // `product` (texto legado) segue preenchido para quem ainda o lê (templates WhatsApp,
        // e-mail do site, API de automação). Vínculo escolhido → o texto legado acompanha.
        // Só o texto veio (API, CSV, formulário do site) → vínculo só com correspondência exata.
        static::saving(function (self $lead): void {
            if ($lead->isDirty('prospect_product_id')) {
                if ($lead->prospect_product_id !== null) {
                    $lead->product = ProspectProduct::query()->findOrFail($lead->prospect_product_id)->legacyProductKey();
                }

                return;
            }

            if ($lead->isDirty('product')) {
                $lead->setAttribute('prospect_product_id', ProspectProduct::resolveLegacyProduct($lead->product)?->id);
            }
        });

        // Toda mudança do próximo contato, venha de qualquer tela, fica no histórico como nota:
        // não é mensagem WhatsApp, então não aparece na conversa nem conta como não lida.
        // Mudança de responsável (formulário, lote ou transferência na fila) também fica no histórico
        // como nota. "Assumir atendimento" grava a própria nota: usa UPDATE direto e não passa aqui.
        static::updated(function (self $lead): void {
            if (! $lead->wasChanged('user_id')) {
                return;
            }

            $lead->activities()->create([
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => self::ownerChangeDescription($lead->getOriginal('user_id'), $lead->user_id),
            ]);
        });

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
     * Datas do teste gravadas em UTC. Instante (ou texto com fuso) vale como está. Só a data
     * (formato do CRM-WA-16) é dia de calendário em São Paulo: o início vale às 00:00 desse dia e
     * o término, que era o último dia do teste, vence às 00:00 do dia seguinte.
     */
    public function setTrialStartedAtAttribute(mixed $value): void
    {
        $this->attributes['trial_started_at'] = self::normalizeTrialAt($value, lastDay: false);
    }

    public function setTrialEndsAtAttribute(mixed $value): void
    {
        $this->attributes['trial_ends_at'] = self::normalizeTrialAt($value, lastDay: true);
    }

    private static function normalizeTrialAt(mixed $value, bool $lastDay): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $date = Carbon::instance($value);
        } else {
            $value = trim((string) $value);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $date = Carbon::parse($value, self::FOLLOW_UP_TIMEZONE)->startOfDay();
                $date = $lastDay ? $date->addDay() : $date;
            } else {
                $hasTimezone = preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $value) === 1;
                $date = Carbon::parse($value, $hasTimezone ? null : self::FOLLOW_UP_TIMEZONE);
            }
        }

        return $date->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    /**
     * Inicia o período de teste (CRM-WA-23): início agora e vencimento agora + trial_days do
     * produto vinculado (0 vence no mesmo instante), com o mesmo "agora" nos dois. Produto inativo
     * já vinculado também vale. O UPDATE condicional é atômico, como em assignTo: entre dois
     * cliques ou requisições simultâneas, só a primeira grava as datas e a nota do histórico; as
     * outras recebem false e nada muda. Nunca recalcula um teste já iniciado. Lead Convertido/
     * Perdido não inicia (CRM-WA-23.1), inclusive se mudou para encerrado depois de carregado.
     */
    public function startTrial(User $user): bool
    {
        $product = $this->prospectProduct;

        if ($this->isClosed() || $product === null || $product->trial_days === null) {
            return false;
        }

        $startedAt = now()->startOfSecond();
        $endsAt = $startedAt->addDays($product->trial_days);

        // Prazo além do que o banco guarda (trial_days não tem máximo no cadastro).
        if ($endsAt->year > 9999) {
            return false;
        }

        return DB::transaction(function () use ($user, $product, $startedAt, $endsAt): bool {
            $started = self::query()
                ->whereKey($this->getKey())
                ->where('prospect_product_id', $product->id)
                ->whereNotIn('status', self::CLOSED_STATUSES)
                ->whereNull('trial_started_at')
                ->update(['trial_started_at' => $startedAt, 'trial_ends_at' => $endsAt]);

            if ($started === 0) {
                return false;
            }

            $this->activities()->create([
                'user_id' => $user->id,
                'type' => 'note',
                'description' => sprintf(
                    'Período de teste iniciado (%s, %d %s). Vencimento: %s.',
                    $product->name,
                    $product->trial_days,
                    $product->trial_days === 1 ? 'dia' : 'dias',
                    Carbon::instance($endsAt)->setTimezone(self::FOLLOW_UP_TIMEZONE)->format('d/m/Y H:i'),
                ),
            ]);

            return true;
        });
    }

    /**
     * Motivo pelo qual o teste não pode ser iniciado agora, ou null quando pode. Mesmas regras
     * de startTrial, para a tela e as mensagens.
     */
    public function trialStartBlocker(): ?string
    {
        if ($this->trial_started_at !== null) {
            return 'O período de teste deste lead já foi iniciado.';
        }

        if ($this->isClosed()) {
            return 'Lead convertido ou perdido não pode iniciar período de teste.';
        }

        if ($this->prospectProduct === null) {
            return 'Vincule o lead a um produto/serviço antes de iniciar o teste.';
        }

        if ($this->prospectProduct->trial_days === null) {
            return 'O produto/serviço deste lead não tem período de teste.';
        }

        if (now()->addDays($this->prospectProduct->trial_days)->year > 9999) {
            return 'O prazo de teste do produto/serviço é longo demais.';
        }

        return null;
    }

    /** Situação do teste na tela do lead (chave de TRIAL_PERIOD_STATUSES). */
    public function trialPeriodStatus(): string
    {
        if ($this->trial_started_at === null) {
            return $this->prospectProduct?->trial_days === null ? 'none' : 'not_started';
        }

        if ($this->trial_ends_at === null) {
            return 'active';
        }

        if ($this->trial_ends_at->lte(now())) {
            return 'expired';
        }

        // Vencimento às 00:00 de amanhã também é "hoje": o teste acaba no fim do dia.
        return $this->trial_ends_at->lte(now(self::FOLLOW_UP_TIMEZONE)->addDay()->startOfDay()) ? 'today' : 'active';
    }

    /**
     * Resumo do período de teste para a tela do lead. As datas vão em ISO (UTC).
     *
     * @return array<string, mixed>
     */
    public function trialPeriod(): array
    {
        $status = $this->trialPeriodStatus();

        return [
            'status' => $status,
            'status_label' => self::TRIAL_PERIOD_STATUSES[$status],
            'product_name' => $this->prospectProduct?->name,
            'trial_days' => $this->prospectProduct?->trial_days,
            'started_at' => $this->trial_started_at?->toIso8601String(),
            'ends_at' => $this->trial_ends_at?->toIso8601String(),
            'can_start' => $this->trialStartBlocker() === null,
            'closed' => $this->isClosed(),
        ];
    }

    /**
     * Último dia do teste em São Paulo (Y-m-d): o dia do último instante antes do vencimento.
     * Para as datas do CRM-WA-16 é o mesmo término de antes; para 12/10 17:00 é 12/10.
     */
    public function trialLastDay(): ?string
    {
        return $this->trial_ends_at?->toImmutable()->subSecond()->setTimezone(self::FOLLOW_UP_TIMEZONE)->toDateString();
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

    private static function ownerChangeDescription(mixed $from, mixed $to): string
    {
        $names = User::query()->whereKey(array_filter([$from, $to]))->pluck('name', 'id');
        $name = fn (mixed $id): string => $names[$id] ?? "usuário #{$id}";

        if ($to === null) {
            return 'Responsável removido (era '.$name($from).').';
        }

        if ($from === null) {
            return 'Responsável definido como '.$name($to).'.';
        }

        return 'Responsável alterado de '.$name($from).' para '.$name($to).'.';
    }

    /**
     * "Assumir atendimento": atribui o lead ao usuário só se ele ainda estiver sem responsável.
     * O UPDATE condicional é atômico no banco: entre dois cliques simultâneos, só o primeiro altera
     * a linha, e o segundo recebe false em vez de sobrescrever. Não mexe em mensagens (read_at,
     * fila de resposta), status nem próximo contato.
     */
    public function assignTo(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $assigned = self::query()
                ->whereKey($this->getKey())
                ->whereNull('user_id')
                ->update(['user_id' => $user->id]);

            if ($assigned === 0) {
                return false;
            }

            $this->activities()->create([
                'user_id' => $user->id,
                'type' => 'note',
                'description' => "Atendimento assumido por {$user->name}.",
            ]);

            return true;
        });
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

    /** Situação do período de teste (chave de TRIAL_STATES); Convertido/Perdido é sempre none. */
    public function getTrialStateAttribute(): string
    {
        if ($this->isClosed() || $this->trial_started_at === null) {
            return 'none';
        }

        if ($this->trial_ends_at !== null && $this->trial_ends_at->lte(now())) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Chave da automação de teste vencido (CRM-WA-18) quando o lead é elegível; nula caso contrário.
     * Elegível = trial_state expired (prospect aberto, teste iniciado, vencimento alcançado).
     * A data é o último dia do teste (trialLastDay), não o dia da consulta: um vencimento, uma chave.
     */
    public function trialExpiredAutomationKey(): ?string
    {
        if ($this->trial_state !== 'expired' || $this->trial_ends_at === null) {
            return null;
        }

        return 'trial_expired:'.$this->trialLastDay();
    }

    /** Texto da mensagem de teste vencido (CRM-WA-24): trial_expiration_text do produto vinculado. */
    public function trialExpirationMessage(): ?string
    {
        return $this->prospectProduct?->trialExpirationText();
    }

    /**
     * Corte da automação trial_expired (CRM-WA-24): só testes vencidos a partir deste instante
     * entram, para não enviar retroativamente a quem já estava vencido quando a automação foi
     * ligada. Setting ausente ou inválido: nulo, e a automação fica desligada (nenhum candidato).
     */
    public static function trialExpiredAutomationSince(): ?CarbonInterface
    {
        $value = Setting::valueFor(Setting::TRIAL_EXPIRED_AUTOMATION_SINCE);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Candidatos à mensagem automática de teste vencido (CRM-WA-24), neste instante. Fonte única
     * da regra: a listagem do n8n e o eligible do automation-state vêm daqui. Todos valem:
     * automação ligada e vencimento a partir do corte; horário de atendimento aberto
     * (WhatsappBusinessHours); trial_state expired (aberto, iniciado, vencimento alcançado);
     * produto com trial_expiration_text; WhatsApp válido; sem cliente aguardando resposta
     * (awaitingWhatsappReply, bloqueio temporário); chave trial_expired ainda não reservada.
     * Somente leitura. Ordem: vencimento mais antigo primeiro.
     *
     * @return Collection<int, self>
     */
    public static function trialExpiredAutomationCandidates(?int $leadId = null): Collection
    {
        $since = self::trialExpiredAutomationSince();

        if ($since === null || ! app(WhatsappBusinessHours::class)->isOpen(now())) {
            return new Collection;
        }

        $leads = self::query()
            ->when($leadId !== null, fn (Builder $query) => $query->whereKey($leadId))
            ->trialState('expired')
            ->where('trial_ends_at', '>=', $since)
            ->whereNotNull('whatsapp')
            ->whereHas('prospectProduct', fn (Builder $product) => $product->whereNotNull('trial_expiration_text'))
            ->whereNot(fn (Builder $query) => $query->awaitingWhatsappReply())
            ->with('prospectProduct')
            ->orderBy('trial_ends_at')
            ->orderBy('id')
            ->get()
            // Espaços e número válido seguem as regras em PHP (trialExpirationText, normalizeNumber).
            ->filter(fn (self $lead) => $lead->trialExpirationMessage() !== null
                && LeadWhatsappService::normalizeNumber($lead->whatsapp) !== null);

        if ($leads->isEmpty()) {
            return $leads->values();
        }

        // A chave depende do último dia em São Paulo, então a reserva é conferida aqui, numa consulta.
        $reserved = LeadAutomationExecution::query()
            ->whereIn('lead_id', $leads->modelKeys())
            ->whereIn('automation_key', $leads->map(fn (self $lead) => $lead->trialExpiredAutomationKey())->unique()->values()->all())
            ->get(['lead_id', 'automation_key'])
            ->map(fn (LeadAutomationExecution $execution) => $execution->lead_id.'|'.$execution->automation_key)
            ->all();

        return $leads
            ->reject(fn (self $lead) => in_array($lead->id.'|'.$lead->trialExpiredAutomationKey(), $reserved, true))
            ->values();
    }

    /** Este lead é candidato agora à mensagem de teste vencido: mesma regra da listagem. */
    public function isTrialExpiredAutomationCandidate(): bool
    {
        return $this->trialExpiredAutomationKey() !== null
            && self::trialExpiredAutomationCandidates($this->id)->isNotEmpty();
    }

    /**
     * Última execução da automação de teste vencido e a resposta do cliente a ela (CRM-WA-28),
     * ou null sem execução trial_expired. Somente leitura; não olha o texto das mensagens.
     * sent_at = mensagem vinculada pelo /whatsapp/log (sem vínculo: envio não confirmado, nunca
     * respondida). Respondida = a mensagem seguinte da conversa, na ordem (created_at, id), é uma
     * inbound: inbound anterior ao envio não conta e uma outbound posterior (manual, pelo CRM ou
     * pelo celular) passa a ser a mensagem respondida. replied_at = essa primeira inbound.
     *
     * @return array{automation_key: string, executed_at: CarbonInterface, sent_at: ?CarbonInterface, replied: bool, replied_at: ?CarbonInterface}|null
     */
    public function lastTrialExpiredExecution(): ?array
    {
        // Prefixo trial_expired: por faixa (';' é o caractere seguinte a ':'), sem o curinga _ do LIKE.
        $execution = LeadAutomationExecution::query()
            ->where('lead_id', $this->id)
            ->where('automation_key', '>=', 'trial_expired:')
            ->where('automation_key', '<', 'trial_expired;')
            ->with('activity')
            ->orderByDesc('executed_at')
            ->orderByDesc('id')
            ->first();

        if ($execution === null) {
            return null;
        }

        $sent = $execution->activity;
        $next = $sent === null ? null : $this->activities()
            ->conversation()
            ->where(fn (Builder $later) => $later
                ->where('created_at', '>', $sent->created_at)
                ->orWhere(fn (Builder $sameSecond) => $sameSecond
                    ->where('created_at', $sent->created_at)
                    ->where('id', '>', $sent->id)))
            ->orderBy('created_at')
            ->orderBy('id')
            ->first(['id', 'direction', 'created_at']);

        $replied = $next?->direction === LeadActivity::DIRECTION_INBOUND;

        return [
            'automation_key' => $execution->automation_key,
            'executed_at' => $execution->executed_at,
            'sent_at' => $sent?->created_at,
            'replied' => $replied,
            'replied_at' => $replied ? $next->created_at : null,
        ];
    }

    /**
     * Leads na situação de teste informada. Mesma regra de trial_state; situação desconhecida não filtra.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function trialState(Builder $query, string $state): void
    {
        if (! array_key_exists($state, self::TRIAL_STATES)) {
            return;
        }

        if ($state === 'none') {
            $query->where(fn (Builder $none) => $none
                ->whereIn('status', self::CLOSED_STATUSES)
                ->orWhereNull('trial_started_at'));

            return;
        }

        $query->whereNotIn('status', self::CLOSED_STATUSES)->whereNotNull('trial_started_at');

        match ($state) {
            'active' => $query->where(fn (Builder $active) => $active
                ->whereNull('trial_ends_at')
                ->orWhere('trial_ends_at', '>', now())),
            'expired' => $query->where('trial_ends_at', '<=', now()),
        };
    }

    /**
     * Leads aguardando resposta no WhatsApp: a mensagem mais recente da conversa veio do cliente.
     * Equivale a existir uma inbound sem outbound posterior. Independe de read_at (ler não
     * responde), do ACK da outbound e do status comercial (Convertido/Perdido também entram).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function awaitingWhatsappReply(Builder $query): void
    {
        $query->whereExists(self::unansweredWhatsappInbound());
    }

    /** Este lead está aguardando resposta no WhatsApp: mesma regra do scope awaitingWhatsappReply. */
    public function isAwaitingWhatsappReply(): bool
    {
        return self::query()->whereKey($this->getKey())->awaitingWhatsappReply()->exists();
    }

    /**
     * Instante da última mensagem WhatsApp de cada direção da conversa (created_at, mesma ordem
     * da fila de resposta), em uma única consulta. Não usa last_contacted_at, que a inbound
     * também atualiza.
     *
     * @return array{inbound: ?CarbonInterface, outbound: ?CarbonInterface}
     */
    public function lastWhatsappMessageTimes(): array
    {
        $row = $this->activities()
            ->conversation()
            ->toBase()
            ->selectRaw('max(case when direction = ? then created_at end) as last_inbound_at', [LeadActivity::DIRECTION_INBOUND])
            ->selectRaw('max(case when direction = ? then created_at end) as last_outbound_at', [LeadActivity::DIRECTION_OUTBOUND])
            ->first();

        $parse = fn (mixed $value): ?CarbonInterface => $value === null ? null : $this->asDateTime($value);

        return [
            'inbound' => $parse($row?->last_inbound_at),
            'outbound' => $parse($row?->last_outbound_at),
        ];
    }

    /**
     * Fila "Aguardando resposta": só os leads aguardando, com o início da espera (primeira
     * inbound sem resposta), a última inbound e o texto dela, do que espera há mais tempo
     * para o mais recente.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function whatsappReplyQueue(Builder $query): void
    {
        $query
            ->awaitingWhatsappReply()
            ->addSelect([
                'awaiting_reply_since' => self::unansweredWhatsappInbound()->selectRaw('min(awaiting_inbound.created_at)'),
                'last_inbound_at' => self::unansweredWhatsappInbound()->selectRaw('max(awaiting_inbound.created_at)'),
                'last_inbound_message' => self::unansweredWhatsappInbound()
                    ->select('awaiting_inbound.description')
                    ->orderByDesc('awaiting_inbound.created_at')
                    ->orderByDesc('awaiting_inbound.id')
                    ->limit(1),
            ])
            ->withCasts([
                'awaiting_reply_since' => 'datetime',
                'last_inbound_at' => 'datetime',
            ])
            ->orderBy('awaiting_reply_since')
            ->orderBy($query->qualifyColumn('id'));
    }

    /**
     * Leads aguardando resposta no nível de SLA informado (chave de WHATSAPP_REPLY_SLA). A espera é
     * medida em tempo de atendimento (WhatsappBusinessHours), que só cresce enquanto o início recua:
     * "atingiu N minutos úteis" equivale a começar até o corte calculado em PHP. Assim o banco segue
     * com o mesmo EXISTS da fila: min(inbound sem resposta) <= corte equivale a existir uma inbound
     * sem resposta até o corte. Nível desconhecido não filtra.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function whatsappReplySla(Builder $query, string $level): void
    {
        $levels = array_keys(self::WHATSAPP_REPLY_SLA);
        $position = array_search($level, $levels, true);

        if ($position === false) {
            return;
        }

        $now = now();
        $businessHours = app(WhatsappBusinessHours::class);
        // Nulo: nenhuma espera alcança esse tempo útil (sem dias de atendimento configurados).
        $waitingSince = function (int $minutes) use ($now, $businessHours): ?QueryBuilder {
            if ($minutes === 0) {
                return self::unansweredWhatsappInbound();
            }

            $cutoff = $businessHours->cutoff($now, $minutes);

            return $cutoff === null ? null : self::unansweredWhatsappInbound()->where('awaiting_inbound.created_at', '<=', $cutoff);
        };

        $reached = $waitingSince(self::WHATSAPP_REPLY_SLA[$level]['from_minutes']);

        if ($reached === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists($reached);

        $next = $levels[$position + 1] ?? null;
        $nextReached = $next === null ? null : $waitingSince(self::WHATSAPP_REPLY_SLA[$next]['from_minutes']);

        if ($nextReached !== null) {
            $query->whereNotExists($nextReached);
        }
    }

    /**
     * Mensagens recebidas do lead (leads.id da consulta externa) sem nenhuma outbound depois
     * delas, na mesma ordem da conversa (created_at, id). Toda outbound registrada conta como
     * resposta: do CRM (/log) ou de fora dele (/outbound-external), com qualquer ACK.
     */
    private static function unansweredWhatsappInbound(): QueryBuilder
    {
        return DB::table('lead_activities', 'awaiting_inbound')
            ->whereColumn('awaiting_inbound.lead_id', 'leads.id')
            ->where('awaiting_inbound.type', 'whatsapp')
            ->where('awaiting_inbound.direction', LeadActivity::DIRECTION_INBOUND)
            ->whereNotExists(fn (QueryBuilder $reply) => $reply
                ->from('lead_activities', 'awaiting_reply')
                ->whereColumn('awaiting_reply.lead_id', 'awaiting_inbound.lead_id')
                ->where('awaiting_reply.type', 'whatsapp')
                ->where('awaiting_reply.direction', LeadActivity::DIRECTION_OUTBOUND)
                ->where(fn (QueryBuilder $later) => $later
                    ->whereColumn('awaiting_reply.created_at', '>', 'awaiting_inbound.created_at')
                    ->orWhere(fn (QueryBuilder $sameSecond) => $sameSecond
                        ->whereColumn('awaiting_reply.created_at', 'awaiting_inbound.created_at')
                        ->whereColumn('awaiting_reply.id', '>', 'awaiting_inbound.id'))));
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

    /** @return BelongsTo<ProspectProduct, $this> */
    public function prospectProduct(): BelongsTo
    {
        return $this->belongsTo(ProspectProduct::class);
    }

    /** @return HasMany<LeadActivity, $this> */
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
