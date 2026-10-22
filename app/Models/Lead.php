<?php

namespace App\Models;

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
     * Situação de uma solicitação recebida pelo site (`request_status`). Não é funil comercial:
     * o status comercial antigo (`status`) continua no banco só como histórico.
     */
    public const REQUEST_STATUSES = [
        'new' => 'Nova',
        'in_progress' => 'Em atendimento',
        'answered' => 'Respondida',
        'done' => 'Concluída',
        'archived' => 'Arquivada',
    ];

    /** Situações que ainda pedem ação da equipe. */
    public const OPEN_REQUEST_STATUSES = ['new', 'in_progress', 'answered'];

    public const PRODUCTS = [
        'vetoros' => 'VetorOS',
        'vetorpet' => 'VetorPet',
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
        'vetorpet' => 'Sistema pronto — VetorPet',
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
     * Solicitação anterior da mesma pessoa (mesmo e-mail ou WhatsApp), para o formulário do site
     * não duplicar contatos. Só procura entre solicitações do site: um prospect importado com o
     * mesmo e-mail não pode "engolir" o contato, senão ele não apareceria em Solicitações.
     *
     * @param  array<string, mixed>  $data
     */
    public static function findSiteRequestDuplicate(array $data): ?self
    {
        $email = filled($data['email'] ?? null) ? strtolower(trim((string) $data['email'])) : null;
        $whatsapp = filled($data['whatsapp'] ?? null) ? preg_replace('/\D+/', '', (string) $data['whatsapp']) : null;

        if (! $email && ! $whatsapp) {
            return null;
        }

        return self::query()
            ->siteRequests()
            ->where(function ($query) use ($email, $whatsapp) {
                if ($email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }

                if ($whatsapp) {
                    $query->orWhere('whatsapp', $whatsapp);
                }
            })
            ->oldest('id')
            ->first();
    }
}
