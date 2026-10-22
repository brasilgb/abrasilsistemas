import { Link, router } from '@inertiajs/react';
import { PlayCircle } from 'lucide-react';
import {
    EmptyState,
    Notice,
    SeverityBadge,
    StatCard,
    formatDateTime,
} from '@/components/seo/indicators';
import type { Severity } from '@/components/seo/indicators';
import { SeoPage } from '@/components/seo/seo-page';
import { Button } from '@/components/ui/button';

type Summary = {
    pages: number;
    indexable: number;
    nonIndexable: number;
    missingMeta: number;
    duplicateMeta: number;
    imagesWithoutAlt: number;
    canonicalIssues: number;
    brokenLinks: number;
    redirectIssues: number;
};

type Props = {
    live: {
        pages: number;
        indexable: number;
        nonIndexable: number;
        missingDescription: number;
        duplicateTitles: number;
        duplicateDescriptions: number;
        canonicalElsewhere: number;
        redirects: number;
        redirectProblems: number;
        withoutKeyword: number;
    };
    audit: {
        id: number;
        finished_at: string;
        pages_scanned: number;
        critical_count: number;
        warning_count: number;
        opportunity_count: number;
        summary: Summary | null;
    } | null;
    runningAudit: { id: number; status: string; created_at: string } | null;
    recommendations: {
        code: string;
        severity: Severity;
        message: string;
        total: number;
    }[];
    indexingAllowed: boolean;
    google: { configured: boolean; connected: boolean };
};

