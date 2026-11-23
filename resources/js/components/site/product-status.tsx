import { productStatuses } from '@/data/site';
import type { ProductStatus } from '@/data/site';

const classes: Record<ProductStatus, string> = {
    available: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    demo: 'border-sky-200 bg-sky-50 text-sky-900',
    development: 'border-amber-200 bg-amber-50 text-amber-900',
};

/** Estágio real do produto: disponível, em demonstração ou em desenvolvimento. */
export function ProductStatusBadge({ status }: { status: ProductStatus }) {
    return (
        <span
            className={`inline-flex w-fit items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${classes[status]}`}
        >
            {productStatuses[status].label}
        </span>
    );
}
