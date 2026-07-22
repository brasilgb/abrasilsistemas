import { Form, Head, Link, router } from '@inertiajs/react';
import {
    BarChart3,
    CalendarClock,
    Columns3,
    FileUp,
    ListTodo,
    MessageCircle,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { type DragEvent, useEffect, useMemo, useState } from 'react';
import AppPagination, {
    PaginationSummary,
    type PaginationData,
} from '@/components/app-pagination';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    readWhatsappMessages,
    whatsappMessageTemplates,
} from '@/lib/lead-whatsapp-messages';
import { cn } from '@/lib/utils';
import { create, destroy, edit, index } from '@/routes/leads';
import type { Lead } from '@/pages/leads/form';

type Paginated<T> = PaginationData & {
    data: T[];
};

type Props = {
    filters: {
        search?: string;
        product?: string;
        status?: string;
        city?: string;
        state?: string;
        industry?: string;
        follow_up?: string;
        owner?: string;
    };
    kanbanLeads: Lead[];
    kanbanTotal: number;
    taskLeads: {
        overdue: Lead[];
        today: Lead[];
        without_follow_up: Lead[];
    };
    taskTotals: {
        overdue: number;
        today: number;
        without_follow_up: number;
    };
    leads: Paginated<Lead>;
    lostReasons: Record<string, string>;
    metrics: {
        total: number;
        open: number;
        converted: number;
        conversion_rate: number;
        high_priority: number;
        follow_ups: {
            overdue: number;
            today: number;
            upcoming: number;
            without_follow_up: number;
        };
        by_status: Record<string, number>;
    };
    products: Record<string, string>;
    statuses: Record<string, string>;
};

function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
}

