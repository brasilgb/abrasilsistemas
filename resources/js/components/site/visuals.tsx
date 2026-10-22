import {
    Bell,
    Check,
    CircleDot,
    Database,
    FileText,
    Globe,
    Mail,
    MessageCircle,
    Plug,
    Search,
    ShoppingCart,
    Smartphone,
    Users,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { VisualKind } from '@/data/site';

/*
 * Ilustrações dos heroes, desenhadas em HTML/CSS: explicam o serviço (fluxos,
 * telas, conexões) sem depender de banco de imagens. Cada página tem a sua.
 * São decorativas para leitores de tela, com um rótulo que resume a ideia.
 */

function Panel({
    children,
    className = '',
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`rounded-lg border border-white/10 bg-[#111a2e] shadow-2xl shadow-black/30 ${className}`}
        >
            {children}
        </div>
    );
}

function WindowBar({ label }: { label?: string }) {
    return (
        <div className="flex items-center gap-2 border-b border-white/10 px-4 py-2.5">
            <span className="size-2.5 rounded-full bg-white/15" />
            <span className="size-2.5 rounded-full bg-white/15" />
            <span className="size-2.5 rounded-full bg-white/15" />
            {label && (
                <span className="ml-3 truncate rounded bg-white/5 px-3 py-0.5 text-[11px] text-slate-400">
                    {label}
                </span>
            )}
        </div>
    );
}

/** Captura de tela real dentro de uma moldura de navegador. */
export function BrowserFrame({
    src,
    alt,
    width,
    height,
    label,
    priority = false,
}: {
    src: string;
    alt: string;
    width: number;
    height: number;
    label?: string;
    priority?: boolean;
}) {
    return (
        <Panel className="overflow-hidden">
            <WindowBar label={label} />
            <img
                src={src}
                alt={alt}
                width={width}
                height={height}
                loading={priority ? 'eager' : 'lazy'}
                fetchPriority={priority ? 'high' : 'auto'}
                className="block h-auto w-full"
            />
        </Panel>
    );
}

