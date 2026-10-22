<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de alterações do módulo de SEO (configurações, metadados e redirecionamentos).
 * Nunca guarda tokens ou credenciais: SeoChangeLogger remove essas chaves antes de gravar.
 *
 * @property int $id
 * @property string $entity_type
 * @property string|null $entity_id
 * @property string $action
 * @property array<string, mixed>|null $changes
 * @property int|null $user_id
 */
class SeoChangeLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['entity_type', 'entity_id', 'action', 'changes', 'user_id'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
