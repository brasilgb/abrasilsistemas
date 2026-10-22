import { Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import {
    ButtonLink,
    Container,
    CtaBand,
    FaqList,
    PageHero,
    ProcessSteps,
    SectionHeading,
} from '@/components/site/blocks';
import { SeoIncluded } from '@/components/site/seo-included';
import { SiteLayout } from '@/components/site/site-layout';
import { ServiceVisual } from '@/components/site/visuals';
import { heroImages } from '@/data/hero-images';
import { contactHref, findService, seoOffer } from '@/data/site';
import type { Service } from '@/data/site';

const CTA_LABEL = 'Solicitar análise SEO';

/**
 * Landing page do SEO profissional (/seo-tecnico-e-otimizacao-de-sites). Os textos ficam
 * em resources/js/data/site.ts (serviço + seoOffer); o SEO da página, em PublicPages.
 * Regra: sem preço fixo e sem promessa de posição no Google.
 */
export default function SeoPage({ slug }: { slug: string }) {
    const service = findService(slug);

    if (!service) {
        return null;
    }

    const related = service.related
        .map(findService)
        .filter((item): item is Service => item !== undefined);
    const contact = contactHref(service.contactType);

    return (
        <SiteLayout>
            <PageHero
                eyebrow={service.hero.eyebrow}
                title={service.hero.title}
                lead={service.hero.lead}
                visual={<ServiceVisual kind={service.visual} />}
                image={heroImages[service.heroImage]}
                actions={
                    <>
                        <ButtonLink href={contact} variant="inverse" arrow>
                            {CTA_LABEL}
                        </ButtonLink>
                        <ButtonLink href="#modalidades" variant="ghostInverse">
                            Ver modalidades
                        </ButtonLink>
                    </>
                }
            />

            {/* Por que SEO: benefícios + sinais de alerta */}
            <section className="py-20 sm:py-24">
                <Container className="grid gap-14 lg:grid-cols-[1.2fr_0.8fr] lg:gap-20">
                    <div>
                        <SectionHeading
                            eyebrow="Para a empresa"
                            title="Busca orgânica é contato de quem já tem a necessidade."
                        />
                        <dl className="mt-10 grid gap-x-10 gap-y-8 sm:grid-cols-2">
                            {seoOffer.benefits.map((benefit) => (
                                <div
                                    key={benefit.title}
                                    className="border-t border-slate-200 pt-5"
                                >
                                    <dt className="text-base font-semibold text-slate-900">
                                        {benefit.title}
                                    </dt>
                                    <dd className="mt-2 text-[15px] leading-7 text-slate-600">
                                        {benefit.description}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                    <aside className="self-start rounded-lg border border-slate-200 bg-slate-50 p-6 sm:p-8">
                        <h2 className="text-lg font-semibold text-slate-900">
                            {service.problems.title}
                        </h2>
                        <ul className="mt-5 grid gap-3">
                            {service.problems.items.map((item) => (
                                <li
                                    key={item}
                                    className="flex gap-3 text-[15px] leading-6 text-slate-700"
                                >
                                    <span
                                        className="mt-2 size-1.5 shrink-0 rounded-full bg-blue-700"
                                        aria-hidden="true"
                                    />
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </aside>
                </Container>
            </section>

            {/* O que o trabalho cobre */}
            <section className="border-y border-slate-200 bg-slate-50 py-20 sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="O que fazemos"
                        title="Técnica, velocidade e conteúdo, com dados oficiais."
                        description="O trabalho começa pelo que impede o site de ser bem lido e termina no acompanhamento do que a busca orgânica traz de volta."
                    />
                    <dl className="mt-12 grid gap-x-12 sm:grid-cols-2 lg:grid-cols-3">
                        {service.offerings.map((offering, index) => (
                            <div
                                key={offering.title}
                                className="border-t border-slate-300 py-6"
                            >
                                <dt className="flex items-baseline gap-3 text-base font-semibold text-slate-900">
                                    <span className="text-sm text-blue-700 tabular-nums">
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    {offering.title}
                                </dt>
                                <dd className="mt-2 text-[15px] leading-7 text-slate-600">
                                    {offering.description}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Container>
            </section>

            {/* Modalidades */}
            <section
                id="modalidades"
                className="scroll-mt-20 bg-[#0b1220] py-20 text-white sm:py-24"
            >
                <Container>
                    <SectionHeading
                        eyebrow="Modalidades"
                        title="Escolha pelo momento do seu site."
                        description="Sem pacote de prateleira: depois da análise inicial, a proposta traz escopo, prazos e entregas conforme o tamanho e a tecnologia do site."
                        inverse
                    />
                    <div className="mt-12 grid gap-6 lg:grid-cols-3">
                        {seoOffer.modalities.map((modality) => (
                            <article
                                key={modality.name}
                                className={`flex flex-col rounded-lg border p-6 sm:p-7 ${modality.highlight ? 'border-sky-400/50 bg-white/[0.06]' : 'border-white/10'}`}
                            >
                                <h3 className="text-xl font-semibold">
                                    {modality.name}
                                </h3>
                                <p className="mt-1 text-sm text-sky-300">
                                    {modality.audience}
                                </p>
                                <p className="mt-4 text-[15px] leading-7 text-slate-300">
                                    {modality.description}
                                </p>
                                <ul className="mt-6 grid gap-2.5 border-t border-white/10 pt-6">
                                    {modality.items.map((item) => (
                                        <li
                                            key={item}
                                            className="flex gap-2.5 text-sm text-slate-200"
                                        >
                                            <Check
                                                className="mt-0.5 size-4 shrink-0 text-emerald-300"
                                                aria-hidden="true"
                                            />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                                <a
                                    href={contact}
                                    className="mt-auto inline-flex items-center gap-2 pt-8 text-sm font-semibold text-white hover:text-sky-200"
                                >
                                    {CTA_LABEL}
                                    <ArrowRight className="size-4" />
                                </a>
                            </article>
                        ))}
                    </div>
                    <p className="mt-8 max-w-3xl text-sm leading-6 text-slate-400">
                        Nenhuma empresa séria garante posição no Google: o
                        ranqueamento é decidido pelo buscador. Garantimos o
                        trabalho técnico, a transparência dos dados e o
                        acompanhamento.
                    </p>
                </Container>
            </section>

            <SeoIncluded />

            {/* Etapas da contratação */}
            <section className="border-y border-slate-200 bg-slate-50 py-20 sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="Como contratar"
                        title="Da solicitação ao acompanhamento."
                    />
                    <div className="mt-12">
                        <ProcessSteps steps={seoOffer.steps} />
                    </div>
                </Container>
            </section>

            {/* FAQ + serviços relacionados */}
            <section className="py-20 sm:py-24">
                <Container className="grid gap-16 lg:grid-cols-[1.3fr_0.7fr]">
                    <div>
                        <SectionHeading
                            eyebrow="Perguntas frequentes"
                            title="Dúvidas comuns sobre SEO."
                        />
                        <div className="mt-8">
                            <FaqList items={seoOffer.faq} />
                        </div>
                    </div>
                    <aside>
                        <h2 className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                            Desenvolvimento e integração
                        </h2>
                        <ul className="mt-4 divide-y divide-slate-200 border-y border-slate-200">
                            {related.map((item) => (
                                <li key={item.slug}>
                                    <Link
                                        href={`/${item.slug}`}
                                        className="group flex items-start justify-between gap-4 py-4"
                                    >
                                        <span>
                                            <span className="block font-semibold text-slate-900 group-hover:text-blue-800">
                                                {item.label}
                                            </span>
                                            <span className="mt-1 block text-sm leading-6 text-slate-600">
                                                {item.summary}
                                            </span>
                                        </span>
                                        <ArrowRight className="mt-1 size-4 shrink-0 text-slate-400" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </aside>
                </Container>
            </section>

            <CtaBand
                title="Quer saber o que impede seu site de aparecer?"
                description="Envie o endereço do site e o que você espera da busca orgânica. Respondemos com uma análise inicial e a modalidade indicada, sem compromisso."
                primary={{ label: CTA_LABEL, href: contact }}
                secondary={{
                    label: 'Criar um site novo',
                    href: '/desenvolvimento-de-sites-para-empresas',
                }}
            />
        </SiteLayout>
    );
}
