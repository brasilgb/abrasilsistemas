import { Head } from '@inertiajs/react';
import { ArrowRight, Check, Code2, LayoutTemplate, MessageCircle, MonitorSmartphone, Plug, Smartphone, Workflow } from 'lucide-react';
import { CookieConsent } from '@/components/cookie-consent';
import { PublicFooter } from '@/components/public-footer';
import { SecondaryPageHeader } from '@/components/secondary-page-header';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import { buildWhatsappUrl, useContact } from '@/lib/contact';

type ServiceSection = {
    id: string;
    icon: typeof Code2;
    title: string;
    description: string;
    items: string[];
    accentBg: string;
    accentText: string;
    sectionBg: string;
};

const services: ServiceSection[] = [
    {
        id: 'sites',
        icon: MonitorSmartphone,
        title: 'Criação de Sites',
        description: 'Sites institucionais profissionais, rápidos e preparados para apresentar sua empresa com credibilidade.',
        items: [
            'Institucionais, responsivos e modernos',
            'Rápidos, adaptados para desktop, tablet e celular',
            'Preparados para SEO básico',
            'Integrados a formulários e WhatsApp',
        ],
        accentBg: 'bg-cyan-300',
        accentText: 'text-blue-800',
        sectionBg: 'bg-[#dffbff]',
    },
    {
        id: 'landing-pages',
        icon: LayoutTemplate,
        title: 'Landing Pages',
        description: 'Páginas desenvolvidas com foco total em apresentar um serviço, um produto ou uma campanha e gerar contatos.',
        items: [
            'Apresentação de produtos e serviços',
            'Campanhas e lançamentos',
            'Geração de leads',
            'Contato comercial direto',
        ],
        accentBg: 'bg-blue-600',
        accentText: 'text-white',
        sectionBg: 'bg-white',
    },
    {
        id: 'sistemas',
        icon: Code2,
        title: 'Sistemas Sob Medida',
        description: 'Desenvolvimento de sistemas para processos que não são atendidos adequadamente por softwares genéricos.',
        items: [
            'Sistemas administrativos e de atendimento',
            'Controle de clientes e de serviços',
            'Dashboards e portais internos',
            'Ferramentas operacionais e sistemas SaaS',
        ],
        accentBg: 'bg-amber-300',
        accentText: 'text-amber-800',
        sectionBg: 'bg-amber-50',
    },
    {
        id: 'aplicativos',
        icon: Smartphone,
        title: 'Aplicativos',
        description: 'Aplicativos integrados a sistemas web e APIs, para uso interno, de clientes ou de equipes externas.',
        items: [
            'Aplicativos operacionais',
            'Aplicativos para clientes',
            'Aplicativos para equipes externas',
            'Integração com plataformas existentes',
        ],
        accentBg: 'bg-violet-300',
        accentText: 'text-violet-800',
        sectionBg: 'bg-violet-50',
    },
    {
        id: 'integracoes',
        icon: Plug,
        title: 'Integrações e APIs',
        description: 'Conexão entre sistemas, APIs, bancos de dados e serviços externos para eliminar retrabalho manual.',
        items: [
            'Integração entre sistemas próprios e de terceiros',
            'Consumo e criação de APIs',
            'Conexão com bancos de dados',
            'Integração com plataformas web',
        ],
        accentBg: 'bg-cyan-300',
        accentText: 'text-blue-800',
        sectionBg: 'bg-[#dffbff]',
    },
    {
        id: 'automacao',
        icon: Workflow,
        title: 'Automação de Processos',
        description: 'Fluxos automáticos para reduzir tarefas manuais, sempre como parte de um projeto de software ou integração.',
        items: [
            'Automação de rotinas operacionais',
            'Fluxos entre sistemas e integrações',
            'Redução de retrabalho manual',
            'Sempre dentro de um projeto de tecnologia',
        ],
        accentBg: 'bg-amber-300',
        accentText: 'text-amber-800',
        sectionBg: 'bg-amber-50',
    },
];

