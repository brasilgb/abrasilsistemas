import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, Check, ChevronDown, Code2, MessageCircle, X } from 'lucide-react';
import { useState } from 'react';
import { CookieConsent } from '@/components/cookie-consent';
import { PublicFooter } from '@/components/public-footer';
import { SecondaryPageHeader } from '@/components/secondary-page-header';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import { buildWhatsappUrl, useContact } from '@/lib/contact';
import { vetorOsFeatures, vetorPetFeatures } from '@/data/product-features';

type OfferSection = {
    id: string;
    name: string;
    eyebrow: string;
    title: string;
    titleHighlight: string;
    description: string;
    pains: string[];
    solutions: string[];
    painsHeading: string;
    solutionsHeading: string;
    features: readonly string[];
    primaryCta: { label: string; href: string; external: boolean };
    secondaryCta: { label: string; href: string; external: boolean };
    accentText: string;
    accentBg: string;
    accentBorder: string;
    accentSoftBg: string;
    sectionBg: string;
    logo?: string;
    logoClass?: string;
    icon?: typeof Code2;
    iconBoxClass?: string;
};

function buildOffers(whatsappNumber: string): OfferSection[] {
    const contactWhatsappUrl = buildWhatsappUrl(whatsappNumber, 'Olá, preciso de ajuda com uma solução da ABrasil.');
    const customProjectWhatsappUrl = buildWhatsappUrl(whatsappNumber, 'Olá, quero falar sobre um site ou sistema sob medida.');

    return [
    {
        id: 'vetoros',
        name: 'VetorOS',
        eyebrow: 'VetorOS · Assistências técnicas',
        title: 'Sua assistência técnica',
        titleHighlight: 'parou de perder informação.',
        description:
            'O VetorOS existe porque uma assistência técnica não pode depender de caderno, grupo de WhatsApp e planilha para saber o que está acontecendo.',
        painsHeading: 'O que trava a operação hoje',
        pains: [
            'Informação de cliente e equipamento espalhada em cadernos e conversas',
            'Cliente ligando o dia inteiro pra saber o status do aparelho',
            'Estoque sem controle real — peça que "sumiu" da bancada',
            'Caixa que nunca bate no fim do mês',
        ],
        solutionsHeading: 'O que muda com o VetorOS',
        solutions: [
            'Histórico completo do cliente e do equipamento em um só lugar',
            'Status da OS visível sem a equipe parar pra responder',
            'Entrada, saída e alerta de reposição do estoque',
            'Financeiro conectado à operação, com relatório real',
        ],
        features: vetorOsFeatures,
        primaryCta: { label: 'Criar minha conta grátis', href: 'https://vetoros.com.br', external: true },
        secondaryCta: { label: 'Falar com a equipe', href: contactWhatsappUrl, external: true },
        accentText: 'text-sky-300',
        accentBg: 'bg-cyan-300',
        accentBorder: 'border-sky-300/20',
        accentSoftBg: 'bg-sky-300/10',
        sectionBg: 'bg-[#dffbff]',
        logo: '/images/logo_os.png',
        logoClass: 'rounded-2xl object-contain',
    },
    {
        id: 'vetorpet',
        name: 'VetorPet',
        eyebrow: 'VetorPet · Representantes do mercado pet',
        title: 'Represente mais,',
        titleHighlight: 'sem perder o controle da rua.',
        description:
            'O VetorPet existe porque quem vende no mercado pet não pode ficar refém de pedido anotado no papel e comissão calculada na mão.',
        painsHeading: 'O que trava a operação hoje',
        pains: [
            'Pedido anotado no papel e digitado depois, com erro',
            'Vendedor sem histórico do cliente na hora da visita',
            'Comissão calculada manualmente, sem transparência',
            'Gestor sem visibilidade da equipe que está em campo',
        ],
        solutionsHeading: 'O que muda com o VetorPet',
        solutions: [
            'Pedido lançado direto no app, ainda na visita',
            'Carteira, histórico e catálogo na mão do vendedor',
            'Comissões calculadas automaticamente',
            'Painel com indicadores de equipe, região e resultado',
        ],
        features: vetorPetFeatures,
        primaryCta: { label: 'Criar minha conta grátis', href: 'https://vetorpet.com.br', external: true },
        secondaryCta: { label: 'Falar com a equipe', href: contactWhatsappUrl, external: true },
        accentText: 'text-violet-300',
        accentBg: 'bg-violet-300',
        accentBorder: 'border-violet-300/20',
        accentSoftBg: 'bg-violet-300/10',
        sectionBg: 'bg-violet-50',
        logo: '/images/logo_pet.png',
        logoClass: 'rounded-2xl bg-white object-contain p-1',
    },
    {
        id: 'sob-medida',
        name: 'Sob medida',
        eyebrow: 'Sob medida · Sites e sistemas',
        title: 'Quando uma ferramenta pronta',
        titleHighlight: 'não resolve o seu processo.',
        description:
            'Nem toda empresa resolve o que precisa com um produto pronto. Por isso também desenvolvemos sites e sistemas sob medida, com escopo definido antes de começar.',
        painsHeading: 'O que geralmente trava a empresa',
        pains: [
            'Processo manual que trava o crescimento da equipe',
            'Site institucional que não gera contato nenhum',
            'Planilhas conectadas na base de copiar e colar',
            'Prazo de meses pra ver qualquer resultado no ar',
        ],
        solutionsHeading: 'O que entregamos',
        solutions: [
            'Sistema feito sob medida para o processo real da empresa',
            'Site rápido, com estratégia e foco em gerar contatos',
            'Integrações que eliminam o retrabalho manual',
            'Entrega em semanas, com escopo fechado antes de começar',
        ],
        features: [
            'Sites institucionais e landing pages',
            'Sistemas e integrações sob medida',
            'Aplicativos web e mobile',
            'Suporte direto com quem constrói',
        ],
        primaryCta: { label: 'Falar sobre um projeto', href: customProjectWhatsappUrl, external: true },
        secondaryCta: {
            label: 'Ver desenvolvimento de sites',
            href: '/desenvolvimento-de-sites-para-empresas',
            external: false,
        },
        accentText: 'text-amber-300',
        accentBg: 'bg-amber-300',
        accentBorder: 'border-amber-300/20',
        accentSoftBg: 'bg-amber-300/10',
        sectionBg: 'bg-amber-50',
        icon: Code2,
        iconBoxClass: 'bg-amber-400/15 text-amber-700',
    },
    ];
}

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
        question: 'Como funciona um projeto sob medida, do início ao fim?',
        answer: 'Conversamos sobre o processo e o escopo, fechamos um plano claro de entrega e construímos o site ou sistema em etapas, com acompanhamento direto — sem depender de uma central de atendimento.',
    },
    {
        question: 'Como faço para falar com a equipe da ABrasil?',
        answer: 'Pelo WhatsApp ou e-mail, com atendimento direto — sem central de atendimento automatizada.',
    },
] as const;

