import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { ButtonLink, Container, Eyebrow } from '@/components/site/blocks';
import { SiteLayout } from '@/components/site/site-layout';
import { services } from '@/data/site';
import { useSiteWhatsappUrl } from '@/lib/contact';

export default function NotFound() {
    const whatsappUrl = useSiteWhatsappUrl(
        'Olá, cheguei numa página que não existe mais no site da ABrasil.',
    );

    return (
        <SiteLayout>
            <Head title="Página não encontrada | ABrasil Sistemas">
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            <section className="bg-[#0b1220] pt-28 pb-16 text-white sm:pt-36 sm:pb-24">
                <Container>
                    <Eyebrow inverse>Erro 404</Eyebrow>
                    <h1 className="mt-4 max-w-3xl text-4xl leading-[1.1] font-semibold tracking-tight text-balance sm:text-5xl">
                        Página não encontrada.
                    </h1>
                    <p className="mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                        O endereço que você tentou acessar não existe ou foi
                        movido. Os caminhos abaixo levam ao conteúdo principal
                        do site.
                    </p>
                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        <ButtonLink href="/" variant="inverse" arrow>
                            Voltar para a página inicial
                        </ButtonLink>
                        <ButtonLink href={whatsappUrl} variant="ghostInverse">
                            Falar com a equipe
                        </ButtonLink>
                    </div>
                </Container>
            </section>

            <section className="py-16 sm:py-20">
                <Container>
                    <h2 className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                        Soluções
                    </h2>
                    <ul className="mt-4 grid gap-x-8 border-t border-slate-200 sm:grid-cols-2 lg:grid-cols-3">
                        {services.map((service) => (
                            <li
                                key={service.slug}
                                className="border-b border-slate-200"
                            >
                                <Link
                                    href={`/${service.slug}`}
                                    className="group flex items-center justify-between gap-4 py-4 font-semibold text-slate-900 hover:text-blue-800"
                                >
                                    {service.label}
                                    <ArrowRight className="size-4 shrink-0 text-slate-400 group-hover:text-blue-800" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Container>
            </section>
        </SiteLayout>
    );
}
