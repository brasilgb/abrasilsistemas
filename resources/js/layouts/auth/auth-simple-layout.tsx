import { Link } from '@inertiajs/react';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { heroImages, heroImageSrcSet } from '@/data/hero-images';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative isolate flex min-h-svh min-w-0 items-center justify-center overflow-hidden bg-brand-navy p-3 sm:p-6">
            <div className="absolute inset-0 -z-10">
                <picture>
                    <source
                        type="image/avif"
                        srcSet={heroImageSrcSet('home', 'avif')}
                        sizes="100vw"
                    />
                    <source
                        type="image/webp"
                        srcSet={heroImageSrcSet('home', 'webp')}
                        sizes="100vw"
                    />
                    <img
                        src="/images/site/heroes/home-1280.webp"
                        alt={heroImages.home.alt}
                        width={1920}
                        height={1080}
                        loading="eager"
                        fetchPriority="high"
                        decoding="async"
                        className="size-full object-cover"
                        style={{ objectPosition: heroImages.home.position }}
                    />
                </picture>
                <div className="absolute inset-0 bg-brand-navy/70" />
            </div>

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
