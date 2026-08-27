<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $author_name
 * @property string|null $author_role
 * @property string|null $photo_url
 * @property string $quote
 * @property int $sort_order
 * @property bool $is_published
 */
class Testimonial extends Model
{
    protected $fillable = ['author_name', 'author_role', 'photo_url', 'quote', 'sort_order', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    /** @param Builder<Testimonial> $query
     * @return Builder<Testimonial>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
