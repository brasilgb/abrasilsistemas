import { router } from '@inertiajs/react';
import { CalendarClock, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { followUp as leadFollowUpRoute } from '@/routes/leads';

/** Situação calculada no servidor (Lead::FOLLOW_UP_STATES); nula para Convertido/Perdido. */
export type LeadFollowUpState = 'overdue' | 'today' | 'upcoming' | 'none';

const stateLabels: Record<LeadFollowUpState, string> = {
    overdue: 'Atrasado',
    today: 'Hoje',
    upcoming: 'Próximo',
    none: 'Sem follow-up',
};

const stateClasses: Record<LeadFollowUpState, string> = {
    overdue:
        'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
    today: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    upcoming:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
    none: 'text-muted-foreground',
};

/** 28/09/2026 09:00, no fuso do navegador. */
export function formatFollowUp(value: string | null | undefined) {
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

/** Valor para <input type="datetime-local"> (horário local, sem fuso). */
export function toDateTimeLocalValue(value: string | Date | null | undefined) {
    if (!value) {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function LeadFollowUpBadge({
    state,
    className,
}: {
    state: LeadFollowUpState | null | undefined;
    className?: string;
}) {
    if (!state) {
        return null;
    }

    return (
        <Badge variant="outline" className={cn(stateClasses[state], className)}>
            {stateLabels[state]}
        </Badge>
    );
}

/** Data, situação e badge numa linha (listagem, Kanban, filas). */
export function LeadFollowUpSummary({
    value,
    state,
    className,
}: {
    value: string | null;
    state: LeadFollowUpState | null | undefined;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex flex-wrap items-center gap-1.5',
                className,
            )}
        >
            <CalendarClock className="size-3.5 shrink-0" aria-hidden="true" />
            {value ? formatFollowUp(value) : 'Sem data'}
            <LeadFollowUpBadge
                state={state === 'none' ? null : state}
                className="px-1.5 py-0 text-[10px]"
            />
        </span>
    );
}

type Preset = { key: string; label: string; date: () => Date };

function atHour(daysFromToday: number, hours: number) {
    const date = new Date();
    date.setDate(date.getDate() + daysFromToday);
    date.setHours(hours, 0, 0, 0);

    return date;
}

/** Hoje: 18:00; depois disso, a próxima hora cheia, sem passar da meia-noite. */
function todayPreset() {
    const now = new Date();
    const evening = atHour(0, 18);

    if (evening > now) {
        return evening;
    }

    const nextHour = new Date(now);
    nextHour.setHours(now.getHours() + 1, 0, 0, 0);

    return nextHour.getDate() === now.getDate() ? nextHour : atHour(0, 23);
}

const presets: Preset[] = [
    { key: 'today', label: 'Hoje', date: todayPreset },
    { key: 'tomorrow', label: 'Amanhã', date: () => atHour(1, 9) },
    { key: 'plus3', label: '+3 dias', date: () => atHour(3, 9) },
    { key: 'plus7', label: '+7 dias', date: () => atHour(7, 9) },
];

function presetHint(date: Date) {
    return new Intl.DateTimeFormat('pt-BR', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

/**
 * Próximo contato na área comercial da conversa: atalhos, data/hora livre e remoção.
 * Só agenda; nenhuma mensagem é enviada quando a data chega.
 */
export function LeadFollowUpControl({
    lead,
}: {
    lead: {
        id: number;
        next_follow_up_at: string | null;
        follow_up_state?: LeadFollowUpState | null;
    };
}) {
    const [picking, setPicking] = useState(false);
    const [custom, setCustom] = useState(() =>
        toDateTimeLocalValue(lead.next_follow_up_at ?? atHour(1, 9)),
    );
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const closed = lead.follow_up_state === null;

    const save = (value: Date | null) => {
        router.patch(
            leadFollowUpRoute.url({ lead: lead.id }),
            { next_follow_up_at: value ? value.toISOString() : null },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['lead'],
                onStart: () => setSaving(true),
                onSuccess: () => {
                    setError(null);
                    setPicking(false);
                },
                onError: (errors) =>
                    setError(
                        errors.next_follow_up_at ??
                            'Não foi possível salvar o próximo contato.',
                    ),
                onFinish: () => setSaving(false),
            },
        );
    };

    const saveCustom = () => {
        const date = new Date(custom);

        if (!custom || Number.isNaN(date.getTime())) {
            setError('Informe a data e a hora do próximo contato.');

            return;
        }

        save(date);
    };

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className="flex items-center gap-1 text-muted-foreground">
                    <CalendarClock className="size-3.5" aria-hidden="true" />
                    Próximo contato:
                </span>
                <span className="font-medium">
                    {lead.next_follow_up_at
                        ? formatFollowUp(lead.next_follow_up_at)
                        : 'não definido'}
                </span>
                <LeadFollowUpBadge
                    state={lead.follow_up_state}
                    className="px-1.5 py-0 text-[10px]"
                />
                {!closed && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                disabled={saving}
                            >
                                {lead.next_follow_up_at ? 'Alterar' : 'Agendar'}
                                <ChevronDown className="size-3.5 opacity-60" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-56">
                            {presets.map((preset) => {
                                const date = preset.date();

                                return (
                                    <DropdownMenuItem
                                        key={preset.key}
                                        onSelect={() => save(preset.date())}
                                        className="justify-between"
                                    >
                                        {preset.label}
                                        <span className="text-xs text-muted-foreground">
                                            {presetHint(date)}
                                        </span>
                                    </DropdownMenuItem>
                                );
                            })}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onSelect={() => {
                                    setError(null);
                                    setPicking(true);
                                }}
                            >
                                Escolher data/hora...
                            </DropdownMenuItem>
                            {lead.next_follow_up_at && (
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => save(null)}
                                >
                                    Remover
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
                {closed && lead.next_follow_up_at && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-7 text-xs"
                        disabled={saving}
                        onClick={() => save(null)}
                    >
                        Remover
                    </Button>
                )}
                {saving && <Spinner className="size-3.5" />}
            </div>
            {closed && (
                <p className="text-xs text-muted-foreground">
                    Prospect encerrado: não entra nos follow-ups pendentes.
                </p>
            )}
            {picking && !closed && (
                <div className="flex flex-wrap items-center gap-2">
                    <label className="sr-only" htmlFor="lead_follow_up_custom">
                        Data e hora do próximo contato
                    </label>
                    <Input
                        id="lead_follow_up_custom"
                        type="datetime-local"
                        value={custom}
                        onChange={(event) => setCustom(event.target.value)}
                        className="h-8 w-auto text-xs"
                    />
                    <Button
                        type="button"
                        size="sm"
                        className="h-8 text-xs"
                        disabled={saving}
                        onClick={saveCustom}
                    >
                        Salvar
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8 text-xs"
                        disabled={saving}
                        onClick={() => setPicking(false)}
                    >
                        Cancelar
                    </Button>
                </div>
            )}
            <InputError message={error ?? undefined} />
        </div>
    );
}
