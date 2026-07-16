import { Link } from '@inertiajs/react';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh min-w-0 items-center justify-center overflow-hidden bg-[#020817] p-3 sm:p-6">
            <div
                className="absolute inset-0 bg-cover bg-center bg-no-repeat opacity-40"
                style={{ backgroundImage: "url('/images/logo_ab.png')" }}
            />
            <div className="absolute inset-0 bg-[linear-gradient(135deg,rgba(2,8,23,0.92),rgba(15,23,42,0.84),rgba(2,8,23,0.92))]" />

            <Card
                className={cn(
                    'relative z-10 w-full max-w-md min-w-0 gap-5 overflow-hidden border-border/50 bg-background/95 px-3 py-8 shadow-2xl backdrop-blur-md sm:px-6',
                    'max-h-[calc(100svh-1.5rem)] sm:max-h-[calc(100svh-3rem)]',
                )}
            >
                <div className="flex min-w-0 flex-col items-center gap-3">
                    <Link
                        href={home()}
                        className="flex flex-col items-center gap-2 font-medium"
                    >
                        <img
                            src="/images/logo_ab.png"
                            alt=""
                            className="size-20 rounded-md object-cover shadow-sm"
                        />
                        <span className="sr-only">{title}</span>
                    </Link>

                    <CardHeader className="w-full space-y-1 px-0 text-center">
                        <CardTitle className="text-xl font-bold text-balance sm:text-2xl">
                            {title}
                        </CardTitle>
                        <CardDescription className="text-base text-balance">
                            {description}
                        </CardDescription>
                    </CardHeader>
                </div>

                <div className="min-w-0 overflow-y-auto px-0">{children}</div>
            </Card>
        </div>
    );
}
