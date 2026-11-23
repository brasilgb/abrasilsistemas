/** Situações de uma solicitação do site (App\Models\Lead::REQUEST_STATUSES). */
export type RequestStatus =
    | 'new'
    | 'in_progress'
    | 'proposal'
    | 'negotiation'
    | 'won'
    | 'lost'
    | 'archived';

/** Cor do selo de cada situação. */
export const requestStatusClasses: Record<RequestStatus, string> = {
    new: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-200',
    in_progress:
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
    proposal:
        'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-200',
    negotiation:
        'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-200',
    won: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    lost: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200',
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
