<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $ebook_id
 * @property int $amount_cents
 * @property string $currency
 * @property string $status
 */
#[Fillable(['user_id', 'ebook_id', 'amount_cents', 'currency', 'status', 'payment_provider', 'external_reference', 'payment_id', 'idempotency_key', 'paid_at', 'expires_at', 'raw_response'])]
class EbookOrder extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ebook, $this> */
    public function ebook(): BelongsTo
    {
        return $this->belongsTo(Ebook::class);
    }
}
