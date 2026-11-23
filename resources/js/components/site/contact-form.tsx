import { Form, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import InputError from '@/components/input-error';
import { Container, SectionHeading } from '@/components/site/blocks';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trackEvent } from '@/lib/analytics';
import { readSourcePage, useContact } from '@/lib/contact';

const fieldClass =
    'border-input flex w-full rounded-md border bg-white px-3 text-base text-slate-900 shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const DEFAULT_PLACEHOLDER =
    'Como o processo funciona hoje, o que precisa mudar e, se houver, prazos ou sistemas envolvidos.';

/**
 * Formulário comercial do site (contato, home, serviços e produtos). A confirmação só aparece
 * depois que o servidor gravou a solicitação; erro de gravação ou de limite de envios volta em
 * `errors.form`, com os campos preenchidos.
 */
export function ContactForm({
    projectTypes,
    selectedType = null,
    placeholder = DEFAULT_PLACEHOLDER,
    submitLabel = 'Solicitar orçamento',
}: {
    projectTypes: Record<string, string>;
    selectedType?: string | null;
    placeholder?: string;
    submitLabel?: string;
}) {
    const id = useId();
    const page = usePage().url.split(/[?#]/)[0] || '/';
    const contact = useContact();
    const [sent, setSent] = useState(false);
    const confirmation = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (sent) {
            confirmation.current?.focus();
        }
    }, [sent]);

    if (sent) {
        return (
            <div
                ref={confirmation}
                role="status"
                tabIndex={-1}
                className="rounded-lg border border-emerald-200 bg-emerald-50 p-6 outline-none"
            >
                <p className="flex items-center gap-2 text-lg font-semibold text-emerald-900">
                    <CheckCircle2 className="size-5" aria-hidden="true" />
                    Recebemos a sua mensagem.
                </p>
                <p className="mt-2 text-[15px] leading-7 text-emerald-900">
                    A solicitação foi registrada e a equipe vai responder pelo
                    contato que você informou.
                </p>
                <button
                    type="button"
                    onClick={() => setSent(false)}
                    className="mt-4 text-sm font-semibold text-emerald-900 underline underline-offset-4"
                >
                    Enviar outra mensagem
                </button>
            </div>
        );
    }

    return (
        <Form
            {...ContactController.store.form()}
            options={{ preserveScroll: true, preserveState: true }}
            onSuccess={() => {
                trackEvent('generate_lead', { form: 'contato', page });
                trackEvent('form_submit', { form: 'contato', page });
                setSent(true);
            }}
            // Página de origem lida no envio: a própria página ou, na de contato, a anterior.
            transform={(data) => ({
                ...data,
                source_page:
                    page === '/contato' ? (readSourcePage() ?? '') : page,
            })}
            // HTML pré-renderizado: sem JavaScript o envio não funciona, então não aceita digitação até montar.
            inert={typeof window === 'undefined'}
            className="grid gap-5"
        >
            {({ processing, errors }) => {
                const field = (name: string) => ({
                    id: `${id}-${name}`,
                    name,
                    'aria-invalid': errors[name] ? true : undefined,
                    'aria-describedby': errors[name]
                        ? `${id}-${name}-erro`
                        : undefined,
                });
                const error = (name: string) => (
                    <InputError
                        id={`${id}-${name}-erro`}
                        message={errors[name]}
                    />
                );

                return (
                    <>
                        <noscript>
                            O envio do formulário precisa de JavaScript. Se
                            preferir, escreva para {contact.email}.
                        </noscript>
                        {errors.form && (
                            <p
                                role="alert"
                                className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800"
                            >
                                {errors.form}
                            </p>
                        )}
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid content-start gap-2">
                                <Label htmlFor={`${id}-contact_name`}>
                                    Nome
                                </Label>
                                <Input
                                    {...field('contact_name')}
                                    required
                                    maxLength={255}
                                    autoComplete="name"
                                    className="h-11 bg-white"
                                />
                                {error('contact_name')}
                            </div>
                            <div className="grid content-start gap-2">
                                <Label htmlFor={`${id}-company_name`}>
                                    Empresa{' '}
                                    <span className="font-normal text-slate-500">
                                        (opcional)
                                    </span>
                                </Label>
                                <Input
                                    {...field('company_name')}
                                    maxLength={255}
                                    autoComplete="organization"
                                    className="h-11 bg-white"
                                />
                                {error('company_name')}
                            </div>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid content-start gap-2">
                                <Label htmlFor={`${id}-email`}>E-mail</Label>
                                <Input
                                    {...field('email')}
                                    type="email"
                                    maxLength={255}
                                    autoComplete="email"
                                    className="h-11 bg-white"
                                />
                                {error('email')}
                            </div>
                            <div className="grid content-start gap-2">
                                <Label htmlFor={`${id}-whatsapp`}>
                                    Telefone
                                </Label>
                                <Input
                                    {...field('whatsapp')}
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    placeholder="(51) 99999-9999"
                                    className="h-11 bg-white"
                                />
                                {error('whatsapp')}
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`${id}-product`}>
                                Serviço de interesse
                            </Label>
                            <select
                                {...field('product')}
                                required
                                defaultValue={selectedType ?? ''}
                                className={`${fieldClass} h-11`}
                            >
                                <option value="" disabled>
                                    Selecione uma opção
                                </option>
                                {Object.entries(projectTypes).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            {error('product')}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`${id}-notes`}>
                                Descrição da necessidade
                            </Label>
                            <textarea
                                {...field('notes')}
                                required
                                rows={5}
                                maxLength={5000}
                                placeholder={placeholder}
                                className={`${fieldClass} py-2.5 placeholder:text-slate-500`}
                            />
                            {error('notes')}
                        </div>

                        {/* Honeypot: invisível para visitantes; robôs que preenchem tudo são barrados. */}
                        <div
                            className="absolute -left-[9999px] opacity-0"
                            aria-hidden="true"
                        >
                            <label htmlFor={`${id}-website_hp`}>
                                Deixe este campo em branco
                            </label>
                            <input
                                id={`${id}-website_hp`}
                                name="website_hp"
                                type="text"
                                tabIndex={-1}
                                autoComplete="off"
                            />
                        </div>

                        <label className="flex gap-3 text-sm leading-6 text-slate-700">
                            <input
                                type="checkbox"
                                name="marketing_consent"
                                value="1"
                                className="mt-1 size-4 shrink-0 accent-brand-blue"
                            />
                            Aceito receber conteúdos e novidades da ABrasil
                            Sistemas (opcional).
                        </label>

                        <p className="text-xs leading-5 text-slate-600">
                            Informe ao menos um e-mail ou telefone. Usamos seus
                            dados para responder a este contato, conforme a{' '}
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
                            className="inline-flex h-12 items-center justify-center rounded-md bg-brand-blue px-6 text-[15px] font-semibold text-white transition hover:bg-brand-blue-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-blue disabled:opacity-60 sm:justify-self-start"
                        >
                            {processing ? 'Enviando…' : submitLabel}
                        </button>
                    </>
                );
            }}
        </Form>
    );
}

