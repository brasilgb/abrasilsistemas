import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { Notice, SeverityBadge } from '@/components/seo/indicators';
import type { Severity } from '@/components/seo/indicators';
import { SeoPage } from '@/components/seo/seo-page';

type Row = {
    key: string;
    label: string;
    group: string;
    url: string;
    types: string[];
    issues: { severity: Severity; message: string }[];
    json: string | null;
    richResultsUrl: string;
    schemaValidatorUrl: string;
};

export default function SeoStructuredData({ pages }: { pages: Row[] }) {
    const [open, setOpen] = useState<string | null>(null);
    const withIssues = pages.filter((page) =>
        page.issues.some((issue) => issue.severity !== 'opportunity'),
    ).length;

    return (
        <SeoPage
            title="Dados estruturados"
            description="JSON-LD gerado automaticamente conforme o tipo real de cada página (Organization, WebSite, BreadcrumbList, BlogPosting, Service e SoftwareApplication), só com dados existentes."
        >
            <Notice>
                Nada de avaliações, estrelas, preços ou ofertas inventados:
                esses campos só entram quando existem no conteúdo. A validação
                abaixo é interna (sintaxe e campos obrigatórios); confira o
                resultado final nas ferramentas oficiais de cada página.
                {withIssues > 0 &&
                    ` ${withIssues} página(s) com pendência crítica ou aviso.`}
            </Notice>

            <ul className="divide-y rounded-md border">
                {pages.map((page) => (
                    <li key={page.key} className="px-4 py-3 text-sm">
                        <div className="grid gap-2 lg:grid-cols-[1fr_1.3fr_auto] lg:items-center lg:gap-4">
                            <span className="min-w-0">
                                <span className="block truncate font-medium">
                                    {page.label}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {page.group} · {page.url}
                                </span>
                            </span>
                            <span className="flex flex-wrap gap-1">
                                {page.types.length === 0 ? (
                                    <span className="text-xs text-muted-foreground">
                                        Sem JSON-LD
                                    </span>
                                ) : (
                                    page.types.map((type) => (
                                        <span
                                            key={type}
                                            className="rounded bg-muted px-1.5 py-0.5 text-xs"
                                        >
                                            {type}
                                        </span>
                                    ))
                                )}
                            </span>
                            <span className="flex flex-wrap items-center gap-3 text-xs">
                                {page.json && (
                                    <button
                                        type="button"
                                        className="font-medium underline underline-offset-2"
                                        aria-expanded={open === page.key}
                                        onClick={() =>
                                            setOpen(
                                                open === page.key
                                                    ? null
                                                    : page.key,
                                            )
                                        }
                                    >
                                        {open === page.key
                                            ? 'Ocultar JSON'
                                            : 'Ver JSON'}
                                    </button>
                                )}
                                <a
                                    href={page.richResultsUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 underline underline-offset-2"
                                >
                                    Google <ExternalLink className="size-3" />
                                </a>
                                <a
                                    href={page.schemaValidatorUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 underline underline-offset-2"
                                >
                                    schema.org{' '}
                                    <ExternalLink className="size-3" />
                                </a>
                            </span>
                        </div>
                        {page.issues.length > 0 && (
                            <ul className="mt-2 space-y-1">
                                {page.issues.map((issue, index) => (
                                    <li
                                        key={index}
                                        className="flex items-start gap-2 text-xs"
                                    >
                                        <SeverityBadge
                                            severity={issue.severity}
                                        />
                                        <span className="pt-0.5">
                                            {issue.message}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {open === page.key && page.json && (
                            <pre className="mt-3 max-h-96 overflow-auto rounded-md border bg-muted/40 p-3 text-xs">
                                {page.json}
                            </pre>
                        )}
                    </li>
                ))}
            </ul>
        </SeoPage>
    );
}
