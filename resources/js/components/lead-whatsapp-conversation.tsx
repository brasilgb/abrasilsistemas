import { Form } from '@inertiajs/react';
import { ArrowDown, MessageCircle, Send } from 'lucide-react';
import {
    Fragment,
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import WhatsappMessageStatus, {
    WhatsappMessageDirection,
} from '@/components/whatsapp-message-status';
import type {
    WhatsappMessageDirectionValue,
    WhatsappMessageStatusValue,
} from '@/components/whatsapp-message-status';
import { cn } from '@/lib/utils';
import {
    messages as messagesRoute,
    read as readRoute,
    store as sendLeadWhatsapp,
} from '@/routes/leads/whatsapp';

const POLL_INTERVAL = 5000;
const MESSAGE_MAX_LENGTH = 4000;
// Distância (px) do fim da conversa em que ainda consideramos o usuário "no final".
const NEAR_BOTTOM_THRESHOLD = 80;

type ConversationMessage = {
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

type Props = {
    leadId: number;
    destination: string | null;
    /** Inbound que chegaram não lidas antes da abertura da página (a abertura já as marcou como lidas). */
    initialUnreadIds: number[];
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

export default function LeadWhatsappConversation({
    leadId,
    destination,
    initialUnreadIds,
}: Props) {
    const [messages, setMessages] = useState<ConversationMessage[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [message, setMessage] = useState('');
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

            if (unread.length === 0) {
                return;
            }

            setNewIds((current) => {
                const next = new Set(current);
                unread.forEach((item) => next.add(item.id));

                return next;
            });

            // Só marca como lido o que foi exibido com a aba visível.
            if (document.visibilityState === 'visible') {
                await markRead(Math.max(...unread.map((item) => item.id)));
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

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <MessageCircle className="size-5" />
                    Conversa WhatsApp
                </CardTitle>
                <CardDescription>
                    Mensagens enviadas e recebidas pela integração do CRM.
                </CardDescription>
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

                <Form
                    action={sendLeadWhatsapp({ lead: leadId })}
                    method="post"
                    options={{
                        preserveScroll: true,
                        preserveState: true,
                        only: ['lead'],
                    }}
                    onSuccess={() => {
                        setMessage('');
                        forceStickToBottom.current = true;
                        void refresh();
                    }}
                    disableWhileProcessing
                    className="space-y-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <textarea
                                id="whatsapp_conversation_message"
                                name="message"
                                aria-label="Mensagem"
                                required
                                disabled={!destination}
                                maxLength={MESSAGE_MAX_LENGTH}
                                value={message}
                                onChange={(event) =>
                                    setMessage(event.target.value)
                                }
                                placeholder={
                                    destination
                                        ? 'Escreva uma mensagem...'
                                        : 'Cadastre um WhatsApp válido para enviar mensagens'
                                }
                                className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            <InputError message={errors.message} />
                            <InputError message={errors.whatsapp} />
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-xs text-muted-foreground">
                                    {destination
                                        ? `${message.length}/${MESSAGE_MAX_LENGTH}`
                                        : 'Cadastre um WhatsApp válido para enviar mensagens'}
                                </p>
                                <Button
                                    type="submit"
                                    disabled={
                                        !destination ||
                                        processing ||
                                        message.trim() === ''
                                    }
                                >
                                    {processing ? (
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
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
