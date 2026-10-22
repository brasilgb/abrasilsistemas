import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import {
    ButtonLink,
    Container,
    CtaBand,
    FlowDiagram,
    PageHero,
    ProcessSteps,
    SectionHeading,
} from '@/components/site/blocks';
import { SeoIncluded } from '@/components/site/seo-included';
import { SiteLayout } from '@/components/site/site-layout';
import { ServiceVisual } from '@/components/site/visuals';
import { contactHref, processSteps, products, services } from '@/data/site';

export default function Servicos() {
    return (
        <SiteLayout>
            <PageHero
                eyebrow="Soluções"
                title="Da necessidade da empresa à solução em produção."
                lead="Sistemas, aplicativos, automações, integrações, sites, landing pages e SEO — desenvolvidos a partir do problema que a empresa precisa resolver e conectados entre si."
                visual={<ServiceVisual kind="integration" />}
                actions={
                    <ButtonLink href={contactHref()} variant="inverse" arrow>
                        Falar sobre meu projeto
                    </ButtonLink>
                }
            />

            <section className="py-20 sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="O que fazemos"
                        title="Escolha pelo problema, não pela tecnologia."
                        description="Muitas vezes a solução combina mais de uma frente — um sistema com aplicativo, uma landing page conectada ao CRM, uma automação em cima de uma integração."
                    />
                    <ul className="mt-12 divide-y divide-slate-200 border-y border-slate-200">
                        {services.map((service) => (
                            <li key={service.slug}>
                                <Link
                                    href={`/${service.slug}`}
                                    className="group grid gap-3 py-7 md:grid-cols-[16rem_1fr_auto] md:items-center md:gap-10"
                                >
                                    <span className="text-xl font-semibold text-slate-900 group-hover:text-blue-800">
                                        {service.label}
                                    </span>
                                    <span className="text-[15px] leading-7 text-slate-600">
                                        {service.summary}
                                    </span>
                                    <span className="inline-flex items-center gap-2 text-sm font-semibold text-blue-700">
                                        Saiba mais{' '}
                                        <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                    </span>
                                </Link>
                            </li>
                        ))}
                        <li>
                            <Link
                                href="/sistemas"
                                className="group grid gap-3 py-7 md:grid-cols-[16rem_1fr_auto] md:items-center md:gap-10"
                            >
                                <span className="text-xl font-semibold text-slate-900 group-hover:text-blue-800">
                                    Sistemas prontos
                                </span>
                                <span className="text-[15px] leading-7 text-slate-600">
                                    Produtos próprios para segmentos
                                    específicos:{' '}
                                    {products
                                        .map((product) => product.name)
                                        .join(' e ')}
                                    .
                                </span>
                                <span className="inline-flex items-center gap-2 text-sm font-semibold text-blue-700">
                                    Conhecer{' '}
                                    <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                </span>
                            </Link>
                        </li>
                    </ul>
                </Container>
            </section>

            <section className="border-y border-slate-200 bg-slate-50 py-20 sm:py-24">
                <Container className="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">
                    <SectionHeading
                        eyebrow="Tudo conectado"
                        title="Soluções que conversam entre si."
                        description="O valor aparece quando cada parte alimenta a seguinte: o contato que chega pelo site vira lead no CRM, que dispara o follow-up, que chega ao vendedor."
                    />
                    <div className="grid content-start gap-4">
                        <FlowDiagram
                            title="Do site à venda"
                            steps={[
                                'Site',
                                'Lead',
                                'CRM',
                                'Automação',
                                'Vendedor',
                            ]}
                        />
                        <FlowDiagram
                            title="Operação integrada"
                            steps={[
                                'ERP',
                                'Sistema',
                                'Aplicativo',
                                'WhatsApp',
                                'Financeiro',
                            ]}
                        />
                    </div>
                </Container>
            </section>

            <SeoIncluded />

            <section className="bg-[#0b1220] py-20 text-white sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="Como trabalhamos"
                        title="O mesmo método em todos os projetos."
                        inverse
                    />
                    <div className="mt-12">
                        <ProcessSteps steps={processSteps} inverse />
                    </div>
                </Container>
            </section>

            <CtaBand
                title="Não sabe por onde começar?"
                description="Descreva a situação da sua empresa. Indicamos o caminho mais direto — produto pronto, projeto sob medida ou uma combinação dos dois."
                primary={{
                    label: 'Falar sobre meu projeto',
                    href: contactHref(),
                }}
            />
        </SiteLayout>
    );
}
