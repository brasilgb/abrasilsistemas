import { Head } from '@inertiajs/react';
import { CookieConsent } from '@/components/cookie-consent';
import { PublicFooter } from '@/components/public-footer';
import { SecondaryPageHeader } from '@/components/secondary-page-header';
import { WhatsAppFloat } from '@/components/whatsapp-float';

export default function PoliticaDeCookies() {
    return (
        <>
            <Head title="Política de Cookies | ABrasil Sistemas">
                <meta name="robots" content="noindex, follow" />
                <meta name="theme-color" content="#08111f" />
            </Head>

            <div className="ab-public-site ab-static-light min-h-screen overflow-x-hidden bg-white text-slate-900">
                <SecondaryPageHeader />

                <main className="mx-auto max-w-3xl px-5 pt-36 pb-24 sm:px-8 sm:pt-44">
                    <p className="text-xs font-extrabold tracking-[0.18em] text-blue-700 uppercase">Legal</p>
                    <h1 className="mt-3 text-4xl font-black tracking-[-0.04em] text-slate-950">Política de Cookies</h1>
                    <p className="mt-3 text-sm text-slate-500">Última atualização: {new Date().toLocaleDateString('pt-BR')}.</p>

                    <div className="prose prose-slate mt-10 max-w-none space-y-6 text-sm leading-7 text-slate-700 sm:text-base">
                        <section>
                            <h2 className="text-xl font-black text-slate-950">O que são cookies</h2>
                            <p>
                                Cookies são pequenos arquivos armazenados no seu navegador que ajudam um site a lembrar informações sobre a
                                sua visita.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">Cookies essenciais</h2>
                            <p>
                                Usamos um cookie/armazenamento local essencial para lembrar sua escolha no banner de consentimento de
                                cookies, evitando que ele apareça novamente a cada visita.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">Cookies analíticos</h2>
                            <p>Atualmente este site não utiliza ferramentas de analytics ou pixels de rastreamento de terceiros.</p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">Cookies de terceiros</h2>
                            <p>
                                Ao clicar em links de WhatsApp ou redes sociais, você é direcionado a serviços de terceiros, que possuem
                                suas próprias políticas de cookies e privacidade.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-xl font-black text-slate-950">Gerenciando seu consentimento</h2>
                            <p>
                                Você pode escolher "Aceitar todos" ou "Somente necessários" no banner exibido na primeira visita. Essa
                                escolha fica salva no seu navegador; para alterá-la, limpe os dados de navegação deste site e visite-o
                                novamente.
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
