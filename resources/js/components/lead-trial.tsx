import { router } from '@inertiajs/react';
import { FlaskConical, Play, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import {
    registrationCheck as registrationCheckRoute,
    startTrial,
} from '@/routes/leads';

/** Situação calculada no servidor (Lead::TRIAL_STATES); Convertido/Perdido é sempre none. */
export type LeadTrialState = 'none' | 'active' | 'expired';

/** Situação exibida na tela do lead (Lead::TRIAL_PERIOD_STATUSES). */
export type LeadTrialPeriodStatus =
    'none' | 'not_started' | 'active' | 'today' | 'expired';

/** Resumo do teste enviado pela edição do lead (Lead::trialPeriod). Datas em ISO. */
export type LeadTrialPeriod = {
    status: LeadTrialPeriodStatus;
    status_label: string;
    product_name: string | null;
    trial_days: number | null;
    started_at: string | null;
    ends_at: string | null;
    can_start: boolean;
    /** Lead Convertido/Perdido: o teste não pode ser iniciado (datas existentes continuam). */
    closed: boolean;
};

/**
 * Verificação de cadastro pela API do produto (Lead::registrationCheck, CRM-WA-29). Só vem para
 * produto em modo API; no modo manual a tela fica como antes.
 */
export type LeadRegistrationCheck = {
    mode: 'api';
    configured: boolean;
    lookup_field: string | null;
    status: 'registered' | 'not_registered' | 'error' | null;
    status_label: string;
    error: string | null;
    error_label: string | null;
    checked_at: string | null;
    can_check: boolean;
};

/** As datas do teste seguem o dia de São Paulo, como no servidor. */
const TRIAL_TIMEZONE = 'America/Sao_Paulo';

/** Vencimento → "30/09": o último dia do teste (o dia do instante anterior ao vencimento). */
function lastDayMonth(endsAt: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        timeZone: TRIAL_TIMEZONE,
    }).format(new Date(new Date(endsAt).getTime() - 1000));
}

/** "12/10/2026 17:00" no horário de São Paulo. */
function formatTrialDateTime(value: string) {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: TRIAL_TIMEZONE,
    })
        .format(new Date(value))
        .replace(',', '');
}

/** Selo discreto do período de teste (Kanban e listagem); nada quando não há teste. */
export function LeadTrialBadge({
    state,
    endsAt,
    className,
}: {
    state: LeadTrialState | null | undefined;
    endsAt: string | null;
    className?: string;
}) {
    if (state !== 'active' && state !== 'expired') {
        return null;
    }

    const label =
        state === 'expired'
            ? 'Teste vencido'
            : endsAt
              ? `Em teste até ${lastDayMonth(endsAt)}`
              : 'Em teste';

    return (
        <Badge
            variant="outline"
            className={cn(
                'px-1.5 py-0 text-[10px]',
                state === 'expired'
                    ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300'
                    : 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-300',
                className,
            )}
        >
            {label}
        </Badge>
    );
}

const PERIOD_STATUS_CLASS: Record<LeadTrialPeriodStatus, string> = {
    none: '',
    not_started: '',
    active: 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-300',
    today: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    expired:
        'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300',
};

function TrialRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}

/**
 * Período de teste na tela do lead (CRM-WA-23). O prazo vem do produto vinculado; depois de
 * iniciado, as datas não mudam por aqui (o backend também recusa um segundo início).
 */
export function LeadTrialPeriodCard({
    leadId,
    trial,
    registration = null,
}: {
    leadId: number;
    trial: LeadTrialPeriod;
    registration?: LeadRegistrationCheck | null;
}) {
    const [processing, setProcessing] = useState(false);
    const [checking, setChecking] = useState(false);
    const started = trial.started_at !== null;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FlaskConical className="size-5" />
                    Período de teste
                </CardTitle>
                <CardDescription>
                    Prazo definido pelo produto/serviço do lead.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
                <TrialRow label="Produto" value={trial.product_name ?? '-'} />
                {!started && (
                    <TrialRow
                        label="Prazo configurado"
                        value={
                            trial.trial_days === null
                                ? 'Sem período de teste'
                                : `${trial.trial_days} ${trial.trial_days === 1 ? 'dia' : 'dias'}`
                        }
                    />
                )}
                {trial.started_at && (
                    <TrialRow
                        label="Iniciado em"
                        value={formatTrialDateTime(trial.started_at)}
                    />
                )}
                {started && (
                    <TrialRow
                        label="Vencimento"
                        value={
                            trial.ends_at
                                ? formatTrialDateTime(trial.ends_at)
                                : 'Sem vencimento'
                        }
                    />
                )}
                <div className="flex justify-between gap-3">
                    <span className="text-muted-foreground">Status</span>
                    <Badge
                        variant="outline"
                        className={PERIOD_STATUS_CLASS[trial.status]}
                    >
                        {trial.status_label}
                    </Badge>
                </div>

                {registration && (
                    <div className="space-y-2 border-t pt-2">
                        <TrialRow
                            label="Cadastro"
                            value="Acompanhado via API"
                        />
                        <TrialRow
                            label="Última verificação"
                            value={
                                registration.checked_at
                                    ? `${formatTrialDateTime(registration.checked_at)} · ${registration.status_label}`
                                    : registration.status_label
                            }
                        />
                        {registration.error_label && (
                            <p className="text-xs text-muted-foreground">
                                {registration.error_label}. Nada foi alterado no
                                lead.
                            </p>
                        )}
                        {!registration.configured && (
                            <p className="text-xs text-muted-foreground">
                                A API do produto ainda não está configurada.
                            </p>
                        )}
                        {registration.can_check && registration.configured && (
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={checking}
                                    onClick={() =>
                                        router.post(
                                            registrationCheckRoute.url({
                                                lead: leadId,
                                            }),
                                            {},
                                            {
                                                preserveScroll: true,
                                                onStart: () =>
                                                    setChecking(true),
                                                onFinish: () =>
                                                    setChecking(false),
                                            },
                                        )
                                    }
                                >
                                    <RefreshCw className="size-4" />
                                    Verificar cadastro agora
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {trial.status === 'not_started' && trial.closed && (
                    <p className="pt-2 text-xs text-muted-foreground">
                        Lead convertido ou perdido: o teste não pode ser
                        iniciado.
                    </p>
                )}

                {trial.status === 'not_started' && !trial.closed && (
                    <div className="flex justify-end pt-2">
                        <Button
                            type="button"
                            size="sm"
                            disabled={processing || !trial.can_start}
                            onClick={() => {
                                if (
                                    !window.confirm(
                                        'Iniciar o período de teste agora? Depois de iniciado, as datas não podem ser alteradas.',
                                    )
                                ) {
                                    return;
                                }

                                router.post(
                                    startTrial.url({ lead: leadId }),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onStart: () => setProcessing(true),
                                        onFinish: () => setProcessing(false),
                                    },
                                );
                            }}
                        >
                            <Play className="size-4" />
                            Iniciar período de teste
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
