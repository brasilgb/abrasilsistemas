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
import { create, edit, index } from '@/routes/leads';
import type { Lead } from '@/pages/leads/form';

type Paginated<T> = {
    data: T[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    total: number;
};

type Props = {
    filters: {
        search?: string;
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

function whatsappUrl(value: string | null) {
    if (!value) {
        return null;
    }

    const phone = value.replace(/\D/g, '');

    return phone ? `https://wa.me/55${phone}` : null;
}

export default function LeadsIndex({
    filters,
    leads,
    metrics,
    statuses,
}: Props) {
    return (
        <>
            <Head title="Prospecção" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Prospecção"
                        description="Organize leads, contatos e follow-ups comerciais."
                    />

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Novo lead
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total no funil</CardDescription>
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
                            <CardDescription>Convertidos</CardDescription>
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
                    <Link href={index({ query: { follow_up: 'overdue' } })}>
                        <Card className="transition hover:border-destructive/50">
                            <CardHeader className="pb-2">
                                <CardDescription>Atrasados</CardDescription>
                                <CardTitle className="text-2xl">
                                    {metrics.follow_ups.overdue}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    </Link>
                    <Link href={index({ query: { follow_up: 'today' } })}>
                        <Card className="transition hover:border-primary/50">
                            <CardHeader className="pb-2">
                                <CardDescription>Para hoje</CardDescription>
                                <CardTitle className="text-2xl">
                                    {metrics.follow_ups.today}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    </Link>
                    <Link href={index({ query: { follow_up: 'upcoming' } })}>
                        <Card className="transition hover:border-primary/50">
                            <CardHeader className="pb-2">
                                <CardDescription>Próximos</CardDescription>
                                <CardTitle className="text-2xl">
                                    {metrics.follow_ups.upcoming}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileUp className="size-5 text-muted-foreground" />
                            Importar leads por CSV
                        </CardTitle>
                        <CardDescription>
                            Campo obrigatorio: company_name. Campos aceitos:
                            company_name, contact_name, industry, city, state,
                            phone, whatsapp, email, website, instagram, source,
                            status, next_follow_up_at e notes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action="/leads/import"
                            method="post"
                            className="grid gap-4 md:grid-cols-[1fr_auto]"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="csv">Arquivo CSV</Label>
                                        <Input
                                            id="csv"
                                            type="file"
                                            name="csv"
                                            accept=".csv,text/csv,text/plain"
                                        />
                                        <InputError message={errors.csv} />
                                    </div>
                                    <div className="flex items-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full md:w-auto"
                                        >
                                            <FileUp className="size-4" />
                                            Importar leads
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Filtros</CardTitle>
                        <CardDescription>
                            Pesquise por empresa, cidade, ramo, status ou
                            contato.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...index.form()}
                            className="grid gap-4 md:grid-cols-[1.4fr_1fr_0.7fr_1fr_1fr_auto]"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="search">Busca</Label>
                                <Input
                                    id="search"
                                    name="search"
                                    defaultValue={filters.search ?? ''}
                                    placeholder="Empresa, contato, e-mail..."
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="industry">Ramo</Label>
                                <Input
                                    id="industry"
                                    name="industry"
                                    defaultValue={filters.industry ?? ''}
                                    placeholder="Pet shop, assistência..."
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="state">UF</Label>
                                <Input
                                    id="state"
                                    name="state"
                                    maxLength={2}
                                    defaultValue={filters.state ?? ''}
                                    placeholder="RS"
                                    className="uppercase"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="status">Status</Label>
                                <select
                                    id="status"
                                    name="status"
                                    defaultValue={filters.status ?? ''}
                                    className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(statuses).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="follow_up">Follow-up</Label>
                                <select
                                    id="follow_up"
                                    name="follow_up"
                                    defaultValue={filters.follow_up ?? ''}
                                    className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    <option value="">Todos</option>
                                    <option value="overdue">Atrasados</option>
                                    <option value="today">Hoje</option>
                                    <option value="upcoming">Próximos</option>
                                </select>
                            </div>
                            <div className="flex items-end">
                                <Button className="w-full">
                                    <Search className="size-4" />
                                    Filtrar
                                </Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Leads</CardTitle>
                        <CardDescription>
                            {leads.total} lead(s) no funil.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-hidden rounded-md border">
                            <div className="hidden grid-cols-[1.3fr_1fr_0.8fr_0.8fr_0.8fr_auto] gap-4 border-b bg-muted/50 px-4 py-3 text-xs font-medium text-muted-foreground lg:grid">
                                <span>Empresa</span>
                                <span>Ramo</span>
                                <span>Cidade</span>
                                <span>Status</span>
                                <span>Follow-up</span>
                                <span className="text-right">Ações</span>
                            </div>

                            {leads.data.length === 0 ? (
                                <div className="px-4 py-10 text-center text-sm text-muted-foreground">
                                    Nenhum lead encontrado.
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {leads.data.map((lead) => {
                                        const whatsapp = whatsappUrl(
                                            lead.whatsapp,
                                        );

                                        return (
                                            <div
                                                key={lead.id}
                                                className="grid gap-3 px-4 py-4 lg:grid-cols-[1.3fr_1fr_0.8fr_0.8fr_0.8fr_auto] lg:items-center lg:gap-4"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">
                                                        {lead.company_name}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {lead.contact_name ||
                                                            lead.whatsapp ||
                                                            lead.email ||
                                                            'Sem contato'}
                                                    </p>
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {lead.industry || '-'}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {[lead.city, lead.state]
                                                        .filter(Boolean)
                                                        .join(' / ') || '-'}
                                                </p>
                                                <Badge variant="secondary">
                                                    {statuses[lead.status] ??
                                                        lead.status}
                                                </Badge>
                                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                                    <CalendarClock className="size-4" />
                                                    {formatDate(
                                                        lead.next_follow_up_at,
                                                    )}
                                                </p>
                                                <div className="flex gap-2 lg:justify-end">
                                                    {whatsapp && (
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <a
                                                                href={whatsapp}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                <MessageCircle className="size-4" />
                                                            </a>
                                                        </Button>
                                                    )}
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={edit({
                                                                lead: lead.id,
                                                            })}
                                                        >
                                                            <Pencil className="size-4" />
                                                            Editar
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
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