export default function Produtos() {
    const [openFaqIndex, setOpenFaqIndex] = useState<number | null>(0);
    const contact = useContact();
    const offers = buildOffers(contact.whatsapp);

    const structuredData = {
        '@context': 'https://schema.org',
        '@graph': [
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
            {
                '@type': 'Service',
                name: 'Desenvolvimento sob medida',
                provider: { '@type': 'Organization', name: 'ABrasil Sistemas', url: '/' },
                areaServed: 'BR',
                serviceType: 'Desenvolvimento de sites e sistemas sob medida',
                description: 'Sites institucionais, landing pages e sistemas sob medida para processos que uma ferramenta pronta não resolve.',
            },
        ],
    };

    return (
        <>
            <Head title="Produtos ABrasil — VetorOS, VetorPet e sistemas sob medida">
                <meta
                    name="description"
                    content="Conheça o VetorOS, o VetorPet e os projetos sob medida da ABrasil: o problema real de cada operação e como cada solução resolve."
                />
                <meta name="robots" content="index, follow, max-image-preview:large" />
                <meta name="theme-color" content="#08111f" />
                <meta property="og:locale" content="pt_BR" />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content="ABrasil Sistemas" />
                <meta property="og:title" content="Produtos ABrasil — VetorOS, VetorPet e sistemas sob medida" />
                <meta
                    property="og:description"
                    content="O problema real de cada operação e a solução da ABrasil para ela: VetorOS, VetorPet e desenvolvimento sob medida."
                />
                <meta property="og:image" content="https://abrasilsistemas.com.br/images/dashboard-vetoros.webp" />
                <meta property="og:image:type" content="image/webp" />
                <meta property="og:image:width" content="1926" />
                <meta property="og:image:height" content="934" />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:image" content="https://abrasilsistemas.com.br/images/dashboard-vetoros.webp" />
                <link rel="canonical" href="https://abrasilsistemas.com.br/produtos" />
                <script type="application/ld+json">{JSON.stringify(structuredData)}</script>
            </Head>

            <div className="ab-public-site ab-static-light min-h-screen overflow-x-hidden bg-white text-slate-900 selection:bg-cyan-200 selection:text-slate-950">
                <SecondaryPageHeader
                    showProductsMenu={false}
                    extraLinks={offers.map((offer) => ({ href: `#${offer.id}`, label: offer.name }))}
                />

                <main>
                    <section className="relative isolate overflow-hidden bg-slate-950/95 pt-36 pb-16 text-white sm:pt-44 sm:pb-20">
                        <div className="absolute inset-0 -z-20 bg-[linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] bg-[size:44px_44px]" />
                        <div className="absolute top-10 right-[-12rem] -z-10 size-[38rem] rounded-full bg-sky-500/20 blur-[120px]" />
                        <div className="mx-auto max-w-[86rem] px-5 sm:px-8 lg:px-12">
                            <p className="inline-flex items-center gap-2 rounded-full border border-sky-300/25 bg-sky-300/10 px-4 py-2 text-xs font-extrabold tracking-[0.12em] text-sky-200 uppercase">
                                Produtos ABrasil
                            </p>
                            <h1 className="mt-7 max-w-3xl text-[clamp(2.75rem,5.5vw,4.75rem)] leading-[0.98] font-black tracking-[-0.05em] text-balance">
                                O problema real da sua operação.
                                <span className="mt-2 block text-sky-300">A solução que resolve de verdade.</span>
                            </h1>
                            <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-300">
                                Antes de falar de recursos, olhamos para o que trava sua empresa hoje. Veja o que muda na prática com o VetorOS,
                                o VetorPet ou um projeto sob medida.
                            </p>
                            <div className="mt-9 flex flex-wrap gap-3">
                                {offers.map((offer) => (
                                    <a
                                        key={offer.id}
                                        href={`#${offer.id}`}
                                        className="inline-flex items-center gap-2 rounded-full border border-white/15 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/5"
                                    >
                                        {offer.name}
                                        <ArrowRight className="size-4" />
                                    </a>
                                ))}
                            </div>
                        </div>
                    </section>

                    {offers.map((offer, index) => {
                        const OfferIcon = offer.icon;

                        return (
                        <section key={offer.id} id={offer.id} className={`scroll-mt-20 py-24 sm:py-32 ${offer.sectionBg}`}>
                            <div className="mx-auto grid max-w-[86rem] items-center gap-16 px-5 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:px-12">
                                <div>
                                    <p className={`text-xs font-extrabold tracking-[0.18em] uppercase ${offer.accentText === 'text-sky-300' ? 'text-blue-800' : offer.accentText === 'text-violet-300' ? 'text-violet-700' : 'text-amber-800'}`}>
                                        {offer.eyebrow}
                                    </p>
                                    <h2 className="mt-5 text-4xl leading-[1.02] font-black tracking-[-0.05em] text-balance text-slate-950 sm:text-6xl">
                                        {offer.title}
                                        <span
                                            className={`block ${offer.accentText === 'text-sky-300' ? 'text-blue-700' : offer.accentText === 'text-violet-300' ? 'text-violet-700' : 'text-amber-700'}`}
                                        >
                                            {offer.titleHighlight}
                                        </span>
                                    </h2>
                                    <p className="mt-7 max-w-xl text-lg leading-8 text-slate-700">{offer.description}</p>
                                    <ul className="mt-8 grid gap-4 sm:grid-cols-2">
                                        {offer.features.map((feature) => (
                                            <li key={feature} className="flex items-start gap-3 text-sm font-bold text-slate-800">
                                                <span
                                                    className={`mt-0.5 grid size-5 shrink-0 place-items-center rounded-full text-white ${offer.accentText === 'text-sky-300' ? 'bg-blue-700' : offer.accentText === 'text-violet-300' ? 'bg-violet-700' : 'bg-amber-600'}`}
                                                >
                                                    <Check className="size-3" strokeWidth={3} />
                                                </span>
                                                {feature}
                                            </li>
                                        ))}
                                    </ul>
                                    <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                        <a
                                            href={offer.primaryCta.href}
                                            target={offer.primaryCta.external ? '_blank' : undefined}
                                            rel={offer.primaryCta.external ? 'noreferrer' : undefined}
                                            className={`inline-flex h-13 items-center justify-center gap-2 rounded-full px-6 text-sm font-black text-slate-950 transition hover:-translate-y-0.5 ${offer.accentBg}`}
                                        >
                                            {offer.primaryCta.label}
                                            <ArrowUpRight className="size-4" />
                                        </a>
                                        <a
                                            href={offer.secondaryCta.href}
                                            target={offer.secondaryCta.external ? '_blank' : undefined}
                                            rel={offer.secondaryCta.external ? 'noreferrer' : undefined}
                                            className="inline-flex h-13 items-center justify-center gap-2 rounded-full border border-slate-300 bg-white/70 px-6 text-sm font-bold text-slate-800 transition hover:bg-white"
                                        >
                                            {offer.secondaryCta.label}
                                        </a>
                                    </div>
                                </div>

                                <div className="relative">
                                    <div className={`absolute top-4 right-4 -bottom-4 left-4 rounded-[2rem] ${offer.accentBg}`} />
                                    <div className="relative rounded-[2rem] bg-slate-950 p-7 text-white shadow-2xl sm:p-10">
                                        <div className="flex items-center gap-4">
                                            {offer.logo ? (
                                                <img src={offer.logo} alt={offer.name} className={`size-12 shrink-0 ${offer.logoClass}`} />
                                            ) : (
                                                OfferIcon && (
                                                    <span className={`grid size-12 shrink-0 place-items-center rounded-2xl ${offer.iconBoxClass}`}>
                                                        <OfferIcon className="size-6" />
                                                    </span>
                                                )
                                            )}
                                            <p className={`text-xs font-bold tracking-[0.14em] uppercase ${offer.accentText}`}>Problema e Solução</p>
                                        </div>
                                        <div className="mt-8 grid gap-4 sm:grid-cols-2">
                                            <div className="rounded-2xl border border-red-300/15 bg-red-300/5 p-6">
                                                <p className="text-sm font-black text-red-200">{offer.painsHeading}</p>
                                                <ul className="mt-5 space-y-4 text-sm leading-6 text-slate-400">
                                                    {offer.pains.map((pain) => (
                                                        <li key={pain} className="flex gap-2">
                                                            <X className="mt-1 size-4 shrink-0 text-red-300" />
                                                            {pain}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                            <div className={`rounded-2xl border p-6 ${offer.accentBorder} ${offer.accentSoftBg}`}>
                                                <p className={`text-sm font-black ${offer.accentText}`}>{offer.solutionsHeading}</p>
                                                <ul className="mt-5 space-y-4 text-sm leading-6 text-slate-200">
                                                    {offer.solutions.map((solution) => (
                                                        <li key={solution} className="flex gap-2">
                                                            <Check className={`mt-1 size-4 shrink-0 ${offer.accentText}`} />
                                                            {solution}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {index < offers.length - 1 && <div className="mt-24 border-t border-slate-900/5 sm:mt-32" />}
                        </section>
                        );
                    })}

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
                        <div className="relative mx-auto max-w-4xl text-center">
                            <p className="text-xs font-extrabold tracking-[0.18em] text-cyan-200 uppercase">Não sabe qual escolher?</p>
                            <h2 className="mt-6 text-4xl leading-[0.98] font-black tracking-[-0.055em] text-balance sm:text-6xl">
                                Fale com a gente e a gente ajuda a decidir.
                            </h2>
                            <p className="mx-auto mt-7 max-w-2xl text-lg leading-8 text-blue-100">
                                Conte qual é o problema da sua operação e mostramos o caminho mais direto — produto pronto ou projeto sob medida.
                            </p>
                            <div className="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <a
                                    href={buildWhatsappUrl(contact.whatsapp, 'Olá, preciso de ajuda com uma solução da ABrasil.')}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex h-14 items-center gap-3 rounded-full bg-white px-7 text-sm font-black text-blue-800 shadow-xl transition hover:-translate-y-1"
                                >
                                    Falar com a equipe <MessageCircle className="size-4" />
                                </a>
                                <Link
                                    href="/"
                                    className="inline-flex h-14 items-center gap-3 rounded-full border border-white/30 bg-white/10 px-7 text-sm font-black text-white transition hover:-translate-y-1 hover:bg-white/20"
                                >
                                    Voltar para a home <ArrowRight className="size-4" />
                                </Link>
                            </div>
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
