import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { edit, index as leadsIndex } from '@/routes/leads';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    BarChart3,
    BookOpen,
    CalendarClock,
    CheckCircle2,
    CircleDollarSign,
    ListTodo,
    UsersRound,
} from 'lucide-react';

type LeadSummary = {
    id: number;
    company_name: string;
    contact_name?: string | null;
    product: string;
    status: string;
    lead_score: number;
    priority: 'high' | 'medium' | 'low';
    next_follow_up_at?: string | null;
    created_at?: string | null;
};

type LeadActivitySummary = {
    id: number;
    type: string;
    status?: string | null;
    description: string;
    contacted_at?: string | null;
    lead?: {
        id: number;
        company_name: string;
    } | null;
};

type Props = {
    metrics: {
        total: number;
        open: number;
        converted: number;
        lost: number;
        conversion_rate: number;
        high_priority: number;
        follow_ups: {
            overdue: number;
            today: number;
        };
        by_status: Record<string, number>;
        by_product: Record<string, number>;
    };
    recentLeads: LeadSummary[];
    priorityLeads: LeadSummary[];
    nextFollowUps: LeadSummary[];
    recentActivities: LeadActivitySummary[];
    products: Record<string, string>;
    statuses: Record<string, string>;
};

function formatDate(value?: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
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

export default function Dashboard({
    metrics,
    recentLeads,
    priorityLeads,
    nextFollowUps,
    recentActivities,
    products,
    statuses,
}: Props) {
    const funnelTotal = Math.max(metrics.total, 1);

    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <Heading
                        title="Dashboard comercial"
                        description="Acompanhe funil, conversões e follow-ups do CRM."
                    />
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button asChild variant="outline">
                            <Link href={leadsIndex()}>
                                Ver leads
                                <ArrowRight className="ml-2 size-4" />
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="/admin/blog/posts">
                                Blog
                                <BookOpen className="ml-2 size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total no funil</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-3xl">
                                <UsersRound className="size-6 text-muted-foreground" />
                                {metrics.total}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Em aberto</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-3xl">
                                <ListTodo className="size-6 text-muted-foreground" />
                                {metrics.open}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Convertidos</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-3xl">
                                <CheckCircle2 className="size-6 text-emerald-600" />
                                {metrics.converted}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Taxa de conversão</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-3xl">
                                <BarChart3 className="size-6 text-muted-foreground" />
                                {metrics.conversion_rate}%
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Prioridade alta</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-3xl">
                                <AlertTriangle className="size-6 text-red-600" />
                                {metrics.high_priority}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Funil por status</CardTitle>
                            <CardDescription>
                                Distribuição atual dos leads cadastrados.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {Object.entries(statuses).map(([status, label]) => {
                                const total = metrics.by_status[status] ?? 0;
                                const percent = Math.round(
                                    (total / funnelTotal) * 100,
                                );

                                return (
                                    <div key={status} className="space-y-1.5">
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className={statusClass(
                                                        status,
                                                    )}
                                                >
                                                    {label}
                                                </Badge>
                                            </div>
                                            <span className="font-medium text-muted-foreground">
                                                {total}
                                            </span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-primary"
                                                style={{ width: `${percent}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <div className="grid gap-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>
                                    Follow-ups atrasados
                                </CardDescription>
                                <CardTitle className="flex items-center gap-2 text-3xl">
                                    <AlertTriangle className="size-6 text-amber-600" />
                                    {metrics.follow_ups.overdue}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>
                                    Follow-ups para hoje
                                </CardDescription>
                                <CardTitle className="flex items-center gap-2 text-3xl">
                                    <CalendarClock className="size-6 text-sky-600" />
                                    {metrics.follow_ups.today}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>
                                    Produtos em prospecção
                                </CardDescription>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {Object.entries(products).map(
                                        ([product, label]) => (
                                            <Badge
                                                key={product}
                                                variant="secondary"
                                            >
                                                {label}:{' '}
                                                {metrics.by_product[product] ??
                                                    0}
                                            </Badge>
                                        ),
                                    )}
                                </div>
                            </CardHeader>
                        </Card>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Leads prioritários</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {priorityLeads.length ? (
                                priorityLeads.map((lead) => (
                                    <Link
                                        key={lead.id}
                                        href={edit({ lead: lead.id })}
                                        className="block rounded-md border p-3 transition hover:bg-muted/50"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {lead.company_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Score {lead.lead_score}/100
                                                </p>
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className={priorityClass(
                                                    lead.priority,
                                                )}
                                            >
                                                {priorityLabels[
                                                    lead.priority
                                                ] ?? lead.priority}
                                            </Badge>
                                        </div>
                                    </Link>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Nenhum lead prioritário no momento.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Próximos follow-ups</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {nextFollowUps.length ? (
                                nextFollowUps.map((lead) => (
                                    <Link
                                        key={lead.id}
                                        href={edit({ lead: lead.id })}
                                        className="block rounded-md border p-3 transition hover:bg-muted/50"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {lead.company_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        lead.next_follow_up_at,
                                                    )}
                                                </p>
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className={statusClass(
                                                    lead.status,
                                                )}
                                            >
                                                {statuses[lead.status] ??
                                                    lead.status}
                                            </Badge>
                                        </div>
                                    </Link>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Nenhum follow-up agendado.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Leads recentes</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {recentLeads.length ? (
                                recentLeads.map((lead) => (
                                    <Link
                                        key={lead.id}
                                        href={edit({ lead: lead.id })}
                                        className="block rounded-md border p-3 transition hover:bg-muted/50"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {lead.company_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {products[lead.product] ??
                                                        lead.product}{' '}
                                                    ·{' '}
                                                    {formatDate(
                                                        lead.created_at,
                                                    )}
                                                </p>
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className={statusClass(
                                                    lead.status,
                                                )}
                                            >
                                                {statuses[lead.status] ??
                                                    lead.status}
                                            </Badge>
                                        </div>
                                    </Link>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Nenhum lead cadastrado.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Atividades recentes</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {recentActivities.length ? (
                                recentActivities.map((activity) => (
                                    <div
                                        key={activity.id}
                                        className="rounded-md border p-3"
                                    >
                                        <div className="flex items-start gap-2">
                                            <CircleDollarSign className="mt-0.5 size-4 text-muted-foreground" />
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {activity.lead
                                                        ?.company_name ??
                                                        'Lead removido'}
                                                </p>
                                                <p className="line-clamp-2 text-xs text-muted-foreground">
                                                    {activity.description}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {formatDate(
                                                        activity.contacted_at,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Nenhuma atividade registrada.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
