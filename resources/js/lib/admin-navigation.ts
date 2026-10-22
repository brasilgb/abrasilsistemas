import {
    Images,
    Inbox,
    MessageSquareQuote,
    MessagesSquare,
    Newspaper,
    SearchCheck,
    Settings,
    Users,
} from 'lucide-react';
import { index as atendimentoIndex } from '@/routes/atendimento';
import { edit as profileEdit } from '@/routes/profile';
import { index as solicitacoesIndex } from '@/routes/solicitacoes';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

/** Submenus de SEO (ABRASIL-SEO-02), também usados nas abas do topo das telas de SEO. */
export const seoNavItems: NavItem[] = [
    { title: 'Visão geral', href: '/admin/seo' },
    { title: 'Auditoria do site', href: '/admin/seo/auditoria' },
    { title: 'Páginas e metadados', href: '/admin/seo/paginas' },
    { title: 'Palavras-chave', href: '/admin/seo/palavras-chave' },
    { title: 'Sitemap e indexação', href: '/admin/seo/sitemap' },
    { title: 'Redirecionamentos', href: '/admin/seo/redirecionamentos' },
    { title: 'Dados estruturados', href: '/admin/seo/dados-estruturados' },
    { title: 'Integrações', href: '/admin/seo/integracoes' },
    { title: 'Configurações', href: '/admin/seo/configuracoes' },
];

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
        title: 'SEO',
        href: '/admin/seo',
        icon: SearchCheck,
        items: seoNavItems,
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
