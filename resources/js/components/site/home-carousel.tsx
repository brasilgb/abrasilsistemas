import { ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ButtonLink, Container } from '@/components/site/blocks';
import { heroImageSrcSet } from '@/data/hero-images';
import type { HeroImageKey } from '@/data/hero-images';
import { contactHref, products } from '@/data/site';

type Slide = {
    id: string;
    eyebrow: string;
    title: string;
    description: string;
    href: string;
    image: HeroImageKey;
    system?: boolean;
    contactType?: string;
};

/** Foto de pessoas operando cada sistema, no mesmo estilo dos outros destaques. */
const systemHeroes: Partial<Record<string, HeroImageKey>> = {
    vetoros: 'sistemas',
    vetorsales: 'vetorsales',
    vetorleads: 'vetorleads',
};

const slides: Slide[] = [
    ...products.map((product) => ({
        id: product.slug,
        eyebrow: `Sistema pronto · ${product.name}`,
        title: product.tagline,
        description:
            product.slug === 'vetoros'
                ? 'Ordens de serviço, clientes, estoque e financeiro reunidos para a rotina da assistência técnica.'
                : product.slug === 'vetorsales'
                  ? 'Clientes, pedidos e comissões organizados para representantes e equipes comerciais.'
                  : 'Prospecção, oportunidades e atendimento acompanhados em um funil comercial organizado.',
        href: `/sistemas/${product.slug}`,
        image: systemHeroes[product.slug] ?? product.heroImage,
        system: true,
        contactType: product.contactType,
    })),
    {
        id: 'sites',
        eyebrow: 'Desenvolvimento sob encomenda · Web',
        title: 'Sua presença digital pronta para gerar negócios.',
        description:
            'Sites institucionais, landing pages e lojas virtuais pensados para apresentar sua empresa e facilitar o contato.',
        href: '/desenvolvimento-de-sites-para-empresas',
        image: 'sites',
        contactType: 'site',
    },
    {
        id: 'aplicativos',
        eyebrow: 'Desenvolvimento sob encomenda · Aplicativos',
        title: 'Sua operação também na palma da mão.',
        description:
            'Aplicativos desenvolvidos para apoiar a equipe e conectar dados ao sistema da empresa.',
        href: '/desenvolvimento-de-aplicativos',
        image: 'aplicativos',
        contactType: 'aplicativo',
    },
    {
        id: 'sob-medida',
        eyebrow: 'Desenvolvimento sob encomenda · Software',
        title: 'Um sistema que acompanha a sua operação.',
        description:
            'Desenvolvemos sistemas conforme os processos e as necessidades da sua empresa.',
        href: '/desenvolvimento-de-sistemas',
        image: 'sistemas-sob-medida',
        contactType: 'sistema_sob_medida',
    },
    {
        id: 'automacao',
        eyebrow: 'Desenvolvimento sob encomenda · Automação',
        title: 'Menos tarefas repetidas. Mais tempo para a equipe.',
        description:
            'Automatizamos rotinas para que informações e avisos cheguem a quem precisa, no momento certo.',
        href: '/automacao-de-processos',
        image: 'automacao',
        contactType: 'automacao',
    },
    {
        id: 'integracoes',
        eyebrow: 'Desenvolvimento sob encomenda · Integração',
        title: 'Seus sistemas trabalhando juntos.',
        description:
            'Conectamos processos, sistemas e APIs para reduzir digitação repetida e tarefas manuais.',
        href: '/integracao-de-sistemas-e-apis',
        image: 'integracoes',
        contactType: 'integracao',
    },
];

