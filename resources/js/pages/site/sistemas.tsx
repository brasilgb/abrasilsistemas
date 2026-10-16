import {
    ButtonLink,
    Container,
    CtaBand,
    PageHero,
    SectionHeading,
} from '@/components/site/blocks';
import { SiteLayout } from '@/components/site/site-layout';
import { BrowserFrame } from '@/components/site/visuals';
import { heroImages } from '@/data/hero-images';
import { contactHref, products } from '@/data/site';
import type { Product } from '@/data/site';

export default function Sistemas() {
    const vetorOs = products[0];

    return (
        <SiteLayout>
            <PageHero
                eyebrow="Sistemas prontos"
                title="Produtos de software criados e mantidos pela ABrasil."
                lead="Sistemas completos para segmentos específicos, que nasceram de operações reais e seguem em evolução constante. Contratação online, sem instalação e com teste gratuito."
                image={heroImages.sistemas}
                visual={
                    vetorOs.screenshot ? (
                        <BrowserFrame
                            {...vetorOs.screenshot}
                            label="VetorOS · painel"
                        />
                    ) : undefined
                }
                actions={
                    <>
                        <ButtonLink href="#vetoros" variant="inverse" arrow>
                            Ver os sistemas
                        </ButtonLink>
                        <ButtonLink
                            href={contactHref('vetoros')}
                            variant="ghostInverse"
                        >
                            Solicitar demonstração
                        </ButtonLink>
                    </>
                }
            />

            {products.map((product, index) => (
                <ProductSection
                    key={product.slug}
                    product={product}
                    shaded={index % 2 === 1}
                />
            ))}

            <section className="border-t border-slate-200 py-20 sm:py-24">
                <Container>
                    <SectionHeading
                        eyebrow="Não encontrou o que precisa?"
                        title="Também desenvolvemos o sistema da sua empresa."
                        description="Os nossos produtos nasceram do mesmo método que usamos em projetos sob medida: entender o processo, construir em etapas e manter o sistema evoluindo."
                    />
                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        <ButtonLink
                            href="/desenvolvimento-de-sistemas"
                            variant="primary"
                            arrow
                        >
                            Sistemas sob medida
                        </ButtonLink>
                        <ButtonLink href="/servicos" variant="secondary">
                            Todas as soluções
                        </ButtonLink>
                    </div>
                </Container>
            </section>

            <CtaBand
                title="Quer ver um sistema funcionando na rotina da sua empresa?"
                description="Agende uma demonstração. Mostramos o sistema a partir do seu processo, não de um roteiro genérico."
                primary={{
                    label: 'Solicitar demonstração',
                    href: contactHref(),
                }}
            />
        </SiteLayout>
    );
}

function ProductSection({
    product,
    shaded,
}: {
    product: Product;
    shaded: boolean;
}) {
    return (
        <section
            id={product.slug}
            className={`scroll-mt-20 py-20 sm:py-24 ${shaded ? 'border-y border-slate-200 bg-slate-50' : ''}`}
        >
            <Container className="grid gap-12 lg:grid-cols-[1fr_1fr] lg:gap-16">
                <div>
                    <div className="flex items-center gap-4">
                        <img
                            src={product.logo}
                            alt=""
                            width={56}
                            height={56}
                            loading="lazy"
                            className={`size-14 shrink-0 ${product.logoClass}`}
                        />
                        <div>
                            <h2 className="text-3xl font-semibold tracking-tight text-slate-900">
                                {product.name}
                            </h2>
                            <p
                                className={`text-sm font-medium ${product.accent}`}
                            >
                                {product.segment}
                            </p>
                        </div>
                    </div>
                    <p className="mt-6 text-lg leading-8 text-slate-900">
                        {product.tagline}
                    </p>
                    <p className="mt-3 text-[15px] leading-7 text-slate-600">
                        {product.problem}
                    </p>
                    <dl className="mt-8 grid gap-5 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="font-semibold text-slate-900">
                                Plataformas
                            </dt>
                            <dd className="mt-1 leading-6 text-slate-600">
                                {product.platforms.join(' · ')}
                            </dd>
                        </div>
                        <div>
                            <dt className="font-semibold text-slate-900">
                                Implantação
                            </dt>
                            <dd className="mt-1 leading-6 text-slate-600">
                                {product.deployment}
                            </dd>
                        </div>
                    </dl>
                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        <ButtonLink
                            href={`/sistemas/${product.slug}`}
                            variant="primary"
                            arrow
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
                </div>
                <div className="grid content-start gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2">
                    {product.features.map((feature) => (
                        <div key={feature.title} className="bg-white p-5">
                            <h3 className="text-sm font-semibold text-slate-900">
                                {feature.title}
                            </h3>
                            <p className="mt-1.5 text-sm leading-6 text-slate-600">
                                {feature.description}
                            </p>
                        </div>
                    ))}
                </div>
            </Container>
        </section>
    );
}
