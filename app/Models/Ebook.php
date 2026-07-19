<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['volume_slug', 'title', 'description', 'price_cents', 'currency', 'version', 'file_path', 'status', 'published_at'])]
class Ebook extends Model
{
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<EbookOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(EbookOrder::class);
    }

    /** @return HasMany<EbookEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(EbookEntitlement::class);
    }
}
