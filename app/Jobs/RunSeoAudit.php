<?php

namespace App\Jobs;

use App\Models\SeoAudit;
use App\Services\Seo\SeoAuditor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Executa uma auditoria de SEO na fila. Única: não roda duas auditorias ao mesmo tempo.
 */
class RunSeoAudit implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Abaixo do --timeout=90 do worker de produção (docker-compose).
    public int $timeout = 85;

    public int $uniqueFor = 600;

    public function __construct(public SeoAudit $audit) {}

    public function uniqueId(): string
    {
        return 'seo-audit';
    }

    public function handle(SeoAuditor $auditor): void
    {
        $auditor->run($this->audit);
    }

    public function failed(?\Throwable $exception): void
    {
        $this->audit->forceFill([
            'status' => SeoAudit::STATUS_FAILED,
            'finished_at' => now(),
            'error' => 'A auditoria foi interrompida (tempo limite ou erro do worker).',
        ])->save();
    }
}
