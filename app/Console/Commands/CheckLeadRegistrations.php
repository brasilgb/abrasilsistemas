<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\ProductRegistrationChecker;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Verificação periódica de cadastro pela API dos produtos (CRM-WA-29). Consulta os leads
 * aguardando cadastro (Lead::awaitingRegistrationCheck) não verificados dentro do intervalo,
 * nunca verificados primeiro, até o teto por execução. 429 de um produto encerra as consultas
 * dele nesta execução. Cadastro confirmado inicia o trial (Lead::startTrial); erro não muda nada.
 */
class CheckLeadRegistrations extends Command
{
    protected $signature = 'leads:check-registrations
        {--lead= : Só este lead (ignora o intervalo)}
        {--limit= : Máximo de leads nesta execução}';

    protected $description = 'Consulta a API de cadastro dos produtos em modo API e inicia o teste de quem se cadastrou';

    public function handle(ProductRegistrationChecker $checker): int
    {
        $leadId = $this->option('lead');
        $limit = (int) ($this->option('limit') ?? config('services.prospect_registration_check.batch_limit'));
        $interval = max(1, (int) config('services.prospect_registration_check.interval_minutes'));

        $leads = Lead::query()
            ->awaitingRegistrationCheck()
            ->when($leadId !== null, fn (Builder $query) => $query->whereKey((int) $leadId))
            ->when($leadId === null, fn (Builder $query) => $query->where(fn (Builder $due) => $due
                ->whereNull('registration_checked_at')
                ->orWhere('registration_checked_at', '<=', now()->subMinutes($interval))))
            ->with('prospectProduct')
            ->orderByRaw('registration_checked_at is not null')
            ->orderBy('registration_checked_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $totals = ['registered' => 0, 'not_registered' => 0, 'error' => 0, 'trial_started' => 0, 'skipped' => 0];
        $rateLimited = [];

        foreach ($leads as $lead) {
            if (in_array($lead->prospect_product_id, $rateLimited, true)) {
                $totals['skipped']++;

                continue;
            }

            $result = $checker->checkAndRecord($lead);

            if ($result === null) {
                $totals['skipped']++;

                continue;
            }

            $totals[$result->status]++;
            $totals['trial_started'] += $result->trialStarted ? 1 : 0;

            if ($result->error === 'rate_limited') {
                $rateLimited[] = $lead->prospect_product_id;
            }
        }

        $this->info(sprintf(
            'Leads verificados: %d. Cadastrados: %d (testes iniciados: %d). Não cadastrados: %d. Erros: %d. Ignorados: %d.',
            $leads->count() - $totals['skipped'],
            $totals['registered'],
            $totals['trial_started'],
            $totals['not_registered'],
            $totals['error'],
            $totals['skipped'],
        ));

        return self::SUCCESS;
    }
}
