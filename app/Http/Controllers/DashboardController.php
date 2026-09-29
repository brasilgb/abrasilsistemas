<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\ProspectProduct;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $total = Lead::query()->count();
        $converted = Lead::query()->where('status', 'converted')->count();
        $lost = Lead::query()->where('status', 'lost')->count();
        $open = Lead::query()->whereNotIn('status', ['converted', 'lost'])->count();
        $overdue = Lead::query()->followUpState('overdue')->count();
        $today = Lead::query()->followUpState('today')->count();
        $highPriority = Lead::query()
            ->whereNotIn('status', ['converted', 'lost'])
            ->get()
            ->where('priority', 'high')
            ->count();

        return Inertia::render('dashboard', [
            'metrics' => [
                'total' => $total,
                'open' => $open,
                'converted' => $converted,
                'lost' => $lost,
                'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
                'high_priority' => $highPriority,
                'follow_ups' => [
                    'overdue' => $overdue,
                    'today' => $today,
                ],
                'awaiting_reply' => Lead::query()->awaitingWhatsappReply()->count(),
                'awaiting_reply_critical' => Lead::query()->whatsappReplySla('critical')->count(),
                'by_status' => Lead::query()
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status'),
                'by_product' => $this->leadsByProduct(),
            ],
            'recentLeads' => Lead::query()
                ->latest()
                ->limit(8)
                ->with('prospectProduct:id,name')
                ->get(['id', 'company_name', 'contact_name', 'product', 'prospect_product_id', 'status', 'next_follow_up_at', 'created_at']),
            'priorityLeads' => Lead::query()
                ->whereNotIn('status', ['converted', 'lost'])
                ->with('prospectProduct:id,name')
                ->get(['id', 'company_name', 'contact_name', 'product', 'prospect_product_id', 'status', 'next_follow_up_at', 'whatsapp', 'email', 'website', 'instagram', 'notes', 'created_at'])
                ->sortByDesc('lead_score')
                ->take(8)
                ->values(),
            'nextFollowUps' => Lead::query()
                ->whereNotNull('next_follow_up_at')
                ->whereNotIn('status', ['converted', 'lost'])
                ->orderBy('next_follow_up_at')
                ->limit(8)
                ->with('prospectProduct:id,name')
                ->get(['id', 'company_name', 'contact_name', 'product', 'prospect_product_id', 'status', 'next_follow_up_at']),
            'recentActivities' => LeadActivity::query()
                ->with('lead:id,company_name')
                ->latest('contacted_at')
                ->limit(8)
                ->get(['id', 'lead_id', 'type', 'status', 'description', 'contacted_at']),
            'products' => Lead::PRODUCTS,
            'statuses' => Lead::STATUSES,
        ]);
    }

    /**
     * Leads por produto: cada produto ativo do catálogo (mesmo sem leads), os inativos que ainda
     * têm leads e, à parte, os leads sem vínculo agrupados pelo texto legado (não somem da conta).
     *
     * @return list<array{key: string, label: string, total: int}>
     */
    private function leadsByProduct(): array
    {
        $totals = Lead::query()
            ->selectRaw('prospect_product_id, product, count(*) as total')
            ->groupBy('prospect_product_id', 'product')
            ->get();

        $linked = $totals->whereNotNull('prospect_product_id')
            ->groupBy('prospect_product_id')
            ->map(fn ($rows) => (int) $rows->sum('total'));

        $catalog = ProspectProduct::query()
            ->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $linked->keys()))
            ->orderBy('name')
            ->get(['id', 'name', 'active'])
            ->map(fn (ProspectProduct $product) => [
                'key' => (string) $product->id,
                'label' => $product->active ? $product->name : "{$product->name} (inativo)",
                'total' => $linked->get($product->id, 0),
            ]);

        $legacy = $totals->whereNull('prospect_product_id')
            ->sortBy('product')
            ->map(fn ($row) => [
                'key' => $row->product,
                'label' => (Lead::PRODUCTS[$row->product] ?? $row->product).' (sem vínculo)',
                'total' => (int) $row->getAttribute('total'),
            ]);

        return [...$catalog, ...$legacy->values()];
    }
}
