import { Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { EmptyState, Notice, StatCard } from '@/components/seo/indicators';
import { SeoPage } from '@/components/seo/seo-page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Entry = {
    source: 'page' | 'post' | 'category';
    label: string;
    url: string;
    included: boolean;
    reason: string | null;
    editUrl: string | null;
};

type Props = {
    entries: Entry[];
    counts: { included: number; excluded: number };
    sitemapUrl: string;
    robotsUrl: string;
    robots: string;
    privatePaths: string[];
    blockIndexing: boolean;
    blockedByEnvironment: boolean;
    googleConnected: boolean;
};

const sourceLabels: Record<Entry['source'], string> = {
    page: 'Página',
    post: 'Artigo',
    category: 'Categoria',
};

export default function SeoIndexing({
    entries,
    counts,
    sitemapUrl,
    robotsUrl,
    robots,
    privatePaths,
    blockIndexing,
    blockedByEnvironment,
    googleConnected,
}: Props) {
    const [filter, setFilter] = useState<'all' | 'included' | 'excluded'>(
        'all',
    );
    const visible = entries.filter(
        (entry) =>
            filter === 'all' ||
            (filter === 'included' ? entry.included : !entry.included),
    );

    return (
        <SeoPage
            title="Sitemap e indexação"
            description="O sitemap só lista URLs canônicas e indexáveis. Estar no sitemap não significa estar indexado: isso só o Google confirma, pelo Search Console."
            actions={
                <>
                    <Button variant="outline" asChild>
                        <a href={sitemapUrl} target="_blank" rel="noreferrer">
                            <ExternalLink className="size-4" />
                            sitemap.xml
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href={robotsUrl} target="_blank" rel="noreferrer">
                            <ExternalLink className="size-4" />
                            robots.txt
                        </a>
                    </Button>
                </>
            }
        >
            {blockIndexing && (
                <Notice tone="danger">
                    A indexação do site está <strong>bloqueada</strong>
                    {blockedByEnvironment
                        ? ' pelo ambiente (SEO_BLOCK_INDEXING no .env, típico de homologação)'
                        : ' em SEO → Configurações'}
                    : o robots.txt responde <code>Disallow: /</code>, todas as
                    páginas saem com noindex e o sitemap fica vazio.
                </Notice>
            )}

            <div className="grid gap-3 sm:grid-cols-3">
                <StatCard
                    label="No sitemap"
                    value={blockIndexing ? 0 : counts.included}
                    tone="good"
                />
                <StatCard
                    label="Fora do sitemap"
                    value={counts.excluded}
                    hint="noindex, canonical para outra URL, rascunho ou preferência"
                />
                <StatCard
                    label="Indexação real"
                    value={googleConnected ? 'Ver Search Console' : '—'}
                    hint={
                        googleConnected
                            ? 'Dados de desempenho em Integrações'
                            : 'Conecte o Search Console em Integrações'
                    }
                    href="/admin/seo/integracoes"
                />
            </div>

            <section className="space-y-3">
                <div className="flex flex-wrap items-center gap-1">
                    {(
                        [
                            ['all', `Todas (${entries.length})`],
                            ['included', `No sitemap (${counts.included})`],
                            ['excluded', `Fora (${counts.excluded})`],
                        ] as const
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => setFilter(value)}
                            className={cn(
                                'h-8 rounded-md px-3 text-sm font-medium transition',
                                filter === value
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                {visible.length === 0 ? (
                    <EmptyState>Nenhuma URL com esse filtro.</EmptyState>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {visible.map((entry) => (
                            <li
                                key={`${entry.source}:${entry.url}`}
                                className="grid gap-1 px-4 py-3 text-sm sm:grid-cols-[1fr_auto] sm:items-center sm:gap-4"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate font-medium">
                                        {entry.label}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {sourceLabels[entry.source]} ·{' '}
                                        {entry.url}
                                    </span>
                                </span>
                                <span className="flex flex-wrap items-center gap-2 text-xs">
                                    {entry.included ? (
                                        <span className="rounded bg-emerald-100 px-1.5 py-0.5 font-medium text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                                            No sitemap
                                        </span>
                                    ) : (
                                        <span className="rounded bg-muted px-1.5 py-0.5 text-muted-foreground">
                                            {entry.reason}
                                        </span>
                                    )}
                                    {entry.editUrl && (
                                        <Link
                                            href={entry.editUrl}
                                            className="font-medium underline underline-offset-2"
                                        >
                                            Editar
                                        </Link>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="grid gap-4 lg:grid-cols-2">
                <div className="space-y-2">
                    <h2 className="text-sm font-semibold">
                        robots.txt servido no domínio canônico
                    </h2>
                    <pre className="max-h-80 overflow-auto rounded-md border bg-muted/40 p-3 text-xs">
                        {robots}
                    </pre>
                    <p className="text-xs text-muted-foreground">
                        Caminhos extras em SEO → Configurações. Ambientes que
                        não são o domínio canônico recebem{' '}
                        <code>Disallow: /</code>.
                    </p>
                </div>
                <div className="space-y-2">
                    <h2 className="text-sm font-semibold">Áreas privadas</h2>
                    <p className="text-sm text-muted-foreground">
                        Estes caminhos ficam no Disallow e respondem com{' '}
                        <code>X-Robots-Tag: noindex</code>. O robots.txt não é
                        proteção: o acesso continua exigindo login e permissão
                        de administrador.
                    </p>
                    <ul className="flex flex-wrap gap-1">
                        {privatePaths.map((path) => (
                            <li key={path}>
                                <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                    {path}
                                </code>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>
        </SeoPage>
    );
}
