import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    Boxes,
    BrainCircuit,
    CheckCircle2,
    CloudCog,
    Code2,
    Download,
    Film,
    Github,
    Layers3,
    Mail,
    Rocket,
    Smartphone,
    Sparkles,
} from 'lucide-react';
import { login, register } from '@/routes';
import type { User } from '@/types';

const tracks = [
    {
        number: '01',
        title: 'Fundamentos e Web',
        description: 'Do planejamento técnico à aplicação full stack.',
        icon: Code2,
        volumes: [
            'Planejamento e Arquitetura',
            'Laravel + Inertia React + MySQL',
            'API REST Laravel',
            'Node.js + Next.js',
        ],
    },
    {
        number: '02',
        title: 'Aplicativos',
        description: 'A experiência do sistema chegando ao mobile.',
        icon: Smartphone,
        volumes: ['React Native + Expo', 'React Native + Node.js'],
    },
    {
        number: '03',
        title: 'Infraestrutura',
        description: 'Da máquina local à publicação escalável.',
        icon: CloudCog,
        volumes: ['Docker', 'Kubernetes', 'Publicação Web, Android e iOS'],
    },
    {
        number: '04',
        title: 'SaaS e Inteligência Artificial',
        description: 'Produto, automação e agentes aplicados ao sistema.',
        icon: BrainCircuit,
        volumes: ['Transformando em SaaS', 'IA + MCP', 'Agentes Inteligentes'],
    },
];

const formats = [
    {
        icon: BookOpen,
        title: 'E-books completos',
        description:
            'Volumes organizados, progressivos e disponíveis para aquisição individual.',
    },
    {
        icon: Code2,
        title: 'Código-fonte',
        description:
            'Repositórios que acompanham a evolução prática de cada etapa do projeto.',
    },
    {
        icon: Film,
        title: 'Vídeos e demonstrações',
        description:
            'Conteúdos complementares para visualizar conceitos e implementações.',
    },
    {
        icon: Download,
        title: 'Materiais livres',
        description:
            'Artigos, amostras e recursos técnicos acessíveis sem burocracia.',
    },
];

const volumeSlugs: Record<string, string> = {
    'Planejamento e Arquitetura': 'planejamento-e-arquitetura',
    'Laravel + Inertia React + MySQL': 'laravel-inertia-react-mysql',
    'API REST Laravel': 'api-rest-laravel',
    'Node.js + Next.js': 'nodejs-nextjs',
    'React Native + Expo': 'react-native-expo',
    'React Native + Node.js': 'react-native-nodejs',
    Docker: 'docker',
    Kubernetes: 'kubernetes',
    'Publicação Web, Android e iOS': 'publicacao-web-android-ios',
    'Transformando em SaaS': 'transformando-em-saas',
    'IA + MCP': 'ia-mcp',
    'Agentes Inteligentes': 'agentes-inteligentes',
};

function Logo() {
    return (
        <Link href="/" className="flex items-center gap-3">
            <img
                src="/images/logo_ab.png"
                alt="AB Sistemas"
                className="size-12 rounded-xl border border-white/60 object-contain shadow-lg shadow-black/25"
            />
            <div className="leading-none">
                <span className="block text-base font-bold text-white">
                    AB Sistemas
                </span>
                <span className="mt-1.5 block text-[9px] font-semibold tracking-[0.25em] text-amber-300 uppercase">
                    Conhecimento
                </span>
            </div>
        </Link>
    );
}

