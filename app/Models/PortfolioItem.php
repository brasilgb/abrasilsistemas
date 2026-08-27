<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $screenshot_url
 * @property string|null $site_url
 * @property int $sort_order
 * @property bool $is_published
 */
class PortfolioItem extends Model
{
    protected $fillable = ['title', 'description', 'screenshot_url', 'site_url', 'sort_order', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    /** @param Builder<PortfolioItem> $query
     * @return Builder<PortfolioItem>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
