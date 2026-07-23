import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { Monitor, Moon, Sun } from 'lucide-react';

export function PublicThemeSelector() {
    const { appearance, updateAppearance } = useAppearance();
    const Icon = appearance === 'dark' ? Moon : appearance === 'light' ? Sun : Monitor;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" aria-label="Selecionar tema">
                    <Icon className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => updateAppearance('light')}><Sun className="size-4" /> Claro</DropdownMenuItem>
                <DropdownMenuItem onClick={() => updateAppearance('dark')}><Moon className="size-4" /> Escuro</DropdownMenuItem>
                <DropdownMenuItem onClick={() => updateAppearance('system')}><Monitor className="size-4" /> Sistema</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
