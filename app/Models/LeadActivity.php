<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id',
    'user_id',
    'type',
    'status',
    'contacted_at',
    'next_follow_up_at',
    'description',
])]
class LeadActivity extends Model
{
    use HasFactory;

    public const TYPES = [
        'note' => 'Nota',
        'whatsapp' => 'WhatsApp',
        'call' => 'Ligação',
        'email' => 'E-mail',
        'meeting' => 'Reunião',
    ];

    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
            'next_follow_up_at' => 'date',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
