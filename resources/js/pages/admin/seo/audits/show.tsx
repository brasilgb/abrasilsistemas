import { Link, router, usePoll } from '@inertiajs/react';
import { ExternalLink, Pencil } from 'lucide-react';
import { useEffect } from 'react';
import AppPagination from '@/components/app-pagination';
import type { PaginationData } from '@/components/app-pagination';
import {
    EmptyState,
    Notice,
    SeverityBadge,
    StatCard,
    formatDateTime,
    severityLabels,
} from '@/components/seo/indicators';
import type { Severity } from '@/components/seo/indicators';
import { SeoPage } from '@/components/seo/seo-page';
import { auditStatusLabels } from '@/lib/seo';
import type { AuditStatus } from '@/lib/seo';
import { cn } from '@/lib/utils';

type Issue = {
    id: number;
    url: string | null;
    subject: string | null;
    severity: Severity;
    code: string;
    message: string;
    context: {
        editUrl?: string;
        contentUrl?: string;
        label?: string;
        linkedFrom?: string[];
    } | null;
    isNew: boolean;
};

type AuditPage = {
    url: string;
    status_code: number | null;
    title: string | null;
    indexable: boolean;
    in_sitemap: boolean;
    h1_count: number | null;
    images_without_alt: number;
    inbound_links: number;
    response_ms: number | null;
};

type Props = {
    audit: {
        id: number;
        status: AuditStatus;
        pages_total: number;
        pages_scanned: number;
        critical_count: number;
        warning_count: number;
        opportunity_count: number;
        error: string | null;
        started_at: string | null;
        finished_at: string | null;
        summary: {
            pages: number;
            indexable: number;
            nonIndexable: number;
        } | null;
    };
    previous: {
        id: number;
        critical_count: number;
        warning_count: number;
        opportunity_count: number;
    } | null;
    resolved: number | null;
    issues: PaginationData & { data: Issue[] };
    codes: Record<string, number>;
    pages: AuditPage[];
    filters: { severidade: Severity | null; codigo: string | null };
};

