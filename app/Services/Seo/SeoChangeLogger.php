<?php

namespace App\Services\Seo;

use App\Models\SeoChangeLog;

/**
 * Grava o histórico de alterações do SEO sem segredos: chaves com token, secret,
 * password ou key têm o valor substituído antes de gravar.
 */
class SeoChangeLogger
{
    private const SENSITIVE = '/token|secret|password|credential|indexnow_key/i';

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function log(string $entityType, string|int|null $entityId, string $action, array $changes = [], ?int $userId = null): void
    {
        SeoChangeLog::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'action' => $action,
            'changes' => self::redact($changes) ?: null,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match(self::SENSITIVE, $key)) {
                $values[$key] = '[oculto]';
            } elseif (is_array($value)) {
                $values[$key] = self::redact($value);
            }
        }

        return $values;
    }
}
