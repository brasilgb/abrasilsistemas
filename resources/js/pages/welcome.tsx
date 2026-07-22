import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    Check,
    CheckCircle2,
    ClipboardCheck,
    Code2,
    Headphones,
    Mail,
    Menu,
    MessageCircle,
    MonitorSmartphone,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Users,
    X,
    Zap,
} from 'lucide-react';
import { useState } from 'react';

const whatsappUrl =
    'https://wa.me/5551998931325?text=Ol%C3%A1%2C%20gostaria%20de%20conhecer%20as%20solu%C3%A7%C3%B5es%20da%20ABrasil%20Sistemas.';

const solutions = [
    {
        id: 'vetoros',
        eyebrow: 'Assistências técnicas',
        name: 'VetorOS',
        headline: 'Sua assistência organizada do atendimento ao financeiro.',
        description:
            'Controle ordens de serviço, clientes, equipamentos, estoque, vendas e equipe em uma única plataforma.',
        url: 'https://vetoros.com.br',
        external: true,
        logo: '/images/logo_os.png',
        tone: 'blue',
        icon: ClipboardCheck,
        features: [
            'Ordens de serviço e orçamentos',
            'Estoque, caixa e financeiro',
            'Aplicativos para equipe e clientes',
        ],
    },
    {
        id: 'vetorpet',
        eyebrow: 'Mercado pet',
        name: 'VetorPet',
        headline: 'Mais agilidade para vender e atender o mercado pet.',
        description:
            'Organize clientes, catálogo, visitas e pedidos para sua equipe comercial vender melhor dentro e fora da empresa.',
        url: 'https://vetorpet.com.br',
        external: true,
        logo: '/images/logo_pet.png',
        tone: 'violet',
        icon: ShoppingCart,
        features: [
            'Carteira e histórico de clientes',
            'Catálogo e pedidos pelo celular',
            'Gestão da equipe comercial',
        ],
    },
] as const;

const services = [
    {
        icon: MonitorSmartphone,
        title: 'Sites para empresas',
        description:
            'Sites institucionais e landing pages que apresentam sua marca e geram oportunidades.',
        href: '/desenvolvimento-de-sites-para-empresas',
        linkLabel: 'Conhecer o serviço',
    },
    {
        icon: Code2,
        title: 'Sistemas sob medida',
        description:
            'Soluções personalizadas para digitalizar processos e atender necessidades específicas da operação.',
        href: whatsappUrl,
        linkLabel: 'Conversar sobre um projeto',
    },
    {
        icon: Smartphone,
        title: 'Aplicativos e integrações',
        description:
            'Aplicativos e conexões entre ferramentas para levar informação e produtividade a toda a equipe.',
        href: whatsappUrl,
        linkLabel: 'Falar com especialista',
    },
] as const;

const benefits = [
    {
        icon: Zap,
        title: 'Negócio antes da tecnologia',
        description:
            'Entendemos a operação e os objetivos antes de indicar ou desenvolver qualquer solução.',
    },
    {
        icon: BarChart3,
        title: 'Experiência em operações reais',
        description:
            'Nossos produtos nasceram de necessidades reais e evoluem com o uso diário das empresas.',
    },
    {
        icon: Users,
        title: 'Atendimento direto e próximo',
        description:
            'Você conversa com quem entende o projeto e acompanha as decisões do início à evolução.',
    },
    {
        icon: ShieldCheck,
        title: 'Tecnologia para evoluir',
        description:
            'Construímos bases seguras e sustentáveis para acompanhar as mudanças e o crescimento da empresa.',
    },
];

const steps = [
    {
        number: '01',
        title: 'Entendemos o desafio',
        description: 'Conversamos sobre sua operação, objetivos e prioridades.',
    },
    {
        number: '02',
        title: 'Indicamos o caminho',
        description: 'Identificamos a solução mais adequada para o seu momento.',
    },
    {
        number: '03',
        title: 'Colocamos para funcionar',
        description: 'Apoiamos a implantação para sua equipe aproveitar a tecnologia.',
    },
];

type BlogPostSummary = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    published_at: string;
    category?: { name: string; slug: string } | null;
};

