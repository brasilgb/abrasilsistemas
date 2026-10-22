import { Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import {
    ButtonLink,
    Container,
    CtaBand,
    FaqList,
    FlowDiagram,
    PageHero,
    ProcessSteps,
    SectionHeading,
} from '@/components/site/blocks';
import { SeoIncluded } from '@/components/site/seo-included';
import { SiteLayout } from '@/components/site/site-layout';
import { ServiceVisual } from '@/components/site/visuals';
import { heroImages } from '@/data/hero-images';
import { contactHref, findService, processSteps } from '@/data/site';
import type { Service } from '@/data/site';

export default function ServicePage({ slug }: { slug: string }) {
    const service = findService(slug);

    if (!service) {
        return null;
    }

    const related = service.related
        .map(findService)
        .filter((item): item is Service => item !== undefined);

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
                        <ButtonLink
                            href={contactHref(service.contactType)}
                            variant="inverse"
                            arrow
                        >
                            Falar sobre meu projeto
                        </ButtonLink>
                        <ButtonLink
                            href="#como-funciona"
                            variant="ghostInverse"
                        >
                            Como funciona
                        </ButtonLink>
                    </>
                }
            />

            {/* Problema */}
            <section className="py-20 sm:py-24">
                <Container className="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-20">
                    <SectionHeading
                        eyebrow="O ponto de partida"
                        title={service.problems.title}
                    />
                    <ul className="grid gap-x-8 sm:grid-cols-2">
                        {service.problems.items.map((item) => (
                            <li
                                key={item}
                                className="border-t border-slate-200 py-4 text-[15px] leading-7 text-slate-700"
                            >
                                {item}
                            </li>
                        ))}
                    </ul>
                </Container>
            </section>

            {/* O que entregamos */}
            <section className="border-y border-slate-200 bg-slate-50 py-20 sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="O que entregamos"
                        title={`${service.label}: o que podemos construir.`}
                    />
                    <div className="mt-12 grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-3">
                        {service.offerings.map((offering) => (
                            <div key={offering.title} className="bg-white p-6">
                                <h3 className="text-base font-semibold text-slate-900">
                                    {offering.title}
                                </h3>
                                <p className="mt-2 text-[15px] leading-7 text-slate-600">
                                    {offering.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </Container>
            </section>

            {/* Resultado + fluxos */}
            <section className="py-20 sm:py-24">
                <Container className="grid gap-12 lg:grid-cols-2 lg:gap-20">
                    <div>
                        <SectionHeading
                            eyebrow="Para a empresa"
                            title="O que muda na prática."
                        />
                        <ul className="mt-8 grid gap-4">
                            {service.outcomes.map((outcome) => (
                                <li
                                    key={outcome}
                                    className="flex gap-3 text-base leading-7 text-slate-800"
                                >
                                    <Check className="mt-1 size-5 shrink-0 text-blue-700" />
                                    {outcome}
                                </li>
                            ))}
                        </ul>
                    </div>
                    {service.flows && service.flows.length > 0 && (
                        <div className="grid content-start gap-4">
                            <p className="text-sm text-slate-500">
                                Exemplos de como a informação passa a circular:
                            </p>
                            {service.flows.map((flow) => (
                                <FlowDiagram
                                    key={flow.title}
                                    title={flow.title}
                                    steps={flow.steps}
                                />
                            ))}
                        </div>
                    )}
                </Container>
            </section>

            {service.seoIncluded && (
                <SeoIncluded className="border-y border-slate-200 bg-slate-50" />
            )}

            {/* Processo */}
            <section
                id="como-funciona"
                className="scroll-mt-20 bg-[#0b1220] py-20 text-white sm:py-24"
            >
                <Container>
                    <SectionHeading
                        eyebrow="Como funciona"
                        title="Primeiro entendemos o processo. Depois desenvolvemos a solução."
                        inverse
                    />
                    <div className="mt-12">
                        <ProcessSteps
                            steps={service.steps ?? processSteps}
                            inverse
                        />
                    </div>
                </Container>
            </section>

            {/* FAQ + relacionados */}
            <section className="py-20 sm:py-24">
                <Container className="grid gap-16 lg:grid-cols-[1.3fr_0.7fr]">
                    <div>
                        <SectionHeading
                            eyebrow="Perguntas frequentes"
                            title="Dúvidas comuns."
                        />
                        <div className="mt-8">
                            <FaqList items={service.faq} />
                        </div>
                    </div>
                    <aside>
                        <h2 className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                            Soluções relacionadas
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
                title="Vamos entender o seu processo?"
                description="Conte o que precisa ser resolvido. Respondemos com um diagnóstico inicial e os próximos passos, sem compromisso."
                primary={{
                    label: 'Falar sobre meu projeto',
                    href: contactHref(service.contactType),
                }}
                secondary={{ label: 'Ver sistemas prontos', href: '/sistemas' }}
            />
        </SiteLayout>
    );
}
