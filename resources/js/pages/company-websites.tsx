import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    Check,
    CheckCircle2,
    ChevronDown,
    Gauge,
    LayoutTemplate,
    LogIn,
    Mail,
    MessageCircle,
    MonitorSmartphone,
    Search,
    ShieldCheck,
    Store,
    Target,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { PublicBrand } from '@/components/public-brand';
import { PublicFooter } from '@/components/public-footer';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import type { User } from '@/types';

const whatsappUrl =
    'https://wa.me/5551998931325?text=Ol%C3%A1%2C%20quero%20um%20site%20profissional%20para%20minha%20empresa.';

const benefits = [
    { icon: Target, title: 'Pensado para vender', description: 'Conteúdo e chamadas para transformar visitantes em novos contatos.' },
    { icon: MonitorSmartphone, title: 'Perfeito em qualquer tela', description: 'Uma ótima experiência no celular, tablet ou computador.' },
    { icon: Search, title: 'Preparado para o Google', description: 'Boas práticas de SEO para sua empresa ser encontrada.' },
    { icon: Gauge, title: 'Rápido de verdade', description: 'Carregamento ágil para não perder clientes pelo caminho.' },
    { icon: ShieldCheck, title: 'Seguro e confiável', description: 'Base técnica sólida para transmitir confiança aos visitantes.' },
    { icon: BarChart3, title: 'Resultados mensuráveis', description: 'Integração com ferramentas para acompanhar acessos e contatos.' },
];

const websiteTypes = [
    {
        icon: LayoutTemplate,
        title: 'Site institucional',
        description: 'Apresente sua empresa, serviços e diferenciais com credibilidade.',
        items: ['Páginas sob medida', 'Formulários de contato', 'SEO e desempenho'],
    },
    {
        icon: Target,
        title: 'Landing page',
        description: 'Divulgue um serviço ou campanha com foco total em conversão.',
        items: ['Mensagem objetiva', 'Integração com WhatsApp', 'Pronta para anúncios'],
    },
    {
        icon: Store,
        title: 'Catálogo digital',
        description: 'Mostre seus produtos e facilite o início da conversa comercial.',
        items: ['Produtos e categorias', 'Busca e filtros', 'Orçamento pelo WhatsApp'],
    },
];

const steps = [
    ['01', 'Conversa e diagnóstico', 'Entendemos sua empresa, seu público e o que o site precisa entregar.'],
    ['02', 'Estratégia e conteúdo', 'Organizamos as páginas e a mensagem que conduzirá o visitante ao contato.'],
    ['03', 'Design e desenvolvimento', 'Criamos uma experiência exclusiva, rápida e alinhada à sua marca.'],
    ['04', 'Publicação e evolução', 'Revisamos, publicamos e deixamos tudo pronto para divulgar.'],
];

const faqs = [
    ['Quanto custa desenvolver um site?', 'O investimento depende do número de páginas, integrações e necessidades do projeto. Depois de uma conversa breve, enviamos uma proposta clara e adequada ao objetivo da empresa.'],
    ['Em quanto tempo o site fica pronto?', 'O prazo varia conforme o escopo e a disponibilidade dos conteúdos. Uma landing page exige menos tempo que um site institucional completo. O cronograma é definido antes do início.'],
    ['O site funciona bem no celular?', 'Sim. Todo projeto é responsivo e desenvolvido com prioridade para uma ótima experiência em celulares, tablets e computadores.'],
    ['Vocês ajudam com domínio e hospedagem?', 'Sim. Orientamos sobre domínio, hospedagem, certificado de segurança e publicação para sua empresa ter uma estrutura confiável.'],
    ['O site poderá aparecer no Google?', 'O projeto nasce com fundamentos de SEO técnico, conteúdo estruturado e boa performance. O posicionamento também depende da concorrência e da estratégia contínua.'],
];

