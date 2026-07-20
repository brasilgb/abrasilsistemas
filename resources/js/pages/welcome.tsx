import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    Blocks,
    Check,
    ClipboardCheck,
    Cloud,
    Code2,
    Headphones,
    Layers3,
    Lightbulb,
    Mail,
    MessageCircle,
    Package,
    Rocket,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Sparkles,
    Users,
} from 'lucide-react';
import { dashboard, login } from '@/routes';
import type { User } from '@/types';

const products = [
    {
        id: 'vetoros',
        name: 'VetorOS',
        url: 'https://vetoros.com.br',
        domain: 'vetoros.com.br',
        label: 'Gestão para assistências técnicas',
        headline: 'Da recepção ao pós-serviço, tudo sob controle.',
        description:
            'Uma plataforma web completa para organizar atendimento, ordens de serviço, bancada, equipe em campo, estoque, vendas, financeiro e relacionamento com clientes.',
        logo: '/images/logo_os.png',
        logoAlt: 'Logo do VetorOS',
        tone: 'sky',
        features: [
            'Ordens de serviço e orçamentos',
            'Clientes, equipamentos e estoque',
            'Financeiro, caixa e notas fiscais',
            'Apps para atendimento, imagens e técnicos',
        ],
        stats: [
            { icon: ClipboardCheck, text: 'Fluxo completo de OS' },
            { icon: Smartphone, text: 'Web + apps móveis' },
            { icon: BarChart3, text: 'Gestão e indicadores' },
        ],
    },
    {
        id: 'vetorpet',
        name: 'VetorPet',
        url: 'https://vetorpet.com.br',
        domain: 'vetorpet.com.br',
        label: 'Vendas para o mercado pet',
        headline: 'Da visita ao pedido, uma operação comercial conectada.',
        description:
            'Feito para distribuidores, representantes e vendedores de suprimentos para pet shops, clínicas veterinárias e agropecuárias venderem com mais organização e agilidade.',
        logo: '/images/logo_pet.png',
        logoAlt: 'Logo do VetorPet',
        tone: 'violet',
        features: [
            'Carteira de clientes e regiões',
            'Catálogo de produtos e preços',
            'Agenda de visitas e pedidos em campo',
            'Painel de gestão e aplicativo Android',
        ],
        stats: [
            { icon: Users, text: 'Equipe ou vendedor individual' },
            { icon: Package, text: 'Catálogo sempre à mão' },
            { icon: ShoppingCart, text: 'Pedidos pelo celular' },
        ],
    },
];

const principles = [
    {
        icon: Layers3,
        title: 'Tudo conectado',
        description:
            'Produtos que unem operação, pessoas e informação em fluxos simples e consistentes.',
    },
    {
        icon: Cloud,
        title: 'Pronto para crescer',
        description:
            'Tecnologia em nuvem para acompanhar a evolução da empresa, do primeiro acesso à expansão da equipe.',
    },
    {
        icon: ShieldCheck,
        title: 'Dados protegidos',
        description:
            'Ambientes separados por empresa, permissões por usuário e cuidado em cada camada da operação.',
    },
    {
        icon: Headphones,
        title: 'Experiência próxima',
        description:
            'Soluções brasileiras, linguagem clara e uma experiência pensada para a realidade de quem trabalha.',
    },
];

const customDevelopmentSteps = [
    {
        number: '01',
        icon: Lightbulb,
        title: 'Entendemos a ideia',
        description:
            'Mergulhamos no problema, no público e nos objetivos para transformar uma visão inicial em um projeto claro e viável.',
    },
    {
        number: '02',
        icon: Blocks,
        title: 'Desenhamos a solução',
        description:
            'Definimos experiência, funcionalidades e arquitetura para construir o produto certo, sem complexidade desnecessária.',
    },
    {
        number: '03',
        icon: Code2,
        title: 'Construímos o produto',
        description:
            'Desenvolvemos sistemas web e aplicativos com foco em qualidade, segurança, desempenho e facilidade de uso.',
    },
    {
        number: '04',
        icon: Rocket,
        title: 'Lançamos e evoluímos',
        description:
            'Colocamos a solução em operação e seguimos aprimorando o produto conforme o negócio e os usuários evoluem.',
    },
];

