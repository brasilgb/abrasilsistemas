<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Redirecionamento gerenciado pelo painel. status_code 410 marca uma URL removida
 * de propósito (sem destino): responde "Gone" e preserva o histórico do endereço.
 *
 * @property int $id
 * @property string $source_path
 * @property string|null $target_url
 * @property int $status_code
 * @property bool $is_active
 * @property string|null $notes
 * @property int $hits
 * @property Carbon|null $last_hit_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeoRedirect extends Model
{
    public const STATUS_CODES = [301 => 'Permanente (301)', 302 => 'Temporário (302)', 410 => 'Removida (410)'];

    protected $fillable = ['source_path', 'target_url', 'status_code', 'is_active', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