export default function CompanyWebsites() {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const [openFaqIndex, setOpenFaqIndex] = useState<number | null>(0);

    const structuredData = {
        '@context': 'https://schema.org',
        '@type': 'Service',
        name: 'Desenvolvimento de sites para empresas',
        provider: { '@type': 'Organization', name: 'ABrasil Sistemas', url: '/' },
        areaServed: 'BR',
        serviceType: 'Desenvolvimento de sites empresariais',
        description: 'Criação de sites profissionais, rápidos e responsivos para empresas.',
    };

    return (
        <>
            <Head title="Desenvolvimento de sites para empresas">
                <meta name="description" content="Desenvolvimento de sites profissionais para empresas. Sites rápidos, responsivos, preparados para o Google e focados em gerar novos contatos." />
                <meta name="keywords" content="desenvolvimento de sites para empresas, criação de site profissional, site institucional, landing page" />
                <meta name="robots" content="index, follow, max-image-preview:large" />
                <meta name="theme-color" content="#ffffff" />
                <meta property="og:locale" content="pt_BR" />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content="ABrasil Sistemas" />
                <meta property="og:title" content="Desenvolvimento de sites para empresas | ABrasil Sistemas" />
                <meta property="og:description" content="Um site rápido, profissional e feito para gerar oportunidades para sua empresa." />
                <meta property="og:image" content="https://abrasilsistemas.com.br/images/dashboard-vetoros.webp" />
                <meta property="og:image:type" content="image/webp" />
                <meta property="og:image:width" content="1926" />
                <meta property="og:image:height" content="934" />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:image" content="https://abrasilsistemas.com.br/images/dashboard-vetoros.webp" />
                <script type="application/ld+json">{JSON.stringify(structuredData)}</script>
            </Head>

            <div className="min-h-screen bg-white text-slate-900 selection:bg-blue-100 selection:text-blue-950">
                <header className="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-[#08111f]/90 text-white backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-12">
                        <PublicBrand inverse />
                        <nav className="hidden items-center gap-7 text-sm font-semibold text-slate-300 md:flex">
                            <a href="#beneficios" className="transition hover:text-white">Benefícios</a>
                            <a href="#solucoes" className="transition hover:text-white">Tipos de site</a>
                            <a href="#processo" className="transition hover:text-white">Como funciona</a>
                            <Link href="/" className="transition hover:text-white">Produtos ABrasil</Link>
                        </nav>
                        <div className="flex items-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={auth.user.role === 'admin' ? '/dashboard' : '/settings/profile'}
                                    className="hidden items-center gap-2 rounded-full border border-white/15 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/5 sm:inline-flex"
                                >
                                    <UserRound className="size-4 shrink-0" />
                                    Minha conta
                                </Link>
                            ) : (
                                <Link
                                    href="/login"
                                    className="hidden items-center gap-2 text-sm font-semibold text-slate-300 transition hover:text-white sm:inline-flex"
                                >
                                    <LogIn className="size-4" /> Entrar
                                </Link>
                            )}
                            <a
                                href={whatsappUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-2 rounded-full bg-cyan-300 px-4 py-2.5 text-xs font-extrabold text-slate-950 transition hover:-translate-y-0.5 hover:bg-cyan-200 sm:text-sm"
                            >
                                <MessageCircle className="size-4" />
                                <span className="hidden sm:inline">Solicitar orçamento</span>
                                <span className="sm:hidden">Orçamento</span>
                            </a>
                        </div>
                    </div>
                </header>

                <main>
                    <section className="relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
                        <div className="absolute inset-x-0 top-0 -z-10 h-[42rem] bg-gradient-to-b from-blue-50 via-white to-white" />
                        <div className="absolute top-36 right-[-10rem] -z-10 size-[30rem] rounded-full bg-cyan-100/70 blur-3xl" />
                        <div className="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-[1.08fr_0.92fr] lg:px-12">
                            <div>
                                <div className="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm">
                                    <MonitorSmartphone className="size-4" /> Sites profissionais sob medida
                                </div>
                                <h1 className="mt-7 max-w-4xl text-5xl leading-[1.03] font-bold tracking-[-0.05em] text-balance text-slate-950 sm:text-7xl">
                                    Um site que ajuda sua empresa a ser{' '}
                                    <span className="text-blue-700">encontrada e escolhida.</span>
                                </h1>
                                <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-600 sm:text-xl">
                                    Criamos sites rápidos, profissionais e preparados para apresentar sua marca, conquistar confiança e gerar novos contatos.
                                </p>
                                <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                    <a href={whatsappUrl} target="_blank" rel="noreferrer" className="inline-flex h-13 items-center justify-center gap-2 rounded-lg bg-blue-700 px-6 text-sm font-bold text-white shadow-lg shadow-blue-700/15 transition hover:-translate-y-0.5 hover:bg-blue-800">
                                        Quero um site profissional <ArrowRight className="size-4" />
                                    </a>
                                    <a href="#solucoes" className="inline-flex h-13 items-center justify-center rounded-lg border border-slate-300 bg-white px-6 text-sm font-bold text-slate-700 hover:bg-slate-50">Ver opções</a>
                                </div>
                                <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-500">
                                    {['Design exclusivo', 'Responsivo', 'Preparado para o Google'].map((item) => (
                                        <span key={item} className="inline-flex items-center gap-2"><Check className="size-4 text-emerald-600" strokeWidth={3} />{item}</span>
                                    ))}
                                </div>
                            </div>

                            <div className="relative mx-auto w-full max-w-lg">
                                <div className="rounded-3xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/10">
                                    <div className="flex items-center gap-2 border-b border-slate-100 px-2 pb-3">
                                        <span className="size-2.5 rounded-full bg-red-300" /><span className="size-2.5 rounded-full bg-amber-300" /><span className="size-2.5 rounded-full bg-emerald-300" />
                                        <div className="ml-3 h-6 flex-1 rounded-md bg-slate-100" />
                                    </div>
                                    <div className="mt-4 rounded-2xl bg-slate-950 p-7 text-white sm:p-9">
                                        <div className="h-2.5 w-24 rounded-full bg-blue-400" />
                                        <div className="mt-6 h-7 w-11/12 rounded bg-white" />
                                        <div className="mt-3 h-7 w-8/12 rounded bg-white" />
                                        <div className="mt-6 space-y-2"><div className="h-2.5 rounded-full bg-white/20" /><div className="h-2.5 w-10/12 rounded-full bg-white/10" /></div>
                                        <div className="mt-7 h-10 w-36 rounded-lg bg-blue-500" />
                                        <div className="mt-10 grid grid-cols-3 gap-3">
                                            {[0, 1, 2].map((item) => <div key={item} className="rounded-xl bg-white/8 p-3"><div className="size-7 rounded-lg bg-blue-400/30" /><div className="mt-4 h-2 rounded bg-white/20" /><div className="mt-2 h-2 w-2/3 rounded bg-white/10" /></div>)}
                                        </div>
                                    </div>
                                </div>
                                <div className="absolute -right-3 -bottom-5 rounded-xl border border-emerald-200 bg-white p-4 shadow-lg sm:-right-8">
                                    <p className="text-xs font-bold text-emerald-700">Sua empresa disponível</p><p className="mt-1 text-xs text-slate-500">24 horas por dia</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="beneficios" className="scroll-mt-20 border-y border-slate-200 bg-slate-50 py-24 sm:py-32">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="max-w-3xl">
                                <p className="text-sm font-bold text-blue-700">Muito além de uma página bonita</p>
                                <h2 className="mt-3 text-4xl font-bold tracking-[-0.04em] text-balance text-slate-950 sm:text-6xl">Uma presença digital feita para dar resultado.</h2>
                            </div>
                            <div className="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                {benefits.map((benefit) => {
                                    const Icon = benefit.icon;

                                    return <article key={benefit.title} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div className="grid size-11 place-items-center rounded-xl bg-blue-50 text-blue-700"><Icon className="size-5" /></div><h3 className="mt-5 text-lg font-bold text-slate-950">{benefit.title}</h3><p className="mt-2 text-sm leading-6 text-slate-600">{benefit.description}</p></article>;
                                })}
                            </div>
                        </div>
                    </section>

                    <section id="solucoes" className="scroll-mt-20 py-24 sm:py-32">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="text-center"><p className="text-sm font-bold text-blue-700">A solução certa para seu momento</p><h2 className="mx-auto mt-3 max-w-3xl text-4xl font-bold tracking-[-0.04em] text-slate-950 sm:text-6xl">O site que sua estratégia precisa.</h2></div>
                            <div className="mt-14 grid gap-5 lg:grid-cols-3">
                                {websiteTypes.map((type) => {
                                    const Icon = type.icon;

                                    return <article key={type.title} className="flex flex-col rounded-2xl border border-slate-200 bg-white p-7 shadow-sm"><div className="grid size-12 place-items-center rounded-xl bg-blue-50 text-blue-700"><Icon className="size-6" /></div><h3 className="mt-6 text-2xl font-bold text-slate-950">{type.title}</h3><p className="mt-3 flex-1 text-sm leading-6 text-slate-600">{type.description}</p><ul className="mt-7 space-y-3 border-t border-slate-200 pt-6 text-sm text-slate-700">{type.items.map((item) => <li key={item} className="flex items-center gap-2.5"><CheckCircle2 className="size-4 text-emerald-600" />{item}</li>)}</ul></article>;
                                })}
                            </div>
                        </div>
                    </section>

                    <section id="processo" className="scroll-mt-20 bg-slate-950 py-24 text-white sm:py-32">
                        <div className="mx-auto grid max-w-7xl gap-14 px-5 sm:px-8 lg:grid-cols-[0.8fr_1.2fr] lg:px-12">
                            <div><p className="text-sm font-bold text-blue-300">Um processo claro</p><h2 className="mt-3 text-4xl font-bold tracking-[-0.04em] sm:text-5xl">Da conversa ao site no ar.</h2><p className="mt-5 text-base leading-7 text-slate-400">Você acompanha cada etapa e sabe o que está sendo construído e qual é o próximo passo.</p><a href={whatsappUrl} target="_blank" rel="noreferrer" className="mt-8 inline-flex items-center gap-2 text-sm font-bold text-blue-300">Conversar sobre meu projeto <ArrowRight className="size-4" /></a></div>
                            <div className="grid gap-4 sm:grid-cols-2">{steps.map(([number, title, description]) => <article key={number} className="rounded-2xl border border-white/10 bg-white/5 p-6"><span className="text-xs font-bold text-blue-300">ETAPA {number}</span><h3 className="mt-4 text-xl font-bold">{title}</h3><p className="mt-3 text-sm leading-6 text-slate-400">{description}</p></article>)}</div>
                        </div>
                    </section>

                    <section className="scroll-mt-20 bg-[#08111f] py-24 text-white sm:py-32">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                            <div className="mx-auto max-w-2xl text-center">
                                <p className="text-xs font-extrabold tracking-[0.18em] text-cyan-300 uppercase">Não é só promessa</p>
                                <h2 className="mt-3 text-4xl font-bold tracking-[-0.04em] text-balance sm:text-5xl">
                                    A ABrasil também constrói software que empresas usam todos os dias.
                                </h2>
                                <p className="mt-5 text-lg leading-8 text-slate-400">
                                    Além de sites, somos a empresa por trás do VetorOS e do VetorPet — dois produtos próprios em produção,
                                    com clientes reais. A mesma qualidade de desenvolvimento vai para o seu site.
                                </p>
                            </div>

                            <div className="mt-14 grid gap-6 lg:grid-cols-[1.3fr_1fr]">
                                <a
                                    href="https://vetoros.com.br"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="group relative overflow-hidden rounded-[1.75rem] border border-sky-300/20 bg-[#0b1625] p-3 shadow-2xl shadow-black/40 transition hover:-translate-y-1"
                                >
                                    <img
                                        src="/images/dashboard-vetoros.webp"
                                        alt="Painel do VetorOS, sistema de gestão para assistências técnicas"
                                        width="1926"
                                        height="934"
                                        className="rounded-2xl"
                                    />
                                    <div className="flex items-center justify-between px-4 py-5">
                                        <div>
                                            <p className="text-xs font-bold tracking-[0.14em] text-sky-300 uppercase">Produto ABrasil</p>
                                            <p className="mt-1 text-lg font-black">VetorOS — gestão para assistências técnicas</p>
                                        </div>
                                        <ArrowUpRight className="size-5 shrink-0 text-sky-300 transition group-hover:translate-x-1 group-hover:-translate-y-1" />
                                    </div>
                                </a>

                                <div className="flex flex-col gap-6">
                                    <a
                                        href="https://vetorpet.com.br"
                                        target="_blank"
                                        rel="noreferrer"
                                        className="group flex flex-1 flex-col justify-between rounded-[1.75rem] border border-violet-300/20 bg-[#11152a] p-7 shadow-2xl shadow-black/40 transition hover:-translate-y-1"
                                    >
                                        <div>
                                            <img src="/images/logo_pet.png" alt="VetorPet" className="size-12 rounded-2xl bg-white object-contain p-1" />
                                            <p className="mt-6 text-xs font-bold tracking-[0.14em] text-violet-300 uppercase">Produto ABrasil</p>
                                            <p className="mt-1 text-lg font-black">VetorPet — gestão comercial para o mercado pet</p>
                                        </div>
                                        <span className="mt-6 flex items-center justify-between border-t border-white/10 pt-5 text-sm font-bold text-white">
                                            Conhecer o VetorPet
                                            <ArrowUpRight className="size-4 transition group-hover:translate-x-1 group-hover:-translate-y-1" />
                                        </span>
                                    </a>
                                    <div className="rounded-[1.75rem] border border-white/10 bg-white/5 p-7">
                                        <p className="text-sm leading-6 text-slate-400">
                                            Sistemas em produção, com tenants pagantes, integrações fiscais e apps móveis — não são projetos de
                                            portfólio, são operações reais que precisam funcionar todos os dias.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="px-5 py-24 sm:px-8 sm:py-32 lg:px-12">
                        <div className="mx-auto max-w-7xl rounded-3xl bg-blue-700 px-7 py-14 text-center text-white shadow-2xl shadow-blue-700/15 sm:px-12 sm:py-20">
                            <h2 className="mx-auto max-w-3xl text-4xl font-bold tracking-[-0.04em] text-balance sm:text-6xl">Sua empresa merece um site à altura do que entrega.</h2>
                            <p className="mx-auto mt-5 max-w-2xl text-lg text-blue-100">Conte sobre seu negócio e receba uma proposta para transformar sua presença digital.</p>
                            <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row"><a href={whatsappUrl} target="_blank" rel="noreferrer" className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-6 py-3.5 text-sm font-bold text-blue-800"><MessageCircle className="size-4" />Pedir orçamento</a><a href="mailto:contato@absistemas.com.br?subject=Orçamento para desenvolvimento de site" className="inline-flex items-center justify-center gap-2 rounded-lg border border-white/30 px-6 py-3.5 text-sm font-bold"><Mail className="size-4" />Enviar e-mail</a></div>
                        </div>
                    </section>

                    <section id="faq" className="scroll-mt-20 border-t border-slate-200 bg-slate-50 py-24">
                        <div className="mx-auto max-w-3xl px-5 sm:px-8">
                            <p className="text-center text-sm font-bold text-blue-700">Perguntas frequentes</p>
                            <h2 className="mt-3 text-center text-4xl font-bold text-slate-950">Antes de começar</h2>
                            <div className="mt-12 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white px-6">
                                {faqs.map(([question, answer], index) => {
                                    const isOpen = openFaqIndex === index;

                                    return (
                                        <Collapsible
                                            key={question}
                                            open={isOpen}
                                            onOpenChange={(open) => setOpenFaqIndex(open ? index : null)}
                                        >
                                            <CollapsibleTrigger className="flex w-full items-center justify-between gap-4 py-5 text-left font-bold text-slate-950">
                                                {question}
                                                <ChevronDown
                                                    className={`size-5 shrink-0 text-blue-700 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                                                />
                                            </CollapsibleTrigger>
                                            <CollapsibleContent className="pb-5 text-sm leading-6 text-slate-600">{answer}</CollapsibleContent>
                                        </Collapsible>
                                    );
                                })}
                            </div>
                        </div>
                    </section>
                </main>

                <PublicFooter />
                <WhatsAppFloat />
            </div>
        </>
    );
}