function TaskColumn({
    title,
    description,
    leads,
    total,
    products,
    whatsappMessages,
}: {
    title: string;
    description: string;
    leads: Lead[];
    total: number;
    products: Record<string, string>;
    whatsappMessages: Record<string, string>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center justify-between text-base">
                    {title}
                    <Badge variant="secondary">{total}</Badge>
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
                {leads.length ? (
                    leads.map((lead) => (
                        <div
                            key={lead.id}
                            className="flex items-center justify-between gap-3 rounded-md border p-3"
                        >
                            <div className="min-w-0">
                                <Link
                                    href={edit({ lead: lead.id })}
                                    className="block truncate text-sm font-medium hover:underline"
                                >
                                    {lead.company_name}
                                </Link>
                                <p className="truncate text-xs text-muted-foreground">
                                    {lead.contact_name || lead.whatsapp || '-'} ·{' '}
                                    {formatDate(lead.next_follow_up_at)}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    Responsável: {lead.user?.name ?? 'Não definido'}
                                </p>
                            </div>
                            <LeadActions
                                lead={lead}
                                products={products}
                                whatsappMessages={whatsappMessages}
                            />
                        </div>
                    ))
                ) : (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        Nenhum lead nesta fila.
                    </p>
                )}
                {total > leads.length && (
                    <p className="text-xs text-amber-700">
                        Exibindo 50 de {total}. Refine os filtros para localizar
                        os demais.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function whatsappUrl(value: string | null, message: string) {
    if (!value) {
        return null;
    }

    const phone = value.replace(/\D/g, '');

    if (!phone) {
        return null;
    }

    const normalizedPhone = phone.startsWith('55') ? phone : `55${phone}`;
    const query = message.trim()
        ? `?text=${encodeURIComponent(message.trim())}`
        : '';

    return `https://wa.me/${normalizedPhone}${query}`;
}

function statusClass(status: string) {
    return (
        {
            new: 'border-sky-200 bg-sky-50 text-sky-700',
            contacted: 'border-amber-200 bg-amber-50 text-amber-700',
            interested: 'border-violet-200 bg-violet-50 text-violet-700',
            meeting: 'border-indigo-200 bg-indigo-50 text-indigo-700',
            converted: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            lost: 'border-rose-200 bg-rose-50 text-rose-700',
        }[status] ?? 'border-muted bg-muted/40 text-muted-foreground'
    );
}

const priorityLabels = {
    high: 'Alta',
    medium: 'Média',
    low: 'Baixa',
};

function priorityClass(priority: string) {
    return (
        {
            high: 'border-red-200 bg-red-50 text-red-700',
            medium: 'border-amber-200 bg-amber-50 text-amber-700',
            low: 'border-slate-200 bg-slate-50 text-slate-700',
        }[priority] ?? 'border-muted bg-muted/40 text-muted-foreground'
    );
}

function LeadActions({
    lead,
    products,
    whatsappMessages,
}: {
    lead: Lead;
    products: Record<string, string>;
    whatsappMessages: Record<string, string>;
}) {
    const leadProduct = lead.product === 'vetorpet' ? 'vetorpet' : 'vetoros';
    const whatsapp = whatsappUrl(lead.whatsapp, whatsappMessages[leadProduct]);

    return (
        <div className="flex justify-end gap-1">
            {whatsapp && (
                <Button
                    asChild
                    variant="outline"
                    size="icon"
                    title={`Conversar sobre ${products[lead.product] ?? lead.product}`}
                >
                    <a href={whatsapp} target="_blank" rel="noreferrer">
                        <MessageCircle className="size-4 text-emerald-600" />
                    </a>
                </Button>
            )}
            <Button asChild variant="outline" size="icon" title="Editar lead">
                <Link href={edit({ lead: lead.id })}>
                    <Pencil className="size-4" />
                </Link>
            </Button>
            <Button
                type="button"
                variant="destructive"
                size="icon"
                title="Excluir prospect"
                onClick={() => {
                    if (confirm(`Excluir o prospect “${lead.company_name}”?`)) {
                        router.delete(`/leads/${lead.id}`, {
                            preserveScroll: true,
                        });
                    }
                }}
            >
                <Trash2 className="size-4" />
            </Button>
        </div>
    );
}

function LeadKanbanCard({
    lead,
    lostReasons,
    products,
    statuses,
    whatsappMessages,
    onDragStart,
}: {
    lead: Lead;
    lostReasons: Record<string, string>;
    products: Record<string, string>;
    statuses: Record<string, string>;
    whatsappMessages: Record<string, string>;
    onDragStart: (event: DragEvent<HTMLElement>, lead: Lead) => void;
}) {
    return (
        <article
            draggable
            onDragStart={(event) => onDragStart(event, lead)}
            className="cursor-grab rounded-md border bg-background p-3 shadow-sm transition hover:border-primary/40 active:cursor-grabbing"
        >
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <h3 className="truncate text-sm font-semibold">
                        {lead.company_name}
                    </h3>
                    <p className="truncate text-xs text-muted-foreground">
                    {lead.contact_name ||
                            lead.whatsapp ||
                            lead.phone ||
                            '-'}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        Responsável: {lead.user?.name ?? 'Não definido'}
                    </p>
                </div>
                <Badge variant="secondary">
                    {products[lead.product] ?? lead.product}
                </Badge>
            </div>
            <div className="mt-3 flex items-center gap-2">
                <Badge
                    variant="outline"
                    className={priorityClass(lead.priority)}
                >
                    Prioridade {priorityLabels[lead.priority] ?? lead.priority}
                </Badge>
                <span className="text-xs font-medium text-muted-foreground">
                    {lead.lead_score}/100
                </span>
            </div>
            <div className="mt-3 space-y-1 text-xs text-muted-foreground">
                <p className="truncate">
                    {[lead.city, lead.state].filter(Boolean).join(' / ') || '-'}
                </p>
                <p className="inline-flex items-center gap-1">
                    <CalendarClock className="size-3.5" />
                    {formatDate(lead.next_follow_up_at)}
                </p>
                {lead.notes && (
                    <p className="line-clamp-2" title={lead.notes}>
                        {lead.notes}
                    </p>
                )}
            </div>
            <div className="mt-3 flex items-center justify-between gap-2">
                <Badge variant="outline" className={statusClass(lead.status)}>
                    {statuses[lead.status] ?? lead.status}
                </Badge>
                <LeadActions
                    lead={lead}
                    products={products}
                    whatsappMessages={whatsappMessages}
                />
            </div>
            <details className="mt-3 rounded-md border bg-muted/30 p-2">
                <summary className="cursor-pointer text-xs font-medium text-muted-foreground">
                    Atividade rápida
                </summary>
                <Form
                    action={`/leads/${lead.id}/activities`}
                    method="post"
                    className="mt-2 grid gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <select
                                name="type"
                                defaultValue="whatsapp"
                                className="flex h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-xs shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                <option value="whatsapp">WhatsApp</option>
                                <option value="call">Ligação</option>
                                <option value="email">E-mail</option>
                                <option value="meeting">Reunião</option>
                                <option value="note">Nota</option>
                            </select>
                            <select
                                name="status"
                                defaultValue=""
                                className="flex h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-xs shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                <option value="">Manter status</option>
                                {Object.entries(statuses).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <select
                                name="lost_reason"
                                defaultValue={lead.lost_reason ?? ''}
                                className="flex h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-xs shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                <option value="">Motivo de perda</option>
                                {Object.entries(lostReasons).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <Input
                                name="next_follow_up_at"
                                type="date"
                                className="h-8 text-xs"
                            />
                            <textarea
                                name="description"
                                placeholder="Resumo do contato"
                                className="min-h-16 w-full rounded-md border border-input bg-background px-2 py-1 text-xs shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            />
                            {(errors.description || errors.lost_reason) && (
                                <p className="text-xs text-destructive">
                                    {errors.description || errors.lost_reason}
                                </p>
                            )}
                            <Button
                                size="sm"
                                type="submit"
                                disabled={processing}
                            >
                                Salvar atividade
                            </Button>
                        </>
                    )}
                </Form>
            </details>
        </article>
    );
}

export default function LeadsIndex({
    filters,
    kanbanLeads,
    kanbanTotal,
    taskLeads,
    taskTotals,
    leads,
    lostReasons,
    metrics,
    products,
    statuses,
}: Props) {
    const [activeTab, setActiveTab] = useState<
        'leads' | 'tasks' | 'kanban' | 'metrics'
    >('leads');
    const [draggedLeadId, setDraggedLeadId] = useState<number | null>(null);
    const [leadAwaitingLostReason, setLeadAwaitingLostReason] =
        useState<Lead | null>(null);
    const [selectedLostReason, setSelectedLostReason] =
        useState('no_response');
    const [whatsappMessages, setWhatsappMessages] = useState(
        whatsappMessageTemplates,
    );

    const leadsByStatus = useMemo(() => {
        return Object.keys(statuses).reduce<Record<string, Lead[]>>(
            (groups, status) => {
                groups[status] = kanbanLeads.filter(
                    (lead) => lead.status === status,
                );

                return groups;
            },
            {},
        );
    }, [kanbanLeads, statuses]);

    const handleDragStart = (event: DragEvent<HTMLElement>, lead: Lead) => {
        setDraggedLeadId(lead.id);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(lead.id));
    };

    const submitLeadStatus = (
        lead: Lead,
        targetStatus: string,
        lostReason: string | null = null,
    ) => {
        router.patch(
            `/leads/${lead.id}/status`,
            {
                status: targetStatus,
                lost_reason: targetStatus === 'lost' ? lostReason : null,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const updateLeadStatus = (targetStatus: string) => {
        if (!draggedLeadId) {
            return;
        }

        const draggedLead = kanbanLeads.find(
            (lead) => lead.id === draggedLeadId,
        );
        setDraggedLeadId(null);

        if (!draggedLead || draggedLead.status === targetStatus) {
            return;
        }

        if (targetStatus === 'lost') {
            setSelectedLostReason(
                draggedLead.lost_reason ?? 'no_response',
            );
            setLeadAwaitingLostReason(draggedLead);

            return;
        }

        submitLeadStatus(draggedLead, targetStatus);
    };

    useEffect(() => {
        const refreshMessages = () =>
            setWhatsappMessages(readWhatsappMessages());

        refreshMessages();
        window.addEventListener('focus', refreshMessages);

        return () => window.removeEventListener('focus', refreshMessages);
    }, []);

    return (
        <>
            <Head title="Prospecção" />

            <Dialog
                open={leadAwaitingLostReason !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setLeadAwaitingLostReason(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Marcar lead como perdido</DialogTitle>
                        <DialogDescription>
                            Informe o motivo da perda de{' '}
                            {leadAwaitingLostReason?.company_name}.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="kanban-lost-reason">
                            Motivo da perda
                        </Label>
                        <select
                            id="kanban-lost-reason"
                            value={selectedLostReason}
                            onChange={(event) =>
                                setSelectedLostReason(event.target.value)
                            }
                            className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs"
                        >
                            {Object.entries(lostReasons).map(
                                ([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </select>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setLeadAwaitingLostReason(null)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                if (!leadAwaitingLostReason) {
                                    return;
                                }

                                submitLeadStatus(
                                    leadAwaitingLostReason,
                                    'lost',
                                    selectedLostReason,
                                );
                                setLeadAwaitingLostReason(null);
                            }}
                        >
                            Confirmar perda
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4">
                    <Heading
                        title="Prospecção"
                        description="Organize leads, contatos e follow-ups comerciais."
                    />

                    <Button
                        type="button"
                        variant="destructive"
                        className="self-start"
                        disabled={kanbanTotal === 0}
                        onClick={() => {
                            if (
                                confirm(
                                    `Remover definitivamente todos os ${kanbanTotal} prospects e seus históricos?`,
                                )
                            ) {
                                router.delete('/leads/clear');
                            }
                        }}
                    >
                        <Trash2 className="size-4" />
                        Limpar prospects
                    </Button>

                    <div className="grid w-full gap-2 rounded-lg border bg-muted p-1 sm:inline-grid sm:w-auto sm:grid-cols-4">
                        <button
                            type="button"
                            onClick={() => setActiveTab('leads')}
                            className={cn(
                                'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                                activeTab === 'leads'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Plus className="size-4" />
                            Leads
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('tasks')}
                            className={cn(
                                'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                                activeTab === 'tasks'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <ListTodo className="size-4" />
                            Tarefas
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('kanban')}
                            className={cn(
                                'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                                activeTab === 'kanban'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Columns3 className="size-4" />
                            Kanban
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('metrics')}
                            className={cn(
                                'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                                activeTab === 'metrics'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <BarChart3 className="size-4" />
                            Métricas
                        </button>
                    </div>
                </div>

                {activeTab === 'kanban' && (
                    <div className="space-y-3">
                        {kanbanTotal > kanbanLeads.length && (
                            <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                                Exibindo os {kanbanLeads.length} leads mais
                                recentes de {kanbanTotal}. Use os filtros para
                                visualizar os demais no funil.
                            </div>
                        )}
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <Form
                                {...index.form()}
                                className="grid w-full gap-2 sm:grid-cols-2 lg:max-w-6xl lg:grid-cols-[1.3fr_0.85fr_0.9fr_0.5fr_0.85fr_0.75fr_auto]"
                            >
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="kanban-search"
                                        className="text-xs"
                                    >
                                        Busca
                                    </Label>
                                    <Input
                                        id="kanban-search"
                                        name="search"
                                        defaultValue={filters.search ?? ''}
                                        placeholder="Empresa, contato, e-mail..."
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="kanban-product"
                                        className="text-xs"
                                    >
                                        Produto
                                    </Label>
                                    <select
                                        id="kanban-product"
                                        name="product"
                                        defaultValue={filters.product ?? ''}
                                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <option value="">Todos</option>
                                        {Object.entries(products).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="kanban-industry"
                                        className="text-xs"
                                    >
                                        Ramo
                                    </Label>
                                    <Input
                                        id="kanban-industry"
                                        name="industry"
                                        defaultValue={filters.industry ?? ''}
                                        placeholder="Pet shop..."
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="kanban-state"
                                        className="text-xs"
                                    >
                                        UF
                                    </Label>
                                    <Input
                                        id="kanban-state"
                                        name="state"
                                        maxLength={2}
                                        defaultValue={filters.state ?? ''}
                                        placeholder="RS"
                                        className="uppercase"
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="kanban-follow-up"
                                        className="text-xs"
                                    >
                                        Follow-up
                                    </Label>
                                    <select
                                        id="kanban-follow-up"
                                        name="follow_up"
                                        defaultValue={filters.follow_up ?? ''}
                                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <option value="">Todos</option>
                                        <option value="overdue">
                                            Atrasados
                                        </option>
                                        <option value="today">Hoje</option>
                                        <option value="upcoming">
                                            Próximos
                                        </option>
                                    </select>
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="kanban-owner"
                                        className="text-xs"
                                    >
                                        Responsável
                                    </Label>
                                    <select
                                        id="kanban-owner"
                                        name="owner"
                                        defaultValue={filters.owner ?? ''}
                                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <option value="">Todos</option>
                                        <option value="mine">Meus leads</option>
                                    </select>
                                </div>
                                <div className="flex items-end">
                                    <Button
                                        className="w-full"
                                        variant="outline"
                                    >
                                        <Search className="size-4" />
                                        Filtrar
                                    </Button>
                                </div>
                            </Form>

                            <Button asChild>
                                <Link href={create()}>
                                    <Plus className="size-4" />
                                    Novo lead
                                </Link>
                            </Button>
                        </div>

                        <div className="grid gap-3 overflow-x-auto pb-2 xl:grid-cols-6">
                            {Object.entries(statuses).map(([status, label]) => {
                                const columnLeads = leadsByStatus[status] ?? [];

                                return (
                                    <section
                                        key={status}
                                        onDragOver={(event) => {
                                            event.preventDefault();
                                            event.dataTransfer.dropEffect =
                                                'move';
                                        }}
                                        onDrop={() => updateLeadStatus(status)}
                                        className="flex min-h-96 min-w-64 flex-col rounded-lg border bg-muted/30"
                                    >
                                        <header className="flex items-center justify-between border-b p-3">
                                            <Badge
                                                variant="outline"
                                                className={statusClass(status)}
                                            >
                                                {label}
                                            </Badge>
                                            <span className="text-xs font-medium text-muted-foreground">
                                                {columnLeads.length}
                                            </span>
                                        </header>
                                        <div
                                            className={cn(
                                                'flex flex-1 flex-col gap-2 p-2',
                                                columnLeads.length > 10 &&
                                                    'max-h-[70vh] overflow-y-auto overscroll-contain',
                                            )}
                                        >
                                            {columnLeads.length ? (
                                                columnLeads.map((lead) => (
                                                    <LeadKanbanCard
                                                        key={lead.id}
                                                        lead={lead}
                                                        lostReasons={
                                                            lostReasons
                                                        }
                                                        products={products}
                                                        statuses={statuses}
                                                        whatsappMessages={
                                                            whatsappMessages
                                                        }
                                                        onDragStart={
                                                            handleDragStart
                                                        }
                                                    />
                                                ))
                                            ) : (
                                                <div className="flex min-h-24 items-center justify-center rounded-md border border-dashed text-xs text-muted-foreground">
                                                    Arraste leads para cá
                                                </div>
                                            )}
                                        </div>
                                    </section>
                                );
                            })}
                        </div>
                    </div>
                )}

                {activeTab === 'metrics' && (
                    <div className="space-y-6">
                        <div className="grid gap-4 md:grid-cols-4">
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>
                                        Total no funil
                                    </CardDescription>
                                    <CardTitle className="text-3xl">
                                        {metrics.total}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>Em aberto</CardDescription>
                                    <CardTitle className="text-3xl">
                                        {metrics.open}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>
                                        Convertidos
                                    </CardDescription>
                                    <CardTitle className="text-3xl">
                                        {metrics.converted}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>Conversão</CardDescription>
                                    <CardTitle className="flex items-center gap-2 text-3xl">
                                        <BarChart3 className="size-6 text-muted-foreground" />
                                        {metrics.conversion_rate}%
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>
                                        Prioridade alta
                                    </CardDescription>
                                    <CardTitle className="text-3xl">
                                        {metrics.high_priority}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                        </div>

                        <div className="grid gap-4 md:grid-cols-4">
                            <Link
                                href={index({
                                    query: { follow_up: 'overdue' },
                                })}
                            >
                                <Card className="transition hover:border-destructive/50">
                                    <CardHeader className="pb-2">
                                        <CardDescription>
                                            Atrasados
                                        </CardDescription>
                                        <CardTitle className="text-2xl">
                                            {metrics.follow_ups.overdue}
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                            </Link>
                            <Link
                                href={index({ query: { follow_up: 'today' } })}
                            >
                                <Card className="transition hover:border-primary/50">
                                    <CardHeader className="pb-2">
                                        <CardDescription>
                                            Para hoje
                                        </CardDescription>
                                        <CardTitle className="text-2xl">
                                            {metrics.follow_ups.today}
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                            </Link>
                            <Link
                                href={index({
                                    query: { follow_up: 'upcoming' },
                                })}
                            >
                                <Card className="transition hover:border-primary/50">
                                    <CardHeader className="pb-2">
                                        <CardDescription>
                                            Próximos
                                        </CardDescription>
                                        <CardTitle className="text-2xl">
                                            {metrics.follow_ups.upcoming}
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                            </Link>
                            <Link
                                href={index({ query: { follow_up: 'none' } })}
                            >
                                <Card className="transition hover:border-primary/50">
                                    <CardHeader className="pb-2">
                                        <CardDescription>
                                            Sem follow-up
                                        </CardDescription>
                                        <CardTitle className="text-2xl">
                                            {
                                                metrics.follow_ups
                                                    .without_follow_up
                                            }
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                            </Link>
                        </div>
                    </div>
                )}

                {activeTab === 'tasks' && (
                    <div className="space-y-4">
                        <Form
                            {...index.form()}
                            className="grid gap-2 sm:grid-cols-2 lg:max-w-4xl lg:grid-cols-[1.4fr_1fr_1fr_auto]"
                        >
                            <Input
                                name="search"
                                defaultValue={filters.search ?? ''}
                                placeholder="Buscar empresa ou contato"
                            />
                            <select
                                name="product"
                                defaultValue={filters.product ?? ''}
                                className="flex h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Todos os produtos</option>
                                {Object.entries(products).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <select
                                name="owner"
                                defaultValue={filters.owner ?? ''}
                                className="flex h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Todos os responsáveis</option>
                                <option value="mine">Meus leads</option>
                            </select>
                            <Button variant="outline">
                                <Search className="size-4" />
                                Filtrar tarefas
                            </Button>
                        </Form>
                        <div className="grid gap-4 xl:grid-cols-3">
                            <TaskColumn
                                title="Atrasados"
                                description="Follow-ups que já passaram da data."
                                leads={taskLeads.overdue}
                                total={taskTotals.overdue}
                                products={products}
                                whatsappMessages={whatsappMessages}
                            />
                            <TaskColumn
                                title="Para hoje"
                                description="Contatos programados para hoje."
                                leads={taskLeads.today}
                                total={taskTotals.today}
                                products={products}
                                whatsappMessages={whatsappMessages}
                            />
                            <TaskColumn
                                title="Sem follow-up"
                                description="Leads abertos que ainda não têm próxima ação."
                                leads={taskLeads.without_follow_up}
                                total={taskTotals.without_follow_up}
                                products={products}
                                whatsappMessages={whatsappMessages}
                            />
                        </div>
                    </div>
                )}

                {activeTab === 'leads' && (
                    <div className="space-y-3">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <Form
                                {...index.form()}
                                className="grid w-full gap-2 sm:grid-cols-2 lg:max-w-7xl lg:grid-cols-[1.3fr_0.85fr_0.9fr_0.5fr_0.8fr_0.85fr_0.75fr_auto]"
                            >
                                <div className="grid gap-1">
                                    <Label htmlFor="search" className="text-xs">
                                        Busca
                                    </Label>
                                    <Input
                                        id="search"
                                        name="search"
                                        defaultValue={filters.search ?? ''}
                                        placeholder="Empresa, contato, e-mail..."
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="product"
                                        className="text-xs"
                                    >
                                        Produto
                                    </Label>
                                    <select
                                        id="product"
                                        name="product"
                                        defaultValue={filters.product ?? ''}
                                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <option value="">Todos</option>
                                        {Object.entries(products).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="industry"
                                        className="text-xs"
                                    >
                                        Ramo
                                    </Label>
                                    <Input
                                        id="industry"
                                        name="industry"
                                        defaultValue={filters.industry ?? ''}
                                        placeholder="Pet shop..."
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="state" className="text-xs">
                                        UF
                                    </Label>
                                    <Input
                                        id="state"
                                        name="state"
                                        maxLength={2}
                                        defaultValue={filters.state ?? ''}
                                        placeholder="RS"
                                        className="uppercase"
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="status" className="text-xs">
                                        Status
                                    </Label>
                                    <select
                                        id="status"
                                        name="status"
                                        defaultValue={filters.status ?? ''}
                                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <option value="">Todos</option>
                                        {Object.entries(statuses).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="follow_up"
                                        className="text-xs"
                                    >
                                        Follow-up
                                    </Label>
                                    <select
                                        id="follow_up"
                                        name="follow_up"
                                        defaultValue={filters.follow_up ?? ''}
                                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <option value="">Todos</option>
                                        <option value="overdue">
                                            Atrasados
                                        </option>
                                        <option value="today">Hoje</option>
                                        <option value="upcoming">
                                            Próximos
                                        </option>
                                    </select>
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="owner" className="text-xs">
                                        Responsável
                                    </Label>
                                    <select
                                        id="owner"
                                        name="owner"
                                        defaultValue={filters.owner ?? ''}
                                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <option value="">Todos</option>
                                        <option value="mine">Meus leads</option>
                                    </select>
                                </div>
                                <div className="flex items-end">
                                    <Button
                                        className="w-full"
                                        variant="outline"
                                    >
                                        <Search className="size-4" />
                                        Filtrar
                                    </Button>
                                </div>
                            </Form>

                            <div className="flex flex-col gap-2 sm:flex-row sm:items-end lg:justify-end">
                                <Form
                                    action="/leads/import"
                                    method="post"
                                    className="flex flex-col gap-2 sm:flex-row"
                                >
                                    {({ errors, processing }) => (
                                        <div className="grid items-end gap-1 sm:grid-cols-[minmax(220px,1fr)_auto]">
                                            <div>
                                                <Input
                                                    id="csv"
                                                    type="file"
                                                    name="csv"
                                                    accept=".csv,text/csv,text/plain"
                                                    className="text-xs"
                                                />
                                                <InputError
                                                    message={errors.csv}
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                <FileUp className="size-4" />
                                                CSV
                                            </Button>
                                        </div>
                                    )}
                                </Form>
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus className="size-4" />
                                        Novo lead
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <PaginationSummary data={leads} />

                        <div className="overflow-hidden rounded-lg border">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/50">
                                        <tr className="text-left text-xs font-medium text-muted-foreground">
                                            <th className="px-3 py-2">
                                                Empresa
                                            </th>
                                            <th className="px-3 py-2">
                                                Contato
                                            </th>
                                            <th className="px-3 py-2">
                                                Produto
                                            </th>
                                            <th className="px-3 py-2">Ramo</th>
                                            <th className="px-3 py-2">
                                                Cidade
                                            </th>
                                            <th className="px-3 py-2">
                                                Responsável
                                            </th>
                                            <th className="px-3 py-2">
                                                Status
                                            </th>
                                            <th className="px-3 py-2">
                                                Prioridade
                                            </th>
                                            <th className="px-3 py-2">
                                                Follow-up
                                            </th>
                                            <th className="px-3 py-2 text-right">
                                                Ações
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {leads.data.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={10}
                                                    className="h-24 px-3 text-center text-sm text-muted-foreground"
                                                >
                                                    Nenhum lead encontrado.
                                                </td>
                                            </tr>
                                        ) : (
                                            leads.data.map((lead) => {
                                                const leadProduct =
                                                    lead.product === 'vetorpet'
                                                        ? 'vetorpet'
                                                        : 'vetoros';
                                                const whatsapp = whatsappUrl(
                                                    lead.whatsapp,
                                                    whatsappMessages[
                                                        leadProduct
                                                    ],
                                                );

                                                return (
                                                    <tr
                                                        key={lead.id}
                                                        className="align-middle"
                                                    >
                                                        <td className="max-w-64 px-3 py-2">
                                                            <div className="truncate font-medium">
                                                                {
                                                                    lead.company_name
                                                                }
                                                            </div>
                                                            {lead.rating && (
                                                                <div className="text-xs text-amber-600">
                                                                    ★ {lead.rating}
                                                                    {lead.reviews !== null &&
                                                                        ` · ${lead.reviews} avaliações`}
                                                                </div>
                                                            )}
                                                            {lead.website && (
                                                                <div
                                                                    className="truncate text-xs text-muted-foreground"
                                                                    title={
                                                                        lead.website
                                                                    }
                                                                >
                                                                    {
                                                                        lead.website
                                                                    }
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="max-w-52 px-3 py-2">
                                                            <div className="truncate">
                                                                {lead.contact_name ||
                                                                    lead.whatsapp ||
                                                                    lead.phone ||
                                                                    '-'}
                                                            </div>
                                                            {lead.email && (
                                                                <div
                                                                    className="truncate text-xs text-muted-foreground"
                                                                    title={
                                                                        lead.email
                                                                    }
                                                                >
                                                                    {lead.email}
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="max-w-40 px-3 py-2 text-muted-foreground">
                                                            <Badge variant="secondary">
                                                                {products[
                                                                    lead.product
                                                                ] ??
                                                                    lead.product}
                                                            </Badge>
                                                        </td>
                                                        <td className="max-w-40 px-3 py-2 text-muted-foreground">
                                                            <span className="line-clamp-1">
                                                                {lead.category ||
                                                                    lead.industry ||
                                                                    '-'}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                                            {[
                                                                lead.city,
                                                                lead.state,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' / ') ||
                                                                '-'}
                                                            {lead.address && (
                                                                <div
                                                                    className="max-w-52 truncate text-xs"
                                                                    title={lead.address}
                                                                >
                                                                    {lead.address}
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="max-w-40 px-3 py-2 text-muted-foreground">
                                                            <span className="line-clamp-1">
                                                                {lead.user
                                                                    ?.name ??
                                                                    'Não definido'}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge
                                                                variant="outline"
                                                                className={statusClass(
                                                                    lead.status,
                                                                )}
                                                            >
                                                                {statuses[
                                                                    lead.status
                                                                ] ??
                                                                    lead.status}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge
                                                                variant="outline"
                                                                className={priorityClass(
                                                                    lead.priority,
                                                                )}
                                                            >
                                                                {priorityLabels[
                                                                    lead
                                                                        .priority
                                                                ] ??
                                                                    lead.priority}{' '}
                                                                ·{' '}
                                                                {
                                                                    lead.lead_score
                                                                }
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                                            <span className="inline-flex items-center gap-2">
                                                                <CalendarClock className="size-4" />
                                                                {formatDate(
                                                                    lead.next_follow_up_at,
                                                                )}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <div className="flex justify-end gap-1">
                                                                {whatsapp && (
                                                                    <Button
                                                                        asChild
                                                                        variant="outline"
                                                                        size="icon"
                                                                        title="Conversar no WhatsApp"
                                                                    >
                                                                        <a
                                                                            href={
                                                                                whatsapp
                                                                            }
                                                                            target="_blank"
                                                                            rel="noreferrer"
                                                                        >
                                                                            <MessageCircle className="size-4 text-emerald-600" />
                                                                        </a>
                                                                    </Button>
                                                                )}
                                                                <Button
                                                                    asChild
                                                                    variant="outline"
                                                                    size="icon"
                                                                    title="Editar lead"
                                                                >
                                                                    <Link
                                                                        href={edit(
                                                                            {
                                                                                lead: lead.id,
                                                                            },
                                                                        )}
                                                                    >
                                                                        <Pencil className="size-4" />
                                                                    </Link>
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="destructive"
                                                                    size="icon"
                                                                    title="Excluir prospect"
                                                                    aria-label={`Excluir prospect ${lead.company_name}`}
                                                                    onClick={() => {
                                                                        if (
                                                                            confirm(
                                                                                `Excluir o prospect “${lead.company_name}”?`,
                                                                            )
                                                                        ) {
                                                                            router.delete(
                                                                                destroy.url(
                                                                                    lead.id,
                                                                                ),
                                                                                {
                                                                                    preserveScroll:
                                                                                        true,
                                                                                },
                                                                            );
                                                                        }
                                                                    }}
                                                                >
                                                                    <Trash2 className="size-4" />
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <div className="border-t px-3">
                                <AppPagination data={leads} />
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Prospecção',
            href: index(),
        },
    ],
};
