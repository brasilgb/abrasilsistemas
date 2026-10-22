<?php

namespace App\Console\Commands;

use App\Jobs\RunSeoAudit;
use App\Models\SeoAudit;
use App\Services\Seo\SeoAuditor;
use Illuminate\Console\Command;

class SeoAuditCommand extends Command
{
    protected $signature = 'seo:audit {--sync : Executa agora, sem a fila} {--trigger=manual : manual ou scheduled}';

    protected $description = 'Executa a auditoria interna de SEO do site';

    public function handle(SeoAuditor $auditor): int
    {
        if (SeoAudit::query()->whereIn('status', [SeoAudit::STATUS_QUEUED, SeoAudit::STATUS_RUNNING])->where('created_at', '>', now()->subMinutes(15))->exists()) {
            $this->warn('Já existe uma auditoria em andamento.');

            return self::SUCCESS;
        }

        $audit = SeoAudit::query()->create(['status' => SeoAudit::STATUS_QUEUED, 'trigger' => $this->option('trigger') === 'scheduled' ? 'scheduled' : 'manual']);

        if (! $this->option('sync')) {
            RunSeoAudit::dispatch($audit);
            $this->info("Auditoria #{$audit->id} enviada para a fila.");

            return self::SUCCESS;
        }

        $audit = $auditor->run($audit);
        $this->line("Auditoria #{$audit->id}: {$audit->status} — {$audit->pages_scanned} páginas, {$audit->critical_count} críticos, {$audit->warning_count} avisos, {$audit->opportunity_count} oportunidades.");

        if ($audit->error) {
            $this->error($audit->error);
        }

        return $audit->status === SeoAudit::STATUS_COMPLETED ? self::SUCCESS : self::FAILURE;
    }
}
