<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    use HasFactory;

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
}
