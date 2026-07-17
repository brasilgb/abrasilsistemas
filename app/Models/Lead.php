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
    'contact_name',
    'industry',
    'city',
    'state',
    'phone',
    'whatsapp',
    'email',
    'website',
    'instagram',
    'source',
    'status',
    'next_follow_up_at',
    'last_contacted_at',
    'notes',
])]
class Lead extends Model
{
    use HasFactory;

    public const STATUSES = [
        'new' => 'Novo',
        'contacted' => 'Contatado',
        'interested' => 'Interessado',
        'meeting' => 'Reunião',
        'converted' => 'Convertido',
        'lost' => 'Perdido',
    ];

    public const PRODUCTS = [
        'vetoros' => 'VetorOS',
        'vetorpet' => 'VetorPet',
    ];

    protected function casts(): array
    {
        return [
            'next_follow_up_at' => 'date',
            'last_contacted_at' => 'datetime',
        ];
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
