import { Form, Head, Link } from '@inertiajs/react';
import {
    BarChart3,
    CalendarClock,
    FileUp,
    MessageCircle,
    Pencil,
    Plus,
    Search,
} from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    readWhatsappMessages,
    whatsappMessageTemplates,
} from '@/lib/lead-whatsapp-messages';
import { cn } from '@/lib/utils';
import { create, edit, index } from '@/routes/leads';
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
    };
    leads: Paginated<Lead>;
    metrics: {
        total: number;
        open: number;
        converted: number;
        conversion_rate: number;
        follow_ups: {
            overdue: number;
            today: number;
            upcoming: number;
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

export default function LeadsIndex({
    filters,
    leads,
    metrics,
    products,
    statuses,
}: Props) {
    const [activeTab, setActiveTab] = useState<'leads' | 'metrics'>('leads');
    const [whatsappMessages, setWhatsappMessages] = useState(
        whatsappMessageTemplates,
    );

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

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4">
                    <Heading
                        title="Prospecção"
                        description="Organize leads, contatos e follow-ups comerciais."
                    />

                    <div className="grid w-full gap-2 rounded-lg border bg-muted p-1 sm:inline-grid sm:w-auto sm:grid-cols-2">
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
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
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
                        </div>
                    </div>
                )}

                {activeTab === 'leads' && (
                    <div className="space-y-3">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <Form
                                {...index.form()}
                                className="grid w-full gap-2 sm:grid-cols-2 lg:max-w-6xl lg:grid-cols-[1.3fr_0.85fr_0.9fr_0.5fr_0.8fr_0.85fr_auto]"
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

                            <div className="flex flex-col gap-2 sm:flex-row lg:justify-end">
                                <Form
                                    action="/leads/import"
                                    method="post"
                                    className="flex flex-col gap-2 sm:flex-row"
                                >
                                    {({ errors, processing }) => (
                                        <div className="grid gap-1 sm:grid-cols-[minmax(220px,1fr)_auto]">
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
                                                Status
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
                                                    colSpan={8}
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
                                                                {lead.industry ||
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
