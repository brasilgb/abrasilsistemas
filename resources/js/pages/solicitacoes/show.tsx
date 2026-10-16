import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, MessagesSquare } from 'lucide-react';
import { useState } from 'react';
import { formatWhatsapp } from '@/components/company-whatsapp-connection';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatRequestDate, requestStatusClasses } from '@/lib/site-requests';
import type { RequestStatus } from '@/lib/site-requests';
import { cn } from '@/lib/utils';
import { index as atendimentoIndex } from '@/routes/atendimento';
import { index, status as statusRoute } from '@/routes/solicitacoes';

type Props = {
    request: {
        id: number;
        received_at: string | null;
        last_contact_at: string | null;
        contact_name: string | null;
        company_name: string;
        whatsapp: string | null;
        phone: string | null;
        email: string | null;
        project_type: string | null;
        message: string | null;
        source_page: string | null;
        request_status: RequestStatus | null;
    };
    conversations: {
        id: number;
        phone: string | null;
        last_message_at: string | null;
        preview: string | null;
    }[];
    history: {
        id: number;
        type: string;
        description: string | null;
        created_at: string | null;
    }[];
    statuses: Record<RequestStatus, string>;
};

export default function SolicitacaoShow({
    request,
    conversations,
    history,
    statuses,
}: Props) {
    const [saving, setSaving] = useState<RequestStatus | null>(null);

    function changeStatus(next: RequestStatus) {
        if (next === request.request_status) {
            return;
        }

        router.patch(
            statusRoute(request.id).url,
            { request_status: next },
            {
                preserveScroll: true,
                onStart: () => setSaving(next),
                onFinish: () => setSaving(null),
            },
        );
    }

    const details: { label: string; value: string | null }[] = [
        { label: 'Recebida em', value: formatRequestDate(request.received_at) },
        {
            label: 'Último contato pelo site',
            value: request.last_contact_at
                ? formatRequestDate(request.last_contact_at)
                : null,
        },
        { label: 'Empresa', value: request.company_name },
        {
            label: 'WhatsApp',
            value: request.whatsapp ? formatWhatsapp(request.whatsapp) : null,
        },
        { label: 'Telefone', value: request.phone },
        { label: 'E-mail', value: request.email },
        { label: 'Tipo de projeto', value: request.project_type },
        { label: 'Página de origem', value: request.source_page },
    ];

    return (
        <>
            <Head
                title={`Solicitação de ${request.contact_name ?? request.company_name}`}
            />

            <div className="space-y-6 p-4">
                <Link
                    href={index()}
                    className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Solicitações
                </Link>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={request.contact_name ?? 'Sem nome'}
                        description={request.company_name}
                    />
                    {request.request_status && (
                        <span
                            className={cn(
                                'inline-flex w-fit items-center rounded-md border px-2.5 py-1 text-sm font-medium',
                                requestStatusClasses[request.request_status],
                            )}
                        >
                            {statuses[request.request_status]}
                        </span>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <div className="space-y-6">
                        <section className="rounded-md border p-4">
                            <h2 className="text-sm font-medium">Mensagem</h2>
                            <p className="mt-3 text-sm leading-6 whitespace-pre-line">
                                {request.message || '—'}
                            </p>
                        </section>

                        <section className="rounded-md border p-4">
                            <h2 className="text-sm font-medium">Situação</h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Só para organizar a equipe. Um novo contato da
                                mesma pessoa pelo site reabre a solicitação como
                                nova.
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {(
                                    Object.entries(statuses) as [
                                        RequestStatus,
                                        string,
                                    ][]
                                ).map(([value, label]) => (
                                    <Button
                                        key={value}
                                        type="button"
                                        size="sm"
                                        variant={
                                            request.request_status === value
                                                ? 'default'
                                                : 'outline'
                                        }
                                        aria-pressed={
                                            request.request_status === value
                                        }
                                        disabled={saving !== null}
                                        onClick={() => changeStatus(value)}
                                    >
                                        {saving === value
                                            ? 'Salvando...'
                                            : value === 'archived'
                                              ? 'Arquivar'
                                              : label}
                                    </Button>
                                ))}
                            </div>
                        </section>

                        {history.length > 0 && (
                            <section className="rounded-md border p-4">
                                <h2 className="text-sm font-medium">
                                    Histórico
                                </h2>
                                <ol className="mt-3 space-y-3">
                                    {history.map((item) => (
                                        <li
                                            key={item.id}
                                            className="border-l-2 pl-3 text-sm"
                                        >
                                            <p className="text-xs text-muted-foreground">
                                                {formatRequestDate(
                                                    item.created_at,
                                                )}{' '}
                                                · {item.type}
                                            </p>
                                            <p className="mt-0.5 whitespace-pre-line">
                                                {item.description ?? '—'}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </section>
                        )}
                    </div>

                    <aside className="space-y-6">
                        <section className="rounded-md border p-4">
                            <h2 className="text-sm font-medium">Dados</h2>
                            <dl className="mt-3 space-y-3 text-sm">
                                {details.map((detail) => (
                                    <div key={detail.label}>
                                        <dt className="text-xs text-muted-foreground">
                                            {detail.label}
                                        </dt>
                                        <dd className="mt-0.5 break-words">
                                            {detail.value || '—'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        <section className="rounded-md border p-4">
                            <h2 className="flex items-center gap-2 text-sm font-medium">
                                <MessagesSquare className="size-4" />
                                Conversa no WhatsApp
                            </h2>
                            {conversations.length === 0 ? (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Ainda não há conversa vinculada. Quando a
                                    pessoa escrever para o WhatsApp do
                                    atendimento pelo mesmo número, a conversa
                                    aparece aqui.
                                </p>
                            ) : (
                                <ul className="mt-3 space-y-2">
                                    {conversations.map((conversation) => (
                                        <li key={conversation.id}>
                                            <Link
                                                href={atendimentoIndex({
                                                    query: {
                                                        conversa:
                                                            conversation.id,
                                                    },
                                                })}
                                                className="block rounded-md border p-3 text-sm transition hover:bg-muted/50"
                                            >
                                                <span className="block font-medium">
                                                    {formatWhatsapp(
                                                        conversation.phone,
                                                    )}
                                                </span>
                                                <span className="mt-1 line-clamp-2 block text-xs text-muted-foreground">
                                                    {conversation.preview ??
                                                        'Sem mensagens'}
                                                </span>
                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                    Abrir no Atendimento ·{' '}
                                                    {formatRequestDate(
                                                        conversation.last_message_at,
                                                    )}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

SolicitacaoShow.layout = {
    breadcrumbs: [{ title: 'Solicitações', href: index() }],
};
