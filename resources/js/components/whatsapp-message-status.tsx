import { AlertCircle, Check, CheckCheck, Clock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type WhatsappMessageStatusValue =
    'PENDING' | 'SERVER' | 'DEVICE' | 'READ' | 'PLAYED' | 'ERROR';

const STATUS_PRESENTATION: Record<
    WhatsappMessageStatusValue,
    { label: string; icon: LucideIcon; className: string }
> = {
    PENDING: {
        label: 'Enviando',
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
        label: 'Erro no envio',
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
