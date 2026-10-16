import {
    Images,
    Inbox,
    MessageSquareQuote,
    MessagesSquare,
    Newspaper,
    Settings,
    Users,
} from 'lucide-react';
import { index as atendimentoIndex } from '@/routes/atendimento';
import { edit as profileEdit } from '@/routes/profile';
import { index as solicitacoesIndex } from '@/routes/solicitacoes';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

/**
 * Menu do painel do site institucional (sidebar e cabeçalho). O site é institucional +
 * solicitações + atendimento; prospecção e CRM ficam no VetorLeads.
 */
export const adminNavItems: NavItem[] = [
    {
        title: 'Atendimento',
        href: atendimentoIndex(),
        icon: MessagesSquare,
    },
    {
        title: 'Solicitações',
        href: solicitacoesIndex(),
        icon: Inbox,
    },
    {
        title: 'Blog',
        href: '/admin/blog/posts',
        icon: Newspaper,
    },
    {
        title: 'Portfólio',
        href: '/portfolio',
        icon: Images,
    },
    {
        title: 'Depoimentos',
        href: '/testimonials',
        icon: MessageSquareQuote,
    },
    {
        title: 'Usuários',
        href: usersIndex(),
        icon: Users,
    },
    {
        title: 'Configurações',
        href: profileEdit(),
        icon: Settings,
    },
];

/** Leitores (não administradores) só acessam as próprias configurações. */
export const readerNavItems: NavItem[] = adminNavItems.filter(
    (item) => item.title === 'Configurações',
);
