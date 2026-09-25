import {
    AlertCircle,
    ArrowDownLeft,
    ArrowUpRight,
    Check,
    CheckCheck,
    Clock,
    MessageCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type WhatsappMessageStatusValue =
    'PENDING' | 'SERVER' | 'DEVICE' | 'READ' | 'PLAYED' | 'ERROR';

export type WhatsappMessageDirectionValue = 'inbound' | 'outbound';

const DIRECTION_PRESENTATION: Record<
    WhatsappMessageDirectionValue,
    { label: string; flow: string; icon: LucideIcon; className: string }
> = {
    inbound: {
        label: 'Recebida',
        flow: 'Cliente → Empresa',
        icon: ArrowDownLeft,
        className: 'text-emerald-600 dark:text-emerald-400',
    },
    outbound: {
        label: 'Enviada',
        flow: 'Empresa → Cliente',
        icon: ArrowUpRight,
        className: 'text-muted-foreground',
    },
};

type DirectionProps = {
    direction: string | null | undefined;
    className?: string;
};

/**
 * Direção da mensagem WhatsApp. Atividades sem direction (legado ou registro manual) não mostram nada.
 */
export function WhatsappMessageDirection({
    direction,
    className,
}: DirectionProps) {
    const presentation = direction
        ? DIRECTION_PRESENTATION[direction as WhatsappMessageDirectionValue]
        : undefined;

    if (!presentation) {
        return null;
    }

    const Icon = presentation.icon;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1',
                presentation.className,
                className,
            )}
            title={`Mensagem ${presentation.label.toLowerCase()}: ${presentation.flow}`}
        >
            <Icon className="size-3.5" aria-hidden="true" />
            {presentation.label} · {presentation.flow}
        </span>
    );
}

const STATUS_PRESENTATION: Record<
    WhatsappMessageStatusValue,
    { label: string; icon: LucideIcon; className: string }
> = {
    PENDING: {
        label: 'Pendente',
        icon: Clock,
        className: 'text-muted-foreground',
    },
    SERVER: {
        label: 'Enviada',
        icon: Check,
        className: 'text-muted-foreground',
    },
    DEVICE: {
        label: 'Entregue',
        icon: CheckCheck,
        className: 'text-muted-foreground',
    },
    READ: {
        label: 'Lida',
        icon: CheckCheck,
        className: 'font-medium text-sky-600 dark:text-sky-400',
    },
    PLAYED: {
        label: 'Reproduzida',
        icon: CheckCheck,
        className: 'font-medium text-sky-600 dark:text-sky-400',
    },
    ERROR: {
        label: 'Falhou',
        icon: AlertCircle,
        className: 'font-medium text-destructive',
    },
};

type Props = {
    status: string | null | undefined;
    className?: string;
};

export default function WhatsappMessageStatus({ status, className }: Props) {
    const presentation = status
        ? STATUS_PRESENTATION[
              status.toUpperCase() as WhatsappMessageStatusValue
          ]
        : undefined;

    if (!presentation) {
        return null;
    }

    const Icon = presentation.icon;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1',
                presentation.className,
                className,
            )}
            title={`Status da mensagem: ${presentation.label}`}
        >
            <Icon className="size-3.5" aria-hidden="true" />
            {presentation.label}
        </span>
    );
}

type UnreadProps = {
    count: number | null | undefined;
    className?: string;
};

/**
 * Quantidade de mensagens WhatsApp recebidas ainda não vistas no CRM. Sem pendências, não mostra nada.
 */
export function WhatsappUnreadBadge({ count, className }: UnreadProps) {
    if (!count) {
        return null;
    }

    const label =
        count === 1
            ? '1 mensagem WhatsApp não lida'
            : `${count} mensagens WhatsApp não lidas`;

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-600 px-1.5 py-0.5 text-[11px] leading-none font-medium text-white dark:bg-emerald-500',
                className,
            )}
            title={label}
            aria-label={label}
        >
            <MessageCircle className="size-3" aria-hidden="true" />
            {count > 99 ? '99+' : count}
        </span>
    );
}
