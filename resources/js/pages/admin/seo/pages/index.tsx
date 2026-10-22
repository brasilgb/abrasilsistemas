import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/seo/indicators';
import { SeoPage } from '@/components/seo/seo-page';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Row = {
    key: string;
    type: 'page' | 'post';
    label: string;
    group: string;
    path: string;
    url: string;
    effectiveTitle: string;
    effectiveDescription: string;
    effectiveRobots: string;
    effectiveCanonical: string;
    indexable: boolean;
    canonicalElsewhere: boolean;
    editUrl: string;
    focusKeyword: string | null;
    customized: boolean;
    score: number | null;
    flags: string[];
};

export default function SeoPages({ pages }: { pages: Row[] }) {
    const groups = useMemo(
        () => Array.from(new Set(pages.map((page) => page.group))),
        [pages],
    );
    const [group, setGroup] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [onlyIssues, setOnlyIssues] = useState(false);
    const term = search.trim().toLowerCase();
    const visible = pages.filter(
        (page) =>
            (!group || page.group === group) &&
            (!onlyIssues || page.flags.length > 0) &&
            (!term ||
                `${page.label} ${page.path} ${page.effectiveTitle}`
                    .toLowerCase()
                    .includes(term)),
    );

    return (
        <SeoPage
            title="Páginas e metadados"
            description="Título, descrição, canonical e robots efetivos de cada página pública. Campos não preenchidos herdam o padrão da página e das configurações globais."
        >
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div className="-mx-1 flex gap-1 overflow-x-auto px-1">
                    {[null, ...groups].map((item) => (
                        <button
                            key={item ?? 'all'}
                            type="button"
                            onClick={() => setGroup(item)}
                            className={cn(
                                'h-8 shrink-0 rounded-md px-3 text-sm font-medium transition',
                                group === item
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {item ?? 'Todas'}
                        </button>
                    ))}
                </div>
                <label className="flex items-center gap-2 text-sm lg:ml-auto">
                    <input
                        type="checkbox"
                        checked={onlyIssues}
                        onChange={(e) => setOnlyIssues(e.target.checked)}
                    />
                    Só com pendências
                </label>
                <div className="relative w-full lg:w-72">
                    <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar página"
                        className="pl-8"
                        aria-label="Buscar página"
                    />
                </div>
            </div>

            {visible.length === 0 ? (
                <EmptyState>Nenhuma página com esse filtro.</EmptyState>
            ) : (
                <ul className="divide-y rounded-md border">
                    {visible.map((page) => (
                        <li key={page.key}>
                            <Link
                                href={page.editUrl}
                                className="grid gap-2 px-4 py-3 transition hover:bg-muted/40 lg:grid-cols-[1.1fr_2fr_10rem] lg:gap-4"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-medium">
                                        {page.label}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {page.group} · {page.path}
                                    </span>
                                </span>
                                <span className="min-w-0 text-sm">
                                    <span className="block truncate">
                                        {page.effectiveTitle}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {page.effectiveDescription ||
                                            'Sem descrição'}
                                    </span>
                                    {page.flags.length > 0 && (
                                        <span className="mt-1 flex flex-wrap gap-1">
                                            {page.flags.map((flag) => (
                                                <span
                                                    key={flag}
                                                    className="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                                                >
                                                    {flag}
                                                </span>
                                            ))}
                                        </span>
                                    )}
                                </span>
                                <span className="flex flex-wrap items-start gap-1 text-xs lg:flex-col lg:items-end">
                                    <span
                                        className={cn(
                                            'rounded px-1.5 py-0.5 font-medium',
                                            page.indexable
                                                ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                                                : 'bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {page.indexable
                                            ? 'Indexável'
                                            : page.canonicalElsewhere
                                              ? 'Canonical externa'
                                              : 'noindex'}
                                    </span>
                                    {page.customized && (
                                        <span className="text-muted-foreground">
                                            Personalizada
                                        </span>
                                    )}
                                    {page.score !== null && (
                                        <span className="text-muted-foreground tabular-nums">
                                            Editorial {page.score}/100
                                        </span>
                                    )}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </SeoPage>
    );
}
