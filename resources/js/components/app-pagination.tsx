import { Link } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';

type PaginationLink = {
    url: string | null;
    label: string;
    active?: boolean;
};

export type PaginationData = {
    links: PaginationLink[];
    first_page_url: string | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    last_page_url: string | null;
    current_page: number;
    last_page: number;
    from?: number | null;
    to?: number | null;
    total?: number;
    per_page?: number;
};

type NavButtonProps = {
    url?: string | null;
    children: React.ReactNode;
    disabled?: boolean;
    variant?: 'outline' | 'secondary' | 'default';
    className?: string;
    srText?: string;
};

function NavButton({
    url,
    children,
    disabled,
    variant = 'outline',
    className = '',
    srText = '',
}: NavButtonProps) {
    const content = (
        <>
            {srText && <span className="sr-only">{srText}</span>}
            {children}
        </>
    );

    if (!url || disabled) {
        return (
            <Button
                variant={variant}
                size="icon"
                className={`size-8 ${className}`}
                disabled
            >
                {content}
            </Button>
        );
    }

    return (
        <Button
            variant={variant}
            size="icon"
            className={`size-8 ${className}`}
            asChild
        >
            <Link href={url}>{content}</Link>
        </Button>
    );
}

export function PaginationSummary({ data }: { data?: PaginationData | null }) {
    if (!data || typeof data.total !== 'number') {
        return null;
    }

    const formatNumber = (value: number) =>
        new Intl.NumberFormat('pt-BR').format(value);

    return (
        <div className="flex max-w-full items-center gap-2 overflow-x-auto rounded-lg border bg-muted/20 px-3 py-2 text-xs whitespace-nowrap">
            <span className="text-muted-foreground">
                Registros por página: {formatNumber(data.per_page ?? 15)}
            </span>
            <span className="text-muted-foreground/40">·</span>
            <span className="text-muted-foreground">
                Listados: {formatNumber(data.to ?? 0)}
            </span>
            <span className="text-muted-foreground/40">·</span>
            <span className="text-muted-foreground">
                Total: {formatNumber(data.total)}
            </span>
        </div>
    );
}

export default function AppPagination({
    data,
}: {
    data?: PaginationData | null;
}) {
    if (!data || !data.links) {
        return null;
    }

    const pageLinks = data.links.filter(
        (link) => !Number.isNaN(Number(link.label)),
    );

    return (
        <div className="flex flex-col gap-3 py-3 md:flex-row md:items-center md:justify-between">
            <div className="flex w-full items-center justify-start gap-2 overflow-x-auto">
                <NavButton
                    url={data.first_page_url}
                    disabled={data.current_page === 1}
                    className="hidden md:flex"
                    srText="Primeira página"
                >
                    <ChevronsLeft className="size-4" />
                </NavButton>

                <NavButton url={data.prev_page_url} srText="Página anterior">
                    <ChevronLeft className="size-4" />
                </NavButton>

                {pageLinks.map((item, index) => (
                    <NavButton
                        key={`${item.label}-${index}`}
                        url={item.url}
                        variant={item.active ? 'default' : 'secondary'}
                        disabled={item.active}
                    >
                        {item.label}
                    </NavButton>
                ))}

                <NavButton url={data.next_page_url} srText="Próxima página">
                    <ChevronRight className="size-4" />
                </NavButton>

                <NavButton
                    url={data.last_page_url}
                    disabled={data.current_page === data.last_page}
                    className="hidden md:flex"
                    srText="Última página"
                >
                    <ChevronsRight className="size-4" />
                </NavButton>
            </div>

            <div className="flex shrink-0 text-sm text-muted-foreground">
                <span>
                    Página <strong>{data.current_page}</strong> de{' '}
                    <strong>{data.last_page}</strong>
                </span>
            </div>
        </div>
    );
}
