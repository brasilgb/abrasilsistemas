<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ocorrência de uma auditoria. severity: critical (impede indexação ou quebra a página),
 * warning (prejudica o resultado) ou opportunity (melhoria recomendada).
 *
 * @property int $id
 * @property int $seo_audit_id
 * @property string|null $url
 * @property string|null $subject
 * @property string $severity
 * @property string $code
 * @property string $message
 * @property array<string, mixed>|null $context
 */
class SeoAuditIssue extends Model
{
    public const CRITICAL = 'critical';

    public const WARNING = 'warning';

    public const OPPORTUNITY = 'opportunity';

    public const SEVERITIES = [self::CRITICAL => 'Crítico', self::WARNING => 'Aviso', self::OPPORTUNITY => 'Oportunidade'];

    protected $fillable = ['seo_audit_id', 'url', 'subject', 'severity', 'code', 'message', 'context'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    /** @return BelongsTo<SeoAudit, $this> */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'seo_audit_id');
    }
}
