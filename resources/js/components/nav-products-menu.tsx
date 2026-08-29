import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

const products = [
    { label: 'VetorOS', description: 'Gestão para assistências técnicas', href: '/produtos#vetoros' },
    { label: 'VetorPet', description: 'Gestão comercial para o mercado pet', href: '/produtos#vetorpet' },
] as const;

export function NavProductsMenu({ mobile = false, onNavigate }: { mobile?: boolean; onNavigate?: () => void }) {
    if (mobile) {
        return (
            <div className="border-b border-white/10 py-4">
                <p className="text-sm font-bold">Produtos</p>
                <div className="mt-3 grid gap-3 pl-3">
                    {products.map((product) => (
                        <Link key={product.href} href={product.href} className="text-slate-300" onClick={onNavigate}>
                            {product.label}
                        </Link>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="flex items-center gap-1 text-sm font-semibold text-slate-300 outline-none transition hover:text-white data-[state=open]:text-white">
                Produtos
                <ChevronDown className="size-3.5" />
            </DropdownMenuTrigger>
            {/* Pin light colors explicitly: this popover is portaled to <body>, outside the page's
                own dark-mode-safe styling, so it must not rely on theme tokens that flip with the
                visitor's OS color scheme (that made the text unreadable in dark mode). */}
            <DropdownMenuContent align="start" className="w-64 border-slate-200 bg-white text-slate-950">
                {products.map((product) => (
                    <DropdownMenuItem key={product.href} asChild className="focus:bg-slate-100 focus:text-slate-950">
                        <Link href={product.href} className="flex flex-col items-start gap-0.5 py-2">
                            <span className="font-bold text-slate-950">{product.label}</span>
                            <span className="text-xs text-slate-500">{product.description}</span>
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