function Logo({ footer = false }: { footer?: boolean }) {
    return (
        <Link href="/" className="flex items-center gap-3" aria-label="ABrasil Sistemas">
            <img
                src="/images/logo_ab.png"
                alt=""
                className="size-12 rounded-xl border border-slate-200 bg-white object-contain shadow-sm"
            />
            <div className="leading-none">
                <span className={`block font-bold ${footer ? 'text-white' : 'text-slate-950'}`}>
                    ABrasil Sistemas
                </span>
                <span className={`mt-1.5 block text-[8px] font-semibold tracking-[0.16em] uppercase ${footer ? 'text-slate-500' : 'text-slate-500'}`}>
                    Tecnologia para empresas
                </span>
            </div>
        </Link>
    );
}

export default function Welcome({ blogPosts }: { blogPosts: BlogPostSummary[] }) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const structuredData = {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: 'ABrasil Sistemas',
        description:
            'Sistemas de gestão, aplicativos e sites profissionais para empresas organizarem processos, venderem mais e crescerem.',
        logo: '/images/logo_ab.png',
        knowsAbout: [
            'Sistemas de gestão',
            'Ordens de serviço',
            'Gestão comercial',
            'Desenvolvimento de sites',
            'Desenvolvimento de software',
        ],
    };

    return (
        <>
            <Head title="Sistemas e sites para sua empresa crescer">
                <meta
                    name="description"
                    content="Sistemas de gestão, aplicativos e sites profissionais que ajudam sua empresa a organizar processos, vender mais e crescer. Conheça a ABrasil Sistemas."
                />
                <meta
                    name="keywords"
                    content="ABrasil Sistemas, sistema de gestão, ordem de serviço, gestão comercial, desenvolvimento de sites, VetorOS, VetorPet"
                />
                <meta name="robots" content="index, follow, max-image-preview:large" />
                <meta name="theme-color" content="#ffffff" />
                <meta property="og:locale" content="pt_BR" />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content="ABrasil Sistemas" />
                <meta property="og:title" content="ABrasil Sistemas — Tecnologia para sua empresa crescer" />
                <meta
                    property="og:description"
                    content="Soluções digitais para organizar sua operação, melhorar o atendimento e gerar novas oportunidades."
                />
                <meta property="og:image" content="/images/logo_ab.png" />
                <meta name="twitter:card" content="summary_large_image" />
                <script type="application/ld+json">{JSON.stringify(structuredData)}</script>
            </Head>

            <div className="min-h-screen bg-white text-slate-900 selection:bg-blue-100 selection:text-blue-950">
                <header className="fixed inset-x-0 top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-12">
                        <Logo />
                        <nav className="hidden items-center gap-8 text-sm font-semibold text-slate-600 lg:flex">
                            <a href="#solucoes" className="transition hover:text-blue-700">Soluções</a>
                            <a href="#servicos" className="transition hover:text-blue-700">Serviços</a>
                            <a href="#diferenciais" className="transition hover:text-blue-700">Diferenciais</a>
                            <Link href="/blog" className="transition hover:text-blue-700">Blog</Link>
                        </nav>
                        <div className="hidden items-center gap-3 sm:flex">
                            <Link
                                href="/area-restrita"
                                className="px-3 py-2 text-sm font-semibold text-slate-600 transition hover:text-slate-950"
                            >
                                Área restrita
                            </Link>
                            <a
                                href={whatsappUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800"
                            >
                                <MessageCircle className="size-4" />
                                Falar com especialista
                            </a>
                        </div>
                        <button
                            type="button"
                            aria-label="Abrir menu"
                            className="grid size-10 place-items-center rounded-lg border border-slate-200 lg:hidden"
                            onClick={() => setMobileMenuOpen((open) => !open)}
                        >
                            {mobileMenuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                        </button>
                    </div>
                    {mobileMenuOpen && (
                        <div className="border-t border-slate-100 bg-white px-5 py-5 lg:hidden">
                            <nav className="grid gap-1 text-sm font-semibold">
                                <a href="#solucoes" className="rounded-lg px-3 py-3 hover:bg-slate-50" onClick={() => setMobileMenuOpen(false)}>Soluções</a>
                                <a href="#servicos" className="rounded-lg px-3 py-3 hover:bg-slate-50" onClick={() => setMobileMenuOpen(false)}>Serviços</a>
                                <a href="#diferenciais" className="rounded-lg px-3 py-3 hover:bg-slate-50" onClick={() => setMobileMenuOpen(false)}>Diferenciais</a>
                                <Link href="/blog" className="rounded-lg px-3 py-3 hover:bg-slate-50">Blog</Link>
                                <Link href="/area-restrita" className="rounded-lg px-3 py-3 hover:bg-slate-50">Área restrita</Link>
                                <a href={whatsappUrl} target="_blank" rel="noreferrer" className="mt-2 inline-flex items-center justify-center gap-2 rounded-lg bg-blue-700 px-4 py-3 text-white">
                                    <MessageCircle className="size-4" /> Falar com especialista
                                </a>
                            </nav>
                        </div>
                    )}
                </header>

                <main>
                    <section className="relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
                        <div className="absolute inset-x-0 top-0 -z-10 h-[44rem] bg-gradient-to-b from-blue-50 via-white to-white" />
                        <div className="absolute top-32 right-[-12rem] -z-10 size-[34rem] rounded-full bg-cyan-100/60 blur-3xl" />
                        <div className="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-[1.04fr_0.96fr] lg:px-12">
                            <div>
                                <div className="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm">
                                    <CheckCircle2 className="size-4" />
                                    Tecnologia simples para negócios reais
                                </div>
                                <h1 className="mt-7 max-w-3xl text-5xl leading-[1.04] font-bold tracking-[-0.05em] text-balance text-slate-950 sm:text-7xl">
                                    Sua empresa mais organizada, produtiva e pronta para{' '}
                                    <span className="text-blue-700">crescer.</span>
                                </h1>
                                <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-600 sm:text-xl">
                                    Criamos sistemas, aplicativos e sites que simplificam a operação, melhoram o atendimento e ajudam sua empresa a vender mais.
                                </p>
                                <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                    <a href={whatsappUrl} target="_blank" rel="noreferrer" className="inline-flex h-13 items-center justify-center gap-2 rounded-lg bg-blue-700 px-6 text-sm font-bold text-white shadow-lg shadow-blue-700/15 transition hover:-translate-y-0.5 hover:bg-blue-800">
                                        Conversar sobre minha empresa
                                        <ArrowRight className="size-4" />
                                    </a>
                                    <a href="#solucoes" className="inline-flex h-13 items-center justify-center rounded-lg border border-slate-300 bg-white px-6 text-sm font-bold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                                        Conhecer as soluções
                                    </a>
                                </div>
                                <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-500">
                                    {['Atendimento próximo', 'Soluções brasileiras', 'Tecnologia segura'].map((item) => (
                                        <span key={item} className="inline-flex items-center gap-2">
                                            <Check className="size-4 text-emerald-600" strokeWidth={3} />
                                            {item}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            <div className="relative mx-auto w-full max-w-xl">
                                <div className="rounded-3xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/10 sm:p-6">
                                    <div className="rounded-2xl bg-slate-950 p-6 text-white sm:p-8">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-xs font-semibold text-blue-300">Visão da operação</p>
                                                <p className="mt-1 text-xl font-bold">Tudo em um só lugar</p>
                                            </div>
                                            <BarChart3 className="size-7 text-blue-300" />
                                        </div>
                                        <div className="mt-8 grid grid-cols-3 gap-3">
                                            {[
                                                ['Atendimentos', '128'],
                                                ['Em andamento', '34'],
                                                ['Concluídos', '94'],
                                            ].map(([label, value]) => (
                                                <div key={label} className="rounded-xl bg-white/8 p-3 sm:p-4">
                                                    <p className="text-lg font-bold sm:text-2xl">{value}</p>
                                                    <p className="mt-1 text-[10px] text-slate-400 sm:text-xs">{label}</p>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="mt-4 rounded-xl bg-white/8 p-4">
                                            <div className="flex h-28 items-end gap-2">
                                                {[32, 45, 38, 62, 55, 78, 90, 72, 88, 100].map((height, index) => (
                                                    <div key={index} className="flex-1 rounded-t bg-blue-400" style={{ height: `${height}%`, opacity: 0.45 + index * 0.05 }} />
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="mt-4 grid grid-cols-2 gap-3">
                                        <div className="rounded-xl border border-slate-200 p-4">
                                            <Smartphone className="size-5 text-blue-700" />
                                            <p className="mt-3 text-sm font-bold">Acesse de onde estiver</p>
                                        </div>
                                        <div className="rounded-xl border border-slate-200 p-4">
                                            <Headphones className="size-5 text-emerald-600" />
                                            <p className="mt-3 text-sm font-bold">Conte com nosso suporte</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-y border-slate-200 bg-slate-50">
                        <div className="mx-auto grid max-w-7xl divide-y divide-slate-200 px-5 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:px-8 lg:px-12">
                            {[
                                ['Organize', 'Centralize informações e acompanhe sua operação.'],
                                ['Simplifique', 'Reduza tarefas manuais e ganhe produtividade.'],
                                ['Cresça', 'Atenda melhor e encontre novas oportunidades.'],
                            ].map(([title, description]) => (
                                <div key={title} className="py-7 sm:px-7 lg:px-10">
                                    <p className="text-lg font-bold text-slate-950">{title}</p>
                                    <p className="mt-1 text-sm leading-6 text-slate-600">{description}</p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section id="solucoes" className="scroll-mt-20 py-24 sm:py-32">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="max-w-3xl">
                                <p className="text-sm font-bold text-blue-700">Produtos próprios</p>
                                <h2 className="mt-3 text-4xl font-bold tracking-[-0.04em] text-balance text-slate-950 sm:text-6xl">
                                    Tecnologia que resolve o que importa.
                                </h2>
                                <p className="mt-5 text-lg leading-8 text-slate-600">
                                    Produtos próprios, criados a partir de operações reais e especializados em desafios específicos.
                                </p>
                            </div>
                            <div className="mt-14 grid gap-6 lg:grid-cols-2">
                                {solutions.map((solution) => {
                                    const Icon = solution.icon;
                                    const content = (
                                        <>
                                            <div className="flex items-start justify-between gap-4">
                                                {solution.logo ? (
                                                    <div className="grid size-16 place-items-center rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
                                                        <img src={solution.logo} alt={`Logo ${solution.name}`} className="size-full object-contain" />
                                                    </div>
                                                ) : (
                                                    <div className="grid size-16 place-items-center rounded-2xl bg-emerald-50 text-emerald-700">
                                                        <Icon className="size-7" />
                                                    </div>
                                                )}
                                                <ArrowUpRight className="size-5 text-slate-400 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-blue-700" />
                                            </div>
                                            <p className="mt-7 text-xs font-bold tracking-[0.12em] text-blue-700 uppercase">{solution.eyebrow}</p>
                                            <h3 className="mt-2 text-2xl font-bold text-slate-950">{solution.name}</h3>
                                            <h4 className="mt-5 text-xl leading-7 font-semibold text-slate-900">{solution.headline}</h4>
                                            <p className="mt-3 text-sm leading-6 text-slate-600">{solution.description}</p>
                                            <ul className="mt-7 space-y-3 border-t border-slate-200 pt-6">
                                                {solution.features.map((feature) => (
                                                    <li key={feature} className="flex items-start gap-2.5 text-sm text-slate-700">
                                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                        {feature}
                                                    </li>
                                                ))}
                                            </ul>
                                            <span className="mt-8 inline-flex items-center gap-2 text-sm font-bold text-blue-700">
                                                Conhecer solução <ArrowRight className="size-4" />
                                            </span>
                                        </>
                                    );

                                    const className =
                                        'group flex flex-col rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:border-blue-200 hover:shadow-xl hover:shadow-slate-900/8';

                                    return solution.external ? (
                                        <a key={solution.id} href={solution.url} target="_blank" rel="noreferrer" className={className}>
                                            {content}
                                        </a>
                                    ) : (
                                        <Link key={solution.id} href={solution.url} className={className}>
                                            {content}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section id="servicos" className="scroll-mt-20 border-y border-slate-200 bg-slate-50 py-24 sm:py-32">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="max-w-3xl">
                                <p className="text-sm font-bold text-blue-700">Serviços</p>
                                <h2 className="mt-3 text-4xl font-bold tracking-[-0.04em] text-balance text-slate-950 sm:text-6xl">
                                    Tecnologia construída para a realidade da sua empresa.
                                </h2>
                                <p className="mt-5 text-lg leading-8 text-slate-600">
                                    Quando um produto pronto não resolve, planejamos e desenvolvemos o projeto certo para seu objetivo.
                                </p>
                            </div>
                            <div className="mt-14 grid gap-6 lg:grid-cols-3">
                                {services.map((service) => {
                                    const Icon = service.icon;
                                    const external = service.href.startsWith('http');

                                    return (
                                        <a
                                            key={service.title}
                                            href={service.href}
                                            target={external ? '_blank' : undefined}
                                            rel={external ? 'noreferrer' : undefined}
                                            className="group rounded-2xl border border-slate-200 bg-white p-7 transition hover:-translate-y-1 hover:border-blue-200 hover:shadow-lg"
                                        >
                                            <div className="grid size-12 place-items-center rounded-xl bg-blue-50 text-blue-700">
                                                <Icon className="size-6" />
                                            </div>
                                            <h3 className="mt-6 text-2xl font-bold text-slate-950">
                                                {service.title}
                                            </h3>
                                            <p className="mt-3 text-sm leading-6 text-slate-600">
                                                {service.description}
                                            </p>
                                            <span className="mt-7 inline-flex items-center gap-2 text-sm font-bold text-blue-700">
                                                {service.linkLabel}
                                                <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                            </span>
                                        </a>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section id="diferenciais" className="scroll-mt-20 bg-slate-950 py-24 text-white sm:py-32">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:items-start">
                                <div>
                                    <p className="text-sm font-bold text-blue-300">Diferenciais da ABrasil Sistemas</p>
                                    <h2 className="mt-3 text-4xl font-bold tracking-[-0.04em] text-balance sm:text-5xl">
                                        Tecnologia sem complicação.
                                    </h2>
                                    <p className="mt-5 max-w-xl text-base leading-7 text-slate-400">
                                        Unimos conhecimento técnico e entendimento do negócio para entregar soluções que as pessoas realmente conseguem usar.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {benefits.map((benefit) => {
                                        const Icon = benefit.icon;
                                        return (
                                            <article key={benefit.title} className="rounded-2xl border border-white/10 bg-white/5 p-6">
                                                <Icon className="size-6 text-blue-300" />
                                                <h3 className="mt-5 text-lg font-bold">{benefit.title}</h3>
                                                <p className="mt-2 text-sm leading-6 text-slate-400">{benefit.description}</p>
                                            </article>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="py-24 sm:py-32">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="text-center">
                                <p className="text-sm font-bold text-blue-700">Como trabalhamos</p>
                                <h2 className="mt-3 text-4xl font-bold tracking-[-0.04em] text-slate-950 sm:text-5xl">Do desafio à solução em funcionamento.</h2>
                            </div>
                            <div className="relative mt-14 grid gap-5 md:grid-cols-3">
                                {steps.map((step) => (
                                    <article key={step.number} className="rounded-2xl border border-slate-200 bg-white p-7 text-center">
                                        <span className="mx-auto grid size-11 place-items-center rounded-full bg-blue-50 text-sm font-bold text-blue-700">{step.number}</span>
                                        <h3 className="mt-5 text-xl font-bold text-slate-950">{step.title}</h3>
                                        <p className="mt-2 text-sm leading-6 text-slate-600">{step.description}</p>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    {blogPosts.length > 0 && (
                        <section className="border-y border-slate-200 bg-slate-50 py-24">
                            <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                                    <div>
                                        <p className="text-sm font-bold text-blue-700">Blog da ABrasil Sistemas</p>
                                        <h2 className="mt-2 text-3xl font-bold tracking-tight text-slate-950">Ideias para melhorar sua gestão</h2>
                                    </div>
                                    <Link href="/blog" className="inline-flex items-center gap-2 text-sm font-bold text-blue-700">Ver todos os artigos <ArrowRight className="size-4" /></Link>
                                </div>
                                <div className="mt-10 grid gap-5 md:grid-cols-3">
                                    {blogPosts.slice(0, 3).map((post) => (
                                        <Link key={post.id} href={`/blog/${post.slug}`} className="group rounded-2xl border border-slate-200 bg-white p-6 transition hover:border-blue-200 hover:shadow-lg">
                                            <time className="text-xs font-semibold text-slate-500">
                                                {new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long' }).format(new Date(post.published_at))}
                                            </time>
                                            <h3 className="mt-3 text-lg leading-6 font-bold text-slate-950 group-hover:text-blue-700">{post.title}</h3>
                                            <p className="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{post.excerpt}</p>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}

                    <section className="px-5 py-24 sm:px-8 sm:py-32 lg:px-12">
                        <div className="mx-auto max-w-7xl overflow-hidden rounded-3xl bg-blue-700 px-7 py-14 text-center text-white shadow-2xl shadow-blue-700/15 sm:px-12 sm:py-20">
                            <Code2 className="mx-auto size-8 text-blue-200" />
                            <h2 className="mx-auto mt-5 max-w-3xl text-4xl font-bold tracking-[-0.04em] text-balance sm:text-6xl">
                                Pronto para dar o próximo passo?
                            </h2>
                            <p className="mx-auto mt-5 max-w-2xl text-base leading-7 text-blue-100 sm:text-lg">
                                Conte o que sua empresa precisa. Vamos conversar sem compromisso e indicar o melhor caminho.
                            </p>
                            <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                                <a href={whatsappUrl} target="_blank" rel="noreferrer" className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-6 py-3.5 text-sm font-bold text-blue-800 transition hover:bg-blue-50">
                                    <MessageCircle className="size-4" /> Falar pelo WhatsApp
                                </a>
                                <a href="mailto:contato@absistemas.com.br?subject=Quero conhecer as soluções da ABrasil Sistemas" className="inline-flex items-center justify-center gap-2 rounded-lg border border-white/30 px-6 py-3.5 text-sm font-bold transition hover:bg-white/10">
                                    <Mail className="size-4" /> Enviar e-mail
                                </a>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="bg-slate-950 text-white">
                    <div className="mx-auto grid max-w-7xl gap-10 px-5 py-12 sm:px-8 md:grid-cols-3 lg:px-12">
                        <div>
                            <Logo footer />
                            <p className="mt-5 max-w-sm text-sm leading-6 text-slate-400">
                                Sistemas, aplicativos e sites que ajudam empresas a trabalhar melhor e crescer.
                            </p>
                        </div>
                        <div>
                            <p className="text-sm font-bold">Produtos</p>
                            <div className="mt-4 grid gap-3 text-sm text-slate-400">
                                <a href="https://vetoros.com.br" target="_blank" rel="noreferrer" className="hover:text-white">VetorOS</a>
                                <a href="https://vetorpet.com.br" target="_blank" rel="noreferrer" className="hover:text-white">VetorPet</a>
                            </div>
                            <p className="mt-6 text-sm font-bold">Serviços</p>
                            <div className="mt-4 grid gap-3 text-sm text-slate-400">
                                <Link href="/desenvolvimento-de-sites-para-empresas" className="hover:text-white">Sites para empresas</Link>
                                <a href="#servicos" className="hover:text-white">Sistemas, aplicativos e integrações</a>
                            </div>
                        </div>
                        <div className="md:text-right">
                            <p className="text-sm font-bold">Fale conosco</p>
                            <div className="mt-4 grid gap-3 text-sm text-slate-400 md:justify-items-end">
                                <a href="mailto:contato@absistemas.com.br" className="inline-flex items-center gap-2 hover:text-white"><Mail className="size-4" /> contato@absistemas.com.br</a>
                                <a href={whatsappUrl} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 hover:text-white"><MessageCircle className="size-4" /> (51) 99893-1325</a>
                            </div>
                        </div>
                    </div>
                    <div className="border-t border-white/10 py-5 text-center text-xs text-slate-500">
                        ABrasil Sistemas · Tecnologia para empresas
                    </div>
                </footer>
            </div>
        </>
    );
}
