import { Mail, MessageCircle } from 'lucide-react';
import { Container, Eyebrow, HeroBackdrop } from '@/components/site/blocks';
import { ContactForm, NextSteps } from '@/components/site/contact-form';
import { SiteLayout } from '@/components/site/site-layout';
import { heroImages } from '@/data/hero-images';
import { services } from '@/data/site';
import { useCompany, useContact, useSiteWhatsappUrl } from '@/lib/contact';

export default function Contato({
    projectTypes,
    selectedType,
}: {
    projectTypes: Record<string, string>;
    selectedType: string | null;
}) {
    const contact = useContact();
    const company = useCompany();
    const details = [
        ['Telefone', company?.phone],
        ['Horário de atendimento', company?.businessHours],
        ['Região atendida', company?.serviceArea.join(', ')],
        ['Localização', company?.location],
    ].filter((detail): detail is [string, string] => Boolean(detail[1]));
    const whatsappUrl = useSiteWhatsappUrl(
        'Olá, conheci a ABrasil Sistemas pelo site e quero conversar sobre um projeto.',
    );

    return (
        <SiteLayout>
            <section className="relative isolate overflow-hidden bg-brand-navy pt-28 pb-14 text-white sm:pt-32 sm:pb-16">
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
                    <div className="relative rounded-lg border border-slate-200 p-6 sm:p-8">
                        <h2 className="text-xl font-semibold text-slate-900">
                            Solicite um orçamento
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Informe ao menos um e-mail ou telefone para retorno.
                        </p>
                        <div className="mt-6">
                            <ContactForm
                                projectTypes={projectTypes}
                                selectedType={selectedType}
                                placeholder={
                                    services.find(
                                        (service) =>
                                            service.contactType ===
                                            selectedType,
                                    )?.formPlaceholder
                                }
                            />
                        </div>
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
                                        <Mail className="size-5 text-brand-blue" />
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
                        {details.length > 0 && (
                            <dl className="grid gap-3 text-sm">
                                {details.map(([label, value]) => (
                                    <div key={label}>
                                        <dt className="font-semibold text-slate-900">
                                            {label}
                                        </dt>
                                        <dd className="mt-0.5 leading-6 text-slate-600">
                                            {value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                        <div>
                            <h2 className="text-base font-semibold text-slate-900">
                                O que acontece depois
                            </h2>
                            <div className="mt-4">
                                <NextSteps />
                            </div>
                        </div>
                    </aside>
                </Container>
            </section>
        </SiteLayout>
    );
}
