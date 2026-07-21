<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $blog_category_id
 * @property string $title
 * @property string $slug
 * @property string $status
 * @property Carbon|null $published_at
 */
class BlogPost extends Model
{
    protected $fillable = ['user_id', 'blog_category_id', 'title', 'slug', 'excerpt', 'body', 'cover_image_url', 'status', 'published_at', 'featured'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'featured' => 'boolean'];
    }

    /** @param Builder<BlogPost> $query
     * @return Builder<BlogPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<BlogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /** @return HasMany<BlogComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }
}