export default function Servicos() {
    const contact = useContact();
    const whatsappUrl = buildWhatsappUrl(contact.whatsapp, 'Olá, quero saber mais sobre os serviços da ABrasil Sistemas.');

    const structuredData = {
        '@context': 'https://schema.org',
        '@graph': services.map((service) => ({
            '@type': 'Service',
            name: service.title,
            provider: { '@type': 'Organization', name: 'ABrasil Sistemas', url: '/' },
            areaServed: 'BR',
            description: service.description,
        })),
    };

    return (
        <>
            <Head title="Serviços — Sites, sistemas, apps e integrações | ABrasil Sistemas">
                <meta
                    name="description"
                    content="Criação de sites, landing pages, sistemas sob medida, aplicativos, integrações e automação de processos."
                />
                <meta name="robots" content="index, follow, max-image-preview:large" />
                <meta name="theme-color" content="#08111f" />
                <link rel="canonical" href="https://abrasilsistemas.com.br/servicos" />
                <script type="application/ld+json">{JSON.stringify(structuredData)}</script>
            </Head>

            <div className="ab-public-site ab-static-light min-h-screen overflow-x-hidden bg-white text-slate-900 selection:bg-cyan-200 selection:text-slate-950">
                {/* The "Serviços" dropdown is hidden here — the hero's own pill links below already cover in-page navigation for this page's 6 sections. */}
                <SecondaryPageHeader showServicesMenu={false} />

                <main>
                    <section className="relative isolate overflow-hidden bg-slate-950/95 pt-36 pb-16 text-white sm:pt-44 sm:pb-20">
                        <div className="absolute inset-0 -z-20 bg-[linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] bg-[size:44px_44px]" />
                        <div className="absolute top-10 right-[-12rem] -z-10 size-[38rem] rounded-full bg-sky-500/20 blur-[120px]" />
                        <div className="mx-auto max-w-[86rem] px-5 sm:px-8 lg:px-12">
                            <p className="inline-flex items-center gap-2 rounded-full border border-sky-300/25 bg-sky-300/10 px-4 py-2 text-xs font-extrabold tracking-[0.12em] text-sky-200 uppercase">
                                Serviços ABrasil
                            </p>
                            <h1 className="mt-7 max-w-3xl text-[clamp(2.75rem,5.5vw,4.75rem)] leading-[0.98] font-black tracking-[-0.05em] text-balance">
                                Tecnologia desenvolvida
                                <span className="mt-2 block text-sky-300">para resolver necessidades reais do negócio.</span>
                            </h1>
                            <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-300">
                                Sites, landing pages, sistemas sob medida, aplicativos, integrações e automação de processos — sempre como
                                parte de um projeto de desenvolvimento de tecnologia.
                            </p>
                            <div className="mt-9 flex flex-wrap gap-3">
                                {services.map((service) => (
                                    <a
                                        key={service.id}
                                        href={`#${service.id}`}
                                        className="inline-flex items-center gap-2 rounded-full border border-white/15 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/5"
                                    >
                                        {service.title}
                                        <ArrowRight className="size-4" />
                                    </a>
                                ))}
                            </div>
                        </div>
                    </section>

                    {services.map((service, index) => {
                        const Icon = service.icon;

                        return (
                            <section key={service.id} id={service.id} className={`scroll-mt-20 py-20 sm:py-24 ${service.sectionBg}`}>
                                <div className="mx-auto grid max-w-[86rem] items-center gap-12 px-5 sm:px-8 lg:grid-cols-[0.5fr_0.5fr] lg:px-12">
                                    <div>
                                        <span className={`grid size-14 place-items-center rounded-2xl ${service.accentBg} ${service.accentText}`}>
                                            <Icon className="size-6" />
                                        </span>
                                        <h2 className="mt-6 text-3xl leading-tight font-black tracking-[-0.04em] text-balance text-slate-950 sm:text-4xl">
                                            {service.title}
                                        </h2>
                                        <p className="mt-4 max-w-lg text-lg leading-8 text-slate-700">{service.description}</p>
                                    </div>
                                    <div className="rounded-[1.75rem] border border-slate-200 bg-white/80 p-7 shadow-sm sm:p-9">
                                        <ul className="grid gap-4">
                                            {service.items.map((item) => (
                                                <li key={item} className="flex items-start gap-3 text-sm font-semibold text-slate-800">
                                                    <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-blue-700 text-white">
                                                        <Check className="size-3" strokeWidth={3} />
                                                    </span>
                                                    {item}
                                                </li>
                                            ))}
                                        </ul>
                                        <a
                                            href="/contato"
                                            className="mt-7 flex items-center justify-between border-t border-slate-200 pt-6 text-sm font-black text-slate-950"
                                        >
                                            Solicitar orçamento
                                            <ArrowRight className="size-4" />
                                        </a>
                                    </div>
                                </div>
                                {index < services.length - 1 && <div className="mt-20 border-t border-slate-900/5 sm:mt-24" />}
                            </section>
                        );
                    })}

                    <section className="relative overflow-hidden bg-blue-700 px-5 py-24 text-white sm:px-8 sm:py-32">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(34,211,238,.35),transparent_30%),radial-gradient(circle_at_90%_80%,rgba(15,23,42,.28),transparent_35%)]" />
                        <div className="relative mx-auto max-w-4xl text-center">
                            <p className="text-xs font-extrabold tracking-[0.18em] text-cyan-200 uppercase">Tem uma ideia ou um processo travado?</p>
                            <h2 className="mt-6 text-4xl leading-[0.98] font-black tracking-[-0.055em] text-balance sm:text-6xl">
                                Vamos conversar sobre o seu projeto.
                            </h2>
                            <div className="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <a
                                    href="/contato"
                                    className="inline-flex h-14 items-center gap-3 rounded-full bg-white px-7 text-sm font-black text-blue-800 shadow-xl transition hover:-translate-y-1"
                                >
                                    Solicitar orçamento <ArrowRight className="size-4" />
                                </a>
                                <a
                                    href={whatsappUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex h-14 items-center gap-3 rounded-full border border-white/30 bg-white/10 px-7 text-sm font-black text-white transition hover:-translate-y-1 hover:bg-white/20"
                                >
                                    Falar no WhatsApp <MessageCircle className="size-4" />
                                </a>
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
