/** Situações de uma solicitação do site (App\Models\Lead::REQUEST_STATUSES). */
export type RequestStatus =
    'new' | 'in_progress' | 'answered' | 'done' | 'archived';

/** Cor do selo de cada situação. */
export const requestStatusClasses: Record<RequestStatus, string> = {
    new: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-200',
    in_progress:
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
    answered:
        'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-200',
    done: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    archived: 'border-border bg-muted text-muted-foreground',
};

export function formatRequestDate(value: string | null) {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
