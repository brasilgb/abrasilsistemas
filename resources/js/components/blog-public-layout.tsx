import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, BookOpen, LogIn, MessageCircle, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import type { User } from '@/types';

const whatsappUrl =
    'https://wa.me/5551998931325?text=Ol%C3%A1%2C%20conheci%20a%20ABrasil%20Sistemas%20pelo%20blog.';

export default function BlogPublicLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;

    return (
        <div className="min-h-screen bg-white text-slate-900">
            <header className="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl">
                <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-12">
                    <Link href="/" className="flex items-center gap-3">
                        <img
                            src="/images/logo_ab.png"
                            alt="ABrasil Sistemas"
                            className="size-12 rounded-xl border border-slate-200 bg-white object-contain shadow-sm"
                        />
                        <div className="leading-none">
                            <strong className="block text-slate-950">ABrasil Sistemas</strong>
                            <span className="mt-1.5 block text-[9px] font-semibold tracking-[0.14em] text-slate-500 uppercase">
                                Blog
                            </span>
                        </div>
                    </Link>
                    <nav className="flex items-center gap-2 text-sm font-semibold sm:gap-4">
                        <Link href="/blog" className="hidden items-center gap-2 text-blue-700 sm:inline-flex">
                            <BookOpen className="size-4" /> Blog
                        </Link>
                        <a
                            href={whatsappUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="hidden items-center gap-2 text-slate-600 hover:text-blue-700 md:inline-flex"
                        >
                            <MessageCircle className="size-4" /> Fale conosco
                        </a>
                        {auth.user ? (
                            <div className="flex items-center">
                                <Link
                                    href={auth.user.role === 'admin' ? '/dashboard' : '/settings/profile'}
                                    className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-slate-700 hover:bg-slate-50"
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
                                <Link href="/login" className="hidden items-center gap-2 text-slate-600 hover:text-blue-700 sm:inline-flex">
                                    <LogIn className="size-4" /> Entrar
                                </Link>
                                <a href="/blog#cadastro" className="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2.5 text-white hover:bg-blue-800">
                                    Cadastre-se
                                </a>
                            </>
                        )}
                    </nav>
                </div>
                <div className="border-t border-slate-100 bg-slate-50/90">
                    <div className="mx-auto flex min-h-10 max-w-7xl items-center justify-between gap-4 px-5 py-2 text-xs sm:px-8 lg:px-12">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2 font-semibold text-slate-600 hover:text-blue-700"
                        >
                            <ArrowLeft className="size-3.5" />
                            Site principal
                        </Link>
                        {auth.user && (
                            <span className="min-w-0 truncate text-slate-500">
                                Logado como{' '}
                                <strong className="font-bold text-slate-800">
                                    {auth.user.name}
                                </strong>
                            </span>
                        )}
                    </div>
                </div>
            </header>
            {children}
            <footer className="mt-20 bg-slate-950 text-white">
                <div className="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-10 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-12">
                    <Link href="/" className="flex items-center gap-3">
                        <img
                            src="/images/logo_ab.png"
                            alt="ABrasil Sistemas"
                            className="size-12 rounded-xl border border-white/10 bg-white object-contain shadow-sm"
                        />
                        <div>
                            <strong className="block text-white">
                                ABrasil Sistemas
                            </strong>
                            <p className="mt-1 text-sm text-slate-400">
                                Tecnologia para empresas trabalharem melhor e
                                crescerem.
                            </p>
                        </div>
                    </Link>
                    <div className="flex gap-5 text-sm text-slate-400">
                        <Link href="/" className="hover:text-white">Início</Link>
                        <Link href="/blog" className="hover:text-white">Blog</Link>
                        <Link href="/desenvolvimento-de-sites-para-empresas" className="hover:text-white">Sites</Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}