const blogPosts = [
    {
        id: 1,
        title: 'Como um sistema de gestão transforma sua assistência técnica',
        category: 'Gestão',
        date: '18 de Julho, 2026',
        description:
            'Descubra como a tecnologia pode otimizar processos, da ordem de serviço ao controle financeiro.',
        href: '#',
    },
    {
        id: 2,
        title: 'O guia completo para automatizar vendas no mercado pet',
        category: 'Vendas',
        date: '15 de Julho, 2026',
        description:
            'Aumente a produtividade da sua equipe comercial com ferramentas que simplificam a rotina de vendas.',
        href: '#',
    },
];

function BrandMark() {
    return (
        <a
            href="#inicio"
            className="flex items-center gap-2"
            aria-label="AB Sistemas — início"
        >
            <img
                src="/images/logo_ab.png"
                alt=""
                className="size-14 rounded-2xl border border-white/60 object-contain shadow-lg shadow-black/25 sm:size-16"
            />
            <div className="leading-none">
                <span className="block text-base font-bold tracking-tight text-white sm:text-lg">
                    AB Sistemas
                </span>
                <span className="mt-1.5 block text-[8px] font-medium tracking-[0.18em] text-slate-400 uppercase sm:text-[9px]">
                    Tecnologia que transforma negócios
                </span>
            </div>
        </a>
    );
}

