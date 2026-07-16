import { Form, Head, Link } from '@inertiajs/react';
import { CalendarClock, History, MessageSquarePlus } from 'lucide-react';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Spinner } from '@/components/ui/spinner';
import { store as storeLeadActivity } from '@/routes/leads/activities';
import { index } from '@/routes/leads';
import LeadForm, { type Lead } from '@/pages/leads/form';

type Props = {
    activityTypes: Record<string, string>;
    lead: Lead;
    statuses: Record<string, string>;
};

function formatDateTime(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

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

export default function EditLead({ activityTypes, lead, statuses }: Props) {
    return (
        <>
            <Head title={`Editar ${lead.company_name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Editar lead"
                    description="Atualize status, contatos e próximos passos."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(380px,0.9fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>{lead.company_name}</CardTitle>
                            <CardDescription>
                                Responsável:{' '}
                                {lead.user?.name ?? 'Sem responsável'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...LeadController.update.form({
                                    lead: lead.id,
                                })}
                                disableWhileProcessing
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <LeadForm
                                            lead={lead}
                                            statuses={statuses}
                                            processing={processing}
                                            errors={errors}
                                        />
                                        <div className="flex justify-end">
                                            <Button asChild variant="outline">
                                                <Link href={index()}>
                                                    Cancelar
                                                </Link>
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MessageSquarePlus className="size-5" />
                                    Registrar contato
                                </CardTitle>
                                <CardDescription>
                                    Adicione uma interação ao histórico do lead.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action={storeLeadActivity({
                                        lead: lead.id,
                                    })}
                                    method="post"
                                    resetOnSuccess={[
                                        'description',
                                        'contacted_at',
                                    ]}
                                    disableWhileProcessing
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="type">
                                                    Tipo
                                                </Label>
                                                <select
                                                    id="type"
                                                    name="type"
                                                    defaultValue="whatsapp"
                                                    className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                                >
                                                    {Object.entries(
                                                        activityTypes,
                                                    ).map(([value, label]) => (
                                                        <option
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError
                                                    message={errors.type}
                                                />
                                            </div>

                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="status">
                                                        Novo status
                                                    </Label>
                                                    <select
                                                        id="status"
                                                        name="status"
                                                        defaultValue={
                                                            lead.status
                                                        }
                                                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                                    >
                                                        <option value="">
                                                            Manter status
                                                        </option>
                                                        {Object.entries(
                                                            statuses,
                                                        ).map(
                                                            ([
                                                                value,
                                                                label,
                                                            ]) => (
                                                                <option
                                                                    key={value}
                                                                    value={
                                                                        value
                                                                    }
                                                                >
                                                                    {label}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                    <InputError
                                                        message={errors.status}
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="contacted_at">
                                                        Quando
                                                    </Label>
                                                    <Input
                                                        id="contacted_at"
                                                        type="datetime-local"
                                                        name="contacted_at"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.contacted_at
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="next_follow_up_at_activity">
                                                    Próximo follow-up
                                                </Label>
                                                <Input
                                                    id="next_follow_up_at_activity"
                                                    type="date"
                                                    name="next_follow_up_at"
                                                />
                                                <InputError
                                                    message={
                                                        errors.next_follow_up_at
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="description">
                                                    Descrição
                                                </Label>
                                                <textarea
                                                    id="description"
                                                    name="description"
                                                    required
                                                    placeholder="O que foi conversado? Qual o próximo passo?"
                                                    className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                                />
                                                <InputError
                                                    message={errors.description}
                                                />
                                            </div>

                                            <Button disabled={processing}>
                                                {processing && <Spinner />}
                                                Registrar histórico
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <History className="size-5" />
                                    Histórico
                                </CardTitle>
                                <CardDescription>
                                    Contatos e anotações registrados neste lead.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {lead.activities &&
                                lead.activities.length > 0 ? (
                                    <div className="space-y-4">
                                        {lead.activities.map((activity) => (
                                            <div
                                                key={activity.id}
                                                className="rounded-md border p-4"
                                            >
                                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {activityTypes[
                                                                activity.type
                                                            ] ?? activity.type}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {activity.user
                                                                ?.name ??
                                                                'Sem responsável'}
                                                        </p>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatDateTime(
                                                            activity.created_at,
                                                        )}
                                                    </p>
                                                </div>

                                                <p className="mt-3 text-sm leading-6 whitespace-pre-line">
                                                    {activity.description}
                                                </p>

                                                <div className="mt-3 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                                    {activity.status && (
                                                        <span>
                                                            Status:{' '}
                                                            {statuses[
                                                                activity.status
                                                            ] ??
                                                                activity.status}
                                                        </span>
                                                    )}
                                                    {activity.contacted_at && (
                                                        <span>
                                                            Contato:{' '}
                                                            {formatDateTime(
                                                                activity.contacted_at,
                                                            )}
                                                        </span>
                                                    )}
                                                    {activity.next_follow_up_at && (
                                                        <span className="inline-flex items-center gap-1">
                                                            <CalendarClock className="size-3" />
                                                            Follow-up:{' '}
                                                            {formatDate(
                                                                activity.next_follow_up_at,
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum contato registrado ainda.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

EditLead.layout = {
    breadcrumbs: [
        {
            title: 'Prospecção',
            href: index(),
        },
        {
            title: 'Editar lead',
            href: index(),
        },
    ],
};