export default function SeoOverview({
    live,
    audit,
    runningAudit,
    recommendations,
    indexingAllowed,
    google,
}: Props) {
    const summary = audit?.summary;
    const tone = (value: number, bad: 'bad' | 'warn' = 'bad') =>
        value > 0 ? bad : ('good' as const);

    return (
        <SeoPage
            title="Visão geral"
            description="Situação do SEO técnico e editorial do site. Os números vêm dos metadados atuais e da última auditoria; não são dados do Google."
            actions={
                runningAudit ? (
                    <Button variant="outline" asChild>
                        <Link href={`/admin/seo/auditoria/${runningAudit.id}`}>
                            Auditoria em andamento…
                        </Link>
                    </Button>
                ) : (
                    <Button onClick={() => router.post('/admin/seo/auditoria')}>
                        <PlayCircle className="size-4" />
                        Executar auditoria
                    </Button>
                )
            }
        >
            {!indexingAllowed && (
                <Notice tone="danger">
                    A indexação do site está <strong>bloqueada</strong>{' '}
                    (configuração global ou SEO_BLOCK_INDEXING). O robots.txt
                    responde “Disallow: /” e todas as páginas saem com noindex.{' '}
                    <Link href="/admin/seo/configuracoes" className="underline">
                        Revisar configurações
                    </Link>
                </Notice>
            )}

            <section className="space-y-3">
                <h3 className="text-sm font-semibold">Páginas públicas</h3>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Páginas públicas conhecidas"
                        value={live.pages}
                        href="/admin/seo/paginas"
                    />
                    <StatCard
                        label="Indexáveis"
                        value={live.indexable}
                        hint="index + canonical própria (pode aparecer na busca)"
                        tone="good"
                    />
                    <StatCard
                        label="Não indexáveis"
                        value={live.nonIndexable}
                        hint="noindex, canonical para outra URL ou não publicada"
                        href="/admin/seo/sitemap"
                    />
                    <StatCard
                        label="Sem palavra-chave principal"
                        value={live.withoutKeyword}
                        hint="páginas indexáveis sem planejamento"
                        tone={live.withoutKeyword > 0 ? 'warn' : 'good'}
                        href="/admin/seo/palavras-chave"
                    />
                </div>
            </section>

            <section className="space-y-3">
                <h3 className="text-sm font-semibold">
                    Metadados e redirecionamentos
                </h3>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Sem meta description"
                        value={live.missingDescription}
                        tone={tone(live.missingDescription)}
                        href="/admin/seo/paginas"
                    />
                    <StatCard
                        label="Títulos duplicados"
                        value={live.duplicateTitles}
                        tone={tone(live.duplicateTitles)}
                        href="/admin/seo/paginas"
                    />
                    <StatCard
                        label="Descrições duplicadas"
                        value={live.duplicateDescriptions}
                        tone={live.duplicateDescriptions > 0 ? 'warn' : 'good'}
                        href="/admin/seo/paginas"
                    />
                    <StatCard
                        label="Redirecionamentos problemáticos"
                        value={live.redirectProblems}
                        hint={`${live.redirects} regras ativas (cadeias e loops contam aqui)`}
                        tone={tone(live.redirectProblems)}
                        href="/admin/seo/redirecionamentos"
                    />
                </div>
            </section>

            <section className="space-y-3">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 className="text-sm font-semibold">Última auditoria</h3>
                    {audit && (
                        <Link
                            href={`/admin/seo/auditoria/${audit.id}`}
                            className="text-sm text-muted-foreground underline"
                        >
                            {formatDateTime(audit.finished_at)} ·{' '}
                            {audit.pages_scanned} URLs verificadas
                        </Link>
                    )}
                </div>
                {audit && summary ? (
                    <>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <StatCard
                                label="Críticos"
                                value={audit.critical_count}
                                tone={tone(audit.critical_count)}
                                href={`/admin/seo/auditoria/${audit.id}?severidade=critical`}
                            />
                            <StatCard
                                label="Avisos"
                                value={audit.warning_count}
                                tone={audit.warning_count ? 'warn' : 'good'}
                                href={`/admin/seo/auditoria/${audit.id}?severidade=warning`}
                            />
                            <StatCard
                                label="Oportunidades"
                                value={audit.opportunity_count}
                                href={`/admin/seo/auditoria/${audit.id}?severidade=opportunity`}
                            />
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard
                                label="Imagens sem texto alternativo"
                                value={summary.imagesWithoutAlt}
                                tone={tone(summary.imagesWithoutAlt, 'warn')}
                            />
                            <StatCard
                                label="Problemas de canonical"
                                value={summary.canonicalIssues}
                                tone={tone(summary.canonicalIssues)}
                            />
                            <StatCard
                                label="Links internos quebrados"
                                value={summary.brokenLinks}
                                tone={tone(summary.brokenLinks)}
                            />
                            <StatCard
                                label="Redirecionamentos (cadeias/loops)"
                                value={summary.redirectIssues}
                                tone={summary.redirectIssues ? 'warn' : 'good'}
                            />
                        </div>
                    </>
                ) : (
                    <EmptyState>
                        Nenhuma auditoria concluída ainda. Execute a primeira
                        para ver alt de imagens, links quebrados e problemas de
                        canonical encontrados nas páginas.
                    </EmptyState>
                )}
            </section>

            <section className="space-y-3">
                <h3 className="text-sm font-semibold">
                    Recomendações por criticidade
                </h3>
                {recommendations.length === 0 ? (
                    <EmptyState>
                        {audit
                            ? 'A última auditoria não encontrou problemas.'
                            : 'As recomendações aparecem depois da primeira auditoria.'}
                    </EmptyState>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {recommendations.map((item) => (
                            <li key={`${item.code}-${item.severity}`}>
                                <Link
                                    href={`/admin/seo/auditoria/${audit?.id}?codigo=${item.code}`}
                                    className="flex flex-col gap-2 px-4 py-3 transition hover:bg-muted/40 sm:flex-row sm:items-center"
                                >
                                    <SeverityBadge severity={item.severity} />
                                    <span className="flex-1 text-sm">
                                        {item.message}
                                    </span>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {item.total} ocorrência(s)
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="space-y-3">
                <h3 className="text-sm font-semibold">Dados do Google</h3>
                <Notice>
                    {google.connected ? (
                        <>
                            Search Console conectado. Cliques, impressões e
                            posição estão em{' '}
                            <Link
                                href="/admin/seo/integracoes"
                                className="underline"
                            >
                                Integrações
                            </Link>
                            .
                        </>
                    ) : (
                        <>
                            Search Console não conectado: o painel não mostra
                            indexação real, cliques nem posição. Estar no
                            sitemap não significa estar indexado.{' '}
                            <Link
                                href="/admin/seo/integracoes"
                                className="underline"
                            >
                                Conectar
                            </Link>
                        </>
                    )}
                </Notice>
            </section>
        </SeoPage>
    );
}
