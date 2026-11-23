import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    ButtonLink,
    Container,
    SectionHeading,
} from '@/components/site/blocks';
import { heroImages, heroImageSrcSet } from '@/data/hero-images';
import type { HeroImageKey } from '@/data/hero-images';
import { productStatuses, products } from '@/data/site';

type Slide = {
    id: string;
    eyebrow: string;
    title: string;
    description: string;
    href: string;
    cta: string;
    screenshot?: { src: string; alt: string; width: number; height: number };
    photo: HeroImageKey;
};

const slides: Slide[] = [
    ...products.map((product) => ({
        id: product.slug,
        eyebrow: `${product.name} · ${productStatuses[product.status].label}`,
        title: product.tagline,
        description: `Para ${product.segment.toLowerCase()}. ${product.outcomes.slice(0, 2).join('. ')}.`,
        href: `/sistemas/${product.slug}`,
        cta: `Conhecer o ${product.name}`,
        screenshot: product.screenshot,
        photo: product.heroImage,
    })),
    {
        id: 'sob-medida',
        eyebrow: 'Serviço · Sistemas sob medida',
        title: 'Um sistema que acompanha a sua operação.',
        description:
            'Software construído a partir dos processos e das necessidades da sua empresa.',
        href: '/desenvolvimento-de-sistemas',
        cta: 'Conhecer o serviço',
        photo: 'sistemas-sob-medida',
    },
    {
        id: 'sites',
        eyebrow: 'Serviço · Sites e landing pages',
        title: 'Presença digital pronta para gerar contatos.',
        description:
            'Sites e páginas de campanha rápidos, claros e com formulário ligado ao seu atendimento.',
        href: '/desenvolvimento-de-sites-para-empresas',
        cta: 'Conhecer o serviço',
        photo: 'sites',
    },
    {
        id: 'automacao',
        eyebrow: 'Serviço · Automação e integrações',
        title: 'Menos tarefas repetidas, sistemas conversando.',
        description:
            'Rotinas automáticas e integrações por API para que a informação seja registrada uma única vez.',
        href: '/automacao-de-processos',
        cta: 'Conhecer o serviço',
        photo: 'automacao',
    },
];

/**
 * Vitrine discreta de produtos e serviços: um item por vez, imagem ao lado do texto (nunca atrás
 * dele) e troca só pelo visitante, sem rotação automática.
 */
export function SolutionsSlider() {
    const [active, setActive] = useState(0);
    const slide = slides[active];
    const select = (index: number) =>
        setActive((index + slides.length) % slides.length);
    const arrowClass =
        'grid size-11 place-items-center rounded-md border border-slate-300 bg-white text-slate-700 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-blue';

    return (
        <section
            aria-roledescription="carrossel"
            aria-label="Soluções e serviços em destaque"
            className="border-y border-slate-200 bg-slate-50 py-20 sm:py-24"
        >
            <Container>
                <div className="flex items-end justify-between gap-6">
                    <SectionHeading
                        eyebrow="Em destaque"
                        title="Conheça de perto o que fazemos."
                    />
                    <div className="hidden shrink-0 gap-2 sm:flex">
                        <button
                            type="button"
                            onClick={() => select(active - 1)}
                            aria-label="Destaque anterior"
                            className={arrowClass}
                        >
                            <ChevronLeft className="size-5" />
                        </button>
                        <button
                            type="button"
                            onClick={() => select(active + 1)}
                            aria-label="Próximo destaque"
                            className={arrowClass}
                        >
                            <ChevronRight className="size-5" />
                        </button>
                    </div>
                </div>

                <div
                    role="group"
                    aria-roledescription="slide"
                    aria-label={`${active + 1} de ${slides.length}`}
                    aria-live="polite"
                    className="mt-10 grid overflow-hidden rounded-lg border border-slate-200 bg-white lg:min-h-[26rem] lg:grid-cols-2"
                >
                    <div
                        key={`${slide.id}-img`}
                        className="flex aspect-[16/10] items-center bg-brand-navy motion-safe:animate-in motion-safe:duration-300 motion-safe:fade-in lg:aspect-auto"
                    >
                        {slide.screenshot ? (
                            <img
                                src={slide.screenshot.src}
                                alt={slide.screenshot.alt}
                                width={slide.screenshot.width}
                                height={slide.screenshot.height}
                                loading="lazy"
                                decoding="async"
                                className="h-full w-full object-contain p-4 sm:p-6"
                            />
                        ) : (
                            <picture className="block size-full">
                                <source
                                    type="image/avif"
                                    srcSet={heroImageSrcSet(
                                        slide.photo,
                                        'avif',
                                    )}
                                    sizes="(min-width: 1024px) 40rem, 100vw"
                                />
                                <img
                                    src={`/images/site/heroes/${slide.photo}-1280.webp`}
                                    alt={heroImages[slide.photo].alt}
                                    width={1280}
                                    height={720}
                                    loading="lazy"
                                    decoding="async"
                                    className="size-full object-cover"
                                    style={{
                                        objectPosition:
                                            heroImages[slide.photo].position,
                                    }}
                                />
                            </picture>
                        )}
                    </div>
                    <div className="flex flex-col justify-center p-6 sm:p-10">
                        <p className="text-xs font-semibold tracking-[0.12em] text-brand-blue uppercase">
                            {slide.eyebrow}
                        </p>
                        <h3 className="mt-3 text-2xl leading-tight font-semibold tracking-tight text-balance text-slate-900 sm:text-3xl">
                            {slide.title}
                        </h3>
                        <p className="mt-4 text-base leading-7 text-slate-600">
                            {slide.description}
                        </p>
                        <div className="mt-7">
                            <ButtonLink href={slide.href} arrow>
                                {slide.cta}
                            </ButtonLink>
                        </div>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-center">
                    {slides.map((item, index) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => select(index)}
                            aria-label={`Mostrar destaque ${index + 1}: ${item.eyebrow}`}
                            aria-current={active === index ? 'true' : undefined}
                            className="grid size-10 place-items-center rounded-md focus-visible:outline-2 focus-visible:outline-brand-blue"
                        >
                            <span
                                className={`block h-1.5 rounded-full transition-all ${active === index ? 'w-6 bg-brand-blue' : 'w-2 bg-slate-400'}`}
                            />
                        </button>
                    ))}
                </div>
            </Container>
        </section>
    );
}
