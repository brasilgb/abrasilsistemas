import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Severity = 'critical' | 'warning' | 'opportunity';

export const severityLabels: Record<Severity, string> = {
    critical: 'Crítico',
    warning: 'Aviso',
    opportunity: 'Oportunidade',
};

const severityClasses: Record<Severity, string> = {
    critical:
        'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200 border-red-200 dark:border-red-900',
    warning:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200 border-amber-200 dark:border-amber-900',
    opportunity:
        'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200 border-sky-200 dark:border-sky-900',
};

export function SeverityBadge({ severity }: { severity: Severity }) {
    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-md border px-2 py-0.5 text-xs font-medium',
                severityClasses[severity],
            )}
        >
            {severityLabels[severity]}
        </span>
    );
}

/** Número com rótulo. tone destaca quando o valor indica problema. */
export function StatCard({
    label,
    value,
    hint,
    tone = 'neutral',
    href,
}: {
    label: string;
    value: ReactNode;
    hint?: string;
    tone?: 'neutral' | 'good' | 'bad' | 'warn';
    href?: string;
}) {
    const body = (
        <>
            <span className="text-xs font-medium text-muted-foreground">
                {label}
            </span>
            <span
                className={cn(
                    'mt-1 block text-2xl font-semibold tabular-nums',
                    tone === 'bad' && 'text-red-700 dark:text-red-400',
                    tone === 'warn' && 'text-amber-700 dark:text-amber-400',
                    tone === 'good' && 'text-emerald-700 dark:text-emerald-400',
                )}
            >
                {value}
            </span>
            {hint && (
                <span className="mt-1 block text-xs text-muted-foreground">
                    {hint}
                </span>
            )}
        </>
    );
    const className = 'block rounded-lg border bg-card p-4';

    return href ? (
        <Link
            href={href}
            className={cn(className, 'transition hover:bg-muted/40')}
        >
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}

export function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-md border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
            {children}
        </div>
    );
}

export function Notice({
    tone = 'info',
    children,
}: {
    tone?: 'info' | 'warn' | 'danger';
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'rounded-md border px-4 py-3 text-sm',
                tone === 'info' && 'bg-muted/40',
                tone === 'warn' &&
                    'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100',
                tone === 'danger' &&
                    'border-red-200 bg-red-50 text-red-950 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100',
            )}
        >
            {children}
        </div>
    );
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

export const fieldClass =
    'mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm';

export function FieldError({ text }: { text?: string }) {
    return text ? (
        <span className="mt-1 block text-xs font-normal text-destructive">
            {text}
        </span>
    ) : null;
}

/** Contador de caracteres com a faixa recomendada. */
export function CharCount({
    value,
    min,
    max,
}: {
    value: string;
    min: number;
    max: number;
}) {
    const length = value.length;
    const tone =
        length === 0
            ? 'text-muted-foreground'
            : length < min || length > max
              ? 'text-amber-700 dark:text-amber-400'
              : 'text-emerald-700 dark:text-emerald-400';

    return (
        <span className={cn('text-xs font-normal tabular-nums', tone)}>
            {length} caracteres (recomendado {min}–{max})
        </span>
    );
}
