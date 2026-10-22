import { Link } from '@inertiajs/react';
import { EmptyState, Notice } from '@/components/seo/indicators';
import { SeoPage } from '@/components/seo/seo-page';

type Row = {
    key: string;
    label: string;
    type: 'page' | 'post';
    path: string;
    editUrl: string;
    indexable: boolean;
    focusKeyword: string | null;
    secondaryKeywords: string[];
    intent: string | null;
    related: string[];
};

type Props = {
    rows: Row[];
    intents: Record<string, string>;
    topics: Record<string, string[]>;
    cannibalization: {
        keyword: string;
        pages: { label: string; editUrl: string }[];
    }[];
    missingLinks: {
        label: string;
        editUrl: string;
        intent: string;
        suggested: string[];
    }[];
    relatedLabels: Record<string, string>;
};

export default function SeoKeywords({
    rows,
    intents,
    topics,
    cannibalization,
    missingLinks,
    relatedLabels,
}: Props) {
    const groups = [...Object.keys(intents), ''].map((intent) => ({
        intent,
        label: intent ? intents[intent] : 'Sem intenção definida',
        rows: rows.filter((row) => (row.intent ?? '') === intent),
    }));

    return (
        <SeoPage
            title="Palavras-chave"
            description="Planejamento editorial por intenção de busca. As palavras-chave organizam o conteúdo e os links internos; volume e posição só existem com o Search Console conectado."
        >
            {cannibalization.length > 0 && (
                <Notice tone="warn">
                    <p className="font-medium">
                        Mesma palavra-chave principal em mais de uma página
                    </p>
                    <ul className="mt-1 space-y-1">
                        {cannibalization.map((item) => (
                            <li key={item.keyword}>
                                “{item.keyword}”:{' '}
                                {item.pages.map((page, index) => (
                                    <span key={page.editUrl}>
                                        {index > 0 && ', '}
                                        <Link
                                            href={page.editUrl}
                                            className="underline underline-offset-2"
                                        >
                                            {page.label}
                                        </Link>
                                    </span>
                                ))}
                                . Escolha uma página principal e diferencie as
                                outras.
                            </li>
                        ))}
                    </ul>
                </Notice>
            )}

            {missingLinks.length > 0 && (
                <Notice>
                    <p className="font-medium">
                        Artigos sem relação com a página comercial da intenção
                    </p>
                    <ul className="mt-1 space-y-1">
                        {missingLinks.map((item) => (
                            <li key={item.editUrl}>
                                <Link
                                    href={item.editUrl}
                                    className="underline underline-offset-2"
                                >
                                    {item.label}
                                </Link>{' '}
                                ({intents[item.intent]}): relacione com{' '}
                                {item.suggested.join(' ou ')}.
                            </li>
                        ))}
                    </ul>
                </Notice>
            )}

            <div className="grid gap-6">
                {groups
                    .filter(
                        (group) =>
                            group.rows.length > 0 || topics[group.intent],
                    )
                    .map((group) => (
                        <section
                            key={group.intent || 'none'}
                            className="space-y-2"
                        >
                            <h2 className="text-sm font-semibold">
                                {group.label}
                            </h2>
                            {topics[group.intent] && (
                                <p className="text-xs text-muted-foreground">
                                    Temas da estratégia:{' '}
                                    {topics[group.intent].join(' · ')}. Temas
                                    sem página própria viram pauta de artigo,
                                    não página nova.
                                </p>
                            )}
                            {group.rows.length === 0 ? (
                                <EmptyState>
                                    Nenhuma página ou artigo nesta intenção.
                                </EmptyState>
                            ) : (
                                <ul className="divide-y rounded-md border">
                                    {group.rows.map((row) => (
                                        <li key={row.key}>
                                            <Link
                                                href={row.editUrl}
                                                className="grid gap-1 px-4 py-3 text-sm transition hover:bg-muted/40 md:grid-cols-[1fr_1.2fr_1fr] md:gap-4"
                                            >
                                                <span className="min-w-0">
                                                    <span className="block truncate font-medium">
                                                        {row.label}
                                                    </span>
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {row.type === 'post'
                                                            ? 'Artigo'
                                                            : 'Página'}{' '}
                                                        · {row.path}
                                                        {!row.indexable &&
                                                            ' · noindex'}
                                                    </span>
                                                </span>
                                                <span className="min-w-0">
                                                    {row.focusKeyword ? (
                                                        <span className="block truncate">
                                                            {row.focusKeyword}
                                                        </span>
                                                    ) : (
                                                        <span className="block text-amber-700 dark:text-amber-400">
                                                            Sem palavra-chave
                                                            principal
                                                        </span>
                                                    )}
                                                    {row.secondaryKeywords
                                                        .length > 0 && (
                                                        <span className="block truncate text-xs text-muted-foreground">
                                                            {row.secondaryKeywords.join(
                                                                ', ',
                                                            )}
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="min-w-0 text-xs text-muted-foreground">
                                                    {row.related.length > 0
                                                        ? `Relacionadas: ${row.related.map((key) => relatedLabels[key] ?? key).join(', ')}`
                                                        : 'Sem páginas relacionadas'}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
            </div>
        </SeoPage>
    );
}
