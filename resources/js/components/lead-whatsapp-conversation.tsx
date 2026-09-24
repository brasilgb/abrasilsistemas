import { Form } from '@inertiajs/react';
import { MessageCircle, Send } from 'lucide-react';
import {
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
import WhatsappMessageStatus from '@/components/whatsapp-message-status';
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

const POLL_INTERVAL = 10000;
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
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
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

export default function LeadWhatsappConversation({
    leadId,
    destination,
    initialUnreadIds,
}: Props) {
    const [messages, setMessages] = useState<ConversationMessage[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [message, setMessage] = useState('');
    // Ids que chegaram não lidos nesta visita: o selo "Nova" continua mesmo depois do read_at.
    const [newIds, setNewIds] = useState<Set<number>>(
        () => new Set(initialUnreadIds),
    );
    const scrollRef = useRef<HTMLDivElement>(null);
    const stickToBottom = useRef(true);
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

    const load = useCallback(async () => {
        const response = await fetch(messagesRoute.url({ lead: leadId }), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error();
        }

        const result = (await response.json()) as {
            messages: ConversationMessage[];
        };
        const unread = result.messages.filter(
            (item) => item.direction === 'inbound' && !item.read_at,
        );

        if (scrollRef.current) {
            stickToBottom.current = isNearBottom(scrollRef.current);
        }

        // A janela do servidor atualiza as mensagens já exibidas (inclusive o ACK) por id.
        setMessages((current) => {
            const byId = new Map(current.map((item) => [item.id, item]));
            result.messages.forEach((item) => byId.set(item.id, item));

            return [...byId.values()].sort(compareMessages);
        });

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
            const upToId = Math.max(
                ...result.messages
                    .filter((item) => item.direction === 'inbound')
                    .map((item) => item.id),
            );
            await markRead(upToId);
        }
    }, [leadId, markRead]);

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

    // Rola para o fim só se o usuário já estava no fim (ou na primeira carga).
    useLayoutEffect(() => {
        const element = scrollRef.current;

        if (element && stickToBottom.current) {
            element.scrollTop = element.scrollHeight;
        }
    }, [messages]);

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
                <div
                    ref={scrollRef}
                    className="max-h-96 min-h-32 space-y-3 overflow-y-auto rounded-md border bg-muted/30 p-3"
                >
                    {loading && messages.length === 0 ? (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner /> Carregando conversa...
                        </p>
                    ) : messages.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nenhuma mensagem trocada pelo WhatsApp ainda.
                        </p>
                    ) : (
                        messages.map((item) => {
                            const inbound = item.direction === 'inbound';

                            return (
                                <div
                                    key={item.id}
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
                                        <p className="leading-6 break-words whitespace-pre-line">
                                            {item.description}
                                        </p>
                                        <div className="mt-1 flex flex-wrap items-center justify-end gap-2 text-[11px] text-muted-foreground">
                                            {inbound && newIds.has(item.id) && (
                                                <Badge className="bg-emerald-600 text-white dark:bg-emerald-500">
                                                    Nova
                                                </Badge>
                                            )}
                                            <span>
                                                {formatTime(item.created_at)}
                                            </span>
                                            {/* Status de entrega (ACK) só existe para mensagem enviada. */}
                                            {!inbound && (
                                                <WhatsappMessageStatus
                                                    status={item.message_status}
                                                />
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })
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
                        stickToBottom.current = true;
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
