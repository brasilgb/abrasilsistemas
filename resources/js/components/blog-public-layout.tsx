import { Link, usePage } from '@inertiajs/react';
import { BookOpen, LogIn, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import type { User } from '@/types';

export default function BlogPublicLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;

    return (
        <div className="min-h-screen bg-[#060d19] text-white">
            <header className="border-b border-white/10 bg-[#060d19]/95">
                <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8">
                    <Link href="/" className="flex items-center gap-3">
                        <img
                            src="/images/logo_ab.png"
                            alt="AB Sistemas"
                            className="size-12 rounded-xl object-contain"
                        />
                        <div>
                            <strong className="block">AB Sistemas</strong>
                            <span className="text-xs text-slate-400">
                                Blog e conhecimento
                            </span>
                        </div>
                    </Link>
                    <nav className="flex items-center gap-4 text-sm">
                        <Link
                            href="/blog"
                            className="inline-flex items-center gap-2 text-sky-300"
                        >
                            <BookOpen className="size-4" /> Blog
                        </Link>
                        {auth.user ? (
                            <Link
                                href={
                                    auth.user.role === 'admin'
                                        ? '/dashboard'
                                        : '/minha-biblioteca'
                                }
                                className="inline-flex items-center gap-2 rounded-lg border border-white/15 px-3 py-2"
                            >
                                <UserRound className="size-4" /> Minha conta
                            </Link>
                        ) : (
                            <Link
                                href="/login"
                                className="inline-flex items-center gap-2 rounded-lg border border-white/15 px-3 py-2"
                            >
                                <LogIn className="size-4" /> Entrar
                            </Link>
                        )}
                    </nav>
                </div>
            </header>
            {children}
            <footer className="mt-20 border-t border-white/10 py-10 text-center text-sm text-slate-500">
                © {new Date().getFullYear()} AB Sistemas
            </footer>
        </div>
    );
}
