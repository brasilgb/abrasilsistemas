import { Link } from '@inertiajs/react';
import { Mail, MessageCircle } from 'lucide-react';
import { products, services } from '@/data/site';
import { useContact, useSiteWhatsappUrl } from '@/lib/contact';
import { PublicBrand } from './public-brand';

function FooterColumn({
    title,
    links,
}: {
    title: string;
    links: { href: string; label: string }[];
}) {
    return (
        <div>
            <p className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                {title}
            </p>
            <ul className="mt-4 grid gap-2.5 text-sm text-slate-400">
                {links.map((link) => (
                    <li key={link.href}>
                        <Link
                            href={link.href}
                            className="transition hover:text-white"
                        >
                            {link.label}
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function PublicFooter() {
    const contact = useContact();
    const whatsappUrl = useSiteWhatsappUrl(
        'Olá, quero falar com a ABrasil Sistemas.',
    );

    return (
        <footer className="border-t border-white/10 bg-[#070c17] text-white">
            <div className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:grid-cols-2 sm:px-6 lg:grid-cols-[1.2fr_1fr_0.8fr_0.7fr_1.4fr] lg:px-8">
                <div>
                    <PublicBrand inverse />
                    <p className="mt-5 max-w-xs text-sm leading-6 text-slate-400">
                        Tecnologia aplicada para transformar processos,
                        operações e negócios. Produtos próprios e
                        desenvolvimento sob medida.
                    </p>
                </div>
                <FooterColumn
                    title="Soluções"
                    links={[
                        ...services.map((service) => ({
                            href: `/${service.slug}`,
                            label: service.label,
                        })),
                        { href: '/servicos', label: 'Todas as soluções' },
                    ]}
                />
                <FooterColumn
                    title="Sistemas prontos"
                    links={[
                        ...products.map((product) => ({
                            href: `/sistemas/${product.slug}`,
                            label: product.name,
                        })),
                        { href: '/sistemas', label: 'Todos os sistemas' },
                    ]}
                />
                <FooterColumn
                    title="Empresa"
                    links={[
                        { href: '/sobre', label: 'Sobre' },
                        { href: '/blog', label: 'Blog' },
                        { href: '/contato', label: 'Contato' },
                        {
                            href: '/politica-de-privacidade',
                            label: 'Privacidade',
                        },
                        { href: '/termos-de-uso', label: 'Termos de uso' },
                        { href: '/politica-de-cookies', label: 'Cookies' },
                    ]}
                />
                <div>
                    <p className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                        Contato
                    </p>
                    <ul className="mt-4 grid gap-3 text-sm text-slate-400">
                        <li>
                            <a
                                href={`mailto:${contact.email}`}
                                className="flex items-center gap-2 transition hover:text-white"
                            >
                                <Mail className="size-4 shrink-0" />{' '}
                                {contact.email}
                            </a>
                        </li>
                        <li>
                            <a
                                href={whatsappUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-2 transition hover:text-white"
                            >
                                <MessageCircle className="size-4 shrink-0" />{' '}
                                {contact.whatsappDisplay}
                            </a>
                        </li>
                    </ul>
                    <Link
                        href="/contato#formulario"
                        className="mt-5 inline-flex h-10 items-center rounded-md border border-white/20 px-4 text-sm font-semibold text-white transition hover:bg-white/10"
                    >
                        Falar sobre um projeto
                    </Link>
                </div>
            </div>
            <div className="border-t border-white/10">
                <p className="mx-auto max-w-7xl px-4 py-5 text-xs text-slate-500 sm:px-6 lg:px-8">
                    © {new Date().getFullYear()} ABrasil Sistemas.
                    Desenvolvimento de software no Brasil.
                </p>
            </div>
        </footer>
    );
}
