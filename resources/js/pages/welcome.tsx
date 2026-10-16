import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, Quote } from 'lucide-react';
import {
    ButtonLink,
    Container,
    CtaBand,
    Eyebrow,
    HeroBackdrop,
    ProcessSteps,
    SectionHeading,
} from '@/components/site/blocks';
import { SiteLayout } from '@/components/site/site-layout';
import {
    BrowserFrame,
    ConnectedOperationBadge,
} from '@/components/site/visuals';
import { heroImages } from '@/data/hero-images';
import {
    caseStudies,
    contactHref,
    processSteps,
    products,
    services,
    technologyGroups,
} from '@/data/site';
import { useContact, useSiteWhatsappUrl } from '@/lib/contact';

type BlogPostSummary = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    published_at: string;
    category?: { name: string; slug: string } | null;
};

type TestimonialSummary = {
    id: number;
    author_name: string;
    author_role: string | null;
    photo_url: string | null;
    quote: string;
};

type PortfolioItemSummary = {
    id: number;
    title: string;
    description: string;
    screenshot_url: string;
    site_url: string | null;
};

const pillars = [
    {
        title: 'Produtos próprios',
        description:
            'Desenvolvemos e mantemos sistemas prontos para segmentos específicos, em operação na nuvem.',
    },
    {
        title: 'Desenvolvimento sob medida',
        description:
            'Quando nenhuma ferramenta pronta atende, construímos o software a partir do processo da empresa.',
    },
    {
        title: 'Integração de ponta a ponta',
        description:
            'Sistemas, aplicativos, site, WhatsApp e financeiro conversando entre si, sem redigitação.',
    },
] as const;

const softwareSlugs = [
    'desenvolvimento-de-sistemas',
    'automacao-de-processos',
    'integracao-de-sistemas-e-apis',
    'desenvolvimento-de-aplicativos',
];

