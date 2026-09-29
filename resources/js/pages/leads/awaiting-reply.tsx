import { Form, Head, Link, usePoll } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    Clock,
    Hourglass,
    MessageCircle,
    Pause,
    Search,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AppPagination, { PaginationSummary } from '@/components/app-pagination';
import type { PaginationData } from '@/components/app-pagination';
import Heading from '@/components/heading';
import {
    AssignToMeButton,
    TransferOwnerSelect,
    useOwnerLabel,
} from '@/components/lead-owner-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { WhatsappUnreadBadge } from '@/components/whatsapp-message-status';
import type { Lead } from '@/pages/leads/form';
import {
    LeadFilterFields,
    selectClass,
    statusClass,
} from '@/pages/leads/index';
import type { ProductFilterOption } from '@/pages/leads/index';
import { awaitingReply, edit, index } from '@/routes/leads';

/** Uma nova mensagem do cliente aparece na fila sem recarregar a tela (a conversa usa 5 s; a fila tolera mais). */
const POLL_INTERVAL = 15000;

type AwaitingReplyLead = Lead & {
    awaiting_reply_since: string;
    /** Espera em tempo de atendimento até slaClock.now (calculada no backend). */
    awaiting_reply_business_seconds: number;
    /** Próxima mudança de nível do SLA (calculada no backend, em tempo de atendimento); null no Crítico. */
    next_sla_level: SlaLevel | null;
    next_sla_at: string | null;
    last_inbound_at: string;
    last_inbound_message: string;
};

type SlaLevel = 'normal' | 'attention' | 'urgent' | 'critical';

/**
 * Relógio do SLA enviado pelo backend: instante do cálculo e os próximos períodos de atendimento
 * (ISO UTC). As regras de horário (fuso, dias, intervalo) ficam só no backend; aqui só se soma
 * o tempo que cai dentro dos períodos.
 */
type SlaClock = {
    now: string;
    periods: [string, string][];
    schedule: string;
};

type Props = {
    filters: {
        search?: string;
        product?: string;
        status?: string;
        owner?: string;
        sla?: string;
    };
    leads: PaginationData & { data: AwaitingReplyLead[] };
    slaClock: SlaClock;
    slaLevels: Record<SlaLevel, { label: string; from_minutes: number }>;
    productOptions: ProductFilterOption[];
    statuses: Record<string, string>;
    users: { id: number; name: string }[];
};

