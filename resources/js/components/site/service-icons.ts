import {
    AppWindow,
    Cog,
    Globe,
    MonitorSmartphone,
    Plug,
    Rocket,
    Search,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { VisualKind } from '@/data/site';

/** Ícone de cada serviço nos cards. */
export const serviceIcons: Record<VisualKind, LucideIcon> = {
    system: AppWindow,
    site: Globe,
    landing: Rocket,
    app: MonitorSmartphone,
    automation: Cog,
    integration: Plug,
    seo: Search,
};
