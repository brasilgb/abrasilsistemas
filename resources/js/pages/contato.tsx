import { Form } from '@inertiajs/react';
import { Mail, MessageCircle } from 'lucide-react';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import InputError from '@/components/input-error';
import { Container, Eyebrow, HeroBackdrop } from '@/components/site/blocks';
import { SiteLayout } from '@/components/site/site-layout';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { heroImages } from '@/data/hero-images';
import { trackEvent } from '@/lib/analytics';
import { readSourcePage, useContact, useSiteWhatsappUrl } from '@/lib/contact';

const fieldClass =
    'border-input flex w-full rounded-md border bg-white px-3 text-[15px] shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export default function Contato({
    projectTypes,
    selectedType,
}: {
    projectTypes: Record<string, string>;
    selectedType: string | null;
}) {
    const contact = useContact();
    const whatsappUrl = useSiteWhatsappUrl(
        'Olá, conheci a ABrasil Sistemas pelo site e quero conversar sobre um projeto.',
    );

    return (
        <SiteLayout>
            <section className="relative isolate overflow-hidden bg-[#0b1220] pt-28 pb-14 text-white sm:pt-32 sm:pb-16">
                <HeroBackdrop image={heroImages.contato} />
                <Container>
                    <Eyebrow inverse>Contato</Eyebrow>
                    <h1 className="mt-4 max-w-3xl text-4xl leading-[1.1] font-semibold tracking-tight text-balance sm:text-5xl">
                        Conte o que a sua empresa precisa resolver.
                    </h1>
                    <p className="mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                        Sua mensagem chega direto à equipe que desenvolve as
                        soluções. Respondemos com perguntas objetivas e os
                        próximos passos.
                    </p>
                </Container>
            </section>

            <section id="formulario" className="scroll-mt-20 py-16 sm:py-20">
                <Container className="grid gap-12 lg:grid-cols-[1.35fr_0.65fr] lg:gap-16">
                    <div className="rounded-lg border border-slate-200 p-6 sm:p-8">
                        <h2 className="text-xl font-semibold text-slate-900">
                            Fale sobre o seu projeto
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Informe ao menos um e-mail ou WhatsApp para retorno.
                        </p>
                        <Form
                            {...ContactController.store.form()}
                            resetOnSuccess
                            onSuccess={() => {
                                trackEvent('generate_lead', {
                                    form: 'contato',
                                });
                                trackEvent('form_submit', { form: 'contato' });
                            }}
                            // Página de origem lida no envio (sessionStorage), só no navegador.
                            transform={(data) => ({
                                ...data,
                                source_page: readSourcePage() ?? '',
                            })}
                            className="mt-6 grid gap-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="contact_name">
                                                Nome
                                            </Label>
                                            <Input
                                                id="contact_name"
                                                name="contact_name"
                                                required
                                                autoComplete="name"
                                                className="h-11 bg-white"
                                            />
                                            <InputError
                                                message={errors.contact_name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="company_name">
                                                Empresa
                                            </Label>
                                            <Input
                                                id="company_name"
                                                name="company_name"
                                                required
                                                autoComplete="organization"
                                                className="h-11 bg-white"
                                            />
                                            <InputError
                                                message={errors.company_name}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="email">
                                                E-mail
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                autoComplete="email"
                                                className="h-11 bg-white"
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="whatsapp">
                                                WhatsApp
                                            </Label>
                                            <Input
                                                id="whatsapp"
                                                name="whatsapp"
                                                type="tel"
                                                inputMode="tel"
                                                autoComplete="tel"
                                                placeholder="(51) 99999-9999"
                                                className="h-11 bg-white"
                                            />
                                            <InputError
                                                message={errors.whatsapp}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="product">
                                            Tipo de projeto
                                        </Label>
                                        <select
                                            id="product"
                                            name="product"
                                            required
                                            defaultValue={selectedType ?? ''}
                                            className={`${fieldClass} h-11`}
                                        >
                                            <option value="" disabled>
                                                Selecione uma opção
                                            </option>
                                            {Object.entries(projectTypes).map(
                                                ([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError message={errors.product} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="notes">
                                            Descrição da necessidade
                                        </Label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            required
                                            rows={5}
                                            placeholder={
                                                selectedType === 'seo'
                                                    ? 'Endereço do site, o que você espera da busca orgânica e, se houver, quem cuida do site hoje.'
                                                    : 'Como o processo funciona hoje, o que precisa mudar e, se houver, prazos ou sistemas envolvidos.'
                                            }
                                            className={`${fieldClass} py-2.5 placeholder:text-muted-foreground`}
                                        />
                                        <InputError message={errors.notes} />
                                    </div>

                                    {/* Honeypot: invisível para visitantes; robôs que preenchem tudo são barrados. */}
                                    <div
                                        className="absolute -left-[9999px] opacity-0"
                                        aria-hidden="true"
                                    >
                                        <label htmlFor="website_hp">
                                            Deixe este campo em branco
                                        </label>
                                        <input
                                            id="website_hp"
                                            name="website_hp"
                                            type="text"
                                            tabIndex={-1}
                                            autoComplete="off"
                                        />
                                    </div>

                                    <p className="text-xs leading-5 text-slate-500">
                                        Usamos seus dados apenas para responder
                                        a este contato, conforme a{' '}
                                        <a
                                            href="/politica-de-privacidade"
                                            className="underline underline-offset-2"
                                        >
                                            Política de Privacidade
                                        </a>
                                        .
                                    </p>

                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex h-12 items-center justify-center rounded-md bg-blue-700 px-6 text-[15px] font-semibold text-white transition hover:bg-blue-800 disabled:opacity-60 sm:justify-self-start"
                                    >
                                        {processing
                                            ? 'Enviando…'
                                            : 'Falar sobre meu projeto'}
                                    </button>
                                </>
                            )}
                        </Form>
                    </div>

                    <aside className="grid content-start gap-8">
                        <div>
                            <h2 className="text-base font-semibold text-slate-900">
                                Prefere falar agora?
                            </h2>
                            <ul className="mt-4 grid gap-3">
                                <li>
                                    <a
                                        href={whatsappUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 rounded-lg border border-slate-200 p-4 transition hover:border-slate-300 hover:bg-slate-50"
                                    >
                                        <MessageCircle className="size-5 text-emerald-700" />
                                        <span>
                                            <span className="block text-sm font-semibold text-slate-900">
                                                WhatsApp
                                            </span>
                                            <span className="block text-sm text-slate-600">
                                                {contact.whatsappDisplay}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href={`mailto:${contact.email}`}
                                        className="flex items-center gap-3 rounded-lg border border-slate-200 p-4 transition hover:border-slate-300 hover:bg-slate-50"
                                    >
                                        <Mail className="size-5 text-blue-700" />
                                        <span className="min-w-0">
                                            <span className="block text-sm font-semibold text-slate-900">
                                                E-mail
                                            </span>
                                            <span className="block text-sm break-all text-slate-600">
                                                {contact.email}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-slate-900">
                                O que acontece depois
                            </h2>
                            <ol className="mt-4 grid gap-4 text-sm leading-6 text-slate-600">
                                <li>
                                    <span className="font-semibold text-slate-900">
                                        1. Retorno da equipe
                                    </span>{' '}
                                    — confirmamos o recebimento e tiramos
                                    dúvidas iniciais.
                                </li>
                                <li>
                                    <span className="font-semibold text-slate-900">
                                        2. Conversa de diagnóstico
                                    </span>{' '}
                                    — entendemos o processo e o resultado
                                    esperado.
                                </li>
                                <li>
                                    <span className="font-semibold text-slate-900">
                                        3. Proposta
                                    </span>{' '}
                                    — escopo, etapas e prazos definidos por
                                    escrito.
                                </li>
                            </ol>
                        </div>
                    </aside>
                </Container>
            </section>
        </SiteLayout>
    );
}
