<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $status
 * @property string $trigger
 * @property int|null $user_id
 * @property int $pages_total
 * @property int $pages_scanned
 * @property int $critical_count
 * @property int $warning_count
 * @property int $opportunity_count
 * @property array<string, mixed>|null $summary
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 */
class SeoAudit extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'status', 'trigger', 'user_id', 'pages_total', 'pages_scanned',
        'critical_count', 'warning_count', 'opportunity_count', 'summary', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return HasMany<SeoAuditIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(SeoAuditIssue::class);
    }

    /** @return HasMany<SeoAuditPage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(SeoAuditPage::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public static function latestCompleted(): ?self
    {
        return static::query()->where('status', self::STATUS_COMPLETED)->latest('finished_at')->latest('id')->first();
    }
}