function formatDateTime(value: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

/**
 * Espera em tempo de atendimento: "há 8 min", "há 2 h 5 min", "há 26 h". Sem dias: 24 h úteis
 * não são um dia de calendário.
 */
function formatWaiting(elapsedMs: number) {
    const minutes = Math.max(0, Math.floor(elapsedMs / 60000));

    if (minutes < 1) {
        return 'há menos de 1 min';
    }

    if (minutes < 60) {
        return `há ${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest ? `há ${hours} h ${rest} min` : `há ${hours} h`;
}

type Periods = [number, number][];

function parsePeriods(clock: SlaClock): Periods {
    return clock.periods.map(([start, end]) => [
        new Date(start).getTime(),
        new Date(end).getTime(),
    ]);
}

/** Tempo de atendimento (ms) entre o cálculo do backend e `now`. */
function businessMsSince(from: number, now: number, periods: Periods) {
    return periods.reduce(
        (total, [start, end]) =>
            total + Math.max(0, Math.min(end, now) - Math.max(start, from)),
        0,
    );
}

/** Espera útil (ms) do lead em `now`: a do backend mais o expediente que passou desde então. */
function waitingMs(
    lead: AwaitingReplyLead,
    clockNow: number,
    now: number,
    periods: Periods,
) {
    return (
        lead.awaiting_reply_business_seconds * 1000 +
        businessMsSince(clockNow, now, periods)
    );
}

const dayKey = new Intl.DateTimeFormat('pt-BR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

/** "às 14:35", "amanhã às 08:10" ou "seg., 05/10 às 08:10": o dia aparece quando não é hoje. */
function formatNextChange(at: number, now: number) {
    const date = new Date(at);
    const time = new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
    const today = new Date(now);
    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (dayKey.format(date) === dayKey.format(today)) {
        return `às ${time}`;
    }

    if (dayKey.format(date) === dayKey.format(tomorrow)) {
        return `amanhã às ${time}`;
    }

    const day = new Intl.DateTimeFormat('pt-BR', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
    }).format(date);

    return `${day} às ${time}`;
}

function isOpenAt(now: number, periods: Periods) {
    return periods.some(([start, end]) => now >= start && now < end);
}

/** Texto + ícone + cor: o nível não depende só da cor. */
const SLA_STYLES: Record<SlaLevel, { icon: LucideIcon; className: string }> = {
    normal: {
        icon: Clock,
        className:
            'border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300',
    },
    attention: {
        icon: Hourglass,
        className:
            'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300',
    },
    urgent: {
        icon: AlertTriangle,
        className:
            'border-orange-400 bg-orange-50 text-orange-800 dark:border-orange-800 dark:bg-orange-950 dark:text-orange-300',
    },
    critical: {
        icon: AlertOctagon,
        className:
            'border-red-400 bg-red-50 font-semibold text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300',
    },
};

/** Mesma regra do backend: o nível começa quando a espera útil atinge o limite (>=). */
function slaLevel(elapsedMs: number, levels: Props['slaLevels']) {
    return (Object.keys(levels) as SlaLevel[]).reduce<SlaLevel>(
        (current, level) =>
            elapsedMs >= levels[level].from_minutes * 60000 &&
            levels[level].from_minutes >= levels[current].from_minutes
                ? level
                : current,
        'normal',
    );
}

function SlaBadge({
    elapsedMs,
    levels,
}: {
    elapsedMs: number;
    levels: Props['slaLevels'];
}) {
    const level = slaLevel(elapsedMs, levels);
    const { icon: Icon, className } = SLA_STYLES[level];

    return (
        <Badge variant="outline" className={className}>
            <Icon className="size-3" aria-hidden />
            {levels[level].label}
        </Badge>
    );
}

export default function LeadsAwaitingReply({
    filters,
    leads,
    productOptions,
    statuses,
    users,
    slaClock,
    slaLevels,
}: Props) {
    const [now, setNow] = useState(() => Date.now());
    const ownerLabel = useOwnerLabel();
    const clockNow = useMemo(
        () => new Date(slaClock.now).getTime(),
        [slaClock.now],
    );
    const periods = useMemo(() => parsePeriods(slaClock), [slaClock]);
    const open = isOpenAt(now, periods);

    // slaClock vem junto: a espera de cada lead é calculada no mesmo instante do backend.
    usePoll(POLL_INTERVAL, {
        only: ['leads', 'slaClock'],
        onFinish: () => setNow(Date.now()),
    });

    // O tempo de espera avança mesmo quando a fila não muda (e fica parado fora do expediente).
    useEffect(() => {
        const tick = window.setInterval(() => setNow(Date.now()), 30000);

        return () => window.clearInterval(tick);
    }, []);

    // O badge de SLA muda exatamente no limite (14:59 → 15:00 de expediente), sem esperar o
    // próximo tick: o instante vem pronto do backend (next_sla_at, já em tempo de atendimento).
    // Depois dele, o polling traz a transição seguinte.
    useEffect(() => {
        const next = Math.min(
            ...leads.data
                .map((lead) =>
                    lead.next_sla_at ? new Date(lead.next_sla_at).getTime() : 0,
                )
                .filter((at) => at > now),
        );

        if (!Number.isFinite(next)) {
            return;
        }

        const timeout = window.setTimeout(
            () => setNow(Date.now()),
            next - Date.now(),
        );

        return () => window.clearTimeout(timeout);
    }, [leads.data, now]);

    return (
        <>
            <Head title="Aguardando resposta" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Aguardando resposta"
                    description="Leads cuja última mensagem no WhatsApp veio do cliente. Quem espera há mais tempo aparece primeiro."
                />

                <Form
                    {...awaitingReply.form()}
                    className="grid gap-2 sm:grid-cols-2 lg:max-w-6xl lg:grid-cols-[1.4fr_1fr_1fr_1fr_1fr_auto]"
                >
                    <LeadFilterFields
                        idPrefix="awaiting-"
                        fields={['search', 'product', 'status', 'owner']}
                        filters={filters}
                        productOptions={productOptions}
                        statuses={statuses}
                        users={users}
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="awaiting-sla" className="text-xs">
                            SLA
                        </Label>
                        <select
                            id="awaiting-sla"
                            name="sla"
                            defaultValue={filters.sla ?? ''}
                            className={selectClass}
                        >
                            <option value="">Todos</option>
                            {Object.entries(slaLevels).map(
                                ([value, { label, from_minutes }]) => (
                                    <option key={value} value={value}>
                                        {label}
                                        {from_minutes > 0 &&
                                            ` (${from_minutes} min ou mais)`}
                                    </option>
                                ),
                            )}
                        </select>
                    </div>
                    <div className="flex items-end">
                        <Button className="w-full" variant="outline">
                            <Search className="size-4" />
                            Filtrar
                        </Button>
                    </div>
                </Form>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between text-base">
                            <span className="flex items-center gap-2">
                                <MessageCircle className="size-4 text-emerald-600" />
                                Conversas sem resposta
                            </span>
                            <Badge variant="secondary">{leads.total}</Badge>
                        </CardTitle>
                        <CardDescription>
                            Ler a mensagem não tira o lead da fila; só uma
                            mensagem enviada pela empresa depois dela. O tempo
                            de espera e o SLA contam só no horário de
                            atendimento: {slaClock.schedule}.
                        </CardDescription>
                        {!open && (
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                <Pause className="size-3" aria-hidden />
                                Fora do horário de atendimento: o SLA está
                                pausado.
                            </p>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {leads.data.length ? (
                            leads.data.map((lead) => (
                                <div
                                    key={lead.id}
                                    className="rounded-md border transition-colors hover:bg-muted/50"
                                >
                                    <Link
                                        href={edit({ lead: lead.id })}
                                        className="flex flex-col gap-2 p-3 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                <span className="truncate text-sm font-medium">
                                                    {lead.company_name}
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className={statusClass(
                                                        lead.status,
                                                    )}
                                                >
                                                    {statuses[lead.status] ??
                                                        lead.status}
                                                </Badge>
                                                <WhatsappUnreadBadge
                                                    count={
                                                        lead.unread_whatsapp_messages_count
                                                    }
                                                />
                                            </div>
                                            <p className="line-clamp-2 text-sm break-words text-muted-foreground">
                                                “{lead.last_inbound_message}”
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {[
                                                    lead.contact_name,
                                                    lead.whatsapp,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') || '-'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-start gap-1 text-xs sm:items-end sm:text-right">
                                            <SlaBadge
                                                elapsedMs={waitingMs(
                                                    lead,
                                                    clockNow,
                                                    now,
                                                    periods,
                                                )}
                                                levels={slaLevels}
                                            />
                                            {lead.next_sla_level &&
                                                lead.next_sla_at &&
                                                new Date(
                                                    lead.next_sla_at,
                                                ).getTime() > now && (
                                                    <p
                                                        className="text-muted-foreground"
                                                        title="Próxima mudança de nível do SLA, contando só o horário de atendimento"
                                                    >
                                                        {
                                                            slaLevels[
                                                                lead
                                                                    .next_sla_level
                                                            ].label
                                                        }{' '}
                                                        {formatNextChange(
                                                            new Date(
                                                                lead.next_sla_at,
                                                            ).getTime(),
                                                            now,
                                                        )}
                                                    </p>
                                                )}
                                            <p className="font-medium">
                                                Aguardando{' '}
                                                {formatWaiting(
                                                    waitingMs(
                                                        lead,
                                                        clockNow,
                                                        now,
                                                        periods,
                                                    ),
                                                )}
                                            </p>
                                            <p className="text-muted-foreground">
                                                Última mensagem:{' '}
                                                {formatDateTime(
                                                    lead.last_inbound_at,
                                                )}
                                            </p>
                                        </div>
                                    </Link>
                                    {/* Fora do link: assumir ou transferir não abre o lead. */}
                                    <div className="flex flex-wrap items-center gap-2 border-t px-3 py-2 text-xs">
                                        <span className="text-muted-foreground">
                                            Responsável:{' '}
                                            <span className="font-medium text-foreground">
                                                {ownerLabel(lead.user)}
                                            </span>
                                        </span>
                                        <div className="ml-auto flex flex-wrap items-center gap-2">
                                            {!lead.user && (
                                                <AssignToMeButton
                                                    leadId={lead.id}
                                                />
                                            )}
                                            <TransferOwnerSelect
                                                leadId={lead.id}
                                                owner={lead.user}
                                                users={users}
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Nenhuma conversa aguardando resposta.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <PaginationSummary data={leads} />
                    <AppPagination data={leads} />
                </div>
            </div>
        </>
    );
}

LeadsAwaitingReply.layout = {
    breadcrumbs: [
        {
            title: 'Prospecção',
            href: index(),
        },
        {
            title: 'Aguardando resposta',
            href: awaitingReply(),
        },
    ],
};