export default function Welcome() {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const user = auth.user;

    const structuredData = {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: 'AB Sistemas',
        alternateName: 'ABrasilSistemas',
        description:
            'Empresa brasileira de tecnologia especializada em plataformas de gestão para assistências técnicas e operações comerciais do mercado pet.',
        logo: '/images/logo_ab.png',
        brand: products.map((product) => ({
            '@type': 'Brand',
            name: product.name,
            url: product.url,
            description: product.description,
        })),
        knowsAbout: [
            'Desenvolvimento de software',
            'Gestão de assistências técnicas',
            'Ordens de serviço',
            'Automação de vendas',
            'Gestão comercial para o mercado pet',
        ],
    };

    return (
        <>
            <Head title="Sistemas de gestão para negócios que querem crescer">
                <meta
                    name="description"
                    content="A AB Sistemas desenvolve plataformas de gestão para assistências técnicas e operações comerciais do mercado pet. Conheça VetorOS e VetorPet."
                />
                <meta
                    name="keywords"
                    content="AB Sistemas, software de gestão, sistema para assistência técnica, ordem de serviço, gestão comercial, representantes comerciais, mercado pet, VetorOS, VetorPet"
                />
                <meta name="author" content="AB Sistemas" />
                <meta
                    name="robots"
                    content="index, follow, max-image-preview:large"
                />
                <meta name="theme-color" content="#060d19" />

                <meta property="og:locale" content="pt_BR" />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content="AB Sistemas" />
                <meta
                    property="og:title"
                    content="AB Sistemas — Tecnologia que transforma negócios"
                />
                <meta
                    property="og:description"
                    content="Plataformas brasileiras que organizam operações, conectam equipes e transformam processos em resultados."
                />
                <meta property="og:image" content="/images/logo_ab.png" />
                <meta property="og:image:alt" content="Marca da AB Sistemas" />

                <meta name="twitter:card" content="summary_large_image" />
                <meta
                    name="twitter:title"
                    content="AB Sistemas — Tecnologia que transforma negócios"
                />
                <meta
                    name="twitter:description"
                    content="Conheça VetorOS e VetorPet, soluções digitais criadas para operações que querem trabalhar melhor e crescer."
                />
                <meta name="twitter:image" content="/images/logo_ab.png" />

                <script type="application/ld+json">
                    {JSON.stringify(structuredData)}
                </script>
            </Head>

            <div className="min-h-screen overflow-hidden bg-[#060d19] text-white selection:bg-sky-300 selection:text-slate-950">
                <header className="fixed inset-x-0 top-0 z-50 border-b border-white/8 bg-[#060d19]/80 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-12">
                        <BrandMark />
                        <nav className="hidden items-center gap-8 text-sm font-medium text-slate-400 md:flex">
                            <a
                                href="#solucoes"
                                className="transition hover:text-white"
                            >
                                Soluções
                            </a>
                            <a
                                href="#sob-medida"
                                className="transition hover:text-white"
                            >
                                Sob medida
                            </a>
                            <a
                                href="#tecnologia"
                                className="transition hover:text-white"
                            >
                                Nossa visão
                            </a>
                            <a href="#blog" className="transition hover:text-white">
                                Blog
                            </a>
                        </nav>
                        <Link
                            href={user ? dashboard() : login()}
                            className="inline-flex min-w-0 items-center gap-2 rounded-full border border-white/12 bg-white/6 px-3 py-2 text-xs font-semibold transition hover:border-sky-300/40 hover:bg-white/10 sm:px-4"
                        >
                            <ShieldCheck className="size-3.5 shrink-0 text-sky-300" />
                            <span className="hidden sm:inline">
                                Área restrita
                            </span>
                            {user && (
                                <span className="max-w-28 truncate text-sky-100 sm:max-w-40">
                                    {user.name}
                                </span>
                            )}
                            <ArrowRight className="size-3.5 shrink-0" />
                        </Link>
                    </div>
                </header>

                <main>
                    <section
                        id="inicio"
                        className="relative flex min-h-[92svh] items-center pt-28"
                    >
                        <div className="pointer-events-none absolute inset-0 overflow-hidden">
                            <div className="absolute top-[-14rem] left-[-12rem] size-[42rem] rounded-full bg-blue-600/18 blur-[130px]" />
                            <div className="absolute top-[16%] right-[-18rem] size-[42rem] rounded-full bg-violet-600/12 blur-[140px]" />
                            <div className="absolute inset-0 bg-[linear-gradient(rgba(148,163,184,0.045)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.045)_1px,transparent_1px)] [mask-image:linear-gradient(to_bottom,black,transparent_85%)] bg-[size:72px_72px]" />
                            <div className="absolute top-1/2 left-1/2 h-px w-[70%] -translate-x-1/2 rotate-[-18deg] bg-gradient-to-r from-transparent via-sky-400/30 to-transparent" />
                        </div>

                        <div className="relative mx-auto grid w-full max-w-7xl items-center gap-14 px-5 py-20 sm:px-8 lg:grid-cols-[1.12fr_0.88fr] lg:px-12">
                            <div>
                                <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-sky-300/20 bg-sky-300/6 px-4 py-2 text-[10px] font-bold tracking-[0.2em] text-sky-200 uppercase sm:text-xs">
                                    <Sparkles className="size-3.5" />
                                    Software brasileiro para negócios reais
                                </div>
                                <h1 className="max-w-4xl text-5xl leading-[0.98] font-semibold tracking-[-0.055em] text-balance sm:text-7xl lg:text-[5.4rem]">
                                    Tecnologia que transforma{' '}
                                    <span className="bg-gradient-to-r from-sky-300 via-blue-400 to-violet-400 bg-clip-text text-transparent">
                                        complexidade
                                    </span>{' '}
                                    em direção.
                                </h1>
                                <p className="mt-8 max-w-2xl text-base leading-7 text-slate-400 sm:text-xl sm:leading-8">
                                    Na AB Sistemas, criamos plataformas que
                                    organizam rotinas, conectam equipes e dão
                                    clareza para empresas tomarem decisões
                                    melhores.
                                </p>
                                <div className="mt-10 flex flex-col gap-3 sm:flex-row">
                                    <a
                                        href="#solucoes"
                                        className="inline-flex h-13 items-center justify-center gap-2 rounded-xl bg-white px-6 text-sm font-bold text-slate-950 transition hover:bg-sky-100"
                                    >
                                        Explorar soluções
                                        <ArrowRight className="size-4" />
                                    </a>
                                    <div className="flex h-13 items-center gap-3 px-2 text-sm text-slate-400">
                                        <span className="relative flex size-2">
                                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60" />
                                            <span className="relative inline-flex size-2 rounded-full bg-emerald-400" />
                                        </span>
                                        Produtos ativos e em evolução
                                    </div>
                                </div>
                            </div>

                            <div className="relative mx-auto w-full max-w-lg lg:max-w-none">
                                <div className="absolute inset-12 rounded-full bg-sky-500/15 blur-3xl" />
                                <div className="relative grid gap-3 rounded-[2rem] border border-white/10 bg-white/[0.035] p-3 shadow-2xl shadow-black/30 backdrop-blur-sm">
                                    {products.map((product, index) => (
                                        <a
                                            key={product.id}
                                            href={`#${product.id}`}
                                            className="group flex items-center gap-4 rounded-2xl border border-white/8 bg-[#0a1424]/80 p-4 transition hover:border-sky-300/25 hover:bg-white/[0.06] sm:p-5"
                                        >
                                            <div className="grid size-16 shrink-0 place-items-center overflow-hidden rounded-2xl border border-white/80 bg-white p-1.5 sm:size-20">
                                                <img
                                                    src={product.logo}
                                                    alt={product.logoAlt}
                                                    className="size-full object-contain"
                                                />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <span className="text-[9px] font-bold tracking-[0.17em] text-slate-500 uppercase">
                                                    Solução 0{index + 1}
                                                </span>
                                                <h2 className="mt-1 text-xl font-bold tracking-tight sm:text-2xl">
                                                    {product.name}
                                                </h2>
                                                <p className="mt-1 truncate text-xs text-slate-400 sm:text-sm">
                                                    {product.label}
                                                </p>
                                            </div>
                                            <ArrowUpRight className="size-5 shrink-0 text-slate-600 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-sky-300" />
                                        </a>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="solucoes"
                        className="border-y border-white/8 bg-[#08111f] py-24 sm:py-32"
                    >
                        <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="max-w-3xl">
                                <p className="text-xs font-bold tracking-[0.22em] text-sky-300 uppercase">
                                    Nosso ecossistema
                                </p>
                                <h2 className="mt-4 text-4xl font-semibold tracking-[-0.04em] text-balance sm:text-6xl">
                                    Soluções especializadas. Resultados
                                    concretos.
                                </h2>
                                <p className="mt-6 text-lg leading-8 text-slate-400">
                                    Cada produto nasce de uma operação real e
                                    resolve desafios específicos com
                                    profundidade, simplicidade e foco em
                                    produtividade.
                                </p>
                            </div>

                            <div className="mt-16 space-y-8">
                                {products.map((product, index) => {
                                    return (
                                        <article
                                            id={product.id}
                                            key={product.id}
                                            className="scroll-mt-28 overflow-hidden rounded-[2rem] border border-white/10 bg-[#0b1627] shadow-2xl shadow-black/20"
                                        >
                                            <div className="grid lg:grid-cols-[0.92fr_1.08fr]">
                                                <div
                                                    className={`relative overflow-hidden p-7 sm:p-10 lg:p-12 ${index === 1 ? 'lg:order-2' : ''}`}
                                                >
                                                    <div
                                                        className={`absolute inset-0 ${product.tone === 'sky' ? 'bg-gradient-to-br from-sky-500/12 via-transparent to-blue-600/8' : 'bg-gradient-to-br from-violet-500/12 via-transparent to-fuchsia-600/8'}`}
                                                    />
                                                    <div className="relative">
                                                        <div className="flex items-center gap-4">
                                                            <div className="grid size-20 place-items-center overflow-hidden rounded-2xl border border-white/80 bg-white p-1.5 shadow-xl sm:size-24">
                                                                <img
                                                                    src={
                                                                        product.logo
                                                                    }
                                                                    alt={
                                                                        product.logoAlt
                                                                    }
                                                                    className="size-full object-contain"
                                                                />
                                                            </div>
                                                            <div>
                                                                <p
                                                                    className={`text-[10px] font-bold tracking-[0.2em] uppercase ${product.tone === 'sky' ? 'text-sky-300' : 'text-violet-300'}`}
                                                                >
                                                                    {
                                                                        product.label
                                                                    }
                                                                </p>
                                                                <h3 className="mt-1 text-3xl font-bold tracking-tight sm:text-4xl">
                                                                    {
                                                                        product.name
                                                                    }
                                                                </h3>
                                                            </div>
                                                        </div>
                                                        <h4 className="mt-9 max-w-xl text-3xl leading-tight font-semibold tracking-[-0.03em] text-balance sm:text-4xl">
                                                            {product.headline}
                                                        </h4>
                                                        <p className="mt-5 max-w-xl text-base leading-7 text-slate-400">
                                                            {
                                                                product.description
                                                            }
                                                        </p>
                                                        <a
                                                            href={product.url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className={`mt-8 inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-bold text-slate-950 transition ${product.tone === 'sky' ? 'bg-sky-300 hover:bg-sky-200' : 'bg-violet-300 hover:bg-violet-200'}`}
                                                        >
                                                            Conhecer{' '}
                                                            {product.name}
                                                            <ArrowUpRight className="size-4" />
                                                        </a>
                                                        <p className="mt-3 text-xs text-slate-600">
                                                            {product.domain}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div
                                                    className={`border-white/8 bg-[#07101d] p-7 sm:p-10 lg:p-12 ${index === 1 ? 'lg:order-1 lg:border-r' : 'lg:border-l'}`}
                                                >
                                                    <div className="grid grid-cols-3 gap-2 sm:gap-3">
                                                        {product.stats.map(
                                                            (stat) => {
                                                                const StatIcon =
                                                                    stat.icon;
                                                                return (
                                                                    <div
                                                                        key={
                                                                            stat.text
                                                                        }
                                                                        className="rounded-2xl border border-white/8 bg-white/[0.035] p-3 sm:p-4"
                                                                    >
                                                                        <StatIcon
                                                                            className={`size-5 ${product.tone === 'sky' ? 'text-sky-300' : 'text-violet-300'}`}
                                                                        />
                                                                        <p className="mt-4 text-[10px] leading-4 font-semibold text-slate-300 sm:text-xs sm:leading-5">
                                                                            {
                                                                                stat.text
                                                                            }
                                                                        </p>
                                                                    </div>
                                                                );
                                                            },
                                                        )}
                                                    </div>
                                                    <div className="mt-8">
                                                        <p className="mb-5 text-[10px] font-bold tracking-[0.2em] text-slate-500 uppercase">
                                                            Principais recursos
                                                        </p>
                                                        <ul className="grid gap-4 sm:grid-cols-2">
                                                            {product.features.map(
                                                                (feature) => (
                                                                    <li
                                                                        key={
                                                                            feature
                                                                        }
                                                                        className="flex items-start gap-3 text-sm leading-6 text-slate-300"
                                                                    >
                                                                        <span
                                                                            className={`mt-1 grid size-5 shrink-0 place-items-center rounded-full ${product.tone === 'sky' ? 'bg-sky-400/12 text-sky-300' : 'bg-violet-400/12 text-violet-300'}`}
                                                                        >
                                                                            <Check
                                                                                className="size-3"
                                                                                strokeWidth={
                                                                                    3
                                                                                }
                                                                            />
                                                                        </span>
                                                                        {
                                                                            feature
                                                                        }
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section
                        id="tecnologia"
                        className="relative py-24 sm:py-32"
                    >
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_0%,rgba(14,165,233,0.08),transparent_45%)]" />
                        <div className="relative mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:items-end">
                                <div>
                                    <p className="text-xs font-bold tracking-[0.22em] text-sky-300 uppercase">
                                        Como construímos
                                    </p>
                                    <h2 className="mt-4 text-4xl font-semibold tracking-[-0.04em] text-balance sm:text-5xl">
                                        Tecnologia a serviço de quem faz
                                        acontecer.
                                    </h2>
                                    <p className="mt-6 text-base leading-7 text-slate-400">
                                        Não criamos apenas telas. Entendemos
                                        rotinas, eliminamos atritos e
                                        transformamos processos em experiências
                                        claras.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {principles.map((principle) => {
                                        const Icon = principle.icon;
                                        return (
                                            <div
                                                key={principle.title}
                                                className="group rounded-2xl border border-white/8 bg-white/[0.03] p-6 transition hover:-translate-y-1 hover:border-sky-300/20 hover:bg-white/[0.05]"
                                            >
                                                <Icon className="size-6 text-sky-300" />
                                                <h3 className="mt-5 text-lg font-bold">
                                                    {principle.title}
                                                </h3>
                                                <p className="mt-2 text-sm leading-6 text-slate-400">
                                                    {principle.description}
                                                </p>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="sob-medida"
                        className="scroll-mt-20 border-y border-white/8 bg-[#08111f] py-24 sm:py-32"
                    >
                        <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="grid gap-12 lg:grid-cols-[0.88fr_1.12fr] lg:gap-20">
                                <div className="lg:sticky lg:top-32 lg:self-start">
                                    <div className="inline-flex items-center gap-2 rounded-full border border-violet-300/20 bg-violet-300/6 px-4 py-2 text-[10px] font-bold tracking-[0.2em] text-violet-200 uppercase sm:text-xs">
                                        <Lightbulb className="size-3.5" />
                                        Desenvolvimento sob medida
                                    </div>
                                    <h2 className="mt-6 text-4xl leading-tight font-semibold tracking-[-0.045em] text-balance sm:text-6xl">
                                        Tem uma ideia? Nós ajudamos a
                                        transformá-la em{' '}
                                        <span className="bg-gradient-to-r from-sky-300 to-violet-400 bg-clip-text text-transparent">
                                            produto digital.
                                        </span>
                                    </h2>
                                    <p className="mt-6 max-w-xl text-base leading-7 text-slate-400 sm:text-lg sm:leading-8">
                                        Além das nossas plataformas,
                                        desenvolvemos sistemas e aplicativos
                                        personalizados para empresas que
                                        precisam resolver desafios únicos,
                                        digitalizar processos ou lançar um novo
                                        negócio.
                                    </p>
                                    <div className="mt-8 rounded-2xl border border-sky-300/15 bg-sky-300/[0.045] p-5">
                                        <p className="text-sm leading-6 text-slate-300">
                                            <strong className="font-semibold text-white">
                                                Sua operação não precisa se
                                                adaptar a uma ferramenta
                                                genérica.
                                            </strong>{' '}
                                            Criamos a tecnologia para se adaptar
                                            à realidade e aos objetivos do seu
                                            negócio.
                                        </p>
                                    </div>
                                </div>

                                <div className="relative">
                                    <div className="absolute top-8 bottom-8 left-6 w-px bg-gradient-to-b from-sky-400/50 via-violet-400/30 to-transparent sm:left-8" />
                                    <div className="space-y-4">
                                        {customDevelopmentSteps.map((step) => {
                                            const Icon = step.icon;
                                            return (
                                                <article
                                                    key={step.number}
                                                    className="group relative rounded-3xl border border-white/8 bg-[#0b1627] p-6 pl-20 transition duration-300 hover:-translate-y-0.5 hover:border-violet-300/20 hover:bg-white/[0.045] sm:p-8 sm:pl-24"
                                                >
                                                    <div className="absolute top-6 left-4 z-10 grid size-12 place-items-center rounded-2xl border border-white/15 bg-[#101e33] shadow-xl transition group-hover:border-sky-300/30 sm:top-8 sm:left-5 sm:size-14">
                                                        <Icon className="size-5 text-sky-300 sm:size-6" />
                                                    </div>
                                                    <span className="text-[10px] font-bold tracking-[0.2em] text-violet-300 uppercase">
                                                        Etapa {step.number}
                                                    </span>
                                                    <h3 className="mt-2 text-xl font-bold tracking-tight sm:text-2xl">
                                                        {step.title}
                                                    </h3>
                                                    <p className="mt-3 text-sm leading-6 text-slate-400 sm:text-base sm:leading-7">
                                                        {step.description}
                                                    </p>
                                                </article>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-16 flex flex-col items-start justify-between gap-6 rounded-3xl border border-white/10 bg-gradient-to-r from-sky-500/10 via-blue-500/8 to-violet-500/10 p-7 sm:p-10 md:flex-row md:items-center">
                                <div>
                                    <p className="text-xs font-bold tracking-[0.18em] text-sky-300 uppercase">
                                        Vamos conversar
                                    </p>
                                    <h3 className="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">
                                        Toda grande solução começa com uma boa
                                        conversa.
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-slate-400">
                                        Conte o que você imagina. Nós ajudamos a
                                        encontrar o melhor caminho para
                                        construir.
                                    </p>
                                </div>
                                <div className="flex shrink-0 flex-col gap-3 sm:flex-row">
                                    <a
                                        href="mailto:contato@absistemas.com.br?subject=Tenho uma ideia para desenvolver"
                                        className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/6 px-5 py-3.5 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-white/10"
                                    >
                                        <Mail className="size-4" />
                                        Enviar e-mail
                                    </a>
                                    <a
                                        href="https://wa.me/5551998931325?text=Ol%C3%A1%2C%20tenho%20uma%20ideia%20de%20sistema%20ou%20aplicativo%20para%20desenvolver."
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3.5 text-sm font-bold text-slate-950 transition hover:-translate-y-0.5 hover:bg-sky-100"
                                    >
                                        <MessageCircle className="size-4" />
                                        Falar no WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="blog" className="py-24 sm:py-32">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="max-w-2xl">
                                <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                                    Do nosso blog
                                </h2>
                                <p className="mt-2 text-lg leading-8 text-slate-400">
                                    Ideias e práticas para transformar a gestão
                                    do seu negócio.
                                </p>
                            </div>
                            <div className="mx-auto mt-16 grid max-w-2xl grid-cols-1 gap-x-8 gap-y-20 lg:mx-0 lg:max-w-none lg:grid-cols-2">
                                {blogPosts.map((post) => (
                                    <article
                                        key={post.id}
                                        className="flex flex-col items-start justify-between"
                                    >
                                        <div className="relative w-full">
                                            <div className="relative w-full">
                                                <time
                                                    dateTime={post.date}
                                                    className="text-xs text-slate-500"
                                                >
                                                    {post.date}
                                                </time>
                                                <h3 className="mt-3 text-lg font-semibold leading-6 text-white group-hover:text-slate-300">
                                                    <a href={post.href}>
                                                        <span className="absolute inset-0" />
                                                        {post.title}
                                                    </a>
                                                </h3>
                                                <p className="mt-4 text-sm leading-6 text-slate-400 line-clamp-3">
                                                    {post.description}
                                                </p>
                                            </div>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="px-5 py-24 sm:px-8 sm:py-32 lg:px-12">
                        <div className="relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] border border-sky-300/15 bg-gradient-to-br from-blue-600/18 via-[#0b1729] to-violet-600/16 px-7 py-14 text-center sm:px-12 sm:py-20">
                            <div className="absolute top-0 left-1/2 h-32 w-2/3 -translate-x-1/2 rounded-full bg-sky-400/15 blur-3xl" />
                            <div className="relative">
                                <p className="text-xs font-bold tracking-[0.22em] text-sky-300 uppercase">
                                    AB Sistemas
                                </p>
                                <h2 className="mx-auto mt-4 max-w-3xl text-4xl font-semibold tracking-[-0.04em] text-balance sm:text-6xl">
                                    O próximo passo do seu negócio começa com
                                    clareza.
                                </h2>
                                <p className="mx-auto mt-6 max-w-2xl text-base leading-7 text-slate-400 sm:text-lg">
                                    Escolha a solução que entende sua operação e
                                    descubra uma forma mais simples de
                                    trabalhar, acompanhar e crescer.
                                </p>
                                <div className="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                                    {products.map((product) => (
                                        <a
                                            key={product.id}
                                            href={product.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-bold text-slate-950 transition hover:-translate-y-0.5 hover:bg-sky-100"
                                        >
                                            Conhecer {product.name}
                                            <ArrowUpRight className="size-4" />
                                        </a>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-white/8 bg-[#040a13]">
                    <div className="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-10 sm:px-8 md:flex-row md:items-end md:justify-between lg:px-12">
                        <div>
                            <BrandMark />
                            <p className="mt-5 max-w-sm text-xs leading-5 text-slate-500">
                                Soluções digitais brasileiras para operações que
                                querem trabalhar com mais controle, agilidade e
                                visão.
                            </p>
                        </div>
                        <div className="text-xs text-slate-500 md:text-right">
                            <div className="flex flex-col gap-2 md:items-end">
                                <a
                                    href="mailto:contato@absistemas.com.br"
                                    className="inline-flex items-center gap-2 transition hover:text-white"
                                >
                                    <Mail className="size-3.5" />
                                    contato@absistemas.com.br
                                </a>
                                <a
                                    href="https://wa.me/5551998931325"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-2 transition hover:text-white"
                                >
                                    <MessageCircle className="size-3.5" />
                                    (51) 99893-1325
                                </a>
                            </div>
                            <p className="mt-5 text-slate-600">
                                VetorOS · VetorPet
                            </p>
                            <p className="mt-2">
                        </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}