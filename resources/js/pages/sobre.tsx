import { Head } from '@inertiajs/react';
import { ArrowRight, Code2, Handshake, MessageCircle, Target, Wrench } from 'lucide-react';
import { CookieConsent } from '@/components/cookie-consent';
import { PublicFooter } from '@/components/public-footer';
import { SecondaryPageHeader } from '@/components/secondary-page-header';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import { buildWhatsappUrl, useContact } from '@/lib/contact';

const highlights = [
    {
        icon: Code2,
        title: 'Desenvolvimento de tecnologia',
        description: 'Sites, sistemas, aplicativos e produtos próprios — o foco da ABrasil é construir software, não apenas peças de marketing.',
    },
    {
        icon: Wrench,
        title: 'Experiência prática com sistemas',
        description: 'O VetorOS e o VetorPet nasceram de processos reais de assistências técnicas e representantes comerciais.',
    },
    {
        icon: Target,
        title: 'Foco em soluções úteis',
        description: 'Cada projeto é pensado para resolver um problema concreto da operação, não para empilhar funcionalidades.',
    },
    {
        icon: Handshake,
        title: 'Proximidade com o cliente',
        description: 'Atendimento direto pelo WhatsApp ou e-mail, sem central de atendimento automatizada.',
    },
] as const;

export default function Sobre() {
    const contact = useContact();
    const whatsappUrl = buildWhatsappUrl(contact.whatsapp, 'Olá, quero conhecer melhor a ABrasil Sistemas.');

    return (
        <>
            <Head title="Sobre a ABrasil Sistemas">
                <meta
                    name="description"
                    content="A ABrasil Sistemas desenvolve soluções digitais pensando na rotina real das empresas: sites, sistemas, aplicativos e produtos próprios."
                />
                <meta name="robots" content="index, follow, max-image-preview:large" />
                <meta name="theme-color" content="#08111f" />
                <link rel="canonical" href="https://abrasilsistemas.com.br/sobre" />
            </Head>

            <div className="ab-public-site ab-static-light min-h-screen overflow-x-hidden bg-white text-slate-900 selection:bg-cyan-200 selection:text-slate-950">
                <SecondaryPageHeader />

                <main>
                    <section className="relative isolate overflow-hidden bg-slate-950/95 pt-36 pb-20 text-white sm:pt-44 sm:pb-28">
                        <div className="absolute inset-0 -z-20 bg-[linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] bg-[size:44px_44px]" />
                        <div className="absolute top-10 right-[-12rem] -z-10 size-[38rem] rounded-full bg-sky-500/20 blur-[120px]" />
                        <div className="mx-auto max-w-[64rem] px-5 text-center sm:px-8 lg:px-12">
                            <p className="mx-auto inline-flex items-center gap-2 rounded-full border border-sky-300/25 bg-sky-300/10 px-4 py-2 text-xs font-extrabold tracking-[0.12em] text-sky-200 uppercase">
                                Sobre a ABrasil
                            </p>
                            <h1 className="mt-7 text-[clamp(2.5rem,5vw,4.25rem)] leading-[1.02] font-black tracking-[-0.045em] text-balance">
                                Tecnologia desenvolvida pensando na rotina real das empresas.
                            </h1>
                            <p className="mx-auto mt-7 max-w-2xl text-lg leading-8 text-slate-300">
                                A ABrasil Sistemas desenvolve soluções digitais pensando na rotina real das empresas. Nosso trabalho envolve
                                criação de sites, sistemas, aplicativos e produtos próprios que ajudam empresas a organizar processos,
                                melhorar o atendimento e utilizar melhor a tecnologia.
                            </p>
                        </div>
                    </section>

                    <section className="py-20 sm:py-28">
                        <div className="mx-auto max-w-[86rem] px-5 sm:px-8 lg:px-12">
                            <div className="mx-auto max-w-2xl text-center">
                                <p className="text-xs font-extrabold tracking-[0.18em] text-blue-700 uppercase">O que nos guia</p>
                                <h2 className="mt-3 text-4xl font-black tracking-[-0.04em] text-balance text-slate-950 sm:text-5xl">
                                    Tecnologia a serviço da operação.
                                </h2>
                            </div>
                            <div className="mt-14 grid gap-6 sm:grid-cols-2">
                                {highlights.map((item) => {
                                    const Icon = item.icon;

                                    return (
                                        <article key={item.title} className="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                                            <span className="grid size-12 place-items-center rounded-2xl bg-blue-50 text-blue-700">
                                                <Icon className="size-5" />
                                            </span>
                                            <h3 className="mt-5 text-lg font-black text-slate-950">{item.title}</h3>
                                            <p className="mt-2 text-sm leading-6 text-slate-600">{item.description}</p>
                                        </article>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section className="relative overflow-hidden bg-blue-700 px-5 py-24 text-white sm:px-8 sm:py-32">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(34,211,238,.35),transparent_30%),radial-gradient(circle_at_90%_80%,rgba(15,23,42,.28),transparent_35%)]" />
                        <div className="relative mx-auto max-w-3xl text-center">
                            <h2 className="text-4xl leading-[0.98] font-black tracking-[-0.055em] text-balance sm:text-6xl">
                                Tem uma ideia ou precisa melhorar um processo da sua empresa?
                            </h2>
                            <p className="mx-auto mt-6 max-w-xl text-lg leading-8 text-blue-100">Vamos conversar.</p>
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