function SystemVisual() {
    const rows = [
        ['OS 1042', 'Aguardando aprovação', 'bg-amber-400/15 text-amber-200'],
        ['OS 1041', 'Em execução', 'bg-sky-400/15 text-sky-200'],
        [
            'OS 1039',
            'Pronto para entrega',
            'bg-emerald-400/15 text-emerald-200',
        ],
        ['OS 1036', 'Entregue', 'bg-white/10 text-slate-300'],
    ];

    return (
        <Panel className="overflow-hidden">
            <WindowBar label="sistema.suaempresa.com.br" />
            <div className="grid grid-cols-[8.5rem_1fr] text-xs">
                <div className="hidden border-r border-white/10 p-3 sm:block">
                    {[
                        'Painel',
                        'Clientes',
                        'Ordens',
                        'Estoque',
                        'Financeiro',
                        'Relatórios',
                    ].map((item, index) => (
                        <p
                            key={item}
                            className={`rounded px-2 py-1.5 ${index === 2 ? 'bg-blue-600 text-white' : 'text-slate-400'}`}
                        >
                            {item}
                        </p>
                    ))}
                </div>
                <div className="col-span-2 p-4 sm:col-span-1">
                    <div className="grid grid-cols-3 gap-2">
                        {[
                            ['Abertas', '18'],
                            ['Atrasadas', '2'],
                            ['Concluídas', '64'],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="rounded border border-white/10 p-2.5"
                            >
                                <p className="text-[10px] text-slate-400">
                                    {label}
                                </p>
                                <p className="mt-1 text-lg font-semibold text-white">
                                    {value}
                                </p>
                            </div>
                        ))}
                    </div>
                    <div className="mt-3 divide-y divide-white/5 rounded border border-white/10">
                        {rows.map(([id, status, tone]) => (
                            <div
                                key={id}
                                className="flex items-center justify-between gap-2 px-3 py-2.5"
                            >
                                <span className="font-medium text-slate-200">
                                    {id}
                                </span>
                                <span
                                    className={`rounded px-2 py-0.5 text-[10px] ${tone}`}
                                >
                                    {status}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
            <div className="flex items-center gap-3 border-t border-white/10 px-4 py-3 text-[11px] text-slate-400">
                <span className="line-through decoration-slate-500">
                    planilhas e cadernos
                </span>
                <span aria-hidden="true">→</span>
                <span className="font-medium text-sky-300">
                    um processo, um sistema
                </span>
            </div>
        </Panel>
    );
}

function AutomationVisual() {
    const actions = [
        { icon: MessageCircle, label: 'Avisar o cliente pelo WhatsApp' },
        { icon: FileText, label: 'Gerar o recibo em PDF' },
        { icon: Wallet, label: 'Lançar no financeiro' },
        { icon: Bell, label: 'Notificar o responsável' },
    ];

    return (
        <Panel className="p-5 sm:p-6">
            <p className="text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                Fluxo automático
            </p>
            <div className="mt-4 flex items-center gap-3 rounded-md border border-sky-400/30 bg-sky-400/10 p-3">
                <CircleDot className="size-4 text-sky-300" />
                <div>
                    <p className="text-[10px] text-sky-200/80 uppercase">
                        Quando
                    </p>
                    <p className="text-sm font-medium text-white">
                        Pagamento confirmado
                    </p>
                </div>
            </div>
            <div className="ml-5 h-5 w-px bg-white/20" />
            <div className="rounded-md border border-white/10 p-3">
                <p className="text-[10px] text-slate-400 uppercase">Então</p>
                <ul className="mt-2 grid gap-2">
                    {actions.map(({ icon: Icon, label }) => (
                        <li
                            key={label}
                            className="flex items-center justify-between gap-3 rounded bg-white/5 px-3 py-2 text-sm text-slate-200"
                        >
                            <span className="flex items-center gap-2">
                                <Icon className="size-4 text-slate-400" />
                                {label}
                            </span>
                            <Check className="size-4 text-emerald-300" />
                        </li>
                    ))}
                </ul>
            </div>
            <p className="mt-4 text-[11px] text-slate-500">
                Cada execução fica registrada. Ninguém precisa lembrar.
            </p>
        </Panel>
    );
}

function IntegrationVisual() {
    const nodes = [
        { icon: Database, label: 'ERP' },
        { icon: Users, label: 'CRM' },
        { icon: ShoppingCart, label: 'E-commerce' },
        { icon: MessageCircle, label: 'WhatsApp' },
        { icon: Wallet, label: 'Financeiro' },
        { icon: Smartphone, label: 'Aplicativo' },
    ];

    return (
        <Panel className="p-5 sm:p-8">
            <div className="grid grid-cols-3 items-center gap-3 sm:gap-4">
                {nodes.slice(0, 3).map(({ icon: Icon, label }) => (
                    <div
                        key={label}
                        className="flex flex-col items-center gap-2 rounded-md border border-white/10 bg-white/5 p-3 text-xs text-slate-200"
                    >
                        <Icon className="size-5 text-slate-300" />
                        {label}
                    </div>
                ))}
                <div
                    className="col-span-3 flex justify-around"
                    aria-hidden="true"
                >
                    {[0, 1, 2].map((i) => (
                        <span
                            key={i}
                            className="h-6 w-px bg-gradient-to-b from-white/10 to-sky-400/60"
                        />
                    ))}
                </div>
                <div className="col-span-3 flex items-center justify-center gap-3 rounded-md border border-sky-400/40 bg-sky-400/10 px-4 py-4">
                    <Plug className="size-5 text-sky-300" />
                    <div>
                        <p className="text-sm font-semibold text-white">
                            Camada de integração
                        </p>
                        <p className="text-[11px] text-sky-100/70">
                            APIs · webhooks · filas
                        </p>
                    </div>
                </div>
                <div
                    className="col-span-3 flex justify-around"
                    aria-hidden="true"
                >
                    {[0, 1, 2].map((i) => (
                        <span
                            key={i}
                            className="h-6 w-px bg-gradient-to-b from-sky-400/60 to-white/10"
                        />
                    ))}
                </div>
                {nodes.slice(3).map(({ icon: Icon, label }) => (
                    <div
                        key={label}
                        className="flex flex-col items-center gap-2 rounded-md border border-white/10 bg-white/5 p-3 text-xs text-slate-200"
                    >
                        <Icon className="size-5 text-slate-300" />
                        {label}
                    </div>
                ))}
            </div>
        </Panel>
    );
}

function AppVisual() {
    return (
        <div className="flex items-end justify-center gap-5">
            <div className="w-56 rounded-[2rem] border border-white/15 bg-[#0e1626] p-2.5 shadow-2xl shadow-black/40 sm:w-60">
                <div className="overflow-hidden rounded-[1.6rem] bg-[#131d33]">
                    <div className="flex justify-center pt-2.5">
                        <span className="h-1.5 w-16 rounded-full bg-white/15" />
                    </div>
                    <div className="px-4 pt-4 pb-5">
                        <p className="text-[10px] text-slate-400">
                            Hoje · rota sul
                        </p>
                        <p className="mt-0.5 text-base font-semibold text-white">
                            Visitas
                        </p>
                        <ul className="mt-3 grid gap-2">
                            {[
                                [
                                    'Pet shop · Centro',
                                    'Pedido enviado',
                                    'text-emerald-300',
                                ],
                                [
                                    'Agropecuária · Zona Sul',
                                    'Em visita',
                                    'text-sky-300',
                                ],
                                [
                                    'Clínica veterinária',
                                    'Agendada 15h',
                                    'text-slate-400',
                                ],
                            ].map(([name, status, tone]) => (
                                <li
                                    key={name}
                                    className="rounded-lg bg-white/5 p-2.5"
                                >
                                    <p className="text-xs font-medium text-slate-100">
                                        {name}
                                    </p>
                                    <p className={`mt-0.5 text-[10px] ${tone}`}>
                                        {status}
                                    </p>
                                </li>
                            ))}
                        </ul>
                        <div className="mt-4 rounded-lg bg-blue-600 py-2.5 text-center text-xs font-semibold text-white">
                            Novo pedido
                        </div>
                    </div>
                </div>
            </div>
            <Panel className="hidden max-w-52 p-4 sm:block">
                <p className="text-[10px] text-slate-400 uppercase">
                    Sistema central
                </p>
                <p className="mt-1 text-sm font-medium text-white">
                    Pedido recebido do app
                </p>
                <p className="mt-3 flex items-center gap-2 text-[11px] text-emerald-300">
                    <Check className="size-3.5" /> sincronizado via API
                </p>
            </Panel>
        </div>
    );
}

function SiteVisual() {
    return (
        <div className="relative pr-6 pb-10 sm:pr-10">
            <Panel className="overflow-hidden">
                <WindowBar label="www.suaempresa.com.br" />
                <div className="p-5 sm:p-7">
                    <div className="flex items-center justify-between">
                        <span className="h-3 w-20 rounded bg-white/25" />
                        <span className="hidden gap-3 sm:flex">
                            {[0, 1, 2, 3].map((i) => (
                                <span
                                    key={i}
                                    className="h-2 w-10 rounded bg-white/10"
                                />
                            ))}
                        </span>
                    </div>
                    <div className="mt-8 h-4 w-4/5 rounded bg-white/70" />
                    <div className="mt-2 h-4 w-3/5 rounded bg-white/70" />
                    <div className="mt-4 h-2 w-2/3 rounded bg-white/15" />
                    <div className="mt-1.5 h-2 w-1/2 rounded bg-white/15" />
                    <div className="mt-5 h-8 w-32 rounded bg-blue-600" />
                    <div className="mt-8 grid grid-cols-3 gap-3">
                        {[0, 1, 2].map((i) => (
                            <div
                                key={i}
                                className="h-16 rounded border border-white/10 bg-white/5"
                            />
                        ))}
                    </div>
                </div>
            </Panel>
            <div className="absolute right-0 bottom-0 w-28 rounded-2xl border border-white/15 bg-[#0e1626] p-1.5 shadow-2xl shadow-black/50 sm:w-32">
                <div className="rounded-xl bg-[#131d33] p-3">
                    <span className="block h-2 w-10 rounded bg-white/25" />
                    <span className="mt-4 block h-2.5 w-full rounded bg-white/70" />
                    <span className="mt-1.5 block h-2.5 w-3/4 rounded bg-white/70" />
                    <span className="mt-3 block h-1.5 w-full rounded bg-white/15" />
                    <span className="mt-1 block h-1.5 w-2/3 rounded bg-white/15" />
                    <span className="mt-4 block h-5 w-full rounded bg-blue-600" />
                </div>
            </div>
        </div>
    );
}

function LandingVisual() {
    return (
        <div className="relative pb-8">
            <Panel className="overflow-hidden">
                <WindowBar label="lancamento.suaempresa.com.br" />
                <div className="grid gap-6 p-5 sm:grid-cols-[1.1fr_0.9fr] sm:p-7">
                    <div>
                        <span className="inline-block rounded bg-sky-400/15 px-2 py-0.5 text-[10px] text-sky-200">
                            Lançamento
                        </span>
                        <div className="mt-4 h-4 w-full rounded bg-white/70" />
                        <div className="mt-2 h-4 w-2/3 rounded bg-white/70" />
                        <div className="mt-4 h-2 w-full rounded bg-white/15" />
                        <div className="mt-1.5 h-2 w-4/5 rounded bg-white/15" />
                        <ul className="mt-5 grid gap-2">
                            {[0, 1, 2].map((i) => (
                                <li key={i} className="flex items-center gap-2">
                                    <Check className="size-3.5 text-emerald-300" />
                                    <span className="h-2 w-2/3 rounded bg-white/15" />
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div className="rounded-md border border-white/10 bg-white/5 p-4">
                        <p className="text-xs font-medium text-white">
                            Quero receber a proposta
                        </p>
                        {['Nome', 'E-mail', 'WhatsApp'].map((field) => (
                            <div key={field} className="mt-3">
                                <p className="text-[10px] text-slate-400">
                                    {field}
                                </p>
                                <div className="mt-1 h-7 rounded border border-white/10 bg-[#0b1220]" />
                            </div>
                        ))}
                        <div className="mt-4 h-8 rounded bg-blue-600" />
                    </div>
                </div>
            </Panel>
            <div className="absolute -bottom-1 left-4 flex items-center gap-3 rounded-md border border-emerald-400/30 bg-[#0f1f1c] px-4 py-3 shadow-xl sm:left-8">
                <Mail className="size-4 text-emerald-300" />
                <div>
                    <p className="text-xs font-medium text-white">
                        Novo lead no CRM
                    </p>
                    <p className="text-[10px] text-emerald-200/70">
                        origem: campanha de lançamento
                    </p>
                </div>
            </div>
        </div>
    );
}

function SeoVisual() {
    const checks = [
        'Título e descrição únicos',
        'Canonical e sitemap XML',
        'Dados estruturados válidos',
        'Carregamento rápido no celular',
        'Áreas privadas fora do índice',
    ];

    return (
        <div className="relative pb-10">
            <Panel className="overflow-hidden">
                <div className="flex items-center gap-3 border-b border-white/10 px-4 py-3">
                    <Search className="size-4 shrink-0 text-slate-400" />
                    <span className="truncate text-xs text-slate-300">
                        sistema para assistência técnica
                    </span>
                </div>
                <div className="grid gap-5 p-5 sm:p-6">
                    <div>
                        <p className="text-[11px] text-slate-400">
                            suaempresa.com.br › servicos
                        </p>
                        <p className="mt-1 text-sm font-medium text-sky-300">
                            Título claro, escrito para quem pesquisa
                        </p>
                        <span className="mt-2 block h-2 w-full rounded bg-white/15" />
                        <span className="mt-1.5 block h-2 w-4/5 rounded bg-white/15" />
                    </div>
                    <div className="opacity-50">
                        <span className="block h-2 w-1/3 rounded bg-white/15" />
                        <span className="mt-2 block h-2.5 w-2/3 rounded bg-white/30" />
                        <span className="mt-2 block h-2 w-full rounded bg-white/10" />
                    </div>
                </div>
            </Panel>
            <div className="absolute right-0 -bottom-1 w-60 rounded-md border border-white/10 bg-[#0e1626] p-4 shadow-2xl shadow-black/50 sm:right-6">
                <p className="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">
                    Auditoria técnica
                </p>
                <ul className="mt-3 grid gap-2">
                    {checks.map((check) => (
                        <li
                            key={check}
                            className="flex items-center gap-2 text-[11px] text-slate-200"
                        >
                            <Check className="size-3.5 shrink-0 text-emerald-300" />
                            {check}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

const visuals: Record<VisualKind, { label: string; render: () => ReactNode }> =
    {
        system: {
            label: 'Ilustração: planilhas substituídas por um sistema com painel e ordens de serviço',
            render: SystemVisual,
        },
        automation: {
            label: 'Ilustração: um pagamento confirmado dispara aviso ao cliente, recibo e lançamento financeiro',
            render: AutomationVisual,
        },
        integration: {
            label: 'Ilustração: ERP, CRM, e-commerce, WhatsApp, financeiro e aplicativo conectados por uma camada de integração',
            render: IntegrationVisual,
        },
        app: {
            label: 'Ilustração: aplicativo de visitas e pedidos sincronizado com o sistema central',
            render: AppVisual,
        },
        site: {
            label: 'Ilustração: site empresarial no computador e no celular',
            render: SiteVisual,
        },
        landing: {
            label: 'Ilustração: landing page com formulário enviando o lead para o CRM',
            render: LandingVisual,
        },
        seo: {
            label: 'Ilustração: resultado de busca com título e descrição claros, ao lado de uma auditoria técnica com as verificações aprovadas',
            render: SeoVisual,
        },
    };

export function ServiceVisual({ kind }: { kind: VisualKind }) {
    const visual = visuals[kind];

    return (
        <div role="img" aria-label={visual.label}>
            <div aria-hidden="true">{visual.render()}</div>
        </div>
    );
}

/** Usado na home para representar o site → sistema → atendimento. */
export function ConnectedOperationBadge() {
    return (
        <div className="flex flex-wrap items-center gap-2 text-[11px] text-slate-300">
            {[
                { icon: Globe, label: 'Site' },
                { icon: Users, label: 'CRM' },
                { icon: MessageCircle, label: 'WhatsApp' },
                { icon: Database, label: 'Sistema' },
            ].map(({ icon: Icon, label }, index, all) => (
                <span key={label} className="flex items-center gap-2">
                    <span className="flex items-center gap-1.5 rounded border border-white/10 bg-white/5 px-2 py-1">
                        <Icon className="size-3.5 text-sky-300" />
                        {label}
                    </span>
                    {index < all.length - 1 && (
                        <span aria-hidden="true">→</span>
                    )}
                </span>
            ))}
        </div>
    );
}