export default function Welcome({
    blogPosts,
    testimonials,
    portfolioItems,
}: {
    blogPosts: BlogPostSummary[];
    testimonials: TestimonialSummary[];
    portfolioItems: PortfolioItemSummary[];
}) {
    const contact = useContact();
    const whatsappUrl = useSiteWhatsappUrl(
        'Olá, quero conversar sobre um projeto com a ABrasil Sistemas.',
    );
    const vetorOs = products[0];
    const softwareServices = services.filter((service) =>
        softwareSlugs.includes(service.slug),
    );
    const presenceServices = services.filter(
        (service) => !softwareSlugs.includes(service.slug),
    );

    return (
        <SiteLayout>
            {/* Hero */}
            <section className="relative isolate overflow-hidden bg-[#0b1220] pt-28 pb-16 text-white sm:pt-36 sm:pb-24">
                <HeroBackdrop image={heroImages.home} />
                <Container className="grid items-center gap-12 lg:grid-cols-[0.95fr_1.05fr] lg:gap-16">
                    <div>
                        <Eyebrow inverse>
                            Empresa de tecnologia e desenvolvimento de software
                        </Eyebrow>
                        <h1 className="mt-5 text-4xl leading-[1.08] font-semibold tracking-tight text-balance sm:text-5xl lg:text-[3.5rem]">
                            Tecnologia que trabalha junto com a sua empresa.
                        </h1>
                        <p className="mt-6 max-w-xl text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                            Desenvolvemos sistemas, aplicativos, sites,
                            automações e integrações que transformam processos
                            manuais em operações digitais organizadas e
                            conectadas. E mantemos produtos próprios, prontos
                            para usar.
                        </p>
                        <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                            <ButtonLink
                                href="/servicos"
                                variant="inverse"
                                arrow
                            >
                                Conhecer soluções
                            </ButtonLink>
                            <ButtonLink
                                href={contactHref()}
                                variant="ghostInverse"
                            >
                                Falar sobre um projeto
                            </ButtonLink>
                        </div>
                    </div>
                    {vetorOs.screenshot && (
                        <figure>
                            <BrowserFrame
                                {...vetorOs.screenshot}
                                label="VetorOS · painel"
                            />
                            <figcaption className="mt-4 flex flex-col gap-3 text-xs text-slate-400">
                                <span>
                                    VetorOS, sistema próprio da ABrasil para
                                    assistências técnicas.
                                </span>
                                <ConnectedOperationBadge />
                            </figcaption>
                        </figure>
                    )}
                </Container>
            </section>

            {/* Quem somos */}
            <section className="py-20 sm:py-28">
                <Container className="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-20">
                    <SectionHeading
                        eyebrow="Quem somos"
                        title="Engenharia de software a serviço da operação."
                        description="A ABrasil Sistemas desenvolve soluções digitais para empresas. Primeiro entendemos como a operação funciona e onde ela perde tempo e dinheiro; depois escolhemos a tecnologia certa para resolver."
                    />
                    <div className="grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200">
                        {pillars.map((pillar) => (
                            <div
                                key={pillar.title}
                                className="bg-white p-6 sm:p-7"
                            >
                                <h3 className="text-lg font-semibold text-slate-900">
                                    {pillar.title}
                                </h3>
                                <p className="mt-2 text-[15px] leading-7 text-slate-600">
                                    {pillar.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </Container>
            </section>

            {/* Sistemas prontos */}
            <section className="border-y border-slate-200 bg-slate-50 py-20 sm:py-28">
                <Container>
                    <div className="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                        <SectionHeading
                            eyebrow="Sistemas prontos"
                            title="Produtos ABrasil em operação."
                            description="Sistemas desenvolvidos e mantidos pela nossa equipe para segmentos específicos. Contratação online e teste gratuito."
                        />
                        <Link
                            href="/sistemas"
                            className="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-800"
                        >
                            Ver todos os sistemas{' '}
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                    <div className="mt-12 grid gap-6 lg:grid-cols-2">
                        {products.map((product) => (
                            <article
                                key={product.slug}
                                className="flex flex-col rounded-lg border border-slate-200 bg-white p-6 sm:p-8"
                            >
                                <div className="flex items-center gap-4">
                                    <img
                                        src={product.logo}
                                        alt=""
                                        width={48}
                                        height={48}
                                        loading="lazy"
                                        className={`size-12 shrink-0 ${product.logoClass}`}
                                    />
                                    <div>
                                        <h3 className="text-xl font-semibold text-slate-900">
                                            {product.name}
                                        </h3>
                                        <p
                                            className={`text-sm font-medium ${product.accent}`}
                                        >
                                            {product.segment}
                                        </p>
                                    </div>
                                </div>
                                <p className="mt-6 text-[15px] leading-7 text-slate-600">
                                    {product.tagline}
                                </p>
                                <ul className="mt-5 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
                                    {product.features
                                        .slice(0, 4)
                                        .map((feature) => (
                                            <li
                                                key={feature.title}
                                                className="flex gap-2"
                                            >
                                                <span className="mt-2 size-1.5 shrink-0 rounded-full bg-slate-400" />
                                                {feature.title}
                                            </li>
                                        ))}
                                </ul>
                                <div className="mt-auto flex flex-col gap-3 pt-8 sm:flex-row">
                                    <ButtonLink
                                        href={`/sistemas/${product.slug}`}
                                        variant="primary"
                                    >
                                        Conhecer solução
                                    </ButtonLink>
                                    <ButtonLink
                                        href={contactHref(product.contactType)}
                                        variant="secondary"
                                    >
                                        Solicitar demonstração
                                    </ButtonLink>
                                </div>
                            </article>
                        ))}
                    </div>
                </Container>
            </section>

            {/* Soluções */}
            <section className="py-20 sm:py-28">
                <Container>
                    <SectionHeading
                        eyebrow="Soluções"
                        title="Do processo travado ao sistema funcionando."
                        description="Desenvolvimento sob medida para o que uma ferramenta pronta não resolve — sempre começando pelo problema e pelo resultado esperado."
                    />
                    <div className="mt-12 grid gap-12 lg:grid-cols-[1.4fr_1fr]">
                        <ServiceList
                            title="Software e processos"
                            items={softwareServices}
                        />
                        <ServiceList
                            title="Presença digital"
                            items={presenceServices}
                        />
                    </div>
                </Container>
            </section>

            {/* Processo */}
            <section className="bg-[#0b1220] py-20 text-white sm:py-28">
                <Container>
                    <SectionHeading
                        eyebrow="Como trabalhamos"
                        title="Um método claro, do entendimento à evolução."
                        description="Cada projeto passa pelas mesmas etapas. Você sabe o que está sendo feito, por quê, e o que vem depois."
                        inverse
                    />
                    <div className="mt-14">
                        <ProcessSteps steps={processSteps} inverse />
                    </div>
                </Container>
            </section>

            {/* Tecnologias */}
            <section className="py-20 sm:py-28">
                <Container className="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">
                    <SectionHeading
                        eyebrow="Tecnologia"
                        title="Ferramentas consolidadas, escolhidas pelo problema."
                        description="Usamos tecnologias maduras e amplamente adotadas, o que facilita a manutenção, a segurança e a evolução dos sistemas ao longo dos anos."
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

            {/* Projetos */}
            {(caseStudies.length > 0 || portfolioItems.length > 0) && (
                <section className="border-y border-slate-200 bg-slate-50 py-20 sm:py-28">
                    <Container>
                        <SectionHeading
                            eyebrow="Projetos"
                            title="Trabalhos entregues."
                            description="Alguns dos projetos que colocamos no ar."
                        />
                        {caseStudies.length > 0 && (
                            <div className="mt-12 grid gap-6 lg:grid-cols-2">
                                {caseStudies.map((study) => (
                                    <article
                                        key={study.slug}
                                        className="rounded-lg border border-slate-200 bg-white p-6 sm:p-8"
                                    >
                                        <p className="text-sm font-medium text-blue-700">
                                            {study.segment}
                                        </p>
                                        <h3 className="mt-1 text-xl font-semibold text-slate-900">
                                            {study.title}
                                        </h3>
                                        <dl className="mt-5 grid gap-4 text-[15px] leading-7">
                                            {(
                                                [
                                                    ['Problema', study.problem],
                                                    ['Solução', study.solution],
                                                    [
                                                        'Tecnologia',
                                                        study.technology.join(
                                                            ', ',
                                                        ),
                                                    ],
                                                    ['Resultado', study.result],
                                                ] as const
                                            ).map(([label, value]) => (
                                                <div key={label}>
                                                    <dt className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                                        {label}
                                                    </dt>
                                                    <dd className="text-slate-700">
                                                        {value}
                                                    </dd>
                                                </div>
                                            ))}
                                        </dl>
                                    </article>
                                ))}
                            </div>
                        )}
                        {portfolioItems.length > 0 && (
                            <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {portfolioItems.map((item) => (
                                    <article
                                        key={item.id}
                                        className="overflow-hidden rounded-lg border border-slate-200 bg-white"
                                    >
                                        <img
                                            src={item.screenshot_url}
                                            alt={`Captura de tela: ${item.title}`}
                                            loading="lazy"
                                            className="aspect-[16/10] w-full object-cover object-top"
                                        />
                                        <div className="p-5">
                                            <h3 className="text-base font-semibold text-slate-900">
                                                {item.title}
                                            </h3>
                                            <p className="mt-2 text-sm leading-6 text-slate-600">
                                                {item.description}
                                            </p>
                                            {item.site_url && (
                                                <a
                                                    href={item.site_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-blue-700"
                                                >
                                                    Visitar site{' '}
                                                    <ArrowUpRight className="size-3.5" />
                                                </a>
                                            )}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </Container>
                </section>
            )}

            {testimonials.length > 0 && (
                <section className="py-20 sm:py-28">
                    <Container>
                        <SectionHeading
                            eyebrow="Clientes"
                            title="Quem trabalha com a ABrasil."
                        />
                        <div className="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {testimonials.map((testimonial) => (
                                <figure
                                    key={testimonial.id}
                                    className="flex flex-col rounded-lg border border-slate-200 p-6"
                                >
                                    <Quote className="size-5 text-slate-300" />
                                    <blockquote className="mt-3 flex-1 text-[15px] leading-7 text-slate-700">
                                        “{testimonial.quote}”
                                    </blockquote>
                                    <figcaption className="mt-5 flex items-center gap-3 border-t border-slate-100 pt-4">
                                        {testimonial.photo_url ? (
                                            <img
                                                src={testimonial.photo_url}
                                                alt=""
                                                loading="lazy"
                                                className="size-10 rounded-full object-cover"
                                            />
                                        ) : (
                                            <span className="grid size-10 place-items-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600">
                                                {testimonial.author_name.charAt(
                                                    0,
                                                )}
                                            </span>
                                        )}
                                        <span>
                                            <span className="block text-sm font-semibold text-slate-900">
                                                {testimonial.author_name}
                                            </span>
                                            {testimonial.author_role && (
                                                <span className="block text-xs text-slate-500">
                                                    {testimonial.author_role}
                                                </span>
                                            )}
                                        </span>
                                    </figcaption>
                                </figure>
                            ))}
                        </div>
                    </Container>
                </section>
            )}

            {blogPosts.length > 0 && (
                <section className="border-t border-slate-200 py-20 sm:py-24">
                    <Container>
                        <div className="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                            <SectionHeading
                                eyebrow="Blog"
                                title="Tecnologia e gestão, sem complicação."
                            />
                            <Link
                                href="/blog"
                                className="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-blue-700"
                            >
                                Ver todos os artigos{' '}
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                        <div className="mt-10 grid gap-6 lg:grid-cols-3">
                            {blogPosts.map((post) => (
                                <Link
                                    key={post.id}
                                    href={`/blog/${post.slug}`}
                                    className="group rounded-lg border border-slate-200 p-6 transition hover:border-slate-300 hover:bg-slate-50"
                                >
                                    <p className="text-xs font-semibold tracking-wider text-blue-700 uppercase">
                                        {post.category?.name ?? 'Artigo'}
                                    </p>
                                    <h3 className="mt-3 text-lg leading-snug font-semibold text-slate-900 group-hover:text-blue-800">
                                        {post.title}
                                    </h3>
                                    {post.excerpt && (
                                        <p className="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">
                                            {post.excerpt}
                                        </p>
                                    )}
                                </Link>
                            ))}
                        </div>
                    </Container>
                </section>
            )}

            <CtaBand
                title="Vamos conversar sobre a operação da sua empresa?"
                description={`Conte o que precisa ser resolvido. Respondemos com um caminho claro — produto pronto ou projeto sob medida. Atendimento direto pelo WhatsApp ${contact.whatsappDisplay} ou por ${contact.email}.`}
                primary={{
                    label: 'Falar sobre meu projeto',
                    href: contactHref(),
                }}
                secondary={{ label: 'Chamar no WhatsApp', href: whatsappUrl }}
            />
        </SiteLayout>
    );
}

function ServiceList({
    title,
    items,
}: {
    title: string;
    items: typeof services;
}) {
    return (
        <div>
            <h3 className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                {title}
            </h3>
            <ul className="mt-4 divide-y divide-slate-200 border-y border-slate-200">
                {items.map((service) => (
                    <li key={service.slug}>
                        <Link
                            href={`/${service.slug}`}
                            className="group flex items-start justify-between gap-6 py-5"
                        >
                            <span>
                                <span className="block text-lg font-semibold text-slate-900 group-hover:text-blue-800">
                                    {service.label}
                                </span>
                                <span className="mt-1 block text-[15px] leading-7 text-slate-600">
                                    {service.summary}
                                </span>
                            </span>
                            <ArrowRight className="mt-1.5 size-5 shrink-0 text-slate-400 transition group-hover:translate-x-1 group-hover:text-blue-700" />
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
