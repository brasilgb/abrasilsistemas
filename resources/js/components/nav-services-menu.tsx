import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

const services = [
    { label: 'Criação de Sites', href: '/servicos#sites' },
    { label: 'Landing Pages', href: '/servicos#landing-pages' },
    { label: 'Sistemas Sob Medida', href: '/servicos#sistemas' },
    { label: 'Aplicativos', href: '/servicos#aplicativos' },
    { label: 'Integrações e APIs', href: '/servicos#integracoes' },
    { label: 'Automação de Processos', href: '/servicos#automacao' },
] as const;

export function NavServicesMenu({ mobile = false, onNavigate }: { mobile?: boolean; onNavigate?: () => void }) {
    if (mobile) {
        return (
            <div className="border-b border-white/10 py-4">
                <p className="text-sm font-bold">Serviços</p>
                <div className="mt-3 grid gap-3 pl-3">
                    {services.map((service) => (
                        <Link key={service.href} href={service.href} className="text-slate-300" onClick={onNavigate}>
                            {service.label}
                        </Link>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="flex items-center gap-1 text-sm font-semibold text-slate-300 outline-none transition hover:text-white data-[state=open]:text-white">
                Serviços
                <ChevronDown className="size-3.5" />
            </DropdownMenuTrigger>
            {/* Pin light colors explicitly — see nav-products-menu.tsx for why. */}
            <DropdownMenuContent align="start" className="w-64 border-slate-200 bg-white text-slate-950">
                {services.map((service) => (
                    <DropdownMenuItem key={service.href} asChild className="focus:bg-slate-100 focus:text-slate-950">
                        <Link href={service.href} className="font-semibold text-slate-950">
                            {service.label}
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
