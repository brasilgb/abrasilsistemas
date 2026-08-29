import { Link } from '@inertiajs/react';
import { Headphones, Mail, MessageCircle } from 'lucide-react';
import { PublicBrand } from './public-brand';
import { buildWhatsappUrl, useContact } from '@/lib/contact';

export function PublicFooter() {
    const contact = useContact();
    const whatsappUrl = buildWhatsappUrl(contact.whatsapp, 'Olá, preciso de ajuda com uma solução da ABrasil.');

    return (
        <footer id="contato" className="bg-[#050b13] text-white">
            <div className="mx-auto grid max-w-[86rem] gap-12 px-5 py-14 sm:px-8 md:grid-cols-[1.3fr_0.7fr_0.7fr_0.7fr_0.8fr] lg:px-12">
                <div>
                    <PublicBrand inverse />
                    <p className="mt-5 max-w-sm text-sm leading-6 text-slate-500">
                        Tecnologia brasileira criada para simplificar operações reais e ajudar empresas a crescer com controle.
                    </p>
                </div>
                <div>
                    <p className="text-xs font-black tracking-[0.15em] text-slate-600 uppercase">Navegação</p>
                    <div className="mt-5 grid gap-3 text-sm text-slate-400">
                        <Link href="/" className="hover:text-white">
                            Home
                        </Link>
                        <Link href="/servicos" className="hover:text-white">
                            Serviços
                        </Link>
                        <Link href="/sobre" className="hover:text-white">
                            Sobre
                        </Link>
                        <Link href="/blog" className="hover:text-white">
                            Blog
                        </Link>
                        <Link href="/contato" className="hover:text-white">
                            Contato
                        </Link>
                    </div>
                </div>
                <div>
                    <p className="text-xs font-black tracking-[0.15em] text-slate-600 uppercase">Produtos</p>
                    <div className="mt-5 grid gap-3 text-sm text-slate-400">
                        <Link href="/produtos" className="hover:text-white">
                            Produtos próprios
                        </Link>
                        <Link href="/produtos#vetoros" className="hover:text-white">
                            VetorOS
                        </Link>
                        <Link href="/produtos#vetorpet" className="hover:text-white">
                            VetorPet
                        </Link>
                        <Link href="/desenvolvimento-de-sites-para-empresas" className="hover:text-white">
                            Sites para empresas
                        </Link>
                    </div>
                </div>
                <div>
                    <p className="text-xs font-black tracking-[0.15em] text-slate-600 uppercase">Legal</p>
                    <div className="mt-5 grid gap-3 text-sm text-slate-400">
                        <Link href="/politica-de-privacidade" className="hover:text-white">
                            Política de Privacidade
                        </Link>
                        <Link href="/termos-de-uso" className="hover:text-white">
                            Termos de Uso
                        </Link>
                        <Link href="/politica-de-cookies" className="hover:text-white">
                            Política de Cookies
                        </Link>
                    </div>
                </div>
                <div>
                    <p className="text-xs font-black tracking-[0.15em] text-slate-600 uppercase">Contato</p>
                    <div className="mt-5 grid gap-3 text-sm text-slate-400">
                        <a href={`mailto:${contact.email}`} className="flex items-center gap-2 hover:text-white">
                            <Mail className="size-4" /> {contact.email}
                        </a>
                        <a href={whatsappUrl} target="_blank" rel="noreferrer" className="flex items-center gap-2 hover:text-white">
                            <MessageCircle className="size-4" /> {contact.whatsappDisplay}
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
