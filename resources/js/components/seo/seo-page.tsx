import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { seoNavItems } from '@/lib/admin-navigation';
import { cn } from '@/lib/utils';

/** Moldura das telas de SEO: título, descrição, abas das seções e ações. */
export function SeoPage({
    title,
    description,
    actions,
    children,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <>
            <Head title={`${title} · SEO`} />
            <div className="space-y-6 p-4">
                <nav
                    aria-label="Seções de SEO"
                    className="-mx-1 flex gap-1 overflow-x-auto border-b px-1 pb-2"
                >
                    {seoNavItems.map((item, index) => {
                        const active =
                            index === 0
                                ? isCurrentUrl(item.href)
                                : isCurrentOrParentUrl(item.href);

                        return (
                            <Link
                                key={item.title}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'inline-flex h-8 shrink-0 items-center rounded-md px-3 text-sm font-medium transition',
                                    active
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading title={title} description={description} />
                    {actions && (
                        <div className="flex shrink-0 flex-wrap gap-2">
                            {actions}
                        </div>
                    )}
                </div>
                {children}
            </div>
        </>
    );
}
