<?php

namespace App\Models;

use App\Services\VisitOrigin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
    'source_page',
    'entry_page',
    'referrer_host',
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_term',
    'utm_content',
    'marketing_consent_at',
    'status',
    'request_status',
    'lost_reason',
    'next_follow_up_at',
    'last_contacted_at',
    'notes',
])]
class Lead extends Model
{
    use HasFactory, SoftDeletes;

    /** Origem gravada pelo formulário de contato do site: só elas aparecem em "Solicitações". */
    public const SOURCE_SITE = 'site';

    /**
     * Situação comercial de uma solicitação recebida pelo site (`request_status`). O status comercial
     * antigo (`status`, do tempo do CRM) continua no banco só como histórico.
     */
    public const REQUEST_STATUSES = [
        'new' => 'Novo contato',
        'in_progress' => 'Em atendimento',
        'proposal' => 'Proposta enviada',
        'negotiation' => 'Negociação',
        'won' => 'Ganho',
        'lost' => 'Perdido',
        'archived' => 'Arquivada',
    ];

    /** Situações em andamento: um novo contato da mesma pessoa entra nelas; as demais estão encerradas. */
    public const OPEN_REQUEST_STATUSES = ['new', 'in_progress', 'proposal', 'negotiation'];

    public const PRODUCTS = [
        'vetoros' => 'VetorOS',
        'vetorsales' => 'VetorSales',
        'vetorleads' => 'VetorLeads',
        'vetorsmart' => 'VetorSmart',
        // Nome anterior do VetorSales: mantido para os leads já gravados e para as importações que ainda o enviam.
        'vetorpet' => 'VetorPet (atual VetorSales)',
        'site' => 'Site institucional',
        'landing_page' => 'Landing Page',
        'sistema_sob_medida' => 'Sistema sob medida',
        'aplicativo' => 'Aplicativo',
        'integracao' => 'Integração / API',
        'automacao' => 'Automação de processos',
        'seo' => 'SEO Profissional',
        'outro' => 'Outro',
    ];

    /**
     * Opções de "Tipo de projeto" no formulário de contato do site, na ordem de
     * exibição. As chaves são as mesmas de PRODUCTS, gravadas em `product`.
     */
    public const CONTACT_PROJECT_TYPES = [
        'vetoros' => 'Sistema pronto — VetorOS',
        'vetorsales' => 'Sistema pronto — VetorSales',
        'vetorleads' => 'Sistema pronto — VetorLeads',
        'vetorsmart' => 'Demonstração — VetorSmart',
        'sistema_sob_medida' => 'Sistema sob medida',
        'site' => 'Site',
        'landing_page' => 'Landing Page',
        'aplicativo' => 'Aplicativo',
        'automacao' => 'Automação',
        'integracao' => 'Integração / API',
        'seo' => 'SEO Profissional',
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
            'next_follow_up_at' => 'date',
            'last_contacted_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
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
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSiteRequests(Builder $query): void
    {
        $query->where('source', self::SOURCE_SITE);
    }

    public function isSiteRequest(): bool
    {
        return $this->source === self::SOURCE_SITE;
    }

    /** Origem comercial da solicitação, para a equipe (campanha, busca, indicação ou não identificada). */
    public function commercialOrigin(): string
    {
        return VisitOrigin::label($this->only(VisitOrigin::FIELDS));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function whatsappConversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class);
    }

    /**
     * Solicitações do site com o mesmo e-mail ou telefone, menos esta.
     *
     * @return Builder<self>
     */
    public function relatedSiteRequests(): Builder
    {
        return self::query()->siteRequests()->whereKeyNot($this->getKey())->where(function (Builder $query) {
            $query->whereRaw('1 = 0');

            if (filled($this->email)) {
                $query->orWhereRaw('LOWER(email) = ?', [strtolower($this->email)]);
            }

            if (filled($this->whatsapp)) {
                $query->orWhere('whatsapp', $this->whatsapp);
            }
        });
    }

    /**
     * Solicitação em aberto que o novo envio pode atualizar. Quem envia o formulário não prova quem é,
     * então só vale quando TUDO o que foi informado (e-mail e telefone) já está na solicitação. Com um
     * dado diferente ou a mais, o envio vira outra solicitação, e o painel mostra as duas como
     * relacionadas: um terceiro não altera nem completa os dados de ninguém.
     *
     * Só procura entre solicitações do site: um prospect importado com o mesmo e-mail não pode
     * "engolir" o contato, senão ele não apareceria em Solicitações.
     *
     * @param  array<string, mixed>  $data
     */
    public static function findOpenSiteRequest(array $data): ?self
    {
        $email = $data['email'] ?? null;
        $whatsapp = $data['whatsapp'] ?? null;

        if (blank($email) && blank($whatsapp)) {
            return null;
        }

        return self::query()
            ->siteRequests()
            ->whereIn('request_status', self::OPEN_REQUEST_STATUSES)
            ->when(filled($email), fn (Builder $query) => $query->whereRaw('LOWER(email) = ?', [strtolower((string) $email)]))
            ->when(filled($whatsapp), fn (Builder $query) => $query->where('whatsapp', $whatsapp))
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }
}
