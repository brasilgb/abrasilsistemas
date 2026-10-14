import {
    ButtonLink,
    Container,
    CtaBand,
    PageHero,
    ProcessSteps,
    SectionHeading,
} from '@/components/site/blocks';
import { SiteLayout } from '@/components/site/site-layout';
import {
    contactHref,
    processSteps,
    products,
    technologyGroups,
} from '@/data/site';

const principles = [
    {
        title: 'Processo antes da tecnologia',
        description:
            'Começamos entendendo como a operação funciona, quem participa e onde estão as perdas. A tecnologia vem depois, escolhida para resolver aquele problema.',
    },
    {
        title: 'Produto e projeto',
        description: `Mantemos produtos próprios em operação — ${products.map((product) => product.name).join(' e ')} — e aplicamos a mesma engenharia em projetos sob medida para outras empresas.`,
    },
    {
        title: 'Proximidade com o cliente',
        description:
            'Quem entende o seu processo é quem desenvolve o sistema. A comunicação é direta, sem intermediários e sem central de atendimento automatizada.',
    },
    {
        title: 'Relacionamento de longo prazo',
        description:
            'Um sistema não termina na entrega. Acompanhamos o uso, mantemos, corrigimos e evoluímos as soluções conforme a empresa cresce.',
    },
] as const;

export default function Sobre() {
    return (
        <SiteLayout>
            <PageHero
                eyebrow="Sobre a ABrasil Sistemas"
                title="Uma empresa de tecnologia feita para durar junto com os clientes."
                lead="A ABrasil Sistemas desenvolve software para empresas brasileiras: produtos próprios, sistemas sob medida, aplicativos, integrações, automações e sites. Nosso trabalho é transformar processos em soluções digitais que funcionam no dia a dia — e continuar cuidando delas depois."
                actions={
                    <>
                        <ButtonLink href="/servicos" variant="inverse" arrow>
                            Conhecer soluções
                        </ButtonLink>
                        <ButtonLink href={contactHref()} variant="ghostInverse">
                            Falar com a equipe
                        </ButtonLink>
                    </>
                }
            />

            <section className="py-20 sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="Como pensamos"
                        title="O que orienta o nosso trabalho."
                    />
                    <div className="mt-12 grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 md:grid-cols-2">
                        {principles.map((principle) => (
                            <div
                                key={principle.title}
                                className="bg-white p-6 sm:p-8"
                            >
                                <h3 className="text-lg font-semibold text-slate-900">
                                    {principle.title}
                                </h3>
                                <p className="mt-2 text-[15px] leading-7 text-slate-600">
                                    {principle.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </Container>
            </section>

            <section className="bg-[#0b1220] py-20 text-white sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="Método"
                        title="Do entendimento à evolução contínua."
                        inverse
                    />
                    <div className="mt-12">
                        <ProcessSteps steps={processSteps} inverse />
                    </div>
                </Container>
            </section>

            <section className="py-20 sm:py-24">
                <Container className="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">
                    <SectionHeading
                        eyebrow="Capacidade técnica"
                        title="Do código à infraestrutura."
                        description="Desenvolvemos, publicamos e operamos as nossas próprias soluções: aplicação web, aplicativo, banco de dados, integrações, automações e servidores. É essa experiência que levamos para cada projeto."
                    />
                    <dl className="grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2">
                        {technologyGroups.map((group) => (
                            <div
                                key={group.title}
                                className="bg-white p-5 sm:last:odd:col-span-2"
                            >
                                <dt className="text-sm font-semibold text-slate-900">
                                    {group.title}
                                </dt>
                                <dd className="mt-2 text-sm leading-6 text-slate-600">
                                    {group.items.join(' · ')}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Container>
            </section>

            <CtaBand
                title="Tem um processo que precisa funcionar melhor?"
                description="Atendemos empresas de todo o Brasil. Conte o que você precisa e conversamos sobre o caminho."
                primary={{
                    label: 'Falar sobre meu projeto',
                    href: contactHref(),
                }}
                secondary={{ label: 'Ver sistemas prontos', href: '/sistemas' }}
            />
        </SiteLayout>
    );
}
