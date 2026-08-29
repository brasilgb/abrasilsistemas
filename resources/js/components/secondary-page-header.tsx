import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, LogIn, Menu, MessageCircle, UserRound, X } from 'lucide-react';
import { useState } from 'react';
import { NavProductsMenu } from '@/components/nav-products-menu';
import { NavServicesMenu } from '@/components/nav-services-menu';
import { PublicBrand } from '@/components/public-brand';
import { buildWhatsappUrl, useContact } from '@/lib/contact';
import type { User } from '@/types';

export type SecondaryNavLink = { href: string; label: string };

/**
 * Header shared by every "secondary" public page (produtos, servicos, sobre,
 * contato, páginas legais, company-websites) — brand, dropdowns for
 * Produtos/Serviços, page-specific anchor links, Sobre/Conteúdo/Contato,
 * CTAs, and the "Site principal" back bar. The home page (welcome.tsx) keeps
 * its own richer header since it has extra same-page anchors.
 */
export function SecondaryPageHeader({
    extraLinks = [],
    showProductsMenu = true,
    showServicesMenu = true,
}: {
    extraLinks?: SecondaryNavLink[];
    /** Hide the "Produtos" dropdown on the /produtos page itself — its own anchors (passed via extraLinks) already cover it. */
    showProductsMenu?: boolean;
    /** Hide the "Serviços" dropdown on the /servicos page itself — its own anchors (passed via extraLinks) already cover it. */
    showServicesMenu?: boolean;
}) {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const contact = useContact();
    const whatsappUrl = buildWhatsappUrl(contact.whatsapp, 'Olá, preciso de ajuda com uma solução da ABrasil.');
    const closeMobile = () => setMobileMenuOpen(false);

    return (
        <header className="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-[#08111f]/90 text-white backdrop-blur-xl">
            <div className="mx-auto flex h-20 max-w-[86rem] items-center justify-between px-5 sm:px-8 lg:px-12">
                <PublicBrand inverse />
                <nav className="hidden items-center gap-7 text-sm font-semibold text-slate-300 lg:flex" aria-label="Navegação principal">
                    {showProductsMenu && <NavProductsMenu />}
                    {showServicesMenu && <NavServicesMenu />}
                    {extraLinks.map((link) => (
                        <a key={link.href} href={link.href} className="transition hover:text-white">
                            {link.label}
                        </a>
                    ))}
                    <Link href="/sobre" className="transition hover:text-white">
                        Sobre
                    </Link>
                    <Link href="/blog" className="transition hover:text-white">
                        Blog
                    </Link>
                    <Link href="/contato" className="transition hover:text-white">
                        Contato
                    </Link>
                </nav>
                <div className="hidden items-center gap-3 sm:flex">
                    {auth.user ? (
                        <Link
                            href={auth.user.role === 'admin' ? '/dashboard' : '/settings/profile'}
                            className="inline-flex items-center gap-2 rounded-full border border-white/15 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/5"
                        >
                            <UserRound className="size-4 shrink-0" />
                            Minha conta
                        </Link>
                    ) : (
                        <Link
                            href="/login"
                            className="inline-flex items-center gap-2 text-sm font-semibold text-slate-300 transition hover:text-white"
                        >
                            <LogIn className="size-4" /> Entrar
                        </Link>
                    )}
                    <Link
                        href="/contato"
                        className="inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2.5 text-sm font-bold text-slate-200 transition hover:bg-white/5"
                    >
                        Solicitar orçamento
                    </Link>
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-2 rounded-full bg-cyan-300 px-5 py-3 text-sm font-extrabold text-slate-950 transition hover:-translate-y-0.5 hover:bg-cyan-200"
                    >
                        Falar com a equipe
                        <MessageCircle className="size-4" />
                    </a>
                </div>
                <button
                    type="button"
                    aria-label={mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'}
                    aria-expanded={mobileMenuOpen}
                    className="grid size-11 place-items-center rounded-full border border-white/15 lg:hidden"
                    onClick={() => setMobileMenuOpen((open) => !open)}
                >
                    {mobileMenuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                </button>
            </div>
            {mobileMenuOpen && (
                <nav className="border-t border-white/10 bg-[#08111f] px-5 py-5 text-sm font-bold lg:hidden" aria-label="Navegação móvel">
                    {showProductsMenu && <NavProductsMenu mobile onNavigate={closeMobile} />}
                    {showServicesMenu && <NavServicesMenu mobile onNavigate={closeMobile} />}
                    {extraLinks.map((link) => (
                        <a
                            key={link.href}
                            href={link.href}
                            className="block border-b border-white/10 py-4"
                            onClick={closeMobile}
                        >
                            {link.label}
                        </a>
                    ))}
                    <Link href="/sobre" className="block border-b border-white/10 py-4" onClick={closeMobile}>
                        Sobre
                    </Link>
                    <Link href="/blog" className="block border-b border-white/10 py-4" onClick={closeMobile}>
                        Blog
                    </Link>
                    <Link href="/contato" className="block border-b border-white/10 py-4" onClick={closeMobile}>
                        Contato
                    </Link>
                    {auth.user ? (
                        <Link
                            href={auth.user.role === 'admin' ? '/dashboard' : '/settings/profile'}
                            className="flex items-center gap-2 border-b border-white/10 py-4"
                            onClick={closeMobile}
                        >
                            <UserRound className="size-4" /> Minha conta
                        </Link>
                    ) : (
                        <Link href="/login" className="flex items-center gap-2 border-b border-white/10 py-4" onClick={closeMobile}>
                            <LogIn className="size-4" /> Entrar
                        </Link>
                    )}
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-5 flex items-center justify-center gap-2 rounded-full bg-cyan-300 px-5 py-3.5 text-slate-950"
                        onClick={closeMobile}
                    >
                        Falar com a equipe <MessageCircle className="size-4" />
                    </a>
                </nav>
            )}
            <div className="border-t border-white/10 bg-white/[0.03]">
                <div className="mx-auto flex min-h-10 max-w-[86rem] items-center px-5 py-2 text-xs sm:px-8 lg:px-12">
                    <Link href="/" className="inline-flex items-center gap-2 font-semibold text-slate-300 transition hover:text-white">
                        <ArrowLeft className="size-3.5" />
                        Site principal
                    </Link>
                </div>
            </div>
        </header>
    );
}
