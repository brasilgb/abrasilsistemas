import { router } from '@inertiajs/react';
import {
    ArrowDown,
    Building2,
    ChevronDown,
    MessageCircle,
    MessageSquareText,
    Phone,
    Send,
    UserRound,
} from 'lucide-react';
import type { KeyboardEvent } from 'react';
import {
    Fragment,
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import InputError from '@/components/input-error';
import { LeadFollowUpControl } from '@/components/lead-follow-up';
import type { LeadFollowUpState } from '@/components/lead-follow-up';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import WhatsappMessageStatus, {
    WhatsappMessageDirection,
} from '@/components/whatsapp-message-status';
import type {
    WhatsappMessageDirectionValue,
    WhatsappMessageStatusValue,
} from '@/components/whatsapp-message-status';
import {
    fillWhatsappVariables,
    readWhatsappMessages,
    unresolvedWhatsappVariables,
    whatsappQuickMessages,
} from '@/lib/lead-whatsapp-messages';
import type {
    WhatsappMessageLead,
    WhatsappQuickMessage,
} from '@/lib/lead-whatsapp-messages';
import { cn } from '@/lib/utils';
import { status as leadStatusRoute } from '@/routes/leads';
import {
    messages as messagesRoute,
    read as readRoute,
    store as sendLeadWhatsapp,
} from '@/routes/leads/whatsapp';

const POLL_INTERVAL = 5000;
const MESSAGE_MAX_LENGTH = 4000;
// Distância (px) do fim da conversa em que ainda consideramos o usuário "no final".
const NEAR_BOTTOM_THRESHOLD = 80;

export type ConversationMessage = {
    id: number;
    direction: WhatsappMessageDirectionValue;
    description: string;
    created_at: string;
    message_status: WhatsappMessageStatusValue | null;
    read_at: string | null;
};

type PageResponse = {
    messages: ConversationMessage[];
    has_more: boolean;
    cursor: number;
};

type ChangesResponse = {
    messages: ConversationMessage[];
    reset: boolean;
    cursor: number;
};

/** Dados comerciais do lead já carregados na tela de edição. */
export type ConversationLead = WhatsappMessageLead & {
    id: number;
    whatsapp: string | null;
    status: string;
    lost_reason: string | null;
    next_follow_up_at: string | null;
    follow_up_state: LeadFollowUpState | null;
    user?: { id: number; name: string } | null;
    prospect_product?: { name: string } | null;
};

type Props = {
    lead: ConversationLead;
    /** Texto WhatsApp do produto vinculado ao lead (CRM-WA-22); null quando não há. */
    productWhatsappText?: string | null;
    statuses: Record<string, string>;
    lostReasons: Record<string, string>;
    destination: string | null;
    /** Inbound que chegaram não lidas antes da abertura da página (a abertura já as marcou como lidas). */
    initialUnreadIds: number[];
    /**
     * Mensagens trazidas pela abertura e por cada ciclo de polling (novas ou alteradas),
     * para a página sincronizar o Histórico sem um segundo polling.
     */
    onMessages?: (items: ConversationMessage[]) => void;
};

function csrfHeaders(): Record<string, string> {
    const csrfToken = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {};
}

function formatTime(value: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function dayKey(value: string) {
    return new Date(value).toDateString();
}

function formatDay(value: string) {
    const date = new Date(value);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
        return 'Hoje';
    }

    if (date.toDateString() === yesterday.toDateString()) {
        return 'Ontem';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year:
            date.getFullYear() === today.getFullYear() ? undefined : 'numeric',
    }).format(date);
}

function compareMessages(a: ConversationMessage, b: ConversationMessage) {
    const byDate =
        new Date(a.created_at).getTime() - new Date(b.created_at).getTime();

    return byDate !== 0 ? byDate : a.id - b.id;
}

function isNearBottom(element: HTMLElement) {
    return (
        element.scrollHeight - element.scrollTop - element.clientHeight <=
        NEAR_BOTTOM_THRESHOLD
    );
}

async function fetchJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error();
    }

    return (await response.json()) as T;
}

function formatWhatsapp(value: string) {
    const digits = value.replace(/\D+/g, '');
    const local =
        digits.length > 11 && digits.startsWith('55')
            ? digits.slice(2)
            : digits;

    if (local.length === 11) {
        return `(${local.slice(0, 2)}) ${local.slice(2, 7)}-${local.slice(7)}`;
    }

    if (local.length === 10) {
        return `(${local.slice(0, 2)}) ${local.slice(2, 6)}-${local.slice(6)}`;
    }

    return value;
}

