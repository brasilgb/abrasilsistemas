import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    Code2,
    LayoutTemplate,
    LogIn,
    Menu,
    MessageCircle,
    MonitorSmartphone,
    Plug,
    Quote,
    Smartphone,
    Sparkles,
    UserRound,
    Workflow,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { CookieConsent } from '@/components/cookie-consent';
import { NavProductsMenu } from '@/components/nav-products-menu';
import { NavServicesMenu } from '@/components/nav-services-menu';
import { PublicBrand } from '@/components/public-brand';
import { PublicFooter } from '@/components/public-footer';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import { buildWhatsappUrl, useContact } from '@/lib/contact';
import { vetorOsFeatures as productFeatures, vetorPetFeatures } from '@/data/product-features';
import type { User } from '@/types';

const outcomes = [
    {
        icon: ClipboardCheck,
        number: '01',
        title: 'Operações organizadas',
        description:
            'Centralize processos, históricos e responsáveis em sistemas feitos para a rotina real.',
    },
    {
        icon: MessageCircle,
        number: '02',
        title: 'Equipes mais produtivas',
        description:
            'Reduza tarefas manuais e dê à equipe informações claras para atender e vender melhor.',
    },
    {
        icon: BarChart3,
        number: '03',
        title: 'Decisões com dados',
        description:
            'Acompanhe operação, vendas e resultados sem depender de informações espalhadas.',
    },
] as const;

function buildHeroSlides(whatsappNumber: string) {
    const contactWhatsappUrl = buildWhatsappUrl(whatsappNumber, 'Olá, preciso de ajuda com uma solução da ABrasil.');

    return [
    {
        id: 'sob-medida',
        name: 'Sob medida',
        eyebrow: 'Projetos sob medida · Sites e sistemas',
        tagline: 'Sites e sistemas',
        titleLead: 'Do briefing',
        titleHighlight: 'ao ar em semanas.',
        description:
            'Sites institucionais, landing pages e sistemas sob medida para quem precisa de retorno rápido — sem esperar meses para ver o projeto no ar.',
        logo: undefined,
        icon: Code2,
        logoClass: 'bg-amber-400/15 text-amber-300',
        features: [
            'Sites institucionais e landing pages',
            'Sistemas e integrações sob medida',
            'Entrega em semanas, não meses',
            'Suporte direto com quem constrói',
        ],
        primaryCta: {
            label: 'Falar sobre um projeto',
            href: contactWhatsappUrl,
        },
        secondaryCta: {
            label: 'Ver serviços sob medida',
            href: '/produtos#sob-medida',
        },
        footNote: {
            text: 'Também temos produtos prontos para usar.',
            linkLabel: 'Ver VetorOS e VetorPet',
            href: '/produtos#vetoros',
        },
        footerLabel: 'Serviço ABrasil',
        badgeLabel: 'Entrega ágil',
        badgeValue: 'Briefing → No ar',
        pillClass: 'border-amber-300/25 bg-amber-300/10 text-amber-200',
        headlineAccent: 'text-amber-300',
        primaryBtnClass:
            'bg-amber-400 text-slate-950 shadow-[0_18px_60px_rgba(251,191,36,.18)] hover:bg-amber-300',
        gradientClass:
            'bg-gradient-to-br from-amber-300/20 via-blue-600/5 to-transparent',
        cardBorder: 'border-amber-300/20',
        cardBg: 'bg-[#1a1408]',
        tagColor: 'text-amber-300',
        checkColor: 'text-amber-300',
        badgeBg: 'bg-amber-300',
        dotColor: 'bg-amber-300',
    },
    {
        id: 'vetoros',
        name: 'VetorOS',
        eyebrow: 'Produto ABrasil · Assistências técnicas',
        tagline: 'Assistências técnicas',
        titleLead: 'Uma tela.',
        titleHighlight: 'A operação inteira.',
        description:
            'Ordens de serviço, clientes, estoque, vendas e financeiro em uma única operação, pensada para o dia a dia da assistência técnica.',
        logo: '/images/logo_os.png',
        icon: undefined,
        logoClass: 'rounded-2xl object-contain',
        features: productFeatures,
        primaryCta: {
            label: 'Criar minha conta grátis',
            href: 'https://vetoros.com.br',
        },
        secondaryCta: { label: 'Ver detalhes do VetorOS', href: '/produtos#vetoros' },
        footNote: {
            text: 'Também criamos sistemas sob medida e sites profissionais.',
            linkLabel: 'Saiba mais',
            href: '/produtos#sob-medida',
        },
        footerLabel: 'Produto ABrasil',
        badgeLabel: 'Tudo conectado',
        badgeValue: 'Atendimento → Financeiro',
        pillClass: 'border-sky-300/25 bg-sky-300/10 text-sky-200',
        headlineAccent: 'text-sky-300',
        primaryBtnClass:
            'bg-sky-400 text-slate-950 shadow-[0_18px_60px_rgba(56,189,248,.18)] hover:bg-sky-300',
        gradientClass:
            'bg-gradient-to-br from-cyan-300/20 via-blue-600/5 to-transparent',
        cardBorder: 'border-sky-300/20',
        cardBg: 'bg-[#0b1625]',
        tagColor: 'text-sky-300',
        checkColor: 'text-sky-300',
        badgeBg: 'bg-cyan-300',
        dotColor: 'bg-sky-300',
    },
    {
        id: 'vetorpet',
        name: 'VetorPet',
        eyebrow: 'Produto ABrasil · Mercado pet',
        tagline: 'Vendas no mercado pet',
        titleLead: 'Da visita.',
        titleHighlight: 'Ao pedido.',
        description:
            'Carteira de clientes, catálogo, pedidos e equipe comercial no painel web e aplicativo Android, feito para quem vende no mercado pet.',
        logo: '/images/logo_pet.png',
        icon: undefined,
        logoClass: 'rounded-2xl bg-white object-contain p-1',
        features: vetorPetFeatures,
        primaryCta: {
            label: 'Criar minha conta grátis',
            href: 'https://vetorpet.com.br',
        },
        secondaryCta: { label: 'Ver detalhes do VetorPet', href: '/produtos#vetorpet' },
        footNote: {
            text: 'Também criamos sistemas sob medida e sites profissionais.',
            linkLabel: 'Saiba mais',
            href: '/produtos#sob-medida',
        },
        footerLabel: 'Produto ABrasil',
        badgeLabel: 'Tudo conectado',
        badgeValue: 'Visita → Pedido',
        pillClass: 'border-violet-300/25 bg-violet-300/10 text-violet-200',
        headlineAccent: 'text-violet-300',
        primaryBtnClass:
            'bg-violet-400 text-slate-950 shadow-[0_18px_60px_rgba(167,139,250,.18)] hover:bg-violet-300',
        gradientClass:
            'bg-gradient-to-br from-violet-300/20 via-blue-600/5 to-transparent',
        cardBorder: 'border-violet-300/20',
        cardBg: 'bg-[#11152a]',
        tagColor: 'text-violet-300',
        checkColor: 'text-violet-300',
        badgeBg: 'bg-violet-300',
        dotColor: 'bg-violet-300',
    },
    ];
}

