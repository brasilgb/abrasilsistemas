import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    ClipboardCheck,
    Code2,
    LogIn,
    Menu,
    MessageCircle,
    MonitorSmartphone,
    Quote,
    Sparkles,
    UserRound,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { PublicBrand } from '@/components/public-brand';
import { PublicFooter } from '@/components/public-footer';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import type { User } from '@/types';

const contactWhatsappUrl =
    'https://wa.me/5551998931325?text=Ol%C3%A1%2C%20preciso%20de%20ajuda%20com%20uma%20solu%C3%A7%C3%A3o%20da%20ABrasil.';

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

const productFeatures = [
    'Ordens de serviço e orçamentos',
    'Clientes e equipamentos',
    'Estoque, vendas e financeiro',
    'Aplicativos para equipe e clientes',
] as const;

const otherSolutions = [
    {
        icon: MonitorSmartphone,
        eyebrow: 'Presença digital',
        title: 'Sites que trabalham pela empresa',
        description:
            'Sites institucionais e landing pages com estratégia, velocidade e foco em gerar oportunidades.',
        href: '/desenvolvimento-de-sites-para-empresas',
        action: 'Conhecer o serviço',
        accent: 'cyan',
    },
    {
        icon: Code2,
        eyebrow: 'Projetos especiais',
        title: 'Software sob medida',
        description:
            'Aplicativos, integrações e sistemas para processos que uma ferramenta pronta não resolve.',
        href: 'mailto:contato@absistemas.com.br?subject=Projeto de software sob medida',
        action: 'Enviar uma solicitação',
        accent: 'amber',
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
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [openFaqIndex, setOpenFaqIndex] = useState<number | null>(0);

    const structuredData = {
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'Organization',
                name: 'ABrasil Sistemas',
                url: 'https://abrasilsistemas.com.br',
                logo: 'https://abrasilsistemas.com.br/images/logo_ab.png',
                email: 'contato@absistemas.com.br',
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
                            className="hidden items-center gap-8 text-sm font-semibold text-slate-300 lg:flex"
                            aria-label="Navegação principal"
                        >
                            <a
                                href="#produtos"
                                className="transition hover:text-white"
                            >
                                Produtos
                            </a>
                            <a
                                href="#resultados"
                                className="transition hover:text-white"
                            >
                                Resultados
                            </a>
                            <a
                                href="#solucoes"
                                className="transition hover:text-white"
                            >
                                Serviços
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
                                href="/blog"
                                className="transition hover:text-white"
                            >
                                Conteúdo
                            </Link>
                        </nav>
                        <div className="hidden items-center gap-4 sm:flex">
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
                            {[
                                ['#produtos', 'Produtos'],
                                ['#resultados', 'Resultados'],
                                ['#solucoes', 'Serviços'],
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
                                href="/blog"
                                className="block border-b border-white/10 py-4"
                            >
                                Conteúdo
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

                        <div className="mx-auto grid max-w-[86rem] items-center gap-16 px-5 pb-20 sm:px-8 sm:pb-28 lg:grid-cols-[0.92fr_1.08fr] lg:px-12">
                            <div>
                                <p className="inline-flex items-center gap-2 rounded-full border border-sky-300/25 bg-sky-300/10 px-4 py-2 text-xs font-extrabold tracking-[0.12em] text-sky-200 uppercase">
                                    <Sparkles className="size-3.5" />
                                    Produtos próprios e projetos para empresas
                                </p>
                                <h1 className="mt-7 max-w-3xl text-[clamp(3.5rem,7vw,6.6rem)] leading-[0.88] font-black tracking-[-0.075em] text-balance">
                                    Tecnologia para
                                    <span className="mt-2 block text-sky-300">
                                        fazer seu negócio avançar.
                                    </span>
                                </h1>
                                <p className="mt-8 max-w-xl text-lg leading-8 text-slate-300 sm:text-xl">
                                    A ABrasil é a empresa por trás do VetorOS e
                                    do VetorPet. Também desenvolvemos sistemas
                                    sob medida e sites profissionais para
                                    transformar ideias e processos em
                                    resultados.
                                </p>
                                <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                    <a
                                        href="#produtos"
                                        className="inline-flex h-14 items-center justify-center gap-3 rounded-full bg-sky-400 px-7 text-sm font-extrabold text-slate-950 shadow-[0_18px_60px_rgba(56,189,248,.18)] transition hover:-translate-y-1 hover:bg-sky-300"
                                    >
                                        Conhecer nossos produtos
                                        <ArrowRight className="size-4" />
                                    </a>
                                    <a
                                        href="#solucoes"
                                        className="inline-flex h-14 items-center justify-center gap-2 rounded-full border border-white/15 px-7 text-sm font-bold text-white transition hover:bg-white/5"
                                    >
                                        Desenvolvimento e sites
                                        <ArrowRight className="size-4" />
                                    </a>
                                </div>
                                <p className="mt-5 flex items-center gap-2 text-xs text-slate-500">
                                    <Check className="size-4 text-cyan-300" />{' '}
                                    Produtos digitais, projetos personalizados
                                    e atendimento próximo.
                                </p>
                            </div>

                            <div className="relative grid gap-4 sm:grid-cols-2">
                                <div className="absolute -inset-5 rounded-[2.2rem] bg-gradient-to-br from-cyan-300/20 via-blue-600/5 to-transparent blur-2xl" />
                                <div className="relative z-10 flex min-h-80 flex-col rounded-[1.4rem] border border-sky-300/20 bg-[#0b1625] p-6 shadow-2xl shadow-black/40">
                                    <img src="/images/logo_os.png" alt="VetorOS" className="size-14 rounded-2xl object-contain" />
                                    <p className="mt-8 text-xs font-bold tracking-[0.15em] text-sky-300 uppercase">Assistências técnicas</p>
                                    <h2 className="mt-3 text-3xl font-black">VetorOS</h2>
                                    <p className="mt-3 flex-1 text-sm leading-6 text-slate-400">
                                        Ordens de serviço, clientes, estoque, vendas e financeiro em uma única operação.
                                    </p>
                                    <span className="mt-6 flex items-center justify-between border-t border-white/10 pt-5 text-sm font-black text-white">
                                        Produto ABrasil
                                        <CheckCircle2 className="size-4 text-sky-300" />
                                    </span>
                                </div>
                                <div className="relative z-10 flex min-h-80 flex-col rounded-[1.4rem] border border-violet-300/20 bg-[#11152a] p-6 shadow-2xl shadow-black/40 sm:translate-y-8">
                                    <img src="/images/logo_pet.png" alt="VetorPet" className="size-14 rounded-2xl bg-white object-contain p-1" />
                                    <p className="mt-8 text-xs font-bold tracking-[0.15em] text-violet-300 uppercase">Vendas no mercado pet</p>
                                    <h2 className="mt-3 text-3xl font-black">VetorPet</h2>
                                    <p className="mt-3 flex-1 text-sm leading-6 text-slate-400">
                                        Clientes, catálogo, visitas, pedidos e vendedores no painel web e aplicativo Android.
                                    </p>
                                    <span className="mt-6 flex items-center justify-between border-t border-white/10 pt-5 text-sm font-black text-white">
                                        Produto ABrasil
                                        <CheckCircle2 className="size-4 text-violet-300" />
                                    </span>
                                </div>
                                <div className="absolute -right-3 -bottom-5 z-20 rounded-2xl border border-cyan-300/20 bg-cyan-300 px-4 py-3 text-slate-950 shadow-xl sm:-right-7">
                                    <p className="text-[10px] font-bold uppercase">
                                        Tudo conectado
                                    </p>
                                    <p className="mt-0.5 text-sm font-black">
                                        Atendimento → Financeiro
                                    </p>
                                </div>
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

                    <section
                        id="vetoros"
                        className="scroll-mt-20 bg-[#dffbff] py-24 sm:py-32"
                    >
                        <div className="mx-auto grid max-w-[86rem] items-center gap-16 px-5 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:px-12">
                            <div className="vetoros-copy">
                                <p className="text-xs font-extrabold tracking-[0.18em] text-blue-800 uppercase">
                                    VetorOS
                                </p>
                                <h2 className="mt-5 text-5xl leading-[0.98] font-black tracking-[-0.06em] text-balance text-slate-950 sm:text-7xl">
                                    Uma tela.
                                    <span className="block text-blue-700">
                                        A operação inteira.
                                    </span>
                                </h2>
                                <p className="mt-7 max-w-xl text-lg leading-8 text-slate-700">
                                    Pare de procurar informação em cadernos,
                                    conversas e planilhas. O VetorOS conecta
                                    atendimento, bancada, estoque, vendas e
                                    gestão.
                                </p>
                                <ul className="mt-8 grid gap-4 sm:grid-cols-2">
                                    {productFeatures.map((feature) => (
                                        <li
                                            key={feature}
                                            className="flex items-start gap-3 text-sm font-bold text-slate-800"
                                        >
                                            <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-blue-700 text-white">
                                                <Check
                                                    className="size-3"
                                                    strokeWidth={3}
                                                />
                                            </span>
                                            {feature}
                                        </li>
                                    ))}
                                </ul>
                                <a
                                    href={contactWhatsappUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-10 inline-flex items-center gap-2 text-sm font-black text-blue-800"
                                >
                                    Falar sobre esta solução{' '}
                                    <MessageCircle className="size-4" />
                                </a>
                            </div>
                            <div className="relative">
                                <div className="absolute top-4 right-4 -bottom-4 left-4 rounded-[2rem] bg-blue-700" />
                                <div className="relative rounded-[2rem] bg-slate-950 p-7 text-white shadow-2xl sm:p-10">
                                    <p className="text-xs font-bold tracking-[0.14em] text-cyan-300 uppercase">
                                        Antes x Depois
                                    </p>
                                    <div className="mt-8 grid gap-4 sm:grid-cols-2">
                                        <div className="rounded-2xl border border-red-300/15 bg-red-300/5 p-6">
                                            <p className="text-sm font-black text-red-200">
                                                Sem uma gestão central
                                            </p>
                                            <ul className="mt-5 space-y-4 text-sm leading-6 text-slate-400">
                                                {[
                                                    'Informação espalhada',
                                                    'Cliente cobrando retorno',
                                                    'Estoque sem precisão',
                                                    'Decisões no escuro',
                                                ].map((item) => (
                                                    <li
                                                        key={item}
                                                        className="flex gap-2"
                                                    >
                                                        <X className="mt-1 size-4 shrink-0 text-red-300" />
                                                        {item}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                        <div className="rounded-2xl border border-cyan-300/25 bg-cyan-300/10 p-6">
                                            <p className="text-sm font-black text-cyan-200">
                                                Com o VetorOS
                                            </p>
                                            <ul className="mt-5 space-y-4 text-sm leading-6 text-slate-200">
                                                {[
                                                    'Histórico em um só lugar',
                                                    'Status claro de cada OS',
                                                    'Movimentações registradas',
                                                    'Indicadores para decidir',
                                                ].map((item) => (
                                                    <li
                                                        key={item}
                                                        className="flex gap-2"
                                                    >
                                                        <Check className="mt-1 size-4 shrink-0 text-cyan-300" />
                                                        {item}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    </div>
                                    <div className="mt-6 flex flex-col items-start justify-between gap-5 border-t border-white/10 pt-6 sm:flex-row sm:items-center">
                                        <div>
                                            <p className="font-black">
                                                Veja na prática, com a sua
                                                realidade.
                                            </p>
                                            <p className="mt-1 text-sm text-slate-400">
                                                Crie sua conta e conheça os
                                                recursos na sua própria rotina.
                                            </p>
                                        </div>
                                        <a
                                            href="https://vetoros.com.br"
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex shrink-0 items-center gap-2 rounded-full bg-cyan-300 px-5 py-3 text-sm font-black text-slate-950"
                                        >
                                            Criar minha conta{' '}
                                            <ArrowUpRight className="size-4" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="vetorpet"
                        className="scroll-mt-20 bg-violet-50 py-24 sm:py-32"
                    >
                        <div className="mx-auto grid max-w-[86rem] items-center gap-16 px-5 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:px-12">
                            <div>
                                <p className="text-xs font-extrabold tracking-[0.18em] text-violet-700 uppercase">
                                    VetorPet
                                </p>
                                <h2 className="mt-5 text-5xl leading-[0.98] font-black tracking-[-0.06em] text-balance text-slate-950 sm:text-7xl">
                                    Da visita.
                                    <span className="block text-violet-700">
                                        Ao pedido.
                                    </span>
                                </h2>
                                <p className="mt-7 max-w-xl text-lg leading-8 text-slate-700">
                                    O VetorPet conecta carteira de clientes,
                                    catálogo, agenda, pedidos e gestão de equipe
                                    para quem vende suprimentos no mercado pet.
                                </p>
                                <ul className="mt-8 grid gap-4 sm:grid-cols-2">
                                    {[
                                        'Painel web e aplicativo Android',
                                        'Carteira de clientes e regiões',
                                        'Catálogo e pedidos em campo',
                                        'Visitas, comissões e resultados',
                                    ].map((feature) => (
                                        <li
                                            key={feature}
                                            className="flex items-start gap-3 text-sm font-bold text-slate-800"
                                        >
                                            <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-violet-700 text-white">
                                                <Check className="size-3" strokeWidth={3} />
                                            </span>
                                            {feature}
                                        </li>
                                    ))}
                                </ul>
                                <a
                                    href={contactWhatsappUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-10 inline-flex items-center gap-2 text-sm font-black text-violet-800"
                                >
                                    Falar sobre esta solução{' '}
                                    <MessageCircle className="size-4" />
                                </a>
                            </div>

                            <div className="relative">
                                <div className="absolute top-4 right-4 -bottom-4 left-4 rounded-[2rem] bg-violet-700" />
                                <div className="relative rounded-[2rem] bg-slate-950 p-7 text-white shadow-2xl sm:p-10">
                                    <div className="flex items-center gap-4">
                                        <img
                                            src="/images/logo_pet.png"
                                            alt="VetorPet"
                                            className="size-14 rounded-2xl bg-white object-contain p-1"
                                        />
                                        <div>
                                            <p className="text-xs font-bold tracking-[0.14em] text-violet-300 uppercase">
                                                Gestão comercial especializada
                                            </p>
                                            <p className="mt-1 text-2xl font-black">
                                                Venda em campo com controle
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-8 grid gap-4 sm:grid-cols-2">
                                        {[
                                            ['Para o vendedor', 'Consulte clientes e produtos, registre visitas e monte pedidos pelo celular.'],
                                            ['Para o gestor', 'Organize equipe e regiões e acompanhe pedidos, comissões e resultados no painel.'],
                                        ].map(([title, description]) => (
                                            <div key={title} className="rounded-2xl border border-violet-300/20 bg-violet-300/10 p-6">
                                                <p className="font-black text-violet-200">{title}</p>
                                                <p className="mt-3 text-sm leading-6 text-slate-400">{description}</p>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-6 flex flex-col items-start justify-between gap-5 border-t border-white/10 pt-6 sm:flex-row sm:items-center">
                                        <div>
                                            <p className="font-black">Teste com a sua operação.</p>
                                            <p className="mt-1 text-sm text-slate-400">Comece sem cartão de crédito.</p>
                                        </div>
                                        <a
                                            href="https://vetorpet.com.br"
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex shrink-0 items-center gap-2 rounded-full bg-violet-300 px-5 py-3 text-sm font-black text-slate-950"
                                        >
                                            Criar minha conta <ArrowUpRight className="size-4" />
                                        </a>
                                    </div>
                                </div>
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
                            <SectionHeading
                                eyebrow="Ecossistema ABrasil"
                                title="Serviços para outros desafios digitais."
                                description="Soluções especializadas para vender, comunicar e transformar processos — sempre com tecnologia a serviço da operação."
                            />
                            <div className="mt-14 grid gap-5 lg:grid-cols-2">
                                {otherSolutions.map((solution) => {
                                    const Icon = solution.icon;
                                    const external =
                                        solution.href.startsWith('http');
                                    const color =
                                        solution.accent === 'violet'
                                            ? 'bg-violet-100 text-violet-700'
                                            : solution.accent === 'amber'
                                              ? 'bg-amber-100 text-amber-800'
                                              : 'bg-cyan-100 text-cyan-800';

                                    return (
                                        <a
                                            key={solution.title}
                                            href={solution.href}
                                            target={
                                                external ? '_blank' : undefined
                                            }
                                            rel={
                                                external
                                                    ? 'noreferrer'
                                                    : undefined
                                            }
                                            className="group flex min-h-[25rem] flex-col rounded-[1.75rem] border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:border-slate-300 hover:shadow-xl sm:p-9"
                                        >
                                            <span
                                                className={`grid size-12 place-items-center rounded-2xl ${color}`}
                                            >
                                                <Icon className="size-5" />
                                            </span>
                                            <p className="mt-9 text-xs font-extrabold tracking-[0.15em] text-slate-400 uppercase">
                                                {solution.eyebrow}
                                            </p>
                                            <h3 className="mt-3 text-3xl leading-tight font-black tracking-[-0.045em] text-slate-950">
                                                {solution.title}
                                            </h3>
                                            <p className="mt-4 flex-1 leading-7 text-slate-600">
                                                {solution.description}
                                            </p>
                                            <span className="mt-8 flex items-center justify-between border-t border-slate-200 pt-5 text-sm font-black text-slate-950">
                                                {solution.action}
                                                <ArrowUpRight className="size-4 transition group-hover:translate-x-1 group-hover:-translate-y-1" />
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
            </div>
        </>
    );
}
