<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'ebook_id', 'ebook_order_id', 'granted_at'])]
class EbookEntitlement extends Model
{
    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
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

    /** @return BelongsTo<EbookOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(EbookOrder::class, 'ebook_order_id');
    }
}