const homeServices = [
    {
        icon: MonitorSmartphone,
        title: 'Criação de Sites',
        description: 'Institucionais e rápidos, prontos para gerar contatos.',
        href: '/servicos#sites',
    },
    {
        icon: LayoutTemplate,
        title: 'Landing Pages',
        description: 'Foco total em apresentar uma campanha ou serviço.',
        href: '/servicos#landing-pages',
    },
    {
        icon: Code2,
        title: 'Sistemas Sob Medida',
        description: 'Para processos que uma ferramenta pronta não resolve.',
        href: '/servicos#sistemas',
    },
    {
        icon: Smartphone,
        title: 'Aplicativos',
        description: 'Integrados a sistemas web, APIs e equipes externas.',
        href: '/servicos#aplicativos',
    },
    {
        icon: Plug,
        title: 'Integrações e APIs',
        description: 'Conexão entre sistemas, bancos de dados e serviços.',
        href: '/servicos#integracoes',
    },
    {
        icon: Workflow,
        title: 'Automação de Processos',
        description: 'Fluxos automáticos dentro de um projeto de software.',
        href: '/servicos#automacao',
    },
] as const;

const faqItems = [
    {
        question: 'VetorOS e VetorPet são produtos prontos, ou vocês desenvolvem sob medida?',
        answer: 'São dois produtos próprios da ABrasil, prontos para usar: o VetorOS para assistências técnicas e o VetorPet para representantes do mercado pet. Além deles, também desenvolvemos sistemas sob medida quando uma ferramenta pronta não resolve o processo da empresa.',
    },
    {
        question: 'Como funciona o teste gratuito do VetorOS e do VetorPet?',
        answer: 'O cadastro é feito online, sem esperar contato comercial, e libera 14 dias de acesso completo sem pedir cartão de crédito.',
    },
    {
        question: 'Vocês também desenvolvem sites para empresas que não usam o VetorOS ou o VetorPet?',
        answer: 'Sim. Desenvolvemos sites institucionais, landing pages e catálogos digitais para qualquer empresa, independente de usar nossos produtos próprios.',
    },
    {
        question: 'Como faço para falar com a equipe da ABrasil?',
        answer: 'Pelo WhatsApp ou e-mail, com atendimento direto — sem central de atendimento automatizada.',
    },
] as const;

const journey = [
    [
        'Escolha a solução',
        'Conheça o VetorOS e o VetorPet e encontre o produto criado para a sua operação.',
    ],
    [
        'Crie sua conta',
        'Faça seu cadastro online em poucos minutos, sem esperar contato comercial.',
    ],
    [
        'Teste por 14 dias',
        'Explore a solução gratuitamente e valide os recursos na rotina da sua empresa.',
    ],
] as const;

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