const nextSteps = [
    {
        title: 'Retorno da equipe',
        description: 'Confirmamos o recebimento e tiramos dúvidas iniciais.',
    },
    {
        title: 'Conversa de diagnóstico',
        description: 'Entendemos o processo e o resultado esperado.',
    },
    {
        title: 'Proposta',
        description: 'Escopo, etapas e prazos definidos por escrito.',
    },
] as const;

/** O que acontece depois do envio: o processo de contratação em três passos. */
export function NextSteps() {
    return (
        <ol className="grid gap-4 text-sm leading-6 text-slate-600">
            {nextSteps.map((step, index) => (
                <li key={step.title}>
                    <span className="font-semibold text-slate-900">
                        {index + 1}. {step.title}
                    </span>{' '}
                    — {step.description}
                </li>
            ))}
        </ol>
    );
}

/** Seção de orçamento com o formulário já no contexto da página (âncora #orcamento). */
export function ContactSection({
    title,
    description,
    children,
    ...form
}: {
    title: string;
    description: string;
    children?: ReactNode;
} & Parameters<typeof ContactForm>[0]) {
    return (
        <section
            id="orcamento"
            className="scroll-mt-16 border-t border-slate-200 bg-slate-50 py-20 sm:py-24"
        >
            {/* No celular o formulário vem antes das etapas; no desktop elas ficam ao lado dele. */}
            <Container className="grid gap-x-12 gap-y-10 lg:grid-cols-[0.8fr_1.2fr] lg:grid-rows-[auto_1fr] lg:gap-x-20">
                <SectionHeading
                    eyebrow="Orçamento"
                    title={title}
                    description={description}
                />
                <div className="relative rounded-lg border border-slate-200 bg-white p-5 sm:p-8 lg:col-start-2 lg:row-span-2 lg:row-start-1">
                    <ContactForm {...form} />
                </div>
                <div>
                    <h3 className="mb-4 text-base font-semibold text-slate-900">
                        Como funciona a contratação
                    </h3>
                    <NextSteps />
                    {children}
                </div>
            </Container>
        </section>
    );
}
