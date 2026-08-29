import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, BookOpen, LogIn, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { CookieConsent } from '@/components/cookie-consent';
import { PublicBrand } from '@/components/public-brand';
import { PublicFooter } from '@/components/public-footer';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import type { User } from '@/types';

export default function BlogPublicLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;

    return (
        <div className="min-h-screen bg-white text-slate-900">
            <header className="sticky top-0 z-50 border-b border-white/10 bg-[#08111f]/90 text-white backdrop-blur-xl">
                <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-12">
                    <PublicBrand inverse />
                    <nav className="flex items-center gap-2 text-sm font-semibold sm:gap-4">
                        <Link href="/blog" className="hidden items-center gap-2 text-cyan-300 sm:inline-flex">
                            <BookOpen className="size-4" /> Blog
                        </Link>
                        <Link
                            href="/contato"
                            className="hidden items-center gap-2 text-slate-300 transition hover:text-white md:inline-flex"
                        >
                            Contato
                        </Link>
                        {auth.user ? (
                            <div className="flex items-center">
                                <Link
                                    href={auth.user.role === 'admin' ? '/dashboard' : '/settings/profile'}
                                    className="inline-flex items-center gap-2 rounded-full border border-white/15 px-3 py-2 text-slate-200 transition hover:bg-white/5"
                                >
                                    <UserRound className="size-4 shrink-0" />
                                    <span className="hidden sm:inline">
                                        Minha conta
                                    </span>
                                    <span className="max-w-20 truncate sm:hidden">
                                        {auth.user.name}
                                    </span>
                                </Link>
                            </div>
                        ) : (
                            <>
                                <Link href="/login" className="hidden items-center gap-2 text-slate-300 transition hover:text-white sm:inline-flex">
                                    <LogIn className="size-4" /> Entrar
                                </Link>
                                <a
                                    href="/blog#cadastro"
                                    className="inline-flex items-center rounded-full bg-cyan-300 px-4 py-2.5 font-extrabold text-slate-950 transition hover:-translate-y-0.5 hover:bg-cyan-200"
                                >
                                    Cadastre-se
                                </a>
                            </>
                        )}
                    </nav>
                </div>
                <div className="border-t border-white/10 bg-white/[0.03]">
                    <div className="mx-auto flex min-h-10 max-w-7xl items-center justify-between gap-4 px-5 py-2 text-xs sm:px-8 lg:px-12">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2 font-semibold text-slate-300 transition hover:text-white"
                        >
                            <ArrowLeft className="size-3.5" />
                            Site principal
                        </Link>
                        {auth.user && (
                            <span className="min-w-0 truncate text-slate-400">
                                Logado como{' '}
                                <strong className="font-bold text-slate-200">
                                    {auth.user.name}
                                </strong>
                            </span>
                        )}
                    </div>
                </div>
            </header>
            {children}
            <PublicFooter />
            <WhatsAppFloat />
            <CookieConsent />
        </div>
    );
}