export function HomeCarousel() {
    const [active, setActive] = useState(0);
    const [paused, setPaused] = useState(false);
    const [interacting, setInteracting] = useState(false);
    const [visible, setVisible] = useState(true);
    const [reducedMotion, setReducedMotion] = useState(false);
    const slide = slides[active];

    useEffect(() => {
        const preference = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const updatePreference = () => setReducedMotion(preference.matches);
        const updateVisibility = () => setVisible(!document.hidden);
        updatePreference();
        updateVisibility();
        preference.addEventListener('change', updatePreference);
        document.addEventListener('visibilitychange', updateVisibility);

        return () => {
            preference.removeEventListener('change', updatePreference);
            document.removeEventListener('visibilitychange', updateVisibility);
        };
    }, []);

    useEffect(() => {
        if (paused || interacting || !visible || reducedMotion) {
            return;
        }

        const timer = window.setTimeout(
            () => setActive((current) => (current + 1) % slides.length),
            8000,
        );

        return () => window.clearTimeout(timer);
    }, [active, paused, interacting, visible, reducedMotion]);

    const select = (index: number) =>
        setActive((index + slides.length) % slides.length);

    return (
        <section
            aria-label="Destaques da ABrasil Sistemas"
            aria-roledescription="carrossel"
            className="relative isolate overflow-hidden bg-[#0b1220] pt-28 pb-10 text-white sm:pt-32 sm:pb-14"
            onMouseEnter={() => setInteracting(true)}
            onMouseLeave={() => setInteracting(false)}
            onFocusCapture={() => setInteracting(true)}
            onBlurCapture={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget)) {
                    setInteracting(false);
                }
            }}
            onKeyDown={(event) => {
                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    select(active + 1);
                }

                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    select(active - 1);
                }

                if (event.key === 'Home') {
                    event.preventDefault();
                    select(0);
                }

                if (event.key === 'End') {
                    event.preventDefault();
                    select(slides.length - 1);
                }
            }}
        >
            <div
                key={slide.id}
                className="absolute inset-0 -z-10 motion-safe:animate-in motion-safe:duration-500 motion-safe:fade-in"
            >
                <picture className="absolute inset-0 block">
                    <source
                        type="image/avif"
                        srcSet={heroImageSrcSet(slide.image, 'avif')}
                        sizes="(min-width: 1024px) 80vw, 1000px"
                    />
                    <source
                        type="image/webp"
                        srcSet={heroImageSrcSet(slide.image, 'webp')}
                        sizes="(min-width: 1024px) 80vw, 1000px"
                    />
                    <img
                        src={`/images/site/heroes/${slide.image}-1280.webp`}
                        alt=""
                        width={1920}
                        height={1080}
                        loading={active === 0 ? 'eager' : 'lazy'}
                        fetchPriority={active === 0 ? 'high' : 'auto'}
                        decoding="async"
                        className="absolute top-0 right-0 h-full w-auto max-w-none"
                    />
                </picture>
                <div className="absolute inset-0 bg-[#0b1220]/90 sm:bg-[#0b1220]/85 lg:bg-transparent lg:bg-[linear-gradient(to_right,#0b1220_0%,#0b1220_58%,rgba(11,18,32,0.7)_72%,rgba(11,18,32,0)_90%)]" />
                <div className="absolute inset-x-0 bottom-0 h-24 bg-linear-to-t from-[#0b1220]/80 to-transparent" />
            </div>
            <Container className="relative">
                <div>
                    <div
                        key={slide.id}
                        aria-roledescription="slide"
                        aria-label={`${active + 1} de ${slides.length}: ${slide.eyebrow}`}
                        className="grid min-h-[35rem] items-center gap-8 motion-safe:animate-in motion-safe:duration-500 motion-safe:fade-in lg:min-h-[31rem] lg:grid-cols-[0.95fr_1.05fr] lg:gap-14"
                    >
                        <div className="flex flex-col justify-center pt-2">
                            <p className="text-xs font-bold tracking-[0.12em] text-sky-300 uppercase">
                                {slide.eyebrow}
                            </p>
                            <h1 className="mt-4 max-w-xl text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-5xl">
                                {slide.title}
                            </h1>
                            <p className="mt-5 max-w-lg text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                                {slide.description}
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <ButtonLink
                                    href={slide.href}
                                    variant="inverse"
                                    arrow
                                >
                                    {slide.system
                                        ? 'Conhecer o sistema'
                                        : 'Conhecer o serviço'}
                                </ButtonLink>
                                <ButtonLink
                                    href={contactHref(slide.contactType)}
                                    variant="ghostInverse"
                                >
                                    Conversar sobre o projeto
                                </ButtonLink>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mt-5 flex flex-wrap items-center justify-between gap-4">
                    <div
                        className="flex items-center gap-1"
                        aria-label="Selecionar destaque"
                    >
                        {slides.map((item, index) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => select(index)}
                                aria-label={`Mostrar destaque ${index + 1}: ${item.eyebrow}`}
                                aria-current={
                                    active === index ? 'true' : undefined
                                }
                                className="grid size-9 place-items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300"
                            >
                                <span
                                    className={`block h-1.5 rounded-full transition-all ${active === index ? 'w-6 bg-sky-300' : 'w-2 bg-white/40'}`}
                                />
                            </button>
                        ))}
                    </div>
                    <div className="flex items-center gap-2 rounded-lg bg-[#0b1220]/55 p-1">
                        <button
                            type="button"
                            onClick={() => select(active - 1)}
                            aria-label="Destaque anterior"
                            className="grid size-10 place-items-center rounded-md text-slate-300 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300"
                        >
                            <ChevronLeft className="size-5" />
                        </button>
                        <button
                            type="button"
                            onClick={() => select(active + 1)}
                            aria-label="Próximo destaque"
                            className="grid size-10 place-items-center rounded-md text-slate-300 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300"
                        >
                            <ChevronRight className="size-5" />
                        </button>
                        <button
                            type="button"
                            onClick={() => setPaused((value) => !value)}
                            aria-label={
                                paused
                                    ? 'Retomar rotação automática'
                                    : 'Pausar rotação automática'
                            }
                            aria-pressed={paused}
                            disabled={reducedMotion}
                            className="grid size-10 place-items-center rounded-md text-slate-300 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300 disabled:opacity-40"
                        >
                            {paused ? (
                                <Play className="size-5" />
                            ) : (
                                <Pause className="size-5" />
                            )}
                        </button>
                    </div>
                </div>
            </Container>
        </section>
    );
}
