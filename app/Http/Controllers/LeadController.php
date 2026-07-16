<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\LeadRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'city', 'state', 'industry', 'follow_up']);

        $leads = Lead::query()
            ->with('user:id,name')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('company_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('whatsapp', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['city'] ?? null, fn ($query, string $city) => $query->where('city', 'like', "%{$city}%"))
            ->when($filters['state'] ?? null, fn ($query, string $state) => $query->where('state', strtoupper($state)))
            ->when($filters['industry'] ?? null, fn ($query, string $industry) => $query->where('industry', 'like', "%{$industry}%"))
            ->when($filters['follow_up'] ?? null, function ($query, string $followUp) {
                $query
                    ->when($followUp === 'overdue', fn ($query) => $query->whereDate('next_follow_up_at', '<', today()))
                    ->when($followUp === 'today', fn ($query) => $query->whereDate('next_follow_up_at', today()))
                    ->when($followUp === 'upcoming', fn ($query) => $query->whereDate('next_follow_up_at', '>', today()));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('leads/index', [
            'filters' => $filters,
            'leads' => $leads,
            'metrics' => $this->metrics(),
            'statuses' => Lead::STATUSES,
        ]);
    }

    private function metrics(): array
    {
        $total = Lead::query()->count();
        $converted = Lead::query()->where('status', 'converted')->count();
        $open = Lead::query()->whereNotIn('status', ['converted', 'lost'])->count();
        $overdue = Lead::query()->whereDate('next_follow_up_at', '<', today())->count();
        $today = Lead::query()->whereDate('next_follow_up_at', today())->count();
        $upcoming = Lead::query()->whereDate('next_follow_up_at', '>', today())->count();

        return [
            'total' => $total,
            'open' => $open,
            'converted' => $converted,
            'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
            'follow_ups' => [
                'overdue' => $overdue,
                'today' => $today,
                'upcoming' => $upcoming,
            ],
            'by_status' => Lead::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];
    }

    public function create(): Response
    {
        return Inertia::render('leads/create', [
            'statuses' => Lead::STATUSES,
        ]);
    }

    public function store(LeadRequest $request): RedirectResponse
    {
        $lead = Lead::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        if ($lead->status !== 'new') {
            $lead->forceFill(['last_contacted_at' => now()])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead criado.']);

        return to_route('leads.index');
    }

    public function edit(Lead $lead): Response
    {
        return Inertia::render('leads/edit', [
            'activityTypes' => LeadActivity::TYPES,
            'lead' => $lead->load([
                'activities' => fn ($query) => $query->with('user:id,name')->latest(),
                'user:id,name',
            ]),
            'statuses' => Lead::STATUSES,
        ]);
    }

    public function update(LeadRequest $request, Lead $lead): RedirectResponse
    {
        $previousStatus = $lead->status;

        $lead->fill($request->validated());

        if ($previousStatus === 'new' && $lead->status !== 'new' && $lead->last_contacted_at === null) {
            $lead->last_contacted_at = now();
        }

        $lead->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead atualizado.']);

        return to_route('leads.index');
    }
}
