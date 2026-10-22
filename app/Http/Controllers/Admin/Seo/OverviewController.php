<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoAudit;
use App\Models\SeoAuditIssue;
use App\Models\SeoRedirect;
use App\Services\Seo\GoogleIntegration;
use App\Services\Seo\PageRegistry;
use App\Services\Seo\RedirectManager;
use App\Services\Seo\SeoSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visão geral do SEO. Os números "ao vivo" vêm dos metadados e regras atuais; os que
 * dependem de visitar as páginas (alt, links, canonical renderizado) vêm da última
 * auditoria. Indexação real e métricas do Google só aparecem com a integração conectada.
 */
class OverviewController extends Controller
{
    public function __invoke(PageRegistry $registry, RedirectManager $redirects, SeoSettings $settings, GoogleIntegration $google): Response
    {
        $pages = $registry->withEffective();
        $titles = $pages->where('indexable', true)->countBy(fn ($page) => Str::lower($page['effectiveTitle']));
        $descriptions = $pages->where('indexable', true)->filter(fn ($page) => $page['effectiveDescription'] !== '')->countBy(fn ($page) => Str::lower($page['effectiveDescription']));

        $redirectProblems = SeoRedirect::query()->where('is_active', true)->where('status_code', '!=', 410)->get()
            ->filter(fn (SeoRedirect $redirect) => ($chain = $redirects->chain($redirect->source_path, $redirect->target_url, $redirect->id))['loop'] || $chain['hops'] !== [])
            ->count();

        $audit = SeoAudit::latestCompleted();
        $running = SeoAudit::query()->whereIn('status', [SeoAudit::STATUS_QUEUED, SeoAudit::STATUS_RUNNING])->latest('id')->first();

        $recommendations = $audit ? SeoAuditIssue::query()
            ->where('seo_audit_id', $audit->id)
            ->selectRaw('code, severity, MIN(message) as message, COUNT(*) as total')
            ->groupBy('code', 'severity')
            ->get()
            ->sortBy(fn ($row) => [array_search($row->severity, array_keys(SeoAuditIssue::SEVERITIES), true), -$row->total])
            ->take(8)
            ->values() : collect();

        return Inertia::render('admin/seo/overview', [
            'live' => [
                'pages' => $pages->count(),
                'indexable' => $pages->where('indexable', true)->count(),
                'nonIndexable' => $pages->where('indexable', false)->count(),
                'missingDescription' => $pages->where('indexable', true)->filter(fn ($page) => $page['effectiveDescription'] === '')->count(),
                'duplicateTitles' => $titles->filter(fn ($count) => $count > 1)->sum(),
                'duplicateDescriptions' => $descriptions->filter(fn ($count) => $count > 1)->sum(),
                'canonicalElsewhere' => $pages->where('canonicalElsewhere', true)->count(),
                'redirects' => SeoRedirect::query()->where('is_active', true)->count(),
                'redirectProblems' => $redirectProblems,
                'withoutKeyword' => $pages->where('indexable', true)->whereNull('focusKeyword')->count(),
            ],
            'audit' => $audit?->only(['id', 'finished_at', 'pages_scanned', 'critical_count', 'warning_count', 'opportunity_count', 'summary']),
            'runningAudit' => $running?->only(['id', 'status', 'created_at']),
            'recommendations' => $recommendations,
            'indexingAllowed' => $settings->indexingAllowed(Request::create($settings->url('/'))),
            'google' => $google->status(),
        ]);
    }
}
