import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Globe,
    Paperclip,
    Phone,
    Search,
    Send,
    UserRound,
} from 'lucide-react';
import type { FormEvent, KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import WhatsappMessageStatus from '@/components/whatsapp-message-status';
import { cn } from '@/lib/utils';

type ConversationSummary = {
    id: number;
    name: string;
    phone: string | null;
    preview: string | null;
    last_message_at: string | null;
    unread: number;
    source_page: string | null;
};

type Message = {
    id: number;
    direction: 'inbound' | 'outbound';
    body: string;
    has_media: boolean;
    status: string | null;
    sent_at: string;
    sender: string | null;
};

type ActiveConversation = {
    id: number;
    name: string;
    push_name: string | null;
    phone: string | null;
    chat_id: string;
    source_page: string | null;
    first_contact_at: string | null;
    lead: {
        id: number;
        company_name: string;
        contact_name: string | null;
        source: string | null;
    } | null;
    messages: Message[];
};

type Props = {
    session: string | null;
    webhookEnabled: boolean;
    filters: { busca: string };
    unreadTotal: number;
    conversations: ConversationSummary[];
    active: ActiveConversation | null;
};

/** Intervalo de atualização: o WAHA grava no banco pelo webhook e o painel busca as novidades. */
const POLL_INTERVAL = 5000;

function formatPhone(phone: string | null): string | null {
    if (!phone) {
        return null;
    }

    const match = phone.match(/^55(\d{2})(\d{4,5})(\d{4})$/);

    return match ? `(${match[1]}) ${match[2]}-${match[3]}` : `+${phone}`;
}

function formatListTime(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const sameDay = date.toDateString() === new Date().toDateString();

    return new Intl.DateTimeFormat(
        'pt-BR',
        sameDay
            ? { hour: '2-digit', minute: '2-digit' }
            : { day: '2-digit', month: '2-digit' },
    ).format(date);
}

function formatMessageTime(value: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function conversationHref(id: number, search: string): string {
    const params = new URLSearchParams({ conversa: String(id) });

    if (search) {
        params.set('busca', search);
    }

    return `/atendimento?${params.toString()}`;
}

export default function AtendimentoIndex({
    session,
    webhookEnabled,
    filters,
    unreadTotal,
    conversations,
    active,
}: Props) {
    const [search, setSearch] = useState(filters.busca);

    usePoll(POLL_INTERVAL, {
        only: ['conversations', 'active', 'unreadTotal'],
    });

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        router.get(
            '/atendimento',
            { busca: search || undefined, conversa: active?.id },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <>
            <Head
                title={
                    unreadTotal > 0
                        ? `(${unreadTotal}) Atendimento`
                        : 'Atendimento'
                }
            />

            <div className="flex h-[calc(100dvh-4rem)] min-h-[32rem] flex-col">
                {(session === null || !webhookEnabled) && (
                    <div className="flex items-start gap-2 border-b border-amber-300/60 bg-amber-50 px-4 py-2.5 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-200">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <p>
                            {session === null ? (
                                <>
                                    Nenhuma sessão do WhatsApp configurada.
                                    Defina a sessão em{' '}
                                    <Link
                                        href="/settings/leads"
                                        className="font-medium underline"
                                    >
                                        Configurações → WhatsApp da empresa
                                    </Link>
                                    .
                                </>
                            ) : (
                                'O recebimento está desligado: falta WAHA_WEBHOOK_HMAC_KEY no ambiente e o webhook na sessão do WAHA. As respostas podem ser enviadas, mas novas mensagens não chegam.'
                            )}
                        </p>
                    </div>
                )}

                <div className="grid min-h-0 flex-1 md:grid-cols-[20rem_1fr] xl:grid-cols-[20rem_1fr_18rem]">
                    {/* Conversas */}
                    <aside
                        className={cn(
                            'flex min-h-0 flex-col border-r',
                            active ? 'hidden md:flex' : 'flex',
                        )}
                    >
                        <div className="border-b p-3">
                            <div className="flex items-center justify-between">
                                <h1 className="text-base font-semibold">
                                    Atendimento
                                </h1>
                                {unreadTotal > 0 && (
                                    <span className="rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-semibold text-white">
                                        {unreadTotal} não{' '}
                                        {unreadTotal === 1 ? 'lida' : 'lidas'}
                                    </span>
                                )}
                            </div>
                            <form
                                onSubmit={submitSearch}
                                className="relative mt-3"
                            >
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <input
                                    type="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Buscar por nome ou telefone"
                                    aria-label="Buscar contato"
                                    className="h-9 w-full rounded-md border bg-background pr-3 pl-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                />
                            </form>
                        </div>
                        <nav
                            className="min-h-0 flex-1 overflow-y-auto"
                            aria-label="Conversas"
                        >
                            {conversations.length === 0 ? (
                                <p className="p-6 text-center text-sm text-muted-foreground">
                                    {filters.busca
                                        ? 'Nenhum contato encontrado.'
                                        : 'Nenhuma conversa ainda.'}
                                </p>
                            ) : (
                                <ul>
                                    {conversations.map((conversation) => (
                                        <li key={conversation.id}>
                                            <Link
                                                href={conversationHref(
                                                    conversation.id,
                                                    filters.busca,
                                                )}
                                                preserveScroll
                                                className={cn(
                                                    'flex gap-3 border-b px-3 py-3 transition hover:bg-muted/60',
                                                    active?.id ===
                                                        conversation.id &&
                                                        'bg-muted',
                                                )}
                                            >
                                                <span className="grid size-10 shrink-0 place-items-center rounded-full bg-muted text-sm font-semibold text-muted-foreground">
                                                    {/^\p{L}/u.test(
                                                        conversation.name,
                                                    ) ? (
                                                        conversation.name
                                                            .charAt(0)
                                                            .toUpperCase()
                                                    ) : (
                                                        <UserRound className="size-4" />
                                                    )}
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="flex items-baseline justify-between gap-2">
                                                        <span
                                                            className={cn(
                                                                'truncate text-sm',
                                                                conversation.unread >
                                                                    0
                                                                    ? 'font-semibold'
                                                                    : 'font-medium',
                                                            )}
                                                        >
                                                            {conversation.name}
                                                        </span>
                                                        <span className="shrink-0 text-xs text-muted-foreground">
                                                            {formatListTime(
                                                                conversation.last_message_at,
                                                            )}
                                                        </span>
                                                    </span>
                                                    <span className="mt-0.5 flex items-center justify-between gap-2">
                                                        <span className="truncate text-xs text-muted-foreground">
                                                            {
                                                                conversation.preview
                                                            }
                                                        </span>
                                                        {conversation.unread >
                                                            0 && (
                                                            <span
                                                                className="grid min-w-5 shrink-0 place-items-center rounded-full bg-emerald-600 px-1.5 text-[11px] font-semibold text-white"
                                                                aria-label={`${conversation.unread} não lidas`}
                                                            >
                                                                {
                                                                    conversation.unread
                                                                }
                                                            </span>
                                                        )}
                                                    </span>
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </nav>
                    </aside>

                    {/* Mensagens */}
                    {active ? (
                        <ConversationPanel
                            key={active.id}
                            conversation={active}
                            search={filters.busca}
                        />
                    ) : (
                        <div className="hidden place-items-center p-8 text-center text-sm text-muted-foreground md:grid xl:col-span-2">
                            Selecione uma conversa para ver as mensagens.
                        </div>
                    )}

                    {/* Contato */}
                    {active && <ContactPanel conversation={active} />}
                </div>
            </div>
        </>
    );
}

function ConversationPanel({
    conversation,
    search,
}: {
    conversation: ActiveConversation;
    search: string;
}) {
    const bottomRef = useRef<HTMLDivElement>(null);
    const lastMessageId = conversation.messages.at(-1)?.id;
    const form = useForm({ body: '' });

    // Rola até a última mensagem ao abrir a conversa e quando chega mensagem nova.
    useEffect(() => {
        bottomRef.current?.scrollIntoView({ block: 'end' });
    }, [lastMessageId]);

    function send(event?: FormEvent) {
        event?.preventDefault();

        if (form.processing || form.data.body.trim() === '') {
            return;
        }

        form.post(`/atendimento/${conversation.id}/mensagens`, {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
        });
    }

    function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            send();
        }
    }

    return (
        <section
            className="flex min-h-0 flex-col"
            aria-label={`Conversa com ${conversation.name}`}
        >
            <header className="flex items-center gap-3 border-b px-4 py-3">
                <Link
                    href={
                        search
                            ? `/atendimento?busca=${encodeURIComponent(search)}`
                            : '/atendimento'
                    }
                    className="md:hidden"
                    aria-label="Voltar às conversas"
                >
                    <ArrowLeft className="size-5" />
                </Link>
                <div className="min-w-0">
                    <p className="truncate font-semibold">
                        {conversation.name}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {formatPhone(conversation.phone) ??
                            conversation.chat_id}
                    </p>
                </div>
            </header>

            <div className="min-h-0 flex-1 overflow-y-auto bg-muted/30 px-4 py-4">
                <ol className="mx-auto flex max-w-3xl flex-col gap-2">
                    {conversation.messages.map((message) => {
                        const outbound = message.direction === 'outbound';

                        return (
                            <li
                                key={message.id}
                                className={cn(
                                    'flex',
                                    outbound ? 'justify-end' : 'justify-start',
                                )}
                            >
                                <div
                                    className={cn(
                                        'max-w-[85%] rounded-lg px-3 py-2 text-sm shadow-xs sm:max-w-[70%]',
                                        outbound
                                            ? 'bg-emerald-100 text-emerald-950 dark:bg-emerald-900/50 dark:text-emerald-50'
                                            : 'bg-background',
                                    )}
                                >
                                    {message.has_media && (
                                        <span className="mb-1 flex items-center gap-1 text-xs text-muted-foreground">
                                            <Paperclip className="size-3" />{' '}
                                            Mídia
                                        </span>
                                    )}
                                    <p className="break-words whitespace-pre-wrap">
                                        {message.body}
                                    </p>
                                    <p className="mt-1 flex items-center justify-end gap-2 text-[11px] text-muted-foreground">
                                        {outbound && message.sender && (
                                            <span>{message.sender}</span>
                                        )}
                                        <span>
                                            {formatMessageTime(message.sent_at)}
                                        </span>
                                        {outbound && (
                                            <WhatsappMessageStatus
                                                status={message.status}
                                            />
                                        )}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ol>
                <div ref={bottomRef} />
            </div>

            <form onSubmit={send} className="border-t bg-background p-3">
                <div className="mx-auto flex max-w-3xl items-end gap-2">
                    <label htmlFor="reply" className="sr-only">
                        Mensagem
                    </label>
                    <textarea
                        id="reply"
                        value={form.data.body}
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                        onKeyDown={handleKeyDown}
                        rows={2}
                        maxLength={4000}
                        placeholder="Escreva uma resposta (Enter envia, Shift+Enter quebra linha)"
                        className="max-h-40 min-h-11 flex-1 resize-y rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                    />
                    <Button
                        type="submit"
                        disabled={
                            form.processing || form.data.body.trim() === ''
                        }
                        aria-label="Enviar"
                    >
                        <Send className="size-4" />
                        <span className="hidden sm:inline">Enviar</span>
                    </Button>
                </div>
                <InputError
                    message={form.errors.body}
                    className="mx-auto mt-2 max-w-3xl"
                />
            </form>
        </section>
    );
}

function ContactPanel({ conversation }: { conversation: ActiveConversation }) {
    const rows = [
        {
            icon: UserRound,
            label: 'Nome no WhatsApp',
            value: conversation.push_name,
        },
        {
            icon: Phone,
            label: 'Telefone',
            value: formatPhone(conversation.phone),
        },
        {
            icon: Globe,
            label: 'Página de origem',
            value: conversation.source_page,
        },
    ];

    return (
        <aside
            className="hidden min-h-0 overflow-y-auto border-l p-4 xl:block"
            aria-label="Informações do contato"
        >
            <h2 className="text-sm font-semibold">Contato</h2>
            <dl className="mt-4 grid gap-4 text-sm">
                {rows.map(({ icon: Icon, label, value }) => (
                    <div key={label}>
                        <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Icon className="size-3.5" /> {label}
                        </dt>
                        <dd className="mt-0.5 break-words">{value ?? '—'}</dd>
                    </div>
                ))}
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Primeiro contato
                    </dt>
                    <dd className="mt-0.5">
                        {conversation.first_contact_at
                            ? formatMessageTime(conversation.first_contact_at)
                            : '—'}
                    </dd>
                </div>
            </dl>
            {conversation.lead && (
                <div className="mt-6 rounded-md border p-3 text-sm">
                    <p className="text-xs text-muted-foreground">
                        Cadastro existente
                    </p>
                    <p className="mt-1 font-medium">
                        {conversation.lead.company_name}
                    </p>
                    {conversation.lead.contact_name && (
                        <p className="text-muted-foreground">
                            {conversation.lead.contact_name}
                        </p>
                    )}
                    <Link
                        href={`/leads/${conversation.lead.id}/edit`}
                        className="mt-2 inline-block text-xs font-medium underline"
                    >
                        Ver solicitação
                    </Link>
                </div>
            )}
        </aside>
    );
}

AtendimentoIndex.layout = {
    breadcrumbs: [{ title: 'Atendimento', href: '/atendimento' }],
};
