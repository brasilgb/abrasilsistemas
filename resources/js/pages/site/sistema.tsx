import { Link } from '@inertiajs/react';
import { ArrowLeft, Check, X } from 'lucide-react';
import {
    ButtonLink,
    Container,
    Eyebrow,
    FaqList,
    HeroBackdrop,
    SectionHeading,
} from '@/components/site/blocks';
import { ContactSection } from '@/components/site/contact-form';
import { ProductStatusBadge } from '@/components/site/product-status';
import { SiteLayout } from '@/components/site/site-layout';
import { BrowserFrame } from '@/components/site/visuals';
import { heroImages } from '@/data/hero-images';
import { findProduct, productStatuses } from '@/data/site';

export default function SistemaPage({
    slug,
    projectTypes,
}: {
    slug: string;
    projectTypes: Record<string, string>;
}) {
    const product = findProduct(slug);

    if (!product) {
        return null;
    }

    return (
        <SiteLayout>
            <section className="relative isolate overflow-hidden bg-brand-navy pt-28 pb-16 text-white sm:pt-32 sm:pb-20">
                <HeroBackdrop image={heroImages[product.heroImage]} />
                <Container>
                    <Link
                        href="/sistemas"
                        className="inline-flex items-center gap-2 text-sm text-slate-400 transition hover:text-white"
                    >
                        <ArrowLeft className="size-4" /> Sistemas prontos
                    </Link>
                    <div
                        className={`mt-8 grid items-center gap-12 ${product.screenshot ? 'lg:grid-cols-[0.9fr_1.1fr]' : ''}`}
                    >
                        <div className="max-w-2xl">
                            <div className="flex items-center gap-4">
                                <img
                                    src={product.logo}
                                    alt=""
                                    width={56}
                                    height={56}
                                    className={`size-14 shrink-0 ${product.logoClass}`}
                                />
                                <div className="grid gap-2">
                                    <Eyebrow inverse>{product.segment}</Eyebrow>
                                    <ProductStatusBadge
                                        status={product.status}
                                    />
                                </div>
                            </div>
                            <h1 className="mt-6 text-4xl leading-[1.1] font-semibold tracking-tight text-balance sm:text-5xl">
                                {product.name}:{' '}
                                {product.tagline.charAt(0).toLowerCase() +
                                    product.tagline.slice(1)}
                            </h1>
                            <p className="mt-6 text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                                {product.problem}
                            </p>
                            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                <ButtonLink
                                    href="#orcamento"
                                    variant="inverse"
                                    arrow
                                >
                                    Solicitar demonstração
                                </ButtonLink>
                                <ButtonLink
                                    href={
                                        product.signupUrl ?? product.externalUrl
                                    }
                                    variant="ghostInverse"
                                >
                                    {product.secondaryCtaLabel ??
                                        'Criar conta e testar grátis'}
                                </ButtonLink>
                            </div>
                        </div>
                        {product.screenshot && (
                            <BrowserFrame
                                {...product.screenshot}
                                label={`${product.name} · painel`}
                            />
                        )}
                    </div>
                </Container>
            </section>

            <section className="py-20 sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="Antes e depois"
                        title={`O que muda com o ${product.name}.`}
                    />
                    <div className="mt-10 grid gap-6 md:grid-cols-2">
                        <div className="rounded-lg border border-slate-200 p-6 sm:p-8">
                            <h3 className="text-sm font-semibold tracking-wider text-slate-500 uppercase">
                                Sem sistema
                            </h3>
                            <ul className="mt-5 grid gap-3">
                                {product.pains.map((pain) => (
                                    <li
                                        key={pain}
                                        className="flex gap-3 text-[15px] leading-7 text-slate-600"
                                    >
                                        <X className="mt-1 size-4 shrink-0 text-slate-400" />
                                        {pain}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-6 sm:p-8">
                            <h3 className="text-sm font-semibold tracking-wider text-brand-blue-hover uppercase">
                                Com o {product.name}
                            </h3>
                            <ul className="mt-5 grid gap-3">
                                {product.outcomes.map((outcome) => (
                                    <li
                                        key={outcome}
                                        className="flex gap-3 text-[15px] leading-7 text-slate-800"
                                    >
                                        <Check className="mt-1 size-4 shrink-0 text-brand-blue" />
                                        {outcome}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </Container>
            </section>

            <section className="border-y border-slate-200 bg-slate-50 py-20 sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="Funcionalidades"
                        title="O que o sistema faz."
                    />
                    <div className="mt-10 grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-3">
                        {product.features.map((feature) => (
                            <div key={feature.title} className="bg-white p-6">
                                <h3 className="text-base font-semibold text-slate-900">
                                    {feature.title}
                                </h3>
                                <p className="mt-2 text-[15px] leading-7 text-slate-600">
                                    {feature.description}
                                </p>
                            </div>
                        ))}
                    </div>
                    {product.inDevelopment && (
                        <div className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-6">
                            <h3 className="text-sm font-semibold text-amber-900">
                                Em desenvolvimento, ainda não disponível
                            </h3>
                            <p className="mt-2 text-sm leading-6 text-amber-900">
                                {product.inDevelopment.join(' · ')}
                            </p>
                        </div>
                    )}
                    <p className="mt-10 text-sm leading-6 text-slate-600">
                        <strong className="font-semibold text-slate-900">
                            Situação: {productStatuses[product.status].label}.
                        </strong>{' '}
                        {productStatuses[product.status].description}
                    </p>
                    <dl className="mt-6 grid gap-8 text-sm md:grid-cols-3">
                        <div>
                            <dt className="font-semibold text-slate-900">
                                Plataformas
                            </dt>
                            <dd className="mt-2 leading-6 text-slate-600">
                                {product.platforms.join(' · ')}
                            </dd>
                        </div>
                        <div>
                            <dt className="font-semibold text-slate-900">
                                Modelo de implantação
                            </dt>
                            <dd className="mt-2 leading-6 text-slate-600">
                                {product.deployment}
                            </dd>
                        </div>
                        <div>
                            <dt className="font-semibold text-slate-900">
                                Integrações
                            </dt>
                            <dd className="mt-2 leading-6 text-slate-600">
                                {product.integrations.join(' · ')}
                            </dd>
                        </div>
                    </dl>
                </Container>
            </section>

            <section className="py-20 sm:py-24">
                <Container>
                    <div className="max-w-3xl">
                        <SectionHeading
                            eyebrow="Perguntas frequentes"
                            title={`Sobre o ${product.name}.`}
                        />
                        <div className="mt-8">
                            <FaqList items={product.faq} />
                        </div>
                        <p className="mt-8 text-[15px] leading-7 text-slate-600">
                            Precisa de algo além do {product.name}? Também
                            fazemos{' '}
                            <Link
                                href="/desenvolvimento-de-sistemas"
                                className="font-semibold text-brand-blue underline underline-offset-4"
                            >
                                sistemas sob medida
                            </Link>{' '}
                            e{' '}
                            <Link
                                href="/integracao-de-sistemas-e-apis"
                                className="font-semibold text-brand-blue underline underline-offset-4"
                            >
                                integrações com o que você já usa
                            </Link>
                            .
                        </p>
                    </div>
                </Container>
            </section>

            <ContactSection
                key={product.slug}
                title={`Veja o ${product.name} na rotina da sua empresa.`}
                description={
                    product.freeTrial
                        ? 'Peça uma demonstração com a equipe ou crie sua conta no site do produto e teste por conta própria.'
                        : 'Peça uma demonstração: a equipe apresenta o sistema a partir da sua rotina.'
                }
                projectTypes={projectTypes}
                selectedType={product.contactType}
                placeholder="Conte como a sua equipe trabalha hoje e o que você quer ver na demonstração."
                submitLabel="Solicitar demonstração"
            />
        </SiteLayout>
    );
}
