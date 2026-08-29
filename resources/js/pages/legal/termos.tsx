import { Head } from '@inertiajs/react';
import { CookieConsent } from '@/components/cookie-consent';
import { PublicFooter } from '@/components/public-footer';
import { SecondaryPageHeader } from '@/components/secondary-page-header';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import { useContact } from '@/lib/contact';

export default function TermosDeUso() {
    const contact = useContact();

    return (
        <>
            <Head title="Termos de Uso | ABrasil Sistemas">
                <meta name="robots" content="noindex, follow" />
                <meta name="theme-color" content="#08111f" />
            </Head>

            <div className="ab-public-site ab-static-light min-h-screen overflow-x-hidden bg-white text-slate-900">
                <SecondaryPageHeader />

                <main className="mx-auto max-w-3xl px-5 pt-36 pb-24 sm:px-8 sm:pt-44">
                    <p className="text-xs font-extrabold tracking-[0.18em] text-blue-700 uppercase">Legal</p>
                    <h1 className="mt-3 text-4xl font-black tracking-[-0.04em] text-slate-950">Termos de Uso</h1>
                    <p className="mt-3 text-sm text-slate-500">
                        [Razão social e CNPJ da ABrasil Sistemas a confirmar] — última atualização: {new Date().toLocaleDateString('pt-BR')}.
                    </p>

                    <div className="prose prose-slate mt-10 max-w-none space-y-6 text-sm leading-7 text-slate-700 sm:text-base">
                        <section>
                            <h2 className="text-xl font-black text-slate-950">1. Finalidade do site</h2>
                            <p>
                                Este site apresenta a ABrasil Sistemas, seus serviços de desenvolvimento de software, sites e aplicativos, e
                                seus produtos próprios (VetorOS e VetorPet), além de servir como canal de contato comercial.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">2. Propriedade intelectual</h2>
                            <p>
                                Textos, imagens, marcas e demais conteúdos deste site pertencem à ABrasil Sistemas ou são utilizados sob
                                licença, sendo vedada a reprodução total ou parcial sem autorização prévia.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">3. Informações institucionais</h2>
                            <p>ABrasil Sistemas — [razão social e CNPJ a confirmar].</p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">4. Links externos</h2>
                            <p>
                                Este site pode conter links para outros sites, como o VetorOS (vetoros.com.br) e o VetorPet, ou perfis em
                                redes sociais. Não nos responsabilizamos pelo conteúdo ou pelas políticas de privacidade desses sites de
                                terceiros.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">5. Responsabilidades</h2>
                            <p>
                                Empregamos esforços razoáveis para manter as informações deste site atualizadas e corretas, mas elas têm
                                caráter informativo e podem ser alteradas sem aviso prévio.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">6. Disponibilidade do site</h2>
                            <p>
                                Buscamos manter o site disponível, mas não garantimos operação ininterrupta, podendo haver indisponibilidade
                                temporária para manutenção ou por motivos fora do nosso controle.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">7. Alterações</h2>
                            <p>Estes termos podem ser atualizados a qualquer momento, com a nova versão publicada nesta mesma página.</p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">8. Legislação aplicável</h2>
                            <p>
                                Estes termos são regidos pelas leis da República Federativa do Brasil. Dúvidas podem ser enviadas para{' '}
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
