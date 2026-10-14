import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, ChevronDown, Menu, X } from 'lucide-react';
import { useState } from 'react';
import { PublicBrand } from '@/components/public-brand';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { products, services } from '@/data/site';
import type { User } from '@/types';

const mainLinks = [
    { href: '/sistemas', label: 'Sistemas prontos' },
    { href: '/sobre', label: 'Sobre' },
    { href: '/blog', label: 'Blog' },
    { href: '/contato', label: 'Contato' },
] as const;

/** Cabeçalho único do site institucional (home, soluções, sistemas, sobre, contato, páginas legais). */
export function SiteHeader() {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const [open, setOpen] = useState(false);
    const close = () => setOpen(false);
    const accountHref =
        auth.user?.role === 'admin' ? '/dashboard' : '/settings/profile';

    return (
        <header className="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-[#0b1220]/95 text-white backdrop-blur">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
                <PublicBrand inverse />

                <nav
                    className="hidden items-center gap-7 text-sm font-medium text-slate-300 lg:flex"
                    aria-label="Navegação principal"
                >
                    <DropdownMenu modal={false}>
                        <DropdownMenuTrigger className="flex items-center gap-1 transition outline-none hover:text-white data-[state=open]:text-white">
                            Soluções
                            <ChevronDown className="size-3.5" />
                        </DropdownMenuTrigger>
                        {/* Cores fixas: o menu é renderizado fora da página e não deve seguir o tema escuro do sistema. */}
                        <DropdownMenuContent
                            align="start"
                            sideOffset={14}
                            className="w-[34rem] border-slate-200 bg-white p-2 text-slate-900"
                        >
                            <div className="grid grid-cols-2 gap-1">
                                {services.map((service) => (
                                    <DropdownMenuItem
                                        key={service.slug}
                                        asChild
                                        className="focus:bg-slate-100 focus:text-slate-900"
                                    >
                                        <Link
                                            href={`/${service.slug}`}
                                            className="flex flex-col items-start gap-1 rounded-md p-3"
                                        >
                                            <span className="text-sm font-semibold">
                                                {service.label}
                                            </span>
                                            <span className="text-xs leading-5 text-slate-500">
                                                {service.summary}
                                            </span>
                                        </Link>
                                    </DropdownMenuItem>
                                ))}
                            </div>
                            <div className="mt-1 border-t border-slate-100 pt-1">
                                <DropdownMenuItem
                                    asChild
                                    className="focus:bg-slate-100 focus:text-slate-900"
                                >
                                    <Link
                                        href="/servicos"
                                        className="flex items-center justify-between rounded-md p-3 text-sm font-semibold"
                                    >
                                        Ver todas as soluções
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </DropdownMenuItem>
                            </div>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    {mainLinks.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className="transition hover:text-white"
                        >
                            {link.label}
                        </Link>
                    ))}
                </nav>

                <div className="hidden items-center gap-5 lg:flex">
                    <Link
                        href={auth.user ? accountHref : '/login'}
                        className="text-sm font-medium text-slate-400 transition hover:text-white"
                    >
                        {auth.user ? 'Minha conta' : 'Entrar'}
                    </Link>
                    <Link
                        href="/contato#formulario"
                        className="inline-flex h-10 items-center gap-2 rounded-md bg-white px-4 text-sm font-semibold text-slate-900 transition hover:bg-slate-200"
                    >
                        Falar sobre um projeto
                    </Link>
                </div>

                <button
                    type="button"
                    aria-label={open ? 'Fechar menu' : 'Abrir menu'}
                    aria-expanded={open}
                    aria-controls="menu-movel"
                    className="grid size-10 place-items-center rounded-md border border-white/15 lg:hidden"
                    onClick={() => setOpen((value) => !value)}
                >
                    {open ? (
                        <X className="size-5" />
                    ) : (
                        <Menu className="size-5" />
                    )}
                </button>
            </div>

            {open && (
                <nav
                    id="menu-movel"
                    className="max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-white/10 bg-[#0b1220] px-4 pt-2 pb-6 sm:px-6 lg:hidden"
                    aria-label="Navegação móvel"
                >
                    <p className="pt-4 pb-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">
                        Soluções
                    </p>
                    {services.map((service) => (
                        <Link
                            key={service.slug}
                            href={`/${service.slug}`}
                            onClick={close}
                            className="block py-2.5 text-[15px] text-slate-200"
                        >
                            {service.label}
                        </Link>
                    ))}
                    <p className="pt-5 pb-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">
                        Sistemas prontos
                    </p>
                    {products.map((product) => (
                        <Link
                            key={product.slug}
                            href={`/sistemas/${product.slug}`}
                            onClick={close}
                            className="block py-2.5 text-[15px] text-slate-200"
                        >
                            {product.name}
                        </Link>
                    ))}
                    <div className="mt-4 border-t border-white/10 pt-2">
                        {mainLinks.slice(1).map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                onClick={close}
                                className="block py-2.5 text-[15px] text-slate-200"
                            >
                                {link.label}
                            </Link>
                        ))}
                        <Link
                            href={auth.user ? accountHref : '/login'}
                            onClick={close}
                            className="block py-2.5 text-[15px] text-slate-400"
                        >
                            {auth.user ? 'Minha conta' : 'Entrar'}
                        </Link>
                    </div>
                    <Link
                        href="/contato#formulario"
                        onClick={close}
                        className="mt-4 flex h-11 items-center justify-center rounded-md bg-white text-sm font-semibold text-slate-900"
                    >
                        Falar sobre um projeto
                    </Link>
                </nav>
            )}
        </header>
    );
}