function SectionHeading({
    eyebrow,
    title,
    description,
    light = false,
}: {
    eyebrow: string;
    title: string;
    description?: string;
    light?: boolean;
}) {
    return (
        <div className="max-w-3xl">
            <p
                className={`flex items-center gap-3 text-xs font-extrabold tracking-[0.18em] uppercase ${light ? 'text-cyan-300' : 'text-blue-700'}`}
            >
                <span
                    className={`h-px w-8 ${light ? 'bg-cyan-400' : 'bg-blue-600'}`}
                />
                {eyebrow}
            </p>
            <h2
                className={`mt-5 text-4xl leading-[1.02] font-black tracking-[-0.055em] text-balance sm:text-6xl ${light ? 'text-white' : 'text-slate-950'}`}
            >
                {title}
            </h2>
            {description && (
                <p
                    className={`mt-6 max-w-2xl text-lg leading-8 ${light ? 'text-slate-400' : 'text-slate-600'}`}
                >
                    {description}
                </p>
            )}
        </div>
    );
}

export default function Welcome({
    blogPosts,
    testimonials,
    portfolioItems,
}: {
    blogPosts: BlogPostSummary[];
    testimonials: TestimonialSummary[];
    portfolioItems: PortfolioItemSummary[];
}) {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const contact = useContact();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [openFaqIndex, setOpenFaqIndex] = useState<number | null>(0);
    const [activeSlide, setActiveSlide] = useState(0);
    const heroSlides = buildHeroSlides(contact.whatsapp);
    const slide = heroSlides[activeSlide];
    const SlideIcon = slide.icon;

    useEffect(() => {
        const timer = setInterval(() => {
            setActiveSlide((current) => (current + 1) % heroSlides.length);
        }, 7000);

        return () => clearInterval(timer);
    }, [activeSlide]);

    const structuredData = {
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'Organization',
                name: 'ABrasil Sistemas',
                url: 'https://abrasilsistemas.com.br',
                logo: 'https://abrasilsistemas.com.br/images/logo_ab.png',
                email: 'contato@abrasilsistemas.com.br',
                telephone: '+55-51-99893-1325',
                description:
                    'Empresa brasileira de tecnologia responsável pelo VetorOS e VetorPet, além do desenvolvimento de sistemas sob medida e sites profissionais.',
            },
            {
                '@type': 'SoftwareApplication',
                name: 'VetorOS',
                applicationCategory: 'BusinessApplication',
                operatingSystem: 'Web',
                description:
                    'Sistema de gestão para assistências técnicas com ordens de serviço, estoque, vendas e financeiro.',
            },
            {
                '@type': 'SoftwareApplication',
                name: 'VetorPet',
                applicationCategory: 'BusinessApplication',
                operatingSystem: 'Web, Android',
                description:
                    'Sistema de gestão comercial para distribuidores e representantes que vendem para o mercado pet.',
            },
        ],
    };

    return (
        <>
            <Head title="Sistemas, sites e software sob medida | ABrasil Sistemas">
                <meta
                    name="description"
                    content="A ABrasil Sistemas desenvolve o VetorOS, o VetorPet, sistemas sob medida e sites profissionais para empresas."
                />
                <meta
                    name="robots"
                    content="index, follow, max-image-preview:large"
                />
                <meta name="theme-color" content="#08111f" />
                <meta property="og:locale" content="pt_BR" />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content="ABrasil Sistemas" />
                <meta
                    property="og:title"
                    content="ABrasil Sistemas — Produtos digitais e desenvolvimento"
                />
                <meta
                    property="og:description"
                    content="VetorOS, VetorPet, desenvolvimento de sistemas sob medida e criação de sites profissionais."
                />
                <meta
                    property="og:image"
                    content="https://abrasilsistemas.com.br/images/dashboard-vetoros.webp"
                />
                <meta property="og:image:type" content="image/webp" />
                <meta property="og:image:width" content="1926" />
                <meta property="og:image:height" content="934" />
                <meta name="twitter:card" content="summary_large_image" />
                <meta
                    name="twitter:image"
                    content="https://abrasilsistemas.com.br/images/dashboard-vetoros.webp"
                />
                <link rel="canonical" href="https://abrasilsistemas.com.br/" />
                <script type="application/ld+json">
                    {JSON.stringify(structuredData)}
                </script>
            </Head>

            <div className="ab-public-site ab-marketing-home min-h-screen overflow-x-hidden bg-[#f7f8fa] text-slate-900 selection:bg-cyan-200 selection:text-slate-950">
                <header className="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-[#08111f]/90 text-white backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-[86rem] items-center justify-between px-5 sm:px-8 lg:px-12">
                        <PublicBrand inverse />
                        <nav
                            className="hidden items-center gap-6 text-sm font-semibold text-slate-300 lg:flex"
                            aria-label="Navegação principal"
                        >
                            <NavProductsMenu />
                            <NavServicesMenu />
                            <a
                                href="#resultados"
                                className="transition hover:text-white"
                            >
                                Resultados
                            </a>
                            {portfolioItems.length > 0 && (
                                <a
                                    href="#trabalhos"
                                    className="transition hover:text-white"
                                >
                                    Trabalhos
                                </a>
                            )}
                            {testimonials.length > 0 && (
                                <a
                                    href="#depoimentos"
                                    className="transition hover:text-white"
                                >
                                    Depoimentos
                                </a>
                            )}
                            <Link
                                href="/sobre"
                                className="transition hover:text-white"
                            >
                                Sobre
                            </Link>
                            <Link
                                href="/blog"
                                className="transition hover:text-white"
                            >
                                Blog
                            </Link>
                            <Link
                                href="/contato"
                                className="transition hover:text-white"
                            >
                                Contato
                            </Link>
                        </nav>
                        <div className="hidden items-center gap-3 sm:flex">
                            {auth.user ? (
                                <Link
                                    href={auth.user.role === 'admin' ? '/dashboard' : '/settings/profile'}
                                    className="inline-flex items-center gap-2 rounded-full border border-white/15 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/5"
                                >
                                    <UserRound className="size-4 shrink-0" />
                                    Minha conta
                                </Link>
                            ) : (
                                <Link
                                    href="/login"
                                    className="inline-flex items-center gap-2 text-sm font-semibold text-slate-300 transition hover:text-white"
                                >
                                    <LogIn className="size-4" /> Entrar
                                </Link>
                            )}
                            <a
                                href="#produtos"
                                className="inline-flex items-center gap-2 rounded-full bg-cyan-300 px-5 py-3 text-sm font-extrabold text-slate-950 transition hover:-translate-y-0.5 hover:bg-cyan-200"
                            >
                                Conhecer as soluções
                                <ArrowRight className="size-4" />
                            </a>
                        </div>
                        <button
                            type="button"
                            aria-label={
                                mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'
                            }
                            aria-expanded={mobileMenuOpen}
                            className="grid size-11 place-items-center rounded-full border border-white/15 lg:hidden"
                            onClick={() => setMobileMenuOpen((open) => !open)}
                        >
                            {mobileMenuOpen ? (
                                <X className="size-5" />
                            ) : (
                                <Menu className="size-5" />
                            )}
                        </button>
                    </div>
                    {mobileMenuOpen && (
                        <nav
                            className="border-t border-white/10 bg-[#08111f] px-5 py-5 text-sm font-bold lg:hidden"
                            aria-label="Navegação móvel"
                        >
                            <NavProductsMenu mobile onNavigate={() => setMobileMenuOpen(false)} />
                            <NavServicesMenu mobile onNavigate={() => setMobileMenuOpen(false)} />
                            {[
                                ['#resultados', 'Resultados'],
                                ...(portfolioItems.length > 0
                                    ? [['#trabalhos', 'Trabalhos']]
                                    : []),
                                ...(testimonials.length > 0
                                    ? [['#depoimentos', 'Depoimentos']]
                                    : []),
                            ].map(([href, label]) => (
                                <a
                                    key={href}
                                    href={href}
                                    className="block border-b border-white/10 py-4"
                                    onClick={() => setMobileMenuOpen(false)}
                                >
                                    {label}
                                </a>
                            ))}
                            <Link
                                href="/sobre"
                                className="block border-b border-white/10 py-4"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Sobre
                            </Link>
                            <Link
                                href="/blog"
                                className="block border-b border-white/10 py-4"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Blog
                            </Link>
                            <Link
                                href="/contato"
                                className="block border-b border-white/10 py-4"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Contato
                            </Link>
                            {auth.user ? (
                                <Link
                                    href={auth.user.role === 'admin' ? '/dashboard' : '/settings/profile'}
                                    className="flex items-center gap-2 border-b border-white/10 py-4"
                                    onClick={() => setMobileMenuOpen(false)}
                                >
                                    <UserRound className="size-4" /> Minha conta
                                </Link>
                            ) : (
                                <Link
                                    href="/login"
                                    className="flex items-center gap-2 border-b border-white/10 py-4"
                                    onClick={() => setMobileMenuOpen(false)}
                                >
                                    <LogIn className="size-4" /> Entrar
                                </Link>
                            )}
                            <a
                                href="#produtos"
                                className="mt-5 flex items-center justify-center gap-2 rounded-full bg-cyan-300 px-5 py-3.5 text-slate-950"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Conhecer as soluções{' '}
                                <ArrowRight className="size-4" />
                            </a>
                        </nav>
                    )}
                </header>

                <main>
                    <section id="produtos" className="relative isolate scroll-mt-20 overflow-hidden bg-slate-950/95 pt-32 text-white sm:pt-40">
                        <div className="absolute inset-0 -z-20 bg-[linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] bg-[size:44px_44px]" />
                        <div className="absolute top-10 right-[-12rem] -z-10 size-[38rem] rounded-full bg-sky-500/20 blur-[120px]" />
                        <div className="absolute bottom-[-18rem] left-[25%] -z-10 size-[32rem] rounded-full bg-sky-400/10 blur-[100px]" />

                        <div
                            className="mx-auto max-w-[86rem] px-5 pb-16 sm:px-8 sm:pb-20 lg:px-12"
                            role="region"
                            aria-roledescription="carrossel"
                            aria-label="Produtos ABrasil"
                        >
                            <div key={slide.id} className="grid items-center gap-16 lg:grid-cols-[0.92fr_1.08fr]">
                                <div className="animate-in fade-in slide-in-from-bottom-2 duration-500">
                                    <p className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-extrabold tracking-[0.12em] uppercase ${slide.pillClass}`}>
                                        <Sparkles className="size-3.5" />
                                        {slide.eyebrow}
                                    </p>
                                    <h1 className="mt-7 max-w-3xl text-[clamp(3.5rem,7vw,6.6rem)] leading-[0.88] font-black tracking-[-0.075em] text-balance">
                                        {slide.titleLead}
                                        <span className={`mt-2 block ${slide.headlineAccent}`}>
                                            {slide.titleHighlight}
                                        </span>
                                    </h1>
                                    <p className="mt-8 max-w-xl text-lg leading-8 text-slate-300 sm:text-xl">
                                        {slide.description}
                                    </p>
                                    <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                        <a
                                            href={slide.primaryCta.href}
                                            target="_blank"
                                            rel="noreferrer"
                                            className={`inline-flex h-14 items-center justify-center gap-3 rounded-full px-7 text-sm font-extrabold transition hover:-translate-y-1 ${slide.primaryBtnClass}`}
                                        >
                                            {slide.primaryCta.label}
                                            <ArrowUpRight className="size-4" />
                                        </a>
                                        <a
                                            href={slide.secondaryCta.href}
                                            className="inline-flex h-14 items-center justify-center gap-2 rounded-full border border-white/15 px-7 text-sm font-bold text-white transition hover:bg-white/5"
                                        >
                                            {slide.secondaryCta.label}
                                            <ArrowRight className="size-4" />
                                        </a>
                                    </div>
                                    <p className="mt-5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <Check className="size-4 text-cyan-300" />
                                        {slide.footNote.text}
                                        <a
                                            href={slide.footNote.href}
                                            className="font-bold text-slate-300 underline underline-offset-2 transition hover:text-white"
                                        >
                                            {slide.footNote.linkLabel}
                                        </a>
                                    </p>
                                </div>

                                <div className="relative animate-in fade-in duration-500">
                                    <div className={`absolute -inset-5 rounded-[2.2rem] blur-2xl ${slide.gradientClass}`} />
                                    <div className={`relative z-10 flex min-h-[26rem] flex-col rounded-[1.75rem] border p-8 shadow-2xl shadow-black/40 sm:p-10 ${slide.cardBorder} ${slide.cardBg}`}>
                                        <div className="flex items-center gap-4">
                                            {slide.logo ? (
                                                <img
                                                    src={slide.logo}
                                                    alt={slide.name}
                                                    className={`size-16 shrink-0 ${slide.logoClass}`}
                                                />
                                            ) : (
                                                SlideIcon && (
                                                    <span className={`grid size-16 shrink-0 place-items-center rounded-2xl ${slide.logoClass}`}>
                                                        <SlideIcon className="size-7" />
                                                    </span>
                                                )
                                            )}
                                            <div>
                                                <p className={`text-xs font-bold tracking-[0.15em] uppercase ${slide.tagColor}`}>
                                                    {slide.tagline}
                                                </p>
                                                <h2 className="mt-1 text-3xl font-black">{slide.name}</h2>
                                            </div>
                                        </div>
                                        <ul className="mt-8 grid flex-1 content-start gap-4 sm:grid-cols-2">
                                            {slide.features.map((feature) => (
                                                <li key={feature} className="flex items-start gap-2 text-sm leading-6 text-slate-300">
                                                    <CheckCircle2 className={`mt-0.5 size-4 shrink-0 ${slide.checkColor}`} />
                                                    {feature}
                                                </li>
                                            ))}
                                        </ul>
                                        <span className="mt-8 flex items-center justify-between border-t border-white/10 pt-6 text-sm font-black text-white">
                                            {slide.footerLabel}
                                            <CheckCircle2 className={`size-4 ${slide.checkColor}`} />
                                        </span>
                                    </div>
                                    <div className={`absolute -right-3 -bottom-5 z-20 rounded-2xl px-4 py-3 text-slate-950 shadow-xl sm:-right-7 ${slide.badgeBg}`}>
                                        <p className="text-[10px] font-bold uppercase">
                                            {slide.badgeLabel}
                                        </p>
                                        <p className="mt-0.5 text-sm font-black">
                                            {slide.badgeValue}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-14 flex items-center justify-center gap-5 sm:mt-16">
                                <button
                                    type="button"
                                    aria-label="Produto anterior"
                                    onClick={() =>
                                        setActiveSlide((current) => (current - 1 + heroSlides.length) % heroSlides.length)
                                    }
                                    className="grid size-10 shrink-0 place-items-center rounded-full border border-white/15 text-white transition hover:bg-white/10"
                                >
                                    <ChevronLeft className="size-4" />
                                </button>
                                <div className="flex items-center gap-2">
                                    {heroSlides.map((item, index) => (
                                        <button
                                            key={item.id}
                                            type="button"
                                            aria-label={`Ver ${item.name}`}
                                            aria-current={index === activeSlide}
                                            onClick={() => setActiveSlide(index)}
                                            className={`h-2 rounded-full transition-all ${
                                                index === activeSlide
                                                    ? `w-8 ${item.dotColor}`
                                                    : 'w-2 bg-white/20 hover:bg-white/40'
                                            }`}
                                        />
                                    ))}
                                </div>
                                <button
                                    type="button"
                                    aria-label="Próximo produto"
                                    onClick={() => setActiveSlide((current) => (current + 1) % heroSlides.length)}
                                    className="grid size-10 shrink-0 place-items-center rounded-full border border-white/15 text-white transition hover:bg-white/10"
                                >
                                    <ChevronRight className="size-4" />
                                </button>
                            </div>
                        </div>

                        <div className="border-t border-white/10">
                            <div className="mx-auto grid max-w-[86rem] divide-y divide-white/10 px-5 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:px-8 lg:px-12">
                                {[
                                    'Criado para operações reais',
                                    'Suporte próximo e humano',
                                    'Acesso seguro de qualquer lugar',
                                ].map((item) => (
                                    <p
                                        key={item}
                                        className="flex items-center justify-center gap-2 py-5 text-xs font-bold tracking-wide text-slate-400"
                                    >
                                        <CheckCircle2 className="size-4 text-cyan-300" />{' '}
                                        {item}
                                    </p>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        id="resultados"
                        className="scroll-mt-20 py-24 sm:py-32"
                    >
                        <div className="mx-auto max-w-[86rem] px-5 sm:px-8 lg:px-12">
                            <SectionHeading
                                eyebrow="O que muda na rotina"
                                title="Sua empresa deixa de apagar incêndios."
                                description="O ganho não está em ter mais uma ferramenta. Está em saber o que acontece, quem precisa agir e onde o dinheiro está."
                            />
                            <div className="mt-14 grid border-y border-slate-300 lg:grid-cols-3 lg:divide-x lg:divide-slate-300">
                                {outcomes.map((outcome) => {
                                    const Icon = outcome.icon;

                                    return (
                                        <article
                                            key={outcome.number}
                                            className="group border-b border-slate-300 py-9 last:border-0 lg:border-0 lg:px-9 lg:first:pl-0 lg:last:pr-0"
                                        >
                                            <div className="flex items-start justify-between">
                                                <span className="grid size-12 place-items-center rounded-2xl bg-slate-950 text-cyan-300 transition group-hover:scale-105 group-hover:-rotate-3">
                                                    <Icon className="size-5" />
                                                </span>
                                                <span className="text-xs font-black text-slate-300">
                                                    {outcome.number}
                                                </span>
                                            </div>
                                            <h3 className="mt-8 text-2xl font-black tracking-[-0.035em] text-slate-950">
                                                {outcome.title}
                                            </h3>
                                            <p className="mt-3 max-w-sm leading-7 text-slate-600">
                                                {outcome.description}
                                            </p>
                                        </article>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section className="bg-[#08111f] py-24 text-white sm:py-32">
                        <div className="mx-auto max-w-[86rem] px-5 sm:px-8 lg:px-12">
                            <SectionHeading
                                eyebrow="Do cadastro ao resultado"
                                title="Tecnologia sem complicar o seu negócio."
                                description="Um caminho simples para conhecer, testar e contratar o sistema sem depender de uma equipe comercial."
                                light
                            />
                            <ol className="mt-16 grid gap-px overflow-hidden rounded-3xl border border-white/10 bg-white/10 lg:grid-cols-3">
                                {journey.map(([title, description], index) => (
                                    <li
                                        key={title}
                                        className="bg-[#0b1625] p-8 sm:p-10"
                                    >
                                        <span className="text-5xl font-black tracking-[-0.06em] text-white/10">
                                            0{index + 1}
                                        </span>
                                        <h3 className="mt-10 text-2xl font-black">
                                            {title}
                                        </h3>
                                        <p className="mt-4 max-w-sm leading-7 text-slate-400">
                                            {description}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <section
                        id="solucoes"
                        className="scroll-mt-20 py-24 sm:py-32"
                    >
                        <div className="mx-auto max-w-[86rem] px-5 sm:px-8 lg:px-12">
                            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                                <SectionHeading
                                    eyebrow="Ecossistema ABrasil"
                                    title="Serviços para outros desafios digitais."
                                    description="Soluções especializadas para vender, comunicar e transformar processos — sempre com tecnologia a serviço da operação."
                                />
                                <Link
                                    href="/servicos"
                                    className="inline-flex shrink-0 items-center gap-2 text-sm font-black text-blue-700"
                                >
                                    Ver todos os serviços
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>
                            <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                {homeServices.map((service) => {
                                    const Icon = service.icon;

                                    return (
                                        <a
                                            key={service.title}
                                            href={service.href}
                                            className="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-slate-300 hover:shadow-lg"
                                        >
                                            <span className="grid size-11 place-items-center rounded-xl bg-cyan-100 text-cyan-800">
                                                <Icon className="size-5" />
                                            </span>
                                            <h3 className="mt-5 text-lg font-black text-slate-950">
                                                {service.title}
                                            </h3>
                                            <p className="mt-2 flex-1 text-sm leading-6 text-slate-600">
                                                {service.description}
                                            </p>
                                            <span className="mt-5 flex items-center gap-1 text-xs font-black text-slate-950">
                                                Saiba mais
                                                <ArrowUpRight className="size-3.5 transition group-hover:translate-x-1 group-hover:-translate-y-1" />
                                            </span>
                                        </a>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    {portfolioItems.length > 0 && (
                        <section
                            id="trabalhos"
                            className="scroll-mt-20 border-y border-slate-200 bg-slate-50 py-24 sm:py-32"
                        >
                            <div className="mx-auto max-w-[86rem] px-5 sm:px-8 lg:px-12">
                                <SectionHeading
                                    eyebrow="Prova de trabalho"
                                    title="Sites que já colocamos no ar."
                                    description="Projetos reais, entregues para empresas que precisavam de presença digital rápida e profissional."
                                />
                                <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    {portfolioItems.map((item) => {
                                        const content = (
                                            <>
                                                <img
                                                    src={item.screenshot_url}
                                                    alt={item.title}
                                                    className="h-48 w-full object-cover object-top"
                                                />
                                                <div className="flex flex-1 flex-col p-6">
                                                    <h3 className="text-lg font-black tracking-[-0.02em] text-slate-950">
                                                        {item.title}
                                                    </h3>
                                                    <p className="mt-2 flex-1 text-sm leading-6 text-slate-600">
                                                        {item.description}
                                                    </p>
                                                    {item.site_url && (
                                                        <span className="mt-4 inline-flex items-center gap-1 text-sm font-bold text-blue-700">
                                                            Visitar site
                                                            <ArrowUpRight className="size-3.5" />
                                                        </span>
                                                    )}
                                                </div>
                                            </>
                                        );

                                        const cardClass =
                                            'group flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl';

                                        return item.site_url ? (
                                            <a
                                                key={item.id}
                                                href={item.site_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className={cardClass}
                                            >
                                                {content}
                                            </a>
                                        ) : (
                                            <div
                                                key={item.id}
                                                className={cardClass}
                                            >
                                                {content}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </section>
                    )}

                    {testimonials.length > 0 && (
                        <section
                            id="depoimentos"
                            className="scroll-mt-20 py-24 sm:py-32"
                        >
                            <div className="mx-auto max-w-[86rem] px-5 sm:px-8 lg:px-12">
                                <SectionHeading
                                    eyebrow="Quem já usa"
                                    title="Empresas que confiam na ABrasil."
                                    description="Depoimentos de clientes que usam o VetorOS, o VetorPet ou contrataram um site com a gente."
                                />
                                <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    {testimonials.map((testimonial) => (
                                        <figure
                                            key={testimonial.id}
                                            className="flex h-full flex-col rounded-[1.75rem] border border-slate-200 bg-white p-7 shadow-sm"
                                        >
                                            <Quote className="size-7 text-blue-700/30" />
                                            <blockquote className="mt-4 flex-1 text-sm leading-7 text-slate-700">
                                                “{testimonial.quote}”
                                            </blockquote>
                                            <figcaption className="mt-6 flex items-center gap-3 border-t border-slate-100 pt-5">
                                                {testimonial.photo_url ? (
                                                    <img
                                                        src={
                                                            testimonial.photo_url
                                                        }
                                                        alt={
                                                            testimonial.author_name
                                                        }
                                                        className="size-10 rounded-full object-cover"
                                                    />
                                                ) : (
                                                    <span className="grid size-10 place-items-center rounded-full bg-blue-50 text-sm font-black text-blue-700">
                                                        {testimonial.author_name.charAt(
                                                            0,
                                                        )}
                                                    </span>
                                                )}
                                                <div>
                                                    <p className="text-sm font-black text-slate-950">
                                                        {
                                                            testimonial.author_name
                                                        }
                                                    </p>
                                                    {testimonial.author_role && (
                                                        <p className="text-xs text-slate-500">
                                                            {
                                                                testimonial.author_role
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </figcaption>
                                        </figure>
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}

                    {blogPosts.length > 0 && (
                        <section className="border-y border-slate-200 bg-white py-20 sm:py-24">
                            <div className="mx-auto max-w-[86rem] px-5 sm:px-8 lg:px-12">
                                <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                                    <div>
                                        <p className="text-xs font-extrabold tracking-[0.18em] text-blue-700 uppercase">
                                            Conteúdo útil
                                        </p>
                                        <h2 className="mt-3 text-3xl font-black tracking-[-0.04em] text-slate-950 sm:text-4xl">
                                            Para quem gere e faz acontecer.
                                        </h2>
                                    </div>
                                    <Link
                                        href="/blog"
                                        className="inline-flex items-center gap-2 text-sm font-black text-blue-700"
                                    >
                                        Ver todos os artigos{' '}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </div>
                                <div className="mt-10 grid gap-4 lg:grid-cols-3">
                                    {blogPosts.map((post) => (
                                        <Link
                                            key={post.id}
                                            href={`/blog/${post.slug}`}
                                            className="group flex min-h-56 flex-col rounded-2xl bg-slate-100 p-6 transition hover:bg-slate-950 hover:text-white"
                                        >
                                            <p className="text-[10px] font-bold tracking-[0.15em] text-blue-600 uppercase">
                                                {post.category?.name ??
                                                    'Gestão'}
                                            </p>
                                            <h3 className="mt-4 text-xl leading-snug font-black tracking-[-0.025em]">
                                                {post.title}
                                            </h3>
                                            <span className="mt-auto flex items-center gap-1 pt-6 text-xs font-bold">
                                                Ler artigo{' '}
                                                <ChevronRight className="size-3.5" />
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}

                    <section id="faq" className="scroll-mt-20 border-y border-slate-200 bg-slate-50 py-24 sm:py-32">
                        <div className="mx-auto max-w-3xl px-5 sm:px-8">
                            <div className="text-center">
                                <p className="text-xs font-extrabold tracking-[0.18em] text-blue-700 uppercase">Perguntas frequentes</p>
                                <h2 className="mt-3 text-4xl font-bold tracking-[-0.04em] text-balance text-slate-950 sm:text-5xl">
                                    Ainda com dúvidas?
                                </h2>
                            </div>

                            <div className="mt-12 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white px-6">
                                {faqItems.map((item, index) => {
                                    const isOpen = openFaqIndex === index;

                                    return (
                                        <Collapsible
                                            key={item.question}
                                            open={isOpen}
                                            onOpenChange={(open) => setOpenFaqIndex(open ? index : null)}
                                        >
                                            <CollapsibleTrigger className="flex w-full items-center justify-between gap-4 py-5 text-left text-base font-semibold text-slate-950">
                                                {item.question}
                                                <ChevronDown
                                                    className={`h-4 w-4 shrink-0 text-slate-500 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                                                />
                                            </CollapsibleTrigger>
                                            <CollapsibleContent className="pb-5 text-sm leading-6 text-slate-600">
                                                {item.answer}
                                            </CollapsibleContent>
                                        </Collapsible>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section className="relative overflow-hidden bg-blue-700 px-5 py-24 text-white sm:px-8 sm:py-32">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(34,211,238,.35),transparent_30%),radial-gradient(circle_at_90%_80%,rgba(15,23,42,.28),transparent_35%)]" />
                        <div className="relative mx-auto max-w-5xl text-center">
                            <p className="text-xs font-extrabold tracking-[0.18em] text-cyan-200 uppercase">
                                Encontre a solução certa
                            </p>
                            <h2 className="mt-6 text-5xl leading-[0.98] font-black tracking-[-0.065em] text-balance sm:text-7xl">
                                Sua operação pode ser mais simples.
                            </h2>
                            <p className="mx-auto mt-7 max-w-2xl text-lg leading-8 text-blue-100">
                                Conheça nossos produtos ou converse com a gente
                                sobre um sistema sob medida ou um novo site para
                                sua empresa.
                            </p>
                            <div className="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <a
                                    href="#produtos"
                                    className="inline-flex h-14 items-center gap-3 rounded-full bg-white px-7 text-sm font-black text-blue-800 shadow-xl transition hover:-translate-y-1"
                                >
                                    Conhecer os produtos <ArrowRight className="size-4" />
                                </a>
                                <a
                                    href="#solucoes"
                                    className="inline-flex h-14 items-center gap-3 rounded-full border border-white/30 bg-white/10 px-7 text-sm font-black text-white transition hover:-translate-y-1 hover:bg-white/20"
                                >
                                    Solicitar um projeto <ArrowRight className="size-4" />
                                </a>
                            </div>
                            <p className="mt-4 text-xs text-blue-200">
                                VetorOS, VetorPet, sistemas personalizados e
                                sites profissionais.
                            </p>
                        </div>
                    </section>
                </main>

                <PublicFooter />
                <WhatsAppFloat />
                <CookieConsent />
            </div>
        </>
    );
}
