<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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
    ];

    public const STATUSES = [
        'new' => 'Novo',
        'contacted' => 'Contatado',
        'interested' => 'Interessado',
        'meeting' => 'Reunião',
        'converted' => 'Convertido',
        'lost' => 'Perdido',
    ];

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

        if ($this->next_follow_up_at !== null) {
            if ($this->next_follow_up_at->isPast() && ! $this->next_follow_up_at->isToday()) {
                $score += 25;
            } elseif ($this->next_follow_up_at->isToday()) {
                $score += 18;
            } else {
                $score += 8;
            }
        }

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
     * Shared dedupe check (same e-mail, WhatsApp, or company+city+state)
     * used both by the admin lead form and the public contact form.
     *
     * @param  array<string, mixed>  $data
     */
    public static function isDuplicate(array $data, ?self $except = null): bool
    {
        $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $whatsapp = isset($data['whatsapp']) ? preg_replace('/\D+/', '', (string) $data['whatsapp']) : null;
        $company = trim((string) ($data['company_name'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $state = strtoupper(trim((string) ($data['state'] ?? '')));
        $hasCompanyLocation = $company !== '' && $city !== '' && $state !== '';

        if (! $email && ! $whatsapp && ! $hasCompanyLocation) {
            return false;
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
            ->exists();
    }
}
