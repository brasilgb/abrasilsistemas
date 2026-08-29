import { Form, Head } from '@inertiajs/react';
import { Mail, MessageCircle } from 'lucide-react';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import { CookieConsent } from '@/components/cookie-consent';
import InputError from '@/components/input-error';
import { PublicFooter } from '@/components/public-footer';
import { SecondaryPageHeader } from '@/components/secondary-page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import { buildWhatsappUrl, useContact } from '@/lib/contact';

export default function Contato({ products }: { products: Record<string, string> }) {
    const contact = useContact();
    const whatsappUrl = buildWhatsappUrl(contact.whatsapp, 'Olá, conheci a ABrasil Sistemas pelo site e gostaria de mais informações.');

    return (
        <>
            <Head title="Contato | ABrasil Sistemas">
                <meta
                    name="description"
                    content="Fale com a ABrasil Sistemas sobre um site, um sistema sob medida, o VetorOS ou o VetorPet."
                />
                <meta name="robots" content="index, follow, max-image-preview:large" />
                <meta name="theme-color" content="#08111f" />
                <link rel="canonical" href="https://abrasilsistemas.com.br/contato" />
            </Head>

            <div className="ab-public-site ab-static-light min-h-screen overflow-x-hidden bg-white text-slate-900 selection:bg-cyan-200 selection:text-slate-950">
                <SecondaryPageHeader />

                <main>
                    <section className="relative isolate overflow-hidden bg-slate-950/95 pt-36 pb-20 text-white sm:pt-44 sm:pb-28">
                        <div className="absolute inset-0 -z-20 bg-[linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] bg-[size:44px_44px]" />
                        <div className="absolute top-10 right-[-12rem] -z-10 size-[38rem] rounded-full bg-sky-500/20 blur-[120px]" />
                        <div className="mx-auto max-w-[86rem] px-5 sm:px-8 lg:px-12">
                            <p className="inline-flex items-center gap-2 rounded-full border border-sky-300/25 bg-sky-300/10 px-4 py-2 text-xs font-extrabold tracking-[0.12em] text-sky-200 uppercase">
                                Contato
                            </p>
                            <h1 className="mt-7 max-w-2xl text-[clamp(2.5rem,5vw,4.25rem)] leading-[1.02] font-black tracking-[-0.045em] text-balance">
                                Tem uma ideia ou precisa melhorar um processo da sua empresa?
                            </h1>
                            <p className="mt-6 max-w-xl text-lg leading-8 text-slate-300">Vamos conversar.</p>
                        </div>
                    </section>

                    <section className="py-16 sm:py-24">
                        <div className="mx-auto grid max-w-[70rem] gap-12 px-5 sm:px-8 lg:grid-cols-[1fr_1.2fr] lg:px-12">
                            <div>
                                <h2 className="text-2xl font-black tracking-[-0.03em] text-slate-950">Fale direto com a gente</h2>
                                <p className="mt-3 text-sm leading-6 text-slate-600">
                                    Sem central de atendimento automatizada — sua mensagem chega direto para a equipe da ABrasil.
                                </p>
                                <div className="mt-8 grid gap-4">
                                    <a
                                        href={whatsappUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                    >
                                        <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-700">
                                            <MessageCircle className="size-5" />
                                        </span>
                                        <div>
                                            <p className="text-sm font-black text-slate-950">WhatsApp</p>
                                            <p className="text-sm text-slate-600">{contact.whatsappDisplay}</p>
                                        </div>
                                    </a>
                                    <a
                                        href={`mailto:${contact.email}`}
                                        className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                    >
                                        <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700">
                                            <Mail className="size-5" />
                                        </span>
                                        <div>
                                            <p className="text-sm font-black text-slate-950">E-mail</p>
                                            <p className="text-sm text-slate-600">{contact.email}</p>
                                        </div>
                                    </a>
                                </div>
                            </div>

                            <div className="rounded-[1.75rem] border border-slate-200 bg-white p-7 shadow-sm sm:p-9">
                                <Form {...ContactController.store.form()} resetOnSuccess className="grid gap-5">
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="contact_name">Nome</Label>
                                                <Input id="contact_name" name="contact_name" required autoComplete="name" />
                                                <InputError message={errors.contact_name} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="company_name">Empresa</Label>
                                                <Input id="company_name" name="company_name" required autoComplete="organization" />
                                                <InputError message={errors.company_name} />
                                            </div>

                                            <div className="grid gap-5 sm:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="whatsapp">Telefone / WhatsApp</Label>
                                                    <Input id="whatsapp" name="whatsapp" autoComplete="tel" placeholder="(51) 99999-9999" />
                                                    <InputError message={errors.whatsapp} />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="email">E-mail</Label>
                                                    <Input id="email" name="email" type="email" autoComplete="email" />
                                                    <InputError message={errors.email} />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="product">Serviço de interesse</Label>
                                                <select
                                                    id="product"
                                                    name="product"
                                                    required
                                                    defaultValue=""
                                                    className="border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                >
                                                    <option value="" disabled>
                                                        Selecione uma opção
                                                    </option>
                                                    {Object.entries(products).map(([value, label]) => (
                                                        <option key={value} value={value}>
                                                            {label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError message={errors.product} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="notes">Mensagem</Label>
                                                <textarea
                                                    id="notes"
                                                    name="notes"
                                                    required
                                                    rows={4}
                                                    placeholder="Conte um pouco sobre o que você precisa"
                                                    className="border-input placeholder:text-muted-foreground flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                />
                                                <InputError message={errors.notes} />
                                            </div>

                                            {/* Honeypot: hidden from real visitors, but a bot filling every field will trip it. */}
                                            <div className="absolute -left-[9999px] opacity-0" aria-hidden="true">
                                                <label htmlFor="website_hp">Deixe este campo em branco</label>
                                                <input id="website_hp" name="website_hp" type="text" tabIndex={-1} autoComplete="off" />
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="mt-2 h-12 rounded-full bg-blue-700 text-base font-bold text-white hover:bg-blue-800"
                                            >
                                                {processing ? 'Enviando...' : 'Enviar mensagem'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
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
