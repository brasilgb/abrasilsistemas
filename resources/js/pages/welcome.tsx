import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    Check,
    CheckCircle2,
    ChevronRight,
    ClipboardCheck,
    Code2,
    Headphones,
    Mail,
    Menu,
    MessageCircle,
    MonitorSmartphone,
    PackageCheck,
    ShoppingCart,
    Sparkles,
    Users,
    Wrench,
    X,
} from 'lucide-react';
import { useState } from 'react';

const vetorosUrl = 'https://vetoros.com.br/';
const vetorosPlansUrl = 'https://vetoros.com.br/planos';
const contactWhatsappUrl =
    'https://wa.me/5551998931325?text=Ol%C3%A1%2C%20preciso%20de%20ajuda%20com%20uma%20solu%C3%A7%C3%A3o%20da%20ABrasil.';

const outcomes = [
    {
        icon: ClipboardCheck,
        number: '01',
        title: 'Nenhuma OS esquecida',
        description:
            'Acompanhe cada equipamento da entrada à entrega, com histórico e responsável.',
    },
    {
        icon: MessageCircle,
        number: '02',
        title: 'Cliente bem informado',
        description:
            'Reduza ligações repetidas e envie atualizações com mais agilidade.',
    },
    {
        icon: BarChart3,
        number: '03',
        title: 'Margem sob controle',
        description:
            'Enxergue estoque, caixa, vendas e resultados sem depender de planilhas.',
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
        icon: ShoppingCart,
        eyebrow: 'Gestão comercial',
        title: 'VetorPet',
        description:
            'Carteira de clientes, catálogo, visitas e pedidos para equipes que vendem no mercado pet.',
        href: 'https://vetorpet.com.br',
        action: 'Conhecer o VetorPet',
        accent: 'violet',
    },
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

const journey = [
    [
        'Conheça os planos',
        'Compare as opções e escolha aquela que acompanha o momento da sua assistência.',
    ],
    [
        'Crie sua conta',
        'Faça seu cadastro online em poucos minutos, sem esperar contato comercial.',
    ],
    [
        'Teste por 14 dias',
        'Explore o VetorOS gratuitamente e valide a solução na rotina da sua empresa.',
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

function Brand({ inverse = false }: { inverse?: boolean }) {
    return (
        <Link
            href="/"
            className="group flex items-center gap-3"
            aria-label="ABrasil Sistemas — início"
        >
            <span className="relative grid size-11 place-items-center overflow-hidden rounded-xl bg-blue-600 shadow-lg shadow-blue-950/20">
                <span className="absolute inset-0 bg-[radial-gradient(circle_at_70%_20%,rgba(255,255,255,.45),transparent_35%)]" />
                <span className="relative text-lg font-black tracking-[-0.08em] text-white">
                    AB
                </span>
            </span>
            <span className="leading-none">
                <span
                    className={`block text-[15px] font-extrabold tracking-[-0.02em] ${inverse ? 'text-white' : 'text-slate-950'}`}
                >
                    ABrasil
                </span>
                <span
                    className={`mt-1 block text-[9px] font-bold tracking-[0.18em] uppercase ${inverse ? 'text-slate-500' : 'text-slate-500'}`}
                >
                    Sistemas
                </span>
            </span>
        </Link>
    );
}

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
}: {
    blogPosts: BlogPostSummary[];
}) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

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
            },
            {
                '@type': 'SoftwareApplication',
                name: 'VetorOS',
                applicationCategory: 'BusinessApplication',
                operatingSystem: 'Web',
                description:
                    'Sistema de gestão para assistências técnicas com ordens de serviço, estoque, vendas e financeiro.',
            },
        ],
    };

    return (
        <>
            <Head title="Sistema para assistência técnica | VetorOS">
                <meta
                    name="description"
                    content="Organize ordens de serviço, clientes, equipamentos, estoque, vendas e financeiro da sua assistência técnica com o VetorOS."
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
                    content="VetorOS — Sua assistência sob controle"
                />
                <meta
                    property="og:description"
                    content="Da entrada do equipamento ao financeiro: organize toda a operação da sua assistência técnica."
                />
                <meta
                    property="og:image"
                    content="https://abrasilsistemas.com.br/images/logo_os.png"
                />
                <meta name="twitter:card" content="summary_large_image" />
                <link rel="canonical" href="https://abrasilsistemas.com.br/" />
                <script type="application/ld+json">
                    {JSON.stringify(structuredData)}
                </script>
            </Head>

            <div className="ab-public-site min-h-screen overflow-x-hidden bg-[#f7f8fa] text-slate-900 selection:bg-cyan-200 selection:text-slate-950">
                <header className="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-[#08111f]/90 text-white backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-[86rem] items-center justify-between px-5 sm:px-8 lg:px-12">
                        <Brand inverse />
                        <nav
                            className="hidden items-center gap-8 text-sm font-semibold text-slate-300 lg:flex"
                            aria-label="Navegação principal"
                        >
                            <a
                                href="#vetoros"
                                className="transition hover:text-white"
                            >
                                VetorOS
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
                                Outras soluções
                            </a>
                            <Link
                                href="/blog"
                                className="transition hover:text-white"
                            >
                                Conteúdo
                            </Link>
                        </nav>
                        <a
                            href={vetorosPlansUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="hidden items-center gap-2 rounded-full bg-cyan-300 px-5 py-3 text-sm font-extrabold text-slate-950 transition hover:-translate-y-0.5 hover:bg-cyan-200 sm:inline-flex"
                        >
                            Testar grátis por 14 dias
                            <ArrowUpRight className="size-4" />
                        </a>
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
                                ['#vetoros', 'VetorOS'],
                                ['#resultados', 'Resultados'],
                                ['#solucoes', 'Outras soluções'],
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
                            <a
                                href={vetorosPlansUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-5 flex items-center justify-center gap-2 rounded-full bg-cyan-300 px-5 py-3.5 text-slate-950"
                            >
                                Começar teste grátis{' '}
                                <ArrowRight className="size-4" />
                            </a>
                        </nav>
                    )}
                </header>

                <main>
                    <section className="relative isolate overflow-hidden bg-[#08111f] pt-32 text-white sm:pt-40">
                        <div className="absolute inset-0 -z-20 bg-[linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] bg-[size:44px_44px]" />
                        <div className="absolute top-10 right-[-12rem] -z-10 size-[38rem] rounded-full bg-blue-600/25 blur-[120px]" />
                        <div className="absolute bottom-[-18rem] left-[25%] -z-10 size-[32rem] rounded-full bg-cyan-400/10 blur-[100px]" />

                        <div className="mx-auto grid max-w-[86rem] items-center gap-16 px-5 pb-20 sm:px-8 sm:pb-28 lg:grid-cols-[0.92fr_1.08fr] lg:px-12">
                            <div>
                                <p className="inline-flex items-center gap-2 rounded-full border border-cyan-300/25 bg-cyan-300/10 px-4 py-2 text-xs font-extrabold tracking-[0.12em] text-cyan-200 uppercase">
                                    <Sparkles className="size-3.5" />
                                    Gestão feita para assistência técnica
                                </p>
                                <h1 className="mt-7 max-w-3xl text-[clamp(3.5rem,7vw,6.6rem)] leading-[0.88] font-black tracking-[-0.075em] text-balance">
                                    Menos caos.
                                    <span className="mt-2 block text-cyan-300">
                                        Mais controle.
                                    </span>
                                </h1>
                                <p className="mt-8 max-w-xl text-lg leading-8 text-slate-300 sm:text-xl">
                                    Do equipamento que entra ao dinheiro que
                                    sai: o VetorOS coloca sua assistência
                                    inteira em uma única operação.
                                </p>
                                <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                    <a
                                        href={vetorosPlansUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex h-14 items-center justify-center gap-3 rounded-full bg-cyan-300 px-7 text-sm font-extrabold text-slate-950 shadow-[0_18px_60px_rgba(34,211,238,.18)] transition hover:-translate-y-1 hover:bg-cyan-200"
                                    >
                                        Quero testar o VetorOS
                                        <ArrowRight className="size-4" />
                                    </a>
                                    <a
                                        href="#vetoros"
                                        className="inline-flex h-14 items-center justify-center gap-2 rounded-full border border-white/15 px-7 text-sm font-bold text-white transition hover:bg-white/5"
                                    >
                                        Ver como funciona
                                    </a>
                                </div>
                                <p className="mt-5 flex items-center gap-2 text-xs text-slate-500">
                                    <Check className="size-4 text-cyan-300" />{' '}
                                    Cadastro online e 14 dias para testar
                                    gratuitamente.
                                </p>
                            </div>

                            <div className="relative">
                                <div className="absolute -inset-5 rounded-[2.2rem] bg-gradient-to-br from-cyan-300/20 via-blue-600/5 to-transparent blur-2xl" />
                                <div className="relative overflow-hidden rounded-[1.8rem] border border-white/10 bg-[#0d1929] shadow-2xl shadow-black/40">
                                    <div className="flex h-12 items-center gap-2 border-b border-white/10 px-5">
                                        <span className="size-2 rounded-full bg-red-400/80" />
                                        <span className="size-2 rounded-full bg-amber-300/80" />
                                        <span className="size-2 rounded-full bg-emerald-400/80" />
                                        <span className="ml-3 text-[10px] font-bold tracking-[0.15em] text-slate-500 uppercase">
                                            Central da operação
                                        </span>
                                    </div>
                                    <div className="grid sm:grid-cols-[5rem_1fr]">
                                        <aside className="hidden border-r border-white/10 py-6 sm:block">
                                            <div className="mx-auto grid size-9 place-items-center rounded-lg bg-blue-600 text-xs font-black">
                                                VO
                                            </div>
                                            <div className="mt-7 grid justify-center gap-4">
                                                {[
                                                    ClipboardCheck,
                                                    Users,
                                                    PackageCheck,
                                                    BarChart3,
                                                ].map((Icon, index) => (
                                                    <span
                                                        key={index}
                                                        className={`grid size-9 place-items-center rounded-lg ${index === 0 ? 'bg-cyan-300 text-slate-950' : 'text-slate-500'}`}
                                                    >
                                                        <Icon className="size-4" />
                                                    </span>
                                                ))}
                                            </div>
                                        </aside>
                                        <div className="p-5 sm:p-7">
                                            <div className="flex items-end justify-between">
                                                <div>
                                                    <p className="text-xs text-slate-500">
                                                        Bom dia, equipe
                                                    </p>
                                                    <p className="mt-1 text-xl font-extrabold">
                                                        Visão de hoje
                                                    </p>
                                                </div>
                                                <span className="rounded-full bg-emerald-400/10 px-3 py-1.5 text-[10px] font-bold text-emerald-300">
                                                    OPERAÇÃO ONLINE
                                                </span>
                                            </div>
                                            <div className="mt-7 grid grid-cols-3 gap-3">
                                                {[
                                                    [
                                                        '18',
                                                        'Novas OS',
                                                        'text-cyan-300',
                                                    ],
                                                    [
                                                        '07',
                                                        'Aguardando',
                                                        'text-amber-300',
                                                    ],
                                                    [
                                                        '12',
                                                        'Prontas',
                                                        'text-emerald-300',
                                                    ],
                                                ].map(
                                                    ([value, label, color]) => (
                                                        <div
                                                            key={label}
                                                            className="rounded-xl border border-white/8 bg-white/[.035] p-3 sm:p-4"
                                                        >
                                                            <p
                                                                className={`text-2xl font-black sm:text-3xl ${color}`}
                                                            >
                                                                {value}
                                                            </p>
                                                            <p className="mt-1 text-[9px] text-slate-500 sm:text-[11px]">
                                                                {label}
                                                            </p>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                            <div className="mt-4 rounded-xl border border-white/8 bg-white/[.035] p-4">
                                                <div className="flex items-center justify-between">
                                                    <p className="text-xs font-bold">
                                                        Fluxo de serviços
                                                    </p>
                                                    <p className="text-[10px] text-emerald-300">
                                                        +18% este mês
                                                    </p>
                                                </div>
                                                <div className="mt-5 flex h-28 items-end gap-2">
                                                    {[
                                                        38, 52, 44, 70, 56, 82,
                                                        68, 94, 78, 100, 88, 96,
                                                    ].map((height, index) => (
                                                        <span
                                                            key={index}
                                                            className="flex-1 rounded-t-sm bg-gradient-to-t from-blue-700 to-cyan-300"
                                                            style={{
                                                                height: `${height}%`,
                                                                opacity:
                                                                    0.45 +
                                                                    index *
                                                                        0.04,
                                                            }}
                                                        />
                                                    ))}
                                                </div>
                                            </div>
                                            <div className="mt-4 grid gap-2 sm:grid-cols-2">
                                                {[
                                                    [
                                                        'OS #2841',
                                                        'Notebook Dell',
                                                        'Em reparo',
                                                    ],
                                                    [
                                                        'OS #2840',
                                                        'iPhone 15',
                                                        'Orçamento',
                                                    ],
                                                ].map(
                                                    ([order, item, status]) => (
                                                        <div
                                                            key={order}
                                                            className="flex items-center gap-3 rounded-xl border border-white/8 px-3 py-3"
                                                        >
                                                            <span className="grid size-8 place-items-center rounded-lg bg-blue-500/15">
                                                                <Wrench className="size-3.5 text-blue-300" />
                                                            </span>
                                                            <span className="min-w-0 flex-1">
                                                                <span className="block text-[10px] font-bold">
                                                                    {order}
                                                                </span>
                                                                <span className="block truncate text-[9px] text-slate-500">
                                                                    {item}
                                                                </span>
                                                            </span>
                                                            <span className="text-[9px] text-cyan-300">
                                                                {status}
                                                            </span>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div className="absolute -right-3 -bottom-5 rounded-2xl border border-cyan-300/20 bg-cyan-300 px-4 py-3 text-slate-950 shadow-xl sm:-right-7">
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
                            <div>
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
                                    href={vetorosUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-10 inline-flex items-center gap-2 text-sm font-black text-blue-800"
                                >
                                    Explorar todos os recursos{' '}
                                    <ArrowUpRight className="size-4" />
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
                                            href={vetorosPlansUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex shrink-0 items-center gap-2 rounded-full bg-cyan-300 px-5 py-3 text-sm font-black text-slate-950"
                                        >
                                            Testar grátis{' '}
                                            <ArrowRight className="size-4" />
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
                                title="Outros desafios. A mesma visão de negócio."
                                description="Soluções especializadas para vender, comunicar e transformar processos — sempre com tecnologia a serviço da operação."
                            />
                            <div className="mt-14 grid gap-5 lg:grid-cols-3">
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

                    <section className="relative overflow-hidden bg-blue-700 px-5 py-24 text-white sm:px-8 sm:py-32">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(34,211,238,.35),transparent_30%),radial-gradient(circle_at_90%_80%,rgba(15,23,42,.28),transparent_35%)]" />
                        <div className="relative mx-auto max-w-5xl text-center">
                            <p className="text-xs font-extrabold tracking-[0.18em] text-cyan-200 uppercase">
                                Pronto para organizar?
                            </p>
                            <h2 className="mt-6 text-5xl leading-[0.98] font-black tracking-[-0.065em] text-balance sm:text-7xl">
                                Sua operação pode ser mais simples.
                            </h2>
                            <p className="mx-auto mt-7 max-w-2xl text-lg leading-8 text-blue-100">
                                Crie sua conta, explore todos os recursos por 14
                                dias e descubra como o VetorOS devolve controle
                                e tempo para você.
                            </p>
                            <a
                                href={vetorosPlansUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-10 inline-flex h-14 items-center gap-3 rounded-full bg-white px-7 text-sm font-black text-blue-800 shadow-xl transition hover:-translate-y-1"
                            >
                                <ArrowRight className="size-5" />
                                Começar meu teste grátis
                            </a>
                            <p className="mt-4 text-xs text-blue-200">
                                Cadastro online, sem esperar atendimento.
                            </p>
                        </div>
                    </section>
                </main>

                <footer className="bg-[#050b13] text-white">
                    <div className="mx-auto grid max-w-[86rem] gap-12 px-5 py-14 sm:px-8 md:grid-cols-[1.2fr_0.8fr_0.8fr] lg:px-12">
                        <div>
                            <Brand inverse />
                            <p className="mt-5 max-w-sm text-sm leading-6 text-slate-500">
                                Tecnologia brasileira criada para simplificar
                                operações reais e ajudar empresas a crescer com
                                controle.
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-black tracking-[0.15em] text-slate-600 uppercase">
                                Soluções
                            </p>
                            <div className="mt-5 grid gap-3 text-sm text-slate-400">
                                <a
                                    href={vetorosUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="hover:text-white"
                                >
                                    VetorOS
                                </a>
                                <a
                                    href="https://vetorpet.com.br"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="hover:text-white"
                                >
                                    VetorPet
                                </a>
                                <Link
                                    href="/desenvolvimento-de-sites-para-empresas"
                                    className="hover:text-white"
                                >
                                    Sites para empresas
                                </Link>
                                <Link href="/blog" className="hover:text-white">
                                    Conteúdo
                                </Link>
                            </div>
                        </div>
                        <div>
                            <p className="text-xs font-black tracking-[0.15em] text-slate-600 uppercase">
                                Contato
                            </p>
                            <div className="mt-5 grid gap-3 text-sm text-slate-400">
                                <a
                                    href="mailto:contato@absistemas.com.br"
                                    className="flex items-center gap-2 hover:text-white"
                                >
                                    <Mail className="size-4" />{' '}
                                    contato@absistemas.com.br
                                </a>
                                <a
                                    href={contactWhatsappUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-2 hover:text-white"
                                >
                                    <MessageCircle className="size-4" /> (51)
                                    99893-1325
                                </a>
                                <span className="flex items-center gap-2">
                                    <Headphones className="size-4" />{' '}
                                    Atendimento próximo
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="border-t border-white/10 px-5 py-5 text-center text-[11px] text-slate-600">
                        © {new Date().getFullYear()} ABrasil Sistemas. Feito no
                        Brasil para negócios que fazem acontecer.
                    </div>
                </footer>
            </div>
        </>
    );
}
