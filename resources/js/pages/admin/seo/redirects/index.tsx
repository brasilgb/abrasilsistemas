import { Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { EmptyState, formatDateTime } from '@/components/seo/indicators';
import { SeoPage } from '@/components/seo/seo-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Redirect = {
    id: number;
    source_path: string;
    target_url: string | null;
    status_code: number;
    is_active: boolean;
    notes: string | null;
    hits: number;
    last_hit_at: string | null;
    created_at: string;
    updated_at: string;
    creator: string | null;
    editor: string | null;
    problem: 'Loop' | 'Cadeia' | null;
};

type Props = {
    redirects: {
        data: Redirect[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { busca: string; situacao: 'todos' | 'ativos' | 'inativos' };
    statusCodes: Record<string, string>;
};

/** Rótulos da paginação do Laravel vêm com entidades (&laquo; Anterior). */
const pageLabel = (label: string) =>
    label.replace('&laquo;', '‹').replace('&raquo;', '›');

export default function SeoRedirects({ redirects, filters }: Props) {
    const [search, setSearch] = useState(filters.busca);
    const visit = (params: Partial<Props['filters']>) =>
        router.get(
            '/admin/seo/redirecionamentos',
            { ...filters, ...params },
            { preserveState: true, replace: true },
        );
    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ busca: search });
    };
    const toggle = (redirect: Redirect) =>
        router.put(
            `/admin/seo/redirecionamentos/${redirect.id}`,
            {
                source_path: redirect.source_path,
                target_url: redirect.target_url ?? '',
                status_code: redirect.status_code,
                is_active: !redirect.is_active,
                notes: redirect.notes ?? '',
            },
            { preserveScroll: true },
        );
    const destroy = (redirect: Redirect) => {
        if (
            confirm(
                `Excluir o redirecionamento de ${redirect.source_path}? Links externos para essa URL voltarão a dar 404. Desativar preserva o histórico.`,
            )
        ) {
            router.delete(`/admin/seo/redirecionamentos/${redirect.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <SeoPage
            title="Redirecionamentos"
            description="301 para mudanças permanentes, 302 para temporárias e 410 para conteúdo removido de propósito. Loops são bloqueados e cadeias são sinalizadas."
            actions={
                <Button asChild>
                    <Link href="/admin/seo/redirecionamentos/create">
                        <Plus className="size-4" />
                        Novo redirecionamento
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="flex gap-1">
                    {(
                        [
                            ['todos', 'Todos'],
                            ['ativos', 'Ativos'],
                            ['inativos', 'Inativos'],
                        ] as const
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => visit({ situacao: value })}
                            className={cn(
                                'h-8 rounded-md px-3 text-sm font-medium transition',
                                filters.situacao === value
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                <form
                    onSubmit={submitSearch}
                    className="relative w-full sm:ml-auto sm:w-72"
                >
                    <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar origem ou destino"
                        className="pl-8"
                        aria-label="Buscar redirecionamento"
                    />
                </form>
            </div>

            {redirects.data.length === 0 ? (
                <EmptyState>
                    {filters.busca || filters.situacao !== 'todos'
                        ? 'Nenhum redirecionamento com esse filtro.'
                        : 'Nenhum redirecionamento ainda. Ao trocar o endereço de um artigo publicado, o painel oferece criar um 301 automaticamente.'}
                </EmptyState>
            ) : (
                <ul className="divide-y rounded-md border">
                    {redirects.data.map((redirect) => (
                        <li
                            key={redirect.id}
                            className={cn(
                                'grid gap-2 px-4 py-3 text-sm lg:grid-cols-[2fr_8rem_11rem_auto] lg:items-center lg:gap-4',
                                !redirect.is_active && 'opacity-60',
                            )}
                        >
                            <span className="min-w-0">
                                <span className="block truncate font-mono text-xs">
                                    {redirect.source_path}
                                </span>
                                <span className="block truncate font-mono text-xs text-muted-foreground">
                                    →{' '}
                                    {redirect.status_code === 410
                                        ? '410 (removida)'
                                        : redirect.target_url}
                                </span>
                                {redirect.notes && (
                                    <span className="mt-1 block truncate text-xs text-muted-foreground">
                                        {redirect.notes}
                                    </span>
                                )}
                            </span>
                            <span className="flex flex-wrap gap-1 text-xs">
                                <span className="rounded bg-muted px-1.5 py-0.5 font-medium tabular-nums">
                                    {redirect.status_code}
                                </span>
                                {!redirect.is_active && (
                                    <span className="rounded bg-muted px-1.5 py-0.5">
                                        Inativo
                                    </span>
                                )}
                                {redirect.problem && (
                                    <span className="rounded bg-amber-100 px-1.5 py-0.5 text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                        {redirect.problem}
                                    </span>
                                )}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                <span className="block tabular-nums">
                                    {redirect.hits}{' '}
                                    {redirect.hits === 1 ? 'acesso' : 'acessos'}
                                </span>
                                <span className="block">
                                    Alterado{' '}
                                    {formatDateTime(redirect.updated_at)}
                                    {redirect.editor &&
                                        ` por ${redirect.editor}`}
                                </span>
                            </span>
                            <span className="flex flex-wrap gap-2 lg:justify-end">
                                <Button size="sm" variant="outline" asChild>
                                    <Link
                                        href={`/admin/seo/redirecionamentos/${redirect.id}/edit`}
                                    >
                                        Editar
                                    </Link>
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => toggle(redirect)}
                                >
                                    {redirect.is_active
                                        ? 'Desativar'
                                        : 'Ativar'}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="text-destructive"
                                    onClick={() => destroy(redirect)}
                                >
                                    Excluir
                                </Button>
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {redirects.links.length > 3 && (
                <nav
                    aria-label="Paginação"
                    className="flex flex-wrap justify-center gap-1"
                >
                    {redirects.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm',
                                    link.active
                                        ? 'bg-foreground text-background'
                                        : 'hover:bg-muted',
                                )}
                            >
                                {pageLabel(link.label)}
                            </Link>
                        ) : (
                            <span
                                key={index}
                                className="px-3 py-1.5 text-sm text-muted-foreground"
                            >
                                {pageLabel(link.label)}
                            </span>
                        ),
                    )}
                </nav>
            )}
        </SeoPage>
    );
}
