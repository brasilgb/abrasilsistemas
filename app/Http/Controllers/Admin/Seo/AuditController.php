<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Jobs\RunSeoAudit;
use App\Models\SeoAudit;
use App\Models\SeoAuditIssue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(): Response
    {
        $audits = SeoAudit::query()->with('user:id,name')->latest('id')->limit(20)->get();
        $latest = SeoAudit::latestCompleted();

        return Inertia::render('admin/seo/audits/index', [
            'audits' => $audits->map(fn (SeoAudit $audit) => [
                ...$audit->only(['id', 'status', 'trigger', 'pages_total', 'pages_scanned', 'critical_count', 'warning_count', 'opportunity_count', 'error', 'started_at', 'finished_at', 'created_at']),
                'user' => $audit->user?->name,
            ]),
            'latestId' => $latest?->id,
            // Evolução: as últimas auditorias concluídas, da mais antiga para a mais nova.
            'history' => SeoAudit::query()->where('status', SeoAudit::STATUS_COMPLETED)->latest('finished_at')->limit(12)->get(['id', 'finished_at', 'critical_count', 'warning_count', 'opportunity_count', 'pages_scanned'])->reverse()->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $running = SeoAudit::query()->whereIn('status', [SeoAudit::STATUS_QUEUED, SeoAudit::STATUS_RUNNING])->where('created_at', '>', now()->subMinutes(15))->exists();

        if ($running) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Já existe uma auditoria em andamento.']);

            return back();
        }

        $audit = SeoAudit::query()->create(['status' => SeoAudit::STATUS_QUEUED, 'trigger' => 'manual', 'user_id' => $request->user()->id]);
        RunSeoAudit::dispatch($audit);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Auditoria iniciada. O resultado aparece aqui quando terminar.']);

        return to_route('admin.seo.audits.show', $audit);
    }

    public function show(Request $request, SeoAudit $audit): Response
    {
        $filters = $request->validate([
            'severidade' => ['nullable', Rule::in(array_keys(SeoAuditIssue::SEVERITIES))],
            'codigo' => ['nullable', 'string', 'max:60'],
        ]);

        $previous = SeoAudit::query()->where('status', SeoAudit::STATUS_COMPLETED)->where('id', '<', $audit->id)->latest('id')->first();
        $previousKeys = $previous ? SeoAuditIssue::query()->where('seo_audit_id', $previous->id)->get(['code', 'url'])->map(fn ($issue) => $issue->code.'|'.$issue->url)->flip() : collect();

        $issues = $audit->issues()
            ->when($filters['severidade'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($filters['codigo'] ?? null, fn ($query, $code) => $query->where('code', $code))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->orderBy('code')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (SeoAuditIssue $issue) => [
                ...$issue->only(['id', 'url', 'subject', 'severity', 'code', 'message', 'context']),
                'isNew' => $previous !== null && ! $previousKeys->has($issue->code.'|'.$issue->url),
            ]);

        return Inertia::render('admin/seo/audits/show', [
            'audit' => $audit->only(['id', 'status', 'trigger', 'pages_total', 'pages_scanned', 'critical_count', 'warning_count', 'opportunity_count', 'summary', 'error', 'started_at', 'finished_at', 'created_at']),
            'previous' => $previous?->only(['id', 'critical_count', 'warning_count', 'opportunity_count', 'finished_at']),
            'resolved' => $previous ? SeoAuditIssue::query()->where('seo_audit_id', $previous->id)->get(['code', 'url'])
                ->reject(fn ($issue) => $audit->issues()->where('code', $issue->code)->where('url', $issue->url)->exists())->count() : null,
            'issues' => $issues,
            'codes' => $audit->issues()->selectRaw('code, COUNT(*) as total')->groupBy('code')->orderByDesc('total')->pluck('total', 'code'),
            'pages' => $audit->pages()->orderBy('url')->limit(500)->get(['url', 'subject', 'status_code', 'title', 'indexable', 'in_sitemap', 'h1_count', 'images_without_alt', 'inbound_links', 'response_ms']),
            'filters' => ['severidade' => $filters['severidade'] ?? null, 'codigo' => $filters['codigo'] ?? null],
        ]);
    }
}
