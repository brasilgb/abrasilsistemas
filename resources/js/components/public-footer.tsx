import { Link } from '@inertiajs/react';
import { Headphones, Mail, MessageCircle } from 'lucide-react';
import { PublicBrand } from './public-brand';

const contactWhatsappUrl = 'https://wa.me/5551998931325?text=Ol%C3%A1%2C%20preciso%20de%20ajuda%20com%20uma%20solu%C3%A7%C3%A3o%20da%20ABrasil.';

export function PublicFooter() {
    return (
        <footer id="contato" className="bg-[#050b13] text-white">
            <div className="mx-auto grid max-w-[86rem] gap-12 px-5 py-14 sm:px-8 md:grid-cols-[1.2fr_0.8fr_0.8fr] lg:px-12">
                <div>
                    <PublicBrand inverse />
                    <p className="mt-5 max-w-sm text-sm leading-6 text-slate-500">
                        Tecnologia brasileira criada para simplificar operações reais e ajudar empresas a crescer com controle.
                    </p>
                </div>
                <div>
                    <p className="text-xs font-black tracking-[0.15em] text-slate-600 uppercase">Soluções</p>
                    <div className="mt-5 grid gap-3 text-sm text-slate-400">
                        <Link href="/#produtos" className="hover:text-white">
                            Produtos próprios
                        </Link>
                        <Link href="/desenvolvimento-de-sites-para-empresas" className="hover:text-white">
                            Sites para empresas
                        </Link>
                        <a href="mailto:contato@absistemas.com.br?subject=Projeto de software sob medida" className="hover:text-white">
                            Sistemas sob medida
                        </a>
                        <Link href="/blog" className="hover:text-white">
                            Conteúdo
                        </Link>
                    </div>
                </div>
                <div>
                    <p className="text-xs font-black tracking-[0.15em] text-slate-600 uppercase">Contato</p>
                    <div className="mt-5 grid gap-3 text-sm text-slate-400">
                        <a href="mailto:contato@absistemas.com.br" className="flex items-center gap-2 hover:text-white">
                            <Mail className="size-4" /> contato@absistemas.com.br
                        </a>
                        <a href={contactWhatsappUrl} target="_blank" rel="noreferrer" className="flex items-center gap-2 hover:text-white">
                            <MessageCircle className="size-4" /> (51) 99893-1325
                        </a>
                        <span className="flex items-center gap-2">
                            <Headphones className="size-4" /> Atendimento próximo
                        </span>
                    </div>
                </div>
            </div>
            <div className="border-t border-white/10 px-5 py-5 text-center text-[11px] text-slate-600">
                © {new Date().getFullYear()} ABrasil Sistemas. Feito no Brasil para negócios que fazem acontecer.
            </div>
        </footer>
    );
}
