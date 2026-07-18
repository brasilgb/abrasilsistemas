<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
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
        $overdue = Lead::query()->whereDate('next_follow_up_at', '<', today())->count();
        $today = Lead::query()->whereDate('next_follow_up_at', today())->count();
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
                'by_status' => Lead::query()
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status'),
                'by_product' => Lead::query()
                    ->selectRaw('product, count(*) as total')
                    ->groupBy('product')
                    ->pluck('total', 'product'),
            ],
            'recentLeads' => Lead::query()
                ->latest()
                ->limit(8)
                ->get(['id', 'company_name', 'contact_name', 'product', 'status', 'next_follow_up_at', 'created_at']),
            'priorityLeads' => Lead::query()
                ->whereNotIn('status', ['converted', 'lost'])
                ->get(['id', 'company_name', 'contact_name', 'product', 'status', 'next_follow_up_at', 'created_at'])
                ->sortByDesc('lead_score')
                ->take(8)
                ->values(),
            'nextFollowUps' => Lead::query()
                ->whereNotNull('next_follow_up_at')
                ->whereNotIn('status', ['converted', 'lost'])
                ->orderBy('next_follow_up_at')
                ->limit(8)
                ->get(['id', 'company_name', 'contact_name', 'product', 'status', 'next_follow_up_at']),
            'recentActivities' => LeadActivity::query()
                ->with('lead:id,company_name')
                ->latest('contacted_at')
                ->limit(8)
                ->get(['id', 'lead_id', 'type', 'status', 'description', 'contacted_at']),
            'products' => Lead::PRODUCTS,
            'statuses' => Lead::STATUSES,
        ]);
    }
}
