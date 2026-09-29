<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\ProspectProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vincula os leads antigos (só com `leads.product`) ao catálogo prospect_products (CRM-WA-21).
 * Sem --apply só relata. Com --apply, grava prospect_product_id apenas quando o valor legado
 * tem exatamente um produto ativo correspondente; o resto fica como está, sem vínculo.
 * `leads.product` nunca é alterado; inclui os leads excluídos (soft delete).
 */
class LinkLeadProspectProducts extends Command
{
    protected $signature = 'leads:link-prospect-products {--apply : Grava os vínculos (sem a opção, só relata)}';

    protected $description = 'Relata os valores de leads.product e vincula os leads sem produto ao catálogo, quando a correspondência é exata';

    public function handle(): int
    {
        $rows = DB::table('leads')
            ->selectRaw('product, count(*) as total, sum(case when deleted_at is null then 1 else 0 end) as active_total, sum(case when prospect_product_id is null then 1 else 0 end) as unlinked_total')
            ->groupBy('product')
            ->orderBy('product')
            ->get();

        $linked = 0;
        $report = [];

        foreach ($rows as $row) {
            $matches = ProspectProduct::legacyProductMatches($row->product);
            $target = $matches->count() === 1 ? $matches->first() : null;

            $result = match (true) {
                (int) $row->unlinked_total === 0 => 'já vinculado',
                $target !== null => "#{$target->id} {$target->name}",
                $matches->isEmpty() => 'sem correspondência (mantido sem vínculo)',
                default => 'ambíguo: '.$matches->map(fn (ProspectProduct $product) => "#{$product->id} {$product->name}")->join(', ').' (mantido sem vínculo)',
            };

            if ($target !== null && $this->option('apply')) {
                $linked += DB::table('leads')
                    ->where('product', $row->product)
                    ->whereNull('prospect_product_id')
                    ->update(['prospect_product_id' => $target->id]);
            }

            $report[] = [
                $row->product,
                Lead::PRODUCTS[$row->product] ?? '—',
                (int) $row->total,
                (int) $row->active_total,
                (int) $row->total - (int) $row->active_total,
                (int) $row->unlinked_total,
                $result,
            ];
        }

        $this->table(['leads.product', 'Lead::PRODUCTS', 'Total', 'Ativos', 'Excluídos', 'Sem vínculo', 'Produto correspondente'], $report);

        $this->info($this->option('apply')
            ? "{$linked} lead(s) vinculado(s)."
            : 'Nada foi gravado. Use --apply para vincular os leads com correspondência exata.');

        return self::SUCCESS;
    }
}