const statusSelectClass =
    'h-8 rounded-md border border-input bg-background px-2 text-xs shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50';

/**
 * Estágio do funil a partir da conversa. Reutiliza o PATCH leads/{lead}/status do Kanban
 * (mesma validação, mesmo registro no histórico), só com a origem "whatsapp".
 */
function ConversationLeadStatus({
    lead,
    statuses,
    lostReasons,
}: {
    lead: ConversationLead;
    statuses: Record<string, string>;
    lostReasons: Record<string, string>;
}) {
    const [status, setStatus] = useState(lead.status);
    const [lostReason, setLostReason] = useState(lead.lost_reason ?? '');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const needsLostReason = status === 'lost';
    const changed =
        status !== lead.status ||
        (needsLostReason && lostReason !== (lead.lost_reason ?? ''));

    const save = (nextStatus: string, nextLostReason: string) => {
        router.patch(
            leadStatusRoute.url({ lead: lead.id }),
            {
                status: nextStatus,
                lost_reason: nextStatus === 'lost' ? nextLostReason : null,
                origin: 'whatsapp',
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['lead'],
                onStart: () => setSaving(true),
                onSuccess: () => setErrors({}),
                onError: (validation) => setErrors(validation),
                onFinish: () => setSaving(false),
            },
        );
    };

    const onStatusChange = (nextStatus: string) => {
        setStatus(nextStatus);
        setErrors({});

        // "Perdido" pede o motivo antes de salvar; os demais estágios salvam na hora.
        if (nextStatus !== 'lost' && nextStatus !== lead.status) {
            save(nextStatus, '');
        }
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <label className="sr-only" htmlFor="whatsapp_conversation_status">
                Estágio do lead
            </label>
            <select
                id="whatsapp_conversation_status"
                value={status}
                disabled={saving}
                onChange={(event) => onStatusChange(event.target.value)}
                className={statusSelectClass}
            >
                {Object.entries(statuses).map(([value, label]) => (
                    <option key={value} value={value}>
                        {label}
                    </option>
                ))}
            </select>
            {needsLostReason && (
                <>
                    <label
                        className="sr-only"
                        htmlFor="whatsapp_conversation_lost_reason"
                    >
                        Motivo de perda
                    </label>
                    <select
                        id="whatsapp_conversation_lost_reason"
                        value={lostReason}
                        disabled={saving}
                        onChange={(event) => setLostReason(event.target.value)}
                        className={statusSelectClass}
                    >
                        <option value="">Motivo de perda</option>
                        {Object.entries(lostReasons).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    {changed && (
                        <Button
                            type="button"
                            size="sm"
                            className="h-8 text-xs"
                            disabled={saving || lostReason === ''}
                            onClick={() => save(status, lostReason)}
                        >
                            Salvar
                        </Button>
                    )}
                </>
            )}
            {saving && <Spinner className="size-3.5" />}
            <InputError message={errors.status ?? errors.lost_reason} />
        </div>
    );
}

export default function LeadWhatsappConversation({
    lead,
    productWhatsappText = null,
    statuses,
    lostReasons,
    destination,
    initialUnreadIds,
    onMessages,
}: Props) {
    const leadId = lead.id;
    // Ref: trocar o callback não reinicia o polling.
    const onMessagesRef = useRef(onMessages);
    // Falso depois do unmount: um fetch que termina após sair do lead não notifica a página.
    const active = useRef(false);
    const [messages, setMessages] = useState<ConversationMessage[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [message, setMessage] = useState('');
    const [sending, setSending] = useState(false);
    const [sendErrors, setSendErrors] = useState<Record<string, string>>({});
    // Textos de apresentação por produto (com a edição salva em Configurações > Leads).
    const [presentations] = useState(readWhatsappMessages);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    // Trava síncrona contra duplo envio (Enter repetido antes do re-render).
    const sendingRef = useRef(false);
    const [hasOlder, setHasOlder] = useState(false);
    const [loadingOlder, setLoadingOlder] = useState(false);
    // Mensagens que chegaram enquanto o usuário lia o histórico (sem rolar sozinho).
    const [unseenCount, setUnseenCount] = useState(0);
    // Ids que chegaram não lidos nesta visita: o selo "Nova" continua mesmo depois do read_at.
    const [newIds, setNewIds] = useState<Set<number>>(
        () => new Set(initialUnreadIds),
    );
    const scrollRef = useRef<HTMLDivElement>(null);
    const stickToBottom = useRef(true);
    const forceStickToBottom = useRef(false);
    // Posição a restaurar depois de inserir mensagens antigas no topo.
    const restoreScroll = useRef<{ height: number; top: number } | null>(null);
    const knownIds = useRef<Set<number>>(new Set());
    // Cursor do polling incremental; null até a primeira carga.
    const cursor = useRef<number | null>(null);
    const inFlight = useRef(false);
    const refreshQueued = useRef(false);

    useLayoutEffect(() => {
        onMessagesRef.current = onMessages;
    });

    const markRead = useCallback(
        (upToId: number) =>
            fetch(readRoute.url({ lead: leadId }), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ up_to_id: upToId }),
            }).catch(() => {
                // Sem rede: as mensagens continuam não lidas e o próximo ciclo tenta de novo.
            }),
        [leadId],
    );

    /** Merge por id: mensagens novas entram, as já exibidas são atualizadas (inclusive o ACK). */
    const merge = useCallback((items: ConversationMessage[]) => {
        items.forEach((item) => knownIds.current.add(item.id));

        setMessages((current) => {
            const byId = new Map(current.map((item) => [item.id, item]));
            items.forEach((item) => byId.set(item.id, item));

            return [...byId.values()].sort(compareMessages);
        });
    }, []);

    /** Aplica o que veio do servidor para o fim da conversa (abertura ou polling). */
    const applyRecent = useCallback(
        async (items: ConversationMessage[]) => {
            const element = scrollRef.current;
            const arrived = items.filter(
                (item) => !knownIds.current.has(item.id),
            ).length;

            if (forceStickToBottom.current) {
                forceStickToBottom.current = false;
                stickToBottom.current = true;
                setUnseenCount(0);
            } else if (element) {
                stickToBottom.current = isNearBottom(element);

                if (!stickToBottom.current && arrived > 0) {
                    setUnseenCount((count) => count + arrived);
                }
            }

            merge(items);

            const unread = items.filter(
                (item) => item.direction === 'inbound' && !item.read_at,
            );

            if (unread.length > 0) {
                setNewIds((current) => {
                    const next = new Set(current);
                    unread.forEach((item) => next.add(item.id));

                    return next;
                });

                // Só marca como lido o que foi exibido com a aba visível.
                if (document.visibilityState === 'visible') {
                    await markRead(Math.max(...unread.map((item) => item.id)));
                }
            }

            if (active.current && items.length > 0) {
                onMessagesRef.current?.(items);
            }
        },
        [markRead, merge],
    );

    const load = useCallback(async () => {
        if (cursor.current !== null) {
            const result = await fetchJson<ChangesResponse>(
                messagesRoute.url(
                    { lead: leadId },
                    { query: { since: cursor.current } },
                ),
            );

            if (!result.reset) {
                cursor.current = result.cursor;
                await applyRecent(result.messages);

                return;
            }
        }

        // Abertura, ou mudança em massa que o polling não cobre: recarrega a página recente.
        const firstLoad = cursor.current === null;
        const result = await fetchJson<PageResponse>(
            messagesRoute.url({ lead: leadId }),
        );

        cursor.current = result.cursor;

        if (firstLoad) {
            setHasOlder(result.has_more);
        }

        await applyRecent(result.messages);
    }, [applyRecent, leadId]);

    const refresh = useCallback(async () => {
        // Um fetch por vez; um pedido durante o fetch vira um novo ciclo logo em seguida.
        if (inFlight.current) {
            refreshQueued.current = true;

            return;
        }

        inFlight.current = true;

        try {
            do {
                refreshQueued.current = false;

                try {
                    await load();
                    setError(null);
                } catch {
                    setError('Não foi possível atualizar a conversa.');
                }
            } while (refreshQueued.current);
        } finally {
            inFlight.current = false;
            setLoading(false);
        }
    }, [load]);

    const loadOlder = useCallback(async () => {
        const element = scrollRef.current;
        const oldest = messages[0];

        if (!oldest || loadingOlder) {
            return;
        }

        setLoadingOlder(true);

        try {
            const result = await fetchJson<PageResponse>(
                messagesRoute.url(
                    { lead: leadId },
                    { query: { before_id: oldest.id } },
                ),
            );

            if (element) {
                restoreScroll.current = {
                    height: element.scrollHeight,
                    top: element.scrollTop,
                };
            }

            stickToBottom.current = false;
            merge(result.messages);
            setHasOlder(result.has_more);
            setError(null);
        } catch {
            setError('Não foi possível carregar as mensagens anteriores.');
        } finally {
            setLoadingOlder(false);
        }
    }, [leadId, loadingOlder, merge, messages]);

    useEffect(() => {
        active.current = true;
        void refresh();

        const poll = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                void refresh();
            }
        }, POLL_INTERVAL);

        const onVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                void refresh();
            }
        };

        document.addEventListener('visibilitychange', onVisibilityChange);

        return () => {
            active.current = false;
            window.clearInterval(poll);
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
        };
    }, [refresh]);

    // Histórico carregado: mantém a mensagem que estava na tela. Senão, rola para o fim
    // só se o usuário já estava no fim (ou na primeira carga).
    useLayoutEffect(() => {
        const element = scrollRef.current;

        if (!element) {
            return;
        }

        if (restoreScroll.current) {
            element.scrollTop =
                element.scrollHeight -
                restoreScroll.current.height +
                restoreScroll.current.top;
            restoreScroll.current = null;

            return;
        }

        if (stickToBottom.current) {
            element.scrollTop = element.scrollHeight;
        }
    }, [messages]);

    const scrollToBottom = () => {
        const element = scrollRef.current;

        if (element) {
            element.scrollTo({ top: element.scrollHeight, behavior: 'smooth' });
        }

        setUnseenCount(0);
    };

    const onScroll = () => {
        if (
            unseenCount > 0 &&
            scrollRef.current &&
            isNearBottom(scrollRef.current)
        ) {
            setUnseenCount(0);
        }
    };

    const productName = lead.prospect_product?.name ?? '';
    const quickMessages = useMemo(
        () =>
            whatsappQuickMessages(presentations, {
                name: productName,
                text: productWhatsappText,
            }),
        [presentations, productName, productWhatsappText],
    );
    const unresolved = useMemo(
        () => unresolvedWhatsappVariables(message),
        [message],
    );
    const canSend =
        Boolean(destination) &&
        !sending &&
        message.trim() !== '' &&
        unresolved.length === 0;

    /** Coloca o texto pronto no campo para revisão. Nunca envia sozinho. */
    const applyQuickMessage = (quick: WhatsappQuickMessage) => {
        if (
            message.trim() !== '' &&
            !window.confirm('Substituir o texto que está sendo escrito?')
        ) {
            return;
        }

        setMessage(
            (quick.literal
                ? quick.text
                : fillWhatsappVariables(quick.text, lead)
            ).slice(0, MESSAGE_MAX_LENGTH),
        );
        setSendErrors({});
        window.requestAnimationFrame(() => textareaRef.current?.focus());
    };

    const send = () => {
        if (!canSend || sendingRef.current) {
            return;
        }

        sendingRef.current = true;
        setSending(true);
        setSendErrors({});

        router.post(
            sendLeadWhatsapp.url({ lead: leadId }),
            { message },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['lead'],
                onSuccess: () => {
                    // Só limpa depois do envio confirmado; em erro o texto continua no campo.
                    setMessage('');
                    forceStickToBottom.current = true;
                    void refresh();
                },
                onError: (validation) => setSendErrors(validation),
                onHttpException: (response) => {
                    setSendErrors({
                        message:
                            response.status === 429
                                ? 'Muitos envios seguidos. Aguarde um instante e tente de novo.'
                                : 'Não foi possível enviar a mensagem. O texto foi mantido; tente de novo.',
                    });

                    return false;
                },
                onNetworkError: () => {
                    setSendErrors({
                        message:
                            'Sem conexão com o servidor. O texto foi mantido; tente de novo.',
                    });

                    return false;
                },
                onFinish: () => {
                    sendingRef.current = false;
                    setSending(false);
                },
            },
        );
    };

    const onComposerKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        // Enter envia; Shift+Enter quebra a linha. Ignora o Enter que confirma um IME.
        if (
            event.key !== 'Enter' ||
            event.shiftKey ||
            event.nativeEvent.isComposing
        ) {
            return;
        }

        event.preventDefault();
        send();
    };

    const contactName = lead.contact_name?.trim();

    return (
        <Card>
            <CardHeader className="gap-3">
                <CardTitle className="flex items-center gap-2">
                    <MessageCircle className="size-5" />
                    Conversa WhatsApp
                </CardTitle>
                <CardDescription className="sr-only">
                    Mensagens enviadas e recebidas pela integração do CRM.
                </CardDescription>
                <div className="space-y-2 rounded-md border bg-muted/30 p-3">
                    <div className="min-w-0">
                        <p className="flex items-center gap-1.5 truncate text-sm font-medium">
                            <UserRound
                                className="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            {contactName || 'Contato sem nome'}
                        </p>
                        <p className="flex items-center gap-1.5 truncate text-xs text-muted-foreground">
                            <Building2
                                className="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            {lead.company_name}
                            {lead.city ? ` · ${lead.city}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1">
                            <Phone className="size-3.5" aria-hidden="true" />
                            {lead.whatsapp
                                ? formatWhatsapp(destination ?? lead.whatsapp)
                                : 'Sem WhatsApp'}
                            {lead.whatsapp && !destination && (
                                <span className="text-destructive">
                                    (inválido)
                                </span>
                            )}
                        </span>
                        <span>
                            Responsável: {lead.user?.name ?? 'Sem responsável'}
                        </span>
                    </div>
                    {/* key: o lead recarregado (aqui ou pelo formulário da página) reinicia o seletor. */}
                    <ConversationLeadStatus
                        key={`${lead.status}:${lead.lost_reason ?? ''}`}
                        lead={lead}
                        statuses={statuses}
                        lostReasons={lostReasons}
                    />
                    {/* key: salvar (aqui, no formulário ou pelo estágio) recarrega o lead e reinicia o controle. */}
                    <LeadFollowUpControl
                        key={`${lead.next_follow_up_at ?? ''}:${lead.follow_up_state ?? 'closed'}`}
                        lead={lead}
                    />
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="relative">
                    <div
                        ref={scrollRef}
                        onScroll={onScroll}
                        aria-live="polite"
                        className="max-h-96 min-h-32 space-y-3 overflow-y-auto rounded-md border bg-muted/30 p-3"
                    >
                        {loading && messages.length === 0 ? (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner /> Carregando conversa...
                            </p>
                        ) : messages.length === 0 ? (
                            <div className="flex min-h-24 flex-col items-center justify-center gap-1 text-center text-sm text-muted-foreground">
                                <MessageCircle
                                    className="size-6 opacity-50"
                                    aria-hidden="true"
                                />
                                <p>
                                    Nenhuma mensagem trocada pelo WhatsApp
                                    ainda.
                                </p>
                                {destination && (
                                    <p className="text-xs">
                                        Envie a primeira mensagem abaixo.
                                    </p>
                                )}
                            </div>
                        ) : (
                            <>
                                {hasOlder && (
                                    <div className="flex justify-center">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 text-xs"
                                            disabled={loadingOlder}
                                            onClick={() => void loadOlder()}
                                        >
                                            {loadingOlder && <Spinner />}
                                            Carregar mensagens anteriores
                                        </Button>
                                    </div>
                                )}
                                {messages.map((item, index) => {
                                    const inbound =
                                        item.direction === 'inbound';
                                    const startsDay =
                                        index === 0 ||
                                        dayKey(
                                            messages[index - 1].created_at,
                                        ) !== dayKey(item.created_at);

                                    return (
                                        <Fragment key={item.id}>
                                            {startsDay && (
                                                <div className="flex justify-center">
                                                    <span className="rounded-full bg-background px-2.5 py-0.5 text-[11px] text-muted-foreground shadow-xs first-letter:uppercase">
                                                        {formatDay(
                                                            item.created_at,
                                                        )}
                                                    </span>
                                                </div>
                                            )}
                                            <div
                                                className={cn(
                                                    'flex',
                                                    inbound
                                                        ? 'justify-start'
                                                        : 'justify-end',
                                                )}
                                            >
                                                <div
                                                    className={cn(
                                                        'max-w-[85%] rounded-lg px-3 py-2 text-sm shadow-xs',
                                                        inbound
                                                            ? 'border bg-background'
                                                            : 'bg-emerald-100 dark:bg-emerald-950',
                                                    )}
                                                >
                                                    <WhatsappMessageDirection
                                                        direction={
                                                            item.direction
                                                        }
                                                        className="mb-0.5 text-[11px]"
                                                    />
                                                    <p className="leading-6 break-words whitespace-pre-line">
                                                        {item.description}
                                                    </p>
                                                    <div className="mt-1 flex flex-wrap items-center justify-end gap-2 text-[11px] text-muted-foreground">
                                                        {inbound &&
                                                            newIds.has(
                                                                item.id,
                                                            ) && (
                                                                <Badge className="bg-emerald-600 text-white dark:bg-emerald-500">
                                                                    Nova
                                                                </Badge>
                                                            )}
                                                        <time
                                                            dateTime={
                                                                item.created_at
                                                            }
                                                        >
                                                            {formatTime(
                                                                item.created_at,
                                                            )}
                                                        </time>
                                                        {/* Status de entrega (ACK) só existe para mensagem enviada. */}
                                                        {!inbound && (
                                                            <WhatsappMessageStatus
                                                                status={
                                                                    item.message_status
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </Fragment>
                                    );
                                })}
                            </>
                        )}
                    </div>

                    {unseenCount > 0 && (
                        <Button
                            type="button"
                            size="sm"
                            className="absolute bottom-3 left-1/2 h-7 -translate-x-1/2 rounded-full text-xs shadow-md"
                            onClick={scrollToBottom}
                        >
                            <ArrowDown className="size-3.5" />
                            {unseenCount === 1
                                ? '1 nova mensagem'
                                : `${unseenCount} novas mensagens`}
                        </Button>
                    )}
                </div>

                {error && <p className="text-xs text-destructive">{error}</p>}

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        send();
                    }}
                    className="space-y-2"
                    aria-busy={sending}
                >
                    <textarea
                        ref={textareaRef}
                        id="whatsapp_conversation_message"
                        name="message"
                        aria-label="Mensagem"
                        aria-describedby="whatsapp_conversation_hint"
                        required
                        disabled={!destination}
                        readOnly={sending}
                        maxLength={MESSAGE_MAX_LENGTH}
                        value={message}
                        onChange={(event) => {
                            setMessage(event.target.value);

                            if (Object.keys(sendErrors).length > 0) {
                                setSendErrors({});
                            }
                        }}
                        onKeyDown={onComposerKeyDown}
                        placeholder={
                            destination
                                ? 'Escreva uma mensagem...'
                                : 'Cadastre um WhatsApp válido para enviar mensagens'
                        }
                        className={cn(
                            'min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                            sending && 'opacity-70',
                            (sendErrors.message || sendErrors.whatsapp) &&
                                'border-destructive',
                        )}
                    />
                    {unresolved.length > 0 && (
                        <p className="text-xs text-amber-700 dark:text-amber-400">
                            Complete antes de enviar: {unresolved.join(', ')}{' '}
                            (sem valor no lead).
                        </p>
                    )}
                    <InputError message={sendErrors.message} />
                    <InputError message={sendErrors.whatsapp} />
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={!destination || sending}
                                    >
                                        <MessageSquareText className="size-4" />
                                        Mensagens rápidas
                                        <ChevronDown className="size-3.5 opacity-60" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    className="w-64"
                                >
                                    {quickMessages.map((quick, index) => (
                                        <Fragment key={quick.key}>
                                            {(index === 0 ||
                                                quickMessages[index - 1]
                                                    .group !== quick.group) && (
                                                <>
                                                    {index > 0 && (
                                                        <DropdownMenuSeparator />
                                                    )}
                                                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                                                        {quick.group}
                                                    </DropdownMenuLabel>
                                                </>
                                            )}
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    applyQuickMessage(quick)
                                                }
                                            >
                                                {quick.label}
                                            </DropdownMenuItem>
                                        </Fragment>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <p
                                id="whatsapp_conversation_hint"
                                className="hidden text-xs text-muted-foreground sm:block"
                            >
                                {destination
                                    ? `Enter envia · Shift+Enter quebra linha · ${message.length}/${MESSAGE_MAX_LENGTH}`
                                    : 'Cadastre um WhatsApp válido para enviar mensagens'}
                            </p>
                        </div>
                        <Button type="submit" disabled={!canSend}>
                            {sending ? (
                                <>
                                    <Spinner />
                                    Enviando...
                                </>
                            ) : (
                                <>
                                    <Send className="size-4" />
                                    Enviar
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
