import { Head, Link, router } from '@inertiajs/react';
import { Inbox, MessagesSquare, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppPagination from '@/components/app-pagination';
import type { PaginationData } from '@/components/app-pagination';
import { formatWhatsapp } from '@/components/company-whatsapp-connection';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatRequestDate, requestStatusClasses } from '@/lib/site-requests';
import type { RequestStatus } from '@/lib/site-requests';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/solicitacoes';

type SiteRequest = {
    id: number;
    received_at: string | null;
    last_contact_at: string | null;
    origin: string;
    contact_name: string | null;
    company_name: string | null;
    whatsapp: string | null;
    phone: string | null;
    email: string | null;
    project_type: string | null;
    excerpt: string;
    source_page: string | null;
    request_status: RequestStatus | null;
    has_conversation: boolean;
};

type Filter = 'abertas' | 'todas' | RequestStatus;

type Props = {
    requests: PaginationData & { data: SiteRequest[] };
    filters: {
        situacao: Filter;
        busca: string;
        servico: string;
        origem: string;
        de: string;
        ate: string;
    };
    statuses: Record<RequestStatus, string>;
    services: Record<string, string>;
    origins: Record<string, string>;
    counts: Record<Filter, number>;
};

export default function SolicitacoesIndex({
    requests,
    filters,
    statuses,
    services,
    origins,
    counts,
}: Props) {
    const [search, setSearch] = useState(filters.busca);
    const tabs: { value: Filter; label: string }[] = [
        { value: 'abertas', label: 'Abertas' },
        ...(Object.entries(statuses) as [RequestStatus, string][]).map(
            ([value, label]) => ({ value, label }),
        ),
        { value: 'todas', label: 'Todas' },
    ];

    function visit(next: Partial<Props['filters']>) {
        const query = { ...filters, ...next };

        // Só os filtros preenchidos entram na URL.
        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(query).filter(([, value]) => value !== ''),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const hasFilters = [
        filters.busca,
        filters.servico,
        filters.origem,
        filters.de,
        filters.ate,
    ].some((value) => value !== '');

    function clearFilters() {
        setSearch('');
        visit({ busca: '', servico: '', origem: '', de: '', ate: '' });
    }

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        visit({ busca: search.trim() });
    }

    return (
        <>
            <Head title="Solicitações" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Solicitações"
                    description="Contatos recebidos pelo formulário do site. Acompanhe cada pedido do primeiro contato ao ganho ou perda; a conversa acontece em Atendimento."
                />

                <div className="flex flex-col gap-3 2xl:flex-row 2xl:items-center 2xl:justify-between">
                    <nav
                        className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1"
                        aria-label="Filtrar por situação"
                    >
                        {tabs.map((tab) => (
                            <button
                                key={tab.value}
                                type="button"
                                onClick={() => visit({ situacao: tab.value })}
                                aria-current={
                                    filters.situacao === tab.value
                                        ? 'page'
                                        : undefined
                                }
                                className={cn(
                                    'inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md px-3 text-sm font-medium transition',
                                    filters.situacao === tab.value
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                {tab.label}
                                <span className="text-xs tabular-nums opacity-70">
                                    {counts[tab.value] ?? 0}
                                </span>
                            </button>
                        ))}
                    </nav>

                    <form
                        onSubmit={submitSearch}
                        className="flex w-full max-w-md gap-2 2xl:w-80"
                        role="search"
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Nome, empresa, e-mail ou telefone"
                            aria-label="Buscar solicitações"
                        />
                        <Button type="submit" variant="outline" size="icon">
                            <Search className="size-4" />
                            <span className="sr-only">Buscar</span>
                        </Button>
                    </form>
                </div>

                <div className="grid gap-3 rounded-md border p-3 sm:grid-cols-2 lg:grid-cols-[1.2fr_1.2fr_1fr_1fr_auto] lg:items-end">
                    <FilterSelect
                        label="Serviço"
                        value={filters.servico}
                        options={services}
                        onChange={(servico) => visit({ servico })}
                    />
                    <FilterSelect
                        label="Origem"
                        value={filters.origem}
                        options={origins}
                        onChange={(origem) => visit({ origem })}
                    />
                    <label className="text-xs font-medium text-muted-foreground">
                        Recebidas de
                        <Input
                            type="date"
                            className="mt-1"
                            value={filters.de}
                            max={filters.ate || undefined}
                            onChange={(event) =>
                                visit({ de: event.target.value })
                            }
                        />
                    </label>
                    <label className="text-xs font-medium text-muted-foreground">
                        Até
                        <Input
                            type="date"
                            className="mt-1"
                            value={filters.ate}
                            min={filters.de || undefined}
                            onChange={(event) =>
                                visit({ ate: event.target.value })
                            }
                        />
                    </label>
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={!hasFilters}
                        onClick={clearFilters}
                    >
                        Limpar filtros
                    </Button>
                </div>

                <div className="overflow-hidden rounded-md border">
                    <div className="hidden grid-cols-[8rem_1.2fr_1fr_1.6fr_9rem] gap-4 border-b bg-muted/50 px-4 py-3 text-xs font-medium text-muted-foreground lg:grid">
                        <span>Recebida</span>
                        <span>Contato</span>
                        <span>Serviço e origem</span>
                        <span>Mensagem</span>
                        <span>Situação</span>
                    </div>

                    {requests.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 px-4 py-14 text-center text-sm text-muted-foreground">
                            <Inbox className="size-6" />
                            {hasFilters
                                ? 'Nenhuma solicitação encontrada com esses filtros.'
                                : 'Nenhuma solicitação nesta situação.'}
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {requests.data.map((request) => (
                                <li key={request.id}>
                                    <Link
                                        href={show(request.id)}
                                        className="grid gap-2 px-4 py-4 transition hover:bg-muted/40 lg:grid-cols-[8rem_1.2fr_1fr_1.6fr_9rem] lg:items-start lg:gap-4"
                                    >
                                        <span className="text-xs text-muted-foreground tabular-nums lg:pt-0.5">
                                            {formatRequestDate(
                                                request.received_at,
                                            )}
                                            {request.last_contact_at && (
                                                <span className="mt-1 block font-medium text-foreground">
                                                    Novo contato em{' '}
                                                    {formatRequestDate(
                                                        request.last_contact_at,
                                                    )}
                                                </span>
                                            )}
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium">
                                                {request.contact_name ??
                                                    'Sem nome'}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {request.company_name ??
                                                    'Empresa não informada'}
                                            </span>
                                            <span className="mt-1 block truncate text-xs text-muted-foreground">
                                                {request.whatsapp
                                                    ? formatWhatsapp(
                                                          request.whatsapp,
                                                      )
                                                    : request.email}
                                            </span>
                                        </span>
                                        <span className="text-sm">
                                            {request.project_type ?? '—'}
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {request.origin}
                                            </span>
                                            {request.source_page && (
                                                <span className="block truncate font-mono text-xs text-muted-foreground">
                                                    {request.source_page}
                                                </span>
                                            )}
                                        </span>
                                        <span className="line-clamp-2 text-sm text-muted-foreground">
                                            {request.excerpt}
                                        </span>
                                        <span className="flex flex-wrap items-center gap-2">
                                            {request.request_status && (
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium',
                                                        requestStatusClasses[
                                                            request
                                                                .request_status
                                                        ],
                                                    )}
                                                >
                                                    {
                                                        statuses[
                                                            request
                                                                .request_status
                                                        ]
                                                    }
                                                </span>
                                            )}
                                            {request.has_conversation && (
                                                <span
                                                    className="inline-flex items-center gap-1 text-xs text-muted-foreground"
                                                    title="Tem conversa no Atendimento"
                                                >
                                                    <MessagesSquare className="size-3.5" />
                                                    WhatsApp
                                                </span>
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <AppPagination data={requests} />
            </div>
        </>
    );
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: Record<string, string>;
    onChange: (value: string) => void;
}) {
    return (
        <label className="text-xs font-medium text-muted-foreground">
            {label}
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm text-foreground"
            >
                <option value="">Todos</option>
                {Object.entries(options).map(([key, text]) => (
                    <option key={key} value={key}>
                        {text}
                    </option>
                ))}
            </select>
        </label>
    );
}

SolicitacoesIndex.layout = {
    breadcrumbs: [{ title: 'Solicitações', href: index() }],
};
