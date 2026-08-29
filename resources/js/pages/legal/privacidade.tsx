import { Head } from '@inertiajs/react';
import { CookieConsent } from '@/components/cookie-consent';
import { PublicFooter } from '@/components/public-footer';
import { SecondaryPageHeader } from '@/components/secondary-page-header';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import { useContact } from '@/lib/contact';

export default function PoliticaDePrivacidade() {
    const contact = useContact();

    return (
        <>
            <Head title="Política de Privacidade | ABrasil Sistemas">
                <meta name="robots" content="noindex, follow" />
                <meta name="theme-color" content="#08111f" />
            </Head>

            <div className="ab-public-site ab-static-light min-h-screen overflow-x-hidden bg-white text-slate-900">
                <SecondaryPageHeader />

                <main className="mx-auto max-w-3xl px-5 pt-36 pb-24 sm:px-8 sm:pt-44">
                    <p className="text-xs font-extrabold tracking-[0.18em] text-blue-700 uppercase">Legal</p>
                    <h1 className="mt-3 text-4xl font-black tracking-[-0.04em] text-slate-950">Política de Privacidade</h1>
                    <p className="mt-3 text-sm text-slate-500">
                        [Razão social e CNPJ da ABrasil Sistemas a confirmar] — última atualização: {new Date().toLocaleDateString('pt-BR')}.
                    </p>

                    <div className="prose prose-slate mt-10 max-w-none space-y-6 text-sm leading-7 text-slate-700 sm:text-base">
                        <section>
                            <h2 className="text-xl font-black text-slate-950">1. Quais dados coletamos</h2>
                            <p>
                                Coletamos os dados que você mesmo nos informa ao preencher o formulário de contato do site (nome, empresa,
                                telefone/WhatsApp, e-mail, serviço de interesse e mensagem). Também podemos registrar dados técnicos básicos
                                de navegação (como endereço IP e páginas visitadas) para segurança e funcionamento do site.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">2. Formulário de contato</h2>
                            <p>
                                Os dados enviados pelo formulário de contato são armazenados em nosso sistema interno de relacionamento com
                                clientes, exclusivamente para que a equipe da ABrasil possa entrar em contato sobre o serviço ou produto de
                                interesse.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">3. Cookies</h2>
                            <p>
                                Utilizamos cookies técnicos necessários ao funcionamento do site e um cookie para lembrar sua preferência de
                                consentimento de cookies. Veja detalhes na nossa{' '}
                                <a href="/politica-de-cookies" className="font-bold text-blue-700 underline underline-offset-2">
                                    Política de Cookies
                                </a>
                                .
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">4. Logs técnicos</h2>
                            <p>
                                Como todo site, mantemos registros técnicos de acesso ao servidor por um período limitado, usados apenas
                                para segurança, prevenção de fraude e diagnóstico de problemas.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">5. Finalidade dos dados</h2>
                            <p>
                                Usamos os dados coletados exclusivamente para responder ao seu contato, apresentar nossos produtos e
                                serviços, e dar andamento a um eventual projeto ou proposta comercial.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">6. Armazenamento e compartilhamento</h2>
                            <p>
                                Os dados são armazenados em nossa infraestrutura e não são vendidos ou compartilhados com terceiros para
                                fins de marketing. Podem ser compartilhados apenas com prestadores de serviço estritamente necessários para
                                o funcionamento do site (por exemplo, hospedagem), sob obrigação de confidencialidade.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">7. Seus direitos (LGPD)</h2>
                            <p>
                                Nos termos da Lei Geral de Proteção de Dados (Lei nº 13.709/2018), você pode solicitar a qualquer momento a
                                confirmação, o acesso, a correção ou a exclusão dos seus dados pessoais em nossa base, entrando em contato
                                pelos canais abaixo.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">8. Contato</h2>
                            <p>
                                Dúvidas sobre esta política ou solicitações relacionadas aos seus dados podem ser enviadas para{' '}
                                <a href={`mailto:${contact.email}`} className="font-bold text-blue-700 underline underline-offset-2">
                                    {contact.email}
                                </a>
                                .
                            </p>
                        </section>
                    </div>
                </main>

                <PublicFooter />
                <WhatsAppFloat />
                <CookieConsent />
            </div>
        </>
    );
}
