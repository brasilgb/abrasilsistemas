import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, Quote } from 'lucide-react';
import {
    ButtonLink,
    Container,
    FaqList,
    HeroBackdrop,
    ProcessSteps,
    SectionHeading,
} from '@/components/site/blocks';
import { ContactSection } from '@/components/site/contact-form';
import { ProductStatusBadge } from '@/components/site/product-status';
import { serviceIcons } from '@/components/site/service-icons';
import { SiteLayout } from '@/components/site/site-layout';
import { SolutionsSlider } from '@/components/site/solutions-slider';
import { heroImages } from '@/data/hero-images';
import {
    caseStudies,
    contactHref,
    differentials,
    homeFaq,
    processSteps,
    products,
    services,
    technologyGroups,
} from '@/data/site';
import { useContact } from '@/lib/contact';

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

export default function Welcome({
    blogPosts,
    testimonials,
    portfolioItems,
    projectTypes,
}: {
    blogPosts: BlogPostSummary[];
    testimonials: TestimonialSummary[];
    portfolioItems: PortfolioItemSummary[];
    projectTypes: Record<string, string>;
}) {
    const contact = useContact();

    return (
        <SiteLayout>
            {/* Hero */}
            <section className="relative isolate overflow-hidden bg-brand-navy pt-28 pb-16 text-white sm:pt-36 sm:pb-24 lg:pb-28">
                <HeroBackdrop image={heroImages.home} />
                <Container>
                    <div className="max-w-2xl">
                        <h1 className="text-4xl leading-[1.08] font-semibold tracking-tight text-balance sm:text-6xl">
                            Tecnologia que transforma ideias em resultados.
                        </h1>
                        <p className="mt-6 text-base leading-7 text-slate-200 sm:text-lg sm:leading-8">
                            Desenvolvemos sites profissionais, sistemas sob
                            medida e soluções digitais que ajudam empresas a
                            organizar processos, ganhar produtividade e
                            conquistar novas oportunidades.
                        </p>
                        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                            <ButtonLink
                                href="#orcamento"
                                variant="inverse"
                                arrow
                            >
                                Solicitar orçamento
                            </ButtonLink>
                            <ButtonLink href="#servicos" variant="ghostInverse">
                                Conhecer nossos serviços
                            </ButtonLink>
                        </div>
                    </div>
                </Container>
            </section>

            {/* Serviços */}
            <section id="servicos" className="scroll-mt-16 py-20 sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="Serviços"
                        title="O que podemos fazer pela sua empresa."
                        description="Escolha pelo problema que precisa resolver. Se a solução envolver mais de uma frente, cuidamos do conjunto."
                    />
                    <ul className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {services.map((service) => {
                            const Icon = serviceIcons[service.visual];

                            return (
                                <li key={service.slug}>
                                    <Link
                                        href={`/${service.slug}`}
                                        className="group flex h-full flex-col rounded-lg border border-slate-200 bg-white p-6 transition hover:border-blue-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-blue"
                                    >
                                        <span className="grid size-11 place-items-center rounded-md bg-blue-50 text-brand-blue">
                                            <Icon
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <h3 className="mt-5 text-lg font-semibold text-slate-900 group-hover:text-brand-blue-hover">
                                            {service.label}
                                        </h3>
                                        <p className="mt-2 text-[15px] leading-6 text-slate-600">
                                            {service.summary}
                                        </p>
                                        <span className="mt-auto inline-flex items-center gap-2 pt-5 text-sm font-semibold text-brand-blue">
                                            Saiba mais
                                            <ArrowRight
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </span>
                                    </Link>
                                </li>
                            );
                        })}
                        <li>
                            <a
                                href="#orcamento"
                                className="flex h-full flex-col rounded-lg bg-brand-navy p-6 text-white transition hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-blue"
                            >
                                <h3 className="text-lg font-semibold">
                                    Não sabe por onde começar?
                                </h3>
                                <p className="mt-2 text-[15px] leading-6 text-slate-300">
                                    Descreva a situação da empresa e indicamos o
                                    caminho mais direto.
                                </p>
                                <span className="mt-auto inline-flex items-center gap-2 pt-5 text-sm font-semibold">
                                    Solicitar orçamento
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                            </a>
                        </li>
                    </ul>
                </Container>
            </section>

            <SolutionsSlider />

            {/* Diferenciais */}
            <section className="py-20 sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="Diferenciais"
                        title="Engenharia de software a serviço da operação."
                        description="Primeiro entendemos como a empresa funciona; depois escolhemos a tecnologia certa para resolver."
                    />
                    <dl className="mt-12 grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                        {differentials.map((item) => (
                            <div
                                key={item.title}
                                className="border-t border-slate-200 pt-5"
                            >
                                <dt className="text-lg font-semibold text-slate-900">
                                    {item.title}
                                </dt>
                                <dd className="mt-2 text-[15px] leading-7 text-slate-600">
                                    {item.description}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Container>
            </section>
            {/* Processo */}
            <section className="bg-brand-navy py-20 text-white sm:py-28">
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

            {/* Sistemas prontos */}
            <section className="border-b border-slate-200 bg-slate-50 py-20 sm:py-24">
                <Container>
                    <div className="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                        <SectionHeading
                            eyebrow="Soluções próprias"
                            title="Sistemas criados e mantidos pela ABrasil."
                            description="Cada produto mostra o estágio em que está, para você saber o que já pode usar."
                        />
                        <Link
                            href="/sistemas"
                            className="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-brand-blue hover:text-brand-blue-hover"
                        >
                            Ver todos os sistemas{' '}
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                    <div className="mt-12 grid gap-6 md:grid-cols-2">
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
                                        <div className="mt-2">
                                            <ProductStatusBadge
                                                status={product.status}
                                            />
                                        </div>
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
                                        <p className="text-sm font-medium text-brand-blue">
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
                                                    className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-blue"
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
                                className="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-brand-blue"
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
                                    <p className="text-xs font-semibold tracking-wider text-brand-blue uppercase">
                                        {post.category?.name ?? 'Artigo'}
                                    </p>
                                    <h3 className="mt-3 text-lg leading-snug font-semibold text-slate-900 group-hover:text-brand-blue-hover">
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

            {/* Perguntas frequentes */}
            <section className="border-t border-slate-200 py-20 sm:py-24">
                <Container className="grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">
                    <SectionHeading
                        eyebrow="Perguntas frequentes"
                        title="Antes de pedir um orçamento."
                    />
                    <FaqList items={homeFaq} />
                </Container>
            </section>

            <ContactSection
                title="Conte o que a sua empresa precisa."
                description={`Sua mensagem chega direto à equipe que desenvolve as soluções. Se preferir, escreva para ${contact.email}.`}
                projectTypes={projectTypes}
            />
        </SiteLayout>
    );
}