export default function Knowledge() {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const user = auth.user;

    return (
        <>
            <Head title="Conhecimento — Conteúdo técnico e e-books">
                <meta
                    name="description"
                    content="AB Sistemas Conhecimento: conteúdos técnicos livres, livros e materiais práticos para aprender desenvolvimento full stack multiplataforma."
                />
                <meta name="robots" content="index, follow" />
                <meta name="theme-color" content="#07111f" />
            </Head>

            <div className="min-h-screen overflow-hidden bg-[#07111f] text-white selection:bg-amber-300 selection:text-slate-950">
                <header className="fixed inset-x-0 top-0 z-50 border-b border-white/8 bg-[#07111f]/85 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-12">
                        <Logo />
                        <nav className="hidden items-center gap-8 text-sm text-slate-400 md:flex">
                            <a href="#colecao" className="hover:text-white">
                                A coleção
                            </a>
                            <a href="#trilhas" className="hover:text-white">
                                Volumes
                            </a>
                            <a href="#conteudos" className="hover:text-white">
                                Conteúdos
                            </a>
                        </nav>
                        <div className="flex items-center gap-2">
                            <Link
                                href="/"
                                className="hidden items-center gap-2 rounded-full border border-white/12 bg-white/5 px-4 py-2 text-xs font-semibold hover:bg-white/10 sm:inline-flex"
                            >
                                <ArrowLeft className="size-3.5" />
                                AB Sistemas
                            </Link>
                            {user ? (
                                <Link
                                    href="/minha-biblioteca"
                                    className="inline-flex items-center rounded-full bg-amber-300 px-4 py-2 text-xs font-bold text-slate-950 hover:bg-amber-200"
                                >
                                    Minha conta
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="inline-flex items-center rounded-full border border-white/12 px-4 py-2 text-xs font-semibold hover:bg-white/10"
                                    >
                                        Entrar
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="inline-flex items-center rounded-full bg-amber-300 px-4 py-2 text-xs font-bold text-slate-950 hover:bg-amber-200"
                                    >
                                        Criar conta
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <main>
                    <section className="relative flex min-h-[90svh] items-center pt-24">
                        <div className="pointer-events-none absolute inset-0">
                            <div className="absolute -top-40 -left-52 size-[38rem] rounded-full bg-amber-500/12 blur-[130px]" />
                            <div className="absolute top-20 -right-52 size-[42rem] rounded-full bg-blue-600/15 blur-[150px]" />
                            <div className="absolute inset-0 bg-[linear-gradient(rgba(148,163,184,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.04)_1px,transparent_1px)] bg-[size:70px_70px] [mask-image:linear-gradient(to_bottom,black,transparent_90%)]" />
                        </div>

                        <div className="relative mx-auto grid w-full max-w-7xl items-center gap-14 px-5 py-20 sm:px-8 lg:grid-cols-[1.1fr_0.9fr] lg:px-12">
                            <div>
                                <div className="inline-flex items-center gap-2 rounded-full border border-amber-300/20 bg-amber-300/6 px-4 py-2 text-xs font-bold tracking-[0.18em] text-amber-200 uppercase">
                                    <Sparkles className="size-3.5" />
                                    Conteúdo técnico de projetos reais
                                </div>
                                <h1 className="mt-8 max-w-4xl text-5xl leading-[0.98] font-semibold tracking-[-0.055em] text-balance sm:text-7xl">
                                    Conhecimento para{' '}
                                    <span className="bg-gradient-to-r from-amber-200 via-orange-300 to-sky-300 bg-clip-text text-transparent">
                                        construir de verdade.
                                    </span>
                                </h1>
                                <p className="mt-8 max-w-2xl text-lg leading-8 text-slate-400">
                                    Conteúdos livres, e-books e código-fonte para
                                    acompanhar a evolução de um sistema completo —
                                    do planejamento à Inteligência Artificial.
                                </p>
                                <div className="mt-10 flex flex-col gap-3 sm:flex-row">
                                    <a
                                        href="#colecao"
                                        className="inline-flex h-13 items-center justify-center gap-2 rounded-xl bg-amber-300 px-6 text-sm font-bold text-slate-950 hover:bg-amber-200"
                                    >
                                        Conhecer a coleção
                                        <ArrowRight className="size-4" />
                                    </a>
                                    <a
                                        href="#conteudos"
                                        className="inline-flex h-13 items-center justify-center gap-2 rounded-xl border border-white/12 bg-white/5 px-6 text-sm font-bold hover:bg-white/10"
                                    >
                                        Explorar conteúdos livres
                                    </a>
                                </div>
                            </div>

                            <div className="relative rounded-[2rem] border border-white/10 bg-white/[0.04] p-4 shadow-2xl shadow-black/30 backdrop-blur-sm sm:p-7">
                                <div className="rounded-2xl border border-amber-300/15 bg-[#0b1728] p-6 sm:p-8">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-bold tracking-[0.18em] text-amber-300 uppercase">
                                            Coleção em desenvolvimento
                                        </span>
                                        <Layers3 className="size-6 text-amber-300" />
                                    </div>
                                    <h2 className="mt-6 text-3xl font-bold tracking-tight">
                                        Full Stack Multiplataforma na Prática
                                    </h2>
                                    <p className="mt-4 leading-7 text-slate-400">
                                        Um único projeto evoluindo ao longo de 12
                                        volumes, mantendo contexto, arquitetura e
                                        prática em cada nova tecnologia.
                                    </p>
                                    <div className="mt-7 grid grid-cols-3 gap-3">
                                        {[
                                            ['12', 'volumes'],
                                            ['4', 'trilhas'],
                                            ['1', 'projeto'],
                                        ].map(([value, label]) => (
                                            <div
                                                key={label}
                                                className="rounded-xl border border-white/8 bg-white/[0.035] p-3 text-center"
                                            >
                                                <strong className="block text-2xl text-white">
                                                    {value}
                                                </strong>
                                                <span className="text-[10px] tracking-wide text-slate-500 uppercase">
                                                    {label}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="colecao" className="border-y border-white/8 bg-white/[0.025]">
                        <div className="mx-auto grid max-w-7xl gap-12 px-5 py-24 sm:px-8 lg:grid-cols-2 lg:px-12">
                            <div>
                                <span className="text-xs font-bold tracking-[0.2em] text-amber-300 uppercase">
                                    Uma jornada contínua
                                </span>
                                <h2 className="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">
                                    Cada livro começa onde o anterior terminou.
                                </h2>
                            </div>
                            <div className="space-y-5 text-base leading-7 text-slate-400">
                                <p>
                                    Em vez de exemplos desconectados, a coleção
                                    acompanha um sistema real de Ordem de Serviço
                                    desde a arquitetura inicial até sua evolução
                                    para uma plataforma SaaS com IA.
                                </p>
                                <p>
                                    A sequência é recomendada para preservar o
                                    contexto, mas todo conteúdo permanece livre para
                                    consulta e cada e-book poderá ser adquirido
                                    individualmente.
                                </p>
                                <div className="flex items-center gap-3 text-sm font-semibold text-emerald-300">
                                    <CheckCircle2 className="size-5" />
                                    Sem provas, bloqueios ou burocracia.
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="trilhas" className="mx-auto max-w-7xl px-5 py-24 sm:px-8 lg:px-12">
                        <div className="max-w-3xl">
                            <span className="text-xs font-bold tracking-[0.2em] text-sky-300 uppercase">
                                12 volumes · 4 trilhas
                            </span>
                            <h2 className="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">
                                Do primeiro diagrama ao agente inteligente.
                            </h2>
                        </div>
                        <div className="mt-12 grid gap-5 lg:grid-cols-2">
                            {tracks.map((track) => {
                                const Icon = track.icon;

                                return (
                                    <article
                                        key={track.number}
                                        className="rounded-2xl border border-white/9 bg-white/[0.035] p-6 sm:p-8"
                                    >
                                        <div className="flex items-start gap-4">
                                            <div className="grid size-12 shrink-0 place-items-center rounded-xl bg-white/8 text-amber-300">
                                                <Icon className="size-6" />
                                            </div>
                                            <div>
                                                <span className="text-[10px] font-bold tracking-[0.2em] text-slate-500 uppercase">
                                                    Trilha {track.number}
                                                </span>
                                                <h3 className="mt-1 text-2xl font-bold">
                                                    {track.title}
                                                </h3>
                                                <p className="mt-2 text-sm text-slate-400">
                                                    {track.description}
                                                </p>
                                            </div>
                                        </div>
                                        <ol className="mt-7 space-y-3">
                                            {track.volumes.map((volume, index) => (
                                                <li
                                                    key={volume}
                                                    className="rounded-xl border border-white/7 bg-[#091525] text-sm transition hover:border-amber-300/25 hover:bg-white/[0.055]"
                                                >
                                                    <Link
                                                        href={`/conhecimento/volumes/${volumeSlugs[volume]}`}
                                                        className="flex items-center gap-3 px-4 py-3"
                                                    >
                                                        <span className="grid size-7 shrink-0 place-items-center rounded-full bg-white/7 text-xs font-bold text-slate-400">
                                                            {tracks
                                                                .slice(
                                                                    0,
                                                                    tracks.indexOf(
                                                                        track,
                                                                    ),
                                                                )
                                                                .reduce(
                                                                    (
                                                                        total,
                                                                        item,
                                                                    ) =>
                                                                        total +
                                                                        item
                                                                            .volumes
                                                                            .length,
                                                                    0,
                                                                ) +
                                                                index +
                                                                1}
                                                        </span>
                                                        <span className="flex-1">
                                                            {volume}
                                                        </span>
                                                        <ArrowRight className="size-4 text-slate-600" />
                                                    </Link>
                                                </li>
                                            ))}
                                        </ol>
                                    </article>
                                );
                            })}
                        </div>
                    </section>

                    <section id="conteudos" className="border-y border-white/8 bg-[#091525]">
                        <div className="mx-auto max-w-7xl px-5 py-24 sm:px-8 lg:px-12">
                            <div className="max-w-3xl">
                                <span className="text-xs font-bold tracking-[0.2em] text-emerald-300 uppercase">
                                    Além dos livros
                                </span>
                                <h2 className="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">
                                    Conteúdo acessível em diferentes formatos.
                                </h2>
                            </div>
                            <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {formats.map((format) => {
                                    const Icon = format.icon;

                                    return (
                                        <article
                                            key={format.title}
                                            className="rounded-2xl border border-white/8 bg-white/[0.035] p-6"
                                        >
                                            <Icon className="size-7 text-emerald-300" />
                                            <h3 className="mt-5 text-lg font-bold">
                                                {format.title}
                                            </h3>
                                            <p className="mt-3 text-sm leading-6 text-slate-400">
                                                {format.description}
                                            </p>
                                        </article>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section className="mx-auto max-w-7xl px-5 py-24 sm:px-8 lg:px-12">
                        <div className="relative overflow-hidden rounded-[2rem] border border-amber-300/15 bg-gradient-to-br from-amber-300/12 via-white/[0.04] to-sky-400/10 p-8 sm:p-12">
                            <Boxes className="absolute -right-10 -bottom-10 size-64 text-white/[0.025]" />
                            <div className="relative max-w-3xl">
                                <span className="text-xs font-bold tracking-[0.2em] text-amber-300 uppercase">
                                    Em preparação
                                </span>
                                <h2 className="mt-4 text-4xl font-semibold tracking-tight">
                                    Acompanhe os primeiros lançamentos.
                                </h2>
                                <p className="mt-5 max-w-2xl leading-7 text-slate-400">
                                    Os volumes serão publicados progressivamente.
                                    Em breve, esta página reunirá amostras,
                                    repositórios, vídeos e formas de aquisição.
                                </p>
                                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                    <a
                                        href="mailto:contato@absistemas.com.br?subject=Quero acompanhar a coleção Full Stack Multiplataforma"
                                        className="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-white/12 bg-white/5 px-6 text-sm font-bold hover:bg-white/10"
                                    >
                                        Receber novidades
                                        <Mail className="size-4" />
                                    </a>
                                    <Link
                                        href={
                                            user
                                                ? '/minha-biblioteca'
                                                : register()
                                        }
                                        className="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-white px-6 text-sm font-bold text-slate-950 hover:bg-amber-100"
                                    >
                                        {user
                                            ? 'Acessar minha conta'
                                            : 'Criar conta para os conteúdos'}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-white/8 bg-[#040a13]">
                    <div className="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-10 sm:px-8 md:flex-row md:items-end md:justify-between lg:px-12">
                        <div>
                            <Logo />
                            <p className="mt-5 max-w-sm text-xs leading-5 text-slate-500">
                                Conteúdo técnico construído a partir de projetos
                                reais.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-4 text-xs text-slate-500">
                            <span className="inline-flex items-center gap-1.5">
                                <Github className="size-3.5" /> Repositórios em breve
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Rocket className="size-3.5" /> Primeiros volumes em
                                preparação
                            </span>
                            <span>© {new Date().getFullYear()} AB Sistemas</span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
