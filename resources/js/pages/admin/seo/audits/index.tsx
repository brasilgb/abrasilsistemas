import { Link, router, usePoll } from '@inertiajs/react';
import { PlayCircle } from 'lucide-react';
import { useEffect } from 'react';
import {
    EmptyState,
    Notice,
    formatDateTime,
} from '@/components/seo/indicators';
import { SeoPage } from '@/components/seo/seo-page';
import { Button } from '@/components/ui/button';
import { auditStatusLabels } from '@/lib/seo';
import type { AuditStatus } from '@/lib/seo';
import { cn } from '@/lib/utils';

type Audit = {
    id: number;
    status: AuditStatus;
    trigger: 'manual' | 'scheduled';
    pages_total: number;
    pages_scanned: number;
    critical_count: number;
    warning_count: number;
    opportunity_count: number;
    error: string | null;
    finished_at: string | null;
    created_at: string;
    user: string | null;
};

type HistoryPoint = {
    id: number;
    finished_at: string;
    critical_count: number;
    warning_count: number;
    opportunity_count: number;
};

export default function SeoAudits({
    audits,
    history,
}: {
    audits: Audit[];
    history: HistoryPoint[];
}) {
    const running = audits.some(
        (audit) => audit.status === 'queued' || audit.status === 'running',
    );

    const { start, stop } = usePoll(5000, {}, { autoStart: false });

    // Atualiza a lista só enquanto houver auditoria na fila ou em execução.
    useEffect(() => {
        if (running) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [running, start, stop]);

    return (
        <SeoPage
            title="Auditoria do site"
            description="Verifica as URLs internas conhecidas (páginas, sitemap e links dos artigos): status HTTP, metadados, canonical, H1, imagens sem alt, links quebrados e redirecionamentos. Só acessa o próprio domínio."
            actions={
                <Button
                    disabled={running}
                    onClick={() => router.post('/admin/seo/auditoria')}
                >
                    <PlayCircle className="size-4" />
                    {running ? 'Auditoria em andamento' : 'Executar auditoria'}
                </Button>
            }
        >
            <Notice>
                A auditoria roda na fila (worker) e também de forma agendada,
                conforme SEO → Configurações. Também pode ser executada no
                servidor com <code>php artisan seo:audit --sync</code>.
            </Notice>

            {history.length > 1 && <Evolution history={history} />}

            {audits.length === 0 ? (
                <EmptyState>Nenhuma auditoria executada ainda.</EmptyState>
            ) : (
                <div className="overflow-hidden rounded-md border">
                    <div className="hidden grid-cols-[6rem_9rem_1fr_repeat(3,6rem)] gap-4 border-b bg-muted/50 px-4 py-3 text-xs font-medium text-muted-foreground md:grid">
                        <span>#</span>
                        <span>Situação</span>
                        <span>Quando</span>
                        <span className="text-right">Críticos</span>
                        <span className="text-right">Avisos</span>
                        <span className="text-right">Oport.</span>
                    </div>
                    <ul className="divide-y">
                        {audits.map((audit) => (
                            <li key={audit.id}>
                                <Link
                                    href={`/admin/seo/auditoria/${audit.id}`}
                                    className="grid gap-1 px-4 py-3 text-sm transition hover:bg-muted/40 md:grid-cols-[6rem_9rem_1fr_repeat(3,6rem)] md:items-center md:gap-4"
                                >
                                    <span className="font-medium">
                                        #{audit.id}
                                    </span>
                                    <span
                                        className={cn(
                                            audit.status === 'failed' &&
                                                'text-red-700 dark:text-red-400',
                                        )}
                                    >
                                        {auditStatusLabels[audit.status]}
                                        {audit.status === 'running' &&
                                            ` (${audit.pages_scanned}/${audit.pages_total})`}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {formatDateTime(
                                            audit.finished_at ??
                                                audit.created_at,
                                        )}{' '}
                                        ·{' '}
                                        {audit.trigger === 'scheduled'
                                            ? 'agendada'
                                            : (audit.user ?? 'manual')}
                                        {audit.error && ` · ${audit.error}`}
                                    </span>
                                    <Count
                                        value={audit.critical_count}
                                        tone="red"
                                    />
                                    <Count
                                        value={audit.warning_count}
                                        tone="amber"
                                    />
                                    <Count
                                        value={audit.opportunity_count}
                                        tone="sky"
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </SeoPage>
    );
}

function Count({
    value,
    tone,
}: {
    value: number;
    tone: 'red' | 'amber' | 'sky';
}) {
    return (
        <span
            className={cn(
                'tabular-nums md:text-right',
                value > 0 && tone === 'red' && 'text-red-700 dark:text-red-400',
                value > 0 &&
                    tone === 'amber' &&
                    'text-amber-700 dark:text-amber-400',
            )}
        >
            {value}
        </span>
    );
}

/** Evolução dos problemas nas últimas auditorias (barras empilhadas). */
function Evolution({ history }: { history: HistoryPoint[] }) {
    const max = Math.max(
        1,
        ...history.map(
            (point) =>
                point.critical_count +
                point.warning_count +
                point.opportunity_count,
        ),
    );

    return (
        <section className="rounded-lg border p-4">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold">
                    Evolução dos problemas
                </h3>
                <div className="flex gap-3 text-xs text-muted-foreground">
                    <Legend className="bg-red-500" label="Críticos" />
                    <Legend className="bg-amber-400" label="Avisos" />
                    <Legend className="bg-sky-400" label="Oportunidades" />
                </div>
            </div>
            <div className="flex h-32 items-end gap-2">
                {history.map((point) => {
                    const total =
                        point.critical_count +
                        point.warning_count +
                        point.opportunity_count;

                    return (
                        <Link
                            key={point.id}
                            href={`/admin/seo/auditoria/${point.id}`}
                            title={`${formatDateTime(point.finished_at)}: ${point.critical_count} críticos, ${point.warning_count} avisos, ${point.opportunity_count} oportunidades`}
                            className="flex h-full flex-1 flex-col justify-end"
                        >
                            <span
                                className="flex flex-col-reverse overflow-hidden rounded-t"
                                style={{ height: `${(total / max) * 100}%` }}
                            >
                                <Segment
                                    value={point.critical_count}
                                    total={total}
                                    className="bg-red-500"
                                />
                                <Segment
                                    value={point.warning_count}
                                    total={total}
                                    className="bg-amber-400"
                                />
                                <Segment
                                    value={point.opportunity_count}
                                    total={total}
                                    className="bg-sky-400"
                                />
                            </span>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}

function Segment({
    value,
    total,
    className,
}: {
    value: number;
    total: number;
    className: string;
}) {
    return total > 0 && value > 0 ? (
        <span
            className={className}
            style={{ height: `${(value / total) * 100}%` }}
        />
    ) : null;
}

function Legend({ className, label }: { className: string; label: string }) {
    return (
        <span className="inline-flex items-center gap-1">
            <span className={cn('size-2 rounded-sm', className)} />
            {label}
        </span>
    );
}