export default function SeoAuditShow({
    audit,
    previous,
    resolved,
    issues,
    codes,
    pages,
    filters,
}: Props) {
    const running = audit.status === 'queued' || audit.status === 'running';
    const { start, stop } = usePoll(4000, {}, { autoStart: false });

    useEffect(() => {
        if (running) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [running, start, stop]);

    const visit = (next: Partial<Props['filters']>) =>
        router.get(
            `/admin/seo/auditoria/${audit.id}`,
            { ...filters, ...next },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    const delta = (current: number, before?: number) =>
        previous && before !== undefined
            ? `${current - before > 0 ? '+' : ''}${current - before} desde a anterior`
            : undefined;

    return (
        <SeoPage
            title={`Auditoria #${audit.id}`}
            description={`${auditStatusLabels[audit.status]} · iniciada ${formatDateTime(audit.started_at)}${audit.finished_at ? ` · concluída ${formatDateTime(audit.finished_at)}` : ''}`}
        >
            {running && (
                <Notice>
                    Auditoria em andamento: {audit.pages_scanned} de{' '}
                    {audit.pages_total} URLs verificadas. A página atualiza
                    sozinha.
                </Notice>
            )}
            {audit.error && <Notice tone="danger">{audit.error}</Notice>}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="URLs verificadas"
                    value={audit.pages_scanned}
                    hint={
                        audit.summary
                            ? `${audit.summary.indexable} indexáveis · ${audit.summary.nonIndexable} não indexáveis`
                            : undefined
                    }
                />
                <StatCard
                    label="Críticos"
                    value={audit.critical_count}
                    hint={delta(audit.critical_count, previous?.critical_count)}
                    tone={audit.critical_count ? 'bad' : 'good'}
                />
                <StatCard
                    label="Avisos"
                    value={audit.warning_count}
                    hint={delta(audit.warning_count, previous?.warning_count)}
                    tone={audit.warning_count ? 'warn' : 'good'}
                />
                <StatCard
                    label="Oportunidades"
                    value={audit.opportunity_count}
                    hint={
                        resolved !== null
                            ? `${resolved} ocorrência(s) da auditoria anterior resolvida(s)`
                            : undefined
                    }
                />
            </div>

            <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div className="flex flex-wrap gap-1">
                    {(
                        [null, 'critical', 'warning', 'opportunity'] as const
                    ).map((severity) => (
                        <button
                            key={severity ?? 'all'}
                            type="button"
                            onClick={() =>
                                visit({ severidade: severity, codigo: null })
                            }
                            className={cn(
                                'h-8 rounded-md px-3 text-sm font-medium transition',
                                filters.severidade === severity
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {severity ? severityLabels[severity] : 'Todas'}
                        </button>
                    ))}
                </div>
                <select
                    className="h-8 rounded-md border bg-background px-2 text-sm lg:ml-auto"
                    value={filters.codigo ?? ''}
                    onChange={(e) => visit({ codigo: e.target.value || null })}
                    aria-label="Filtrar por tipo de problema"
                >
                    <option value="">Todos os tipos</option>
                    {Object.entries(codes).map(([code, total]) => (
                        <option key={code} value={code}>
                            {code} ({total})
                        </option>
                    ))}
                </select>
            </div>

            {issues.data.length === 0 ? (
                <EmptyState>
                    {running
                        ? 'As ocorrências aparecem quando a auditoria terminar.'
                        : 'Nenhuma ocorrência com esse filtro.'}
                </EmptyState>
            ) : (
                <ul className="divide-y rounded-md border">
                    {issues.data.map((issue) => (
                        <li
                            key={issue.id}
                            className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start"
                        >
                            <SeverityBadge severity={issue.severity} />
                            <div className="min-w-0 flex-1 space-y-1">
                                <p className="text-sm">
                                    {issue.message}
                                    {issue.isNew && (
                                        <span className="ml-2 rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold uppercase">
                                            nova
                                        </span>
                                    )}
                                </p>
                                {issue.url && (
                                    <p className="truncate text-xs text-muted-foreground">
                                        {issue.context?.label &&
                                            `${issue.context.label} · `}
                                        {issue.url}
                                    </p>
                                )}
                                {issue.context?.linkedFrom && (
                                    <p className="text-xs text-muted-foreground">
                                        Linkado em:{' '}
                                        {issue.context.linkedFrom.join(', ')}
                                    </p>
                                )}
                            </div>
                            <div className="flex shrink-0 gap-3 text-xs">
                                {issue.context?.editUrl && (
                                    <Link
                                        href={issue.context.editUrl}
                                        className="inline-flex items-center gap-1 font-medium underline"
                                    >
                                        <Pencil className="size-3" />
                                        Corrigir
                                    </Link>
                                )}
                                {issue.url && (
                                    <a
                                        href={issue.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1 text-muted-foreground underline"
                                    >
                                        <ExternalLink className="size-3" />
                                        Abrir
                                    </a>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            <AppPagination data={issues} />

            {pages.length > 0 && (
                <details className="rounded-md border">
                    <summary className="cursor-pointer px-4 py-3 text-sm font-semibold">
                        URLs verificadas ({pages.length})
                    </summary>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[48rem] text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        URL
                                    </th>
                                    <th className="px-2 py-2 font-medium">
                                        HTTP
                                    </th>
                                    <th className="px-2 py-2 font-medium">
                                        Indexável
                                    </th>
                                    <th className="px-2 py-2 font-medium">
                                        Sitemap
                                    </th>
                                    <th className="px-2 py-2 font-medium">
                                        Links recebidos
                                    </th>
                                    <th className="px-2 py-2 font-medium">
                                        Tempo
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {pages.map((page) => (
                                    <tr key={page.url}>
                                        <td
                                            className="max-w-[28rem] truncate px-4 py-2"
                                            title={page.title ?? ''}
                                        >
                                            {page.url}
                                        </td>
                                        <td
                                            className={cn(
                                                'px-2 py-2 tabular-nums',
                                                (page.status_code ?? 500) >=
                                                    400 &&
                                                    'text-red-700 dark:text-red-400',
                                            )}
                                        >
                                            {page.status_code ?? 'erro'}
                                        </td>
                                        <td className="px-2 py-2">
                                            {page.indexable ? 'Sim' : 'Não'}
                                        </td>
                                        <td className="px-2 py-2">
                                            {page.in_sitemap ? 'Sim' : 'Não'}
                                        </td>
                                        <td className="px-2 py-2 tabular-nums">
                                            {page.inbound_links}
                                        </td>
                                        <td className="px-2 py-2 tabular-nums">
                                            {page.response_ms !== null
                                                ? `${page.response_ms} ms`
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </details>
            )}
        </SeoPage>
    );
}
