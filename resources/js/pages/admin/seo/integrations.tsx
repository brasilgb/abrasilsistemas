import { Deferred, Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import {
    FieldError,
    Notice,
    StatCard,
    fieldClass,
    formatDateTime,
} from '@/components/seo/indicators';
import { SeoPage } from '@/components/seo/seo-page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Result<T> = { data: T | null; error: string | null } | null;

type Totals = {
    clicks: number;
    impressions: number;
    ctr: number;
    position: number;
};
type PerformanceRow = Totals & { key: string };
type SearchPerformance = {
    period: { start: string; end: string };
    previousPeriod: { start: string; end: string };
    current: Totals;
    previous: Totals;
    queries: PerformanceRow[];
    pages: PerformanceRow[];
    fetchedAt: string;
};
type OrganicTotals = {
    sessions: number;
    users: number;
    engagementRate: number;
};
type AnalyticsReport = {
    organic: { current: OrganicTotals; previous: OrganicTotals };
    events: { name: string; current: number; previous: number }[];
    fetchedAt: string;
};

type Props = {
    google: {
        configured: boolean;
        connected: boolean;
        connectedAt: string | null;
        scopes: string[];
    };
    redirectUri: string;
    settings: {
        google_verification: string;
        bing_verification: string;
        ga4_measurement_id: string;
        ga4_property_id: string;
        ga4_events: string[];
        gsc_property: string;
        indexnow_enabled: boolean;
    };
    indexNow: { enabled: boolean; keyUrl: string | null };
    ga4Events: Record<string, string>;
    days: number;
    periods: number[];
    delayDays: number;
    sites?: Result<{ siteUrl: string; permissionLevel: string }[]>;
    searchConsole?: Result<SearchPerformance>;
    analytics?: Result<AnalyticsReport>;
};

const number = (value: number) => new Intl.NumberFormat('pt-BR').format(value);
const date = (value: string) =>
    new Intl.DateTimeFormat('pt-BR').format(new Date(`${value}T12:00:00`));

/** Variação em relação ao período anterior. lowerIsBetter para posição média. */
function delta(current: number, previous: number, lowerIsBetter = false) {
    if (!previous) {
        return 'sem base anterior';
    }

    const change = ((current - previous) / previous) * 100;
    const better = lowerIsBetter ? change < 0 : change > 0;

    return `${change > 0 ? '+' : ''}${change.toFixed(1)}% vs. anterior${Math.abs(change) < 0.05 ? '' : better ? ' ↑' : ' ↓'}`;
}

function Loading() {
    return (
        <div className="flex items-center gap-2 rounded-md border p-4 text-sm text-muted-foreground">
            <LoaderCircle className="size-4 animate-spin" />
            Consultando a API do Google…
        </div>
    );
}

function Card({
    title,
    status,
    children,
}: {
    title: string;
    status: { label: string; tone: 'good' | 'muted' | 'warn' };
    children: ReactNode;
}) {
    return (
        <section className="space-y-4 rounded-lg border p-4 sm:p-5">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-base font-semibold">{title}</h2>
                <span
                    className={cn(
                        'rounded px-2 py-0.5 text-xs font-medium',
                        status.tone === 'good' &&
                            'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
                        status.tone === 'warn' &&
                            'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
                        status.tone === 'muted' &&
                            'bg-muted text-muted-foreground',
                    )}
                >
                    {status.label}
                </span>
            </div>
            {children}
        </section>
    );
}

function RowsTable({ title, rows }: { title: string; rows: PerformanceRow[] }) {
    return (
        <div className="space-y-2">
            <h3 className="text-sm font-semibold">{title}</h3>
            {rows.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Sem dados no período.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    {title === 'Consultas'
                                        ? 'Consulta'
                                        : 'Página'}
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Cliques
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Impressões
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    CTR
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Posição
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.map((row) => (
                                <tr key={row.key}>
                                    <td className="max-w-xs truncate px-3 py-2">
                                        {row.key}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {number(row.clicks)}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {number(row.impressions)}
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {row.ctr.toFixed(2)}%
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {row.position.toFixed(1)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

export default function SeoIntegrations({
    google,
    redirectUri,
    settings,
    indexNow,
    ga4Events,
    days,
    periods,
    delayDays,
    sites,
    searchConsole,
    analytics,
}: Props) {
    const form = useForm({
        ...settings,
        ga4_events: settings.ga4_events ?? [],
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/admin/seo/integracoes', { preserveScroll: true });
    };
    const disconnect = () => {
        if (
            confirm(
                'Desconectar o Google? A autorização será revogada e os dados deixam de aparecer aqui.',
            )
        ) {
            router.delete('/admin/seo/integracoes/google');
        }
    };
    const periodPicker = (
        <div className="flex gap-1">
            {periods.map((period) => (
                <Link
                    key={period}
                    href={`/admin/seo/integracoes?dias=${period}`}
                    preserveScroll
                    className={cn(
                        'h-8 rounded-md px-3 text-sm leading-8 font-medium transition',
                        days === period
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:bg-muted',
                    )}
                >
                    {period} dias
                </Link>
            ))}
        </div>
    );

    return (
        <SeoPage
            title="Integrações"
            description="Conexões oficiais com Google Search Console, Google Analytics 4, Bing Webmaster Tools e IndexNow. Sem conexão, o painel não mostra números."
        >
            <form onSubmit={submit} className="grid gap-6">
                <Card
                    title="Google (Search Console e Analytics)"
                    status={
                        google.connected
                            ? { label: 'Conectado', tone: 'good' }
                            : google.configured
                              ? { label: 'Não conectado', tone: 'warn' }
                              : { label: 'Credenciais ausentes', tone: 'muted' }
                    }
                >
                    {!google.configured ? (
                        <Notice>
                            Para conectar, crie um cliente OAuth (tipo
                            “Aplicativo da Web”) no Google Cloud com as APIs
                            Search Console e Google Analytics Data ativadas,
                            cadastre a URI de redirecionamento{' '}
                            <code className="break-all">{redirectUri}</code> e
                            defina <code>GOOGLE_SEARCH_CONSOLE_CLIENT_ID</code>{' '}
                            e <code>GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET</code>{' '}
                            no .env do servidor.
                        </Notice>
                    ) : google.connected ? (
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span className="text-muted-foreground">
                                Conectado em{' '}
                                {formatDateTime(google.connectedAt)} com acesso
                                só de leitura.
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={disconnect}
                            >
                                Desconectar
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span className="text-muted-foreground">
                                Autorize com a conta Google que administra a
                                propriedade. O acesso pedido é só de leitura.
                            </span>
                            <Button asChild size="sm">
                                {/* Navegação completa: o OAuth sai do site. */}
                                <a href="/admin/seo/integracoes/google/conectar">
                                    Conectar com o Google
                                </a>
                            </Button>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            Propriedade do Search Console
                            {google.connected ? (
                                <Deferred
                                    data="sites"
                                    fallback={
                                        <input
                                            className={fieldClass}
                                            value={form.data.gsc_property}
                                            disabled
                                        />
                                    }
                                >
                                    <select
                                        className={fieldClass}
                                        value={form.data.gsc_property}
                                        onChange={(e) =>
                                            form.setData(
                                                'gsc_property',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Escolha…</option>
                                        {(sites?.data ?? []).map((site) => (
                                            <option
                                                key={site.siteUrl}
                                                value={site.siteUrl}
                                            >
                                                {site.siteUrl}
                                            </option>
                                        ))}
                                    </select>
                                    {sites?.error && (
                                        <FieldError text={sites.error} />
                                    )}
                                </Deferred>
                            ) : (
                                <input
                                    className={fieldClass}
                                    value={form.data.gsc_property}
                                    placeholder="sc-domain:abrasilsistemas.com.br"
                                    onChange={(e) =>
                                        form.setData(
                                            'gsc_property',
                                            e.target.value,
                                        )
                                    }
                                />
                            )}
                            <FieldError text={form.errors.gsc_property} />
                        </label>
                        <label className="text-sm font-medium">
                            Verificação do Google (google-site-verification)
                            <input
                                className={fieldClass}
                                value={form.data.google_verification}
                                placeholder="valor do atributo content"
                                onChange={(e) =>
                                    form.setData(
                                        'google_verification',
                                        e.target.value,
                                    )
                                }
                            />
                            <FieldError
                                text={form.errors.google_verification}
                            />
                        </label>
                    </div>
                </Card>

                <Card
                    title="Google Analytics 4"
                    status={
                        form.data.ga4_measurement_id
                            ? { label: 'Tag configurada', tone: 'good' }
                            : { label: 'Sem tag', tone: 'muted' }
                    }
                >
                    <p className="text-sm text-muted-foreground">
                        A tag só carrega no site público e depois do “Aceitar
                        todos” do banner de cookies (LGPD). Ela é inserida uma
                        única vez; não adicione o mesmo ID por outro meio (Tag
                        Manager, plugin) para não contar em dobro.
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            ID de métricas (tag)
                            <input
                                className={fieldClass}
                                value={form.data.ga4_measurement_id}
                                placeholder="G-XXXXXXXXXX"
                                onChange={(e) =>
                                    form.setData(
                                        'ga4_measurement_id',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                            />
                            <FieldError text={form.errors.ga4_measurement_id} />
                        </label>
                        <label className="text-sm font-medium">
                            ID da propriedade (relatórios)
                            <input
                                className={fieldClass}
                                value={form.data.ga4_property_id}
                                placeholder="123456789"
                                onChange={(e) =>
                                    form.setData(
                                        'ga4_property_id',
                                        e.target.value,
                                    )
                                }
                            />
                            <span className="mt-1 block text-xs font-normal text-muted-foreground">
                                Numérico, em Administrador → Detalhes da
                                propriedade. Os relatórios exigem o Google
                                conectado.
                            </span>
                            <FieldError text={form.errors.ga4_property_id} />
                        </label>
                    </div>
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            Eventos comerciais enviados
                        </legend>
                        {Object.entries(ga4Events).map(([name, label]) => (
                            <label
                                key={name}
                                className="flex items-start gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    className="mt-0.5"
                                    checked={form.data.ga4_events.includes(
                                        name,
                                    )}
                                    onChange={(e) =>
                                        form.setData(
                                            'ga4_events',
                                            e.target.checked
                                                ? [
                                                      ...form.data.ga4_events,
                                                      name,
                                                  ]
                                                : form.data.ga4_events.filter(
                                                      (item) => item !== name,
                                                  ),
                                        )
                                    }
                                />
                                <span>
                                    <code>{name}</code>{' '}
                                    <span className="text-muted-foreground">
                                        {label}
                                    </span>
                                </span>
                            </label>
                        ))}
                        <span className="text-xs text-muted-foreground">
                            Marque generate_lead como evento principal
                            (conversão) no próprio GA4.
                        </span>
                    </fieldset>
                </Card>

                <Card
                    title="Bing Webmaster Tools e IndexNow"
                    status={
                        indexNow.enabled
                            ? { label: 'IndexNow ativo', tone: 'good' }
                            : form.data.bing_verification
                              ? {
                                    label: 'Verificação configurada',
                                    tone: 'good',
                                }
                              : { label: 'Não configurado', tone: 'muted' }
                    }
                >
                    <label className="text-sm font-medium">
                        Verificação do Bing (msvalidate.01)
                        <input
                            className={fieldClass}
                            value={form.data.bing_verification}
                            placeholder="valor do atributo content"
                            onChange={(e) =>
                                form.setData(
                                    'bing_verification',
                                    e.target.value,
                                )
                            }
                        />
                        <span className="mt-1 block text-xs font-normal text-muted-foreground">
                            No Bing Webmaster Tools também dá para importar o
                            site do Search Console. Envie o sitemap por lá.
                        </span>
                        <FieldError text={form.errors.bing_verification} />
                    </label>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="mt-0.5"
                            checked={form.data.indexnow_enabled}
                            onChange={(e) =>
                                form.setData(
                                    'indexnow_enabled',
                                    e.target.checked,
                                )
                            }
                        />
                        <span>
                            <span className="font-medium">
                                Avisar o IndexNow (Bing, Yandex e outros) ao
                                publicar ou atualizar artigos
                            </span>
                            <span className="block text-muted-foreground">
                                O Google não usa IndexNow. A chave é gerada
                                automaticamente
                                {indexNow.keyUrl && (
                                    <>
                                        {' '}
                                        e servida em{' '}
                                        <code className="break-all">
                                            {indexNow.keyUrl}
                                        </code>
                                    </>
                                )}
                                .
                            </span>
                        </span>
                    </label>
                </Card>

                <div className="flex justify-end">
                    <Button disabled={form.processing}>
                        Salvar integrações
                    </Button>
                </div>
            </form>

            {google.connected && (
                <section className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-base font-semibold">
                            Desempenho orgânico
                        </h2>
                        {periodPicker}
                    </div>

                    <div className="space-y-3">
                        <h3 className="text-sm font-semibold">
                            Search Console
                        </h3>
                        {!settings.gsc_property ? (
                            <Notice>
                                Escolha e salve a propriedade do Search Console
                                para ver cliques e impressões.
                            </Notice>
                        ) : (
                            <Deferred
                                data="searchConsole"
                                fallback={<Loading />}
                            >
                                <SearchConsoleReport
                                    result={searchConsole ?? null}
                                    delayDays={delayDays}
                                />
                            </Deferred>
                        )}
                    </div>

                    <div className="space-y-3">
                        <h3 className="text-sm font-semibold">
                            Google Analytics 4
                        </h3>
                        {!settings.ga4_property_id ? (
                            <Notice>
                                Informe e salve o ID da propriedade do GA4 para
                                ver sessões e conversões.
                            </Notice>
                        ) : (
                            <Deferred data="analytics" fallback={<Loading />}>
                                <AnalyticsSection
                                    result={analytics ?? null}
                                    ga4Events={ga4Events}
                                />
                            </Deferred>
                        )}
                    </div>
                </section>
            )}
        </SeoPage>
    );
}

function SearchConsoleReport({
    result,
    delayDays,
}: {
    result: Result<SearchPerformance>;
    delayDays: number;
}) {
    if (!result) {
        return null;
    }

    if (result.error || !result.data) {
        return <Notice tone="danger">{result.error}</Notice>;
    }

    const { current, previous, period, previousPeriod } = result.data;

    return (
        <div className="space-y-4">
            <p className="text-xs text-muted-foreground">
                {date(period.start)} a {date(period.end)}, comparado com{' '}
                {date(previousPeriod.start)} a {date(previousPeriod.end)}. O
                Search Console consolida os dados com cerca de {delayDays} dias
                de atraso; a API traz no máximo 25 linhas por lista e omite
                consultas anonimizadas. Consultado em{' '}
                {formatDateTime(result.data.fetchedAt)} (cache de 6 h).
            </p>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Cliques"
                    value={number(current.clicks)}
                    hint={delta(current.clicks, previous.clicks)}
                />
                <StatCard
                    label="Impressões"
                    value={number(current.impressions)}
                    hint={delta(current.impressions, previous.impressions)}
                />
                <StatCard
                    label="CTR"
                    value={`${current.ctr.toFixed(2)}%`}
                    hint={delta(current.ctr, previous.ctr)}
                />
                <StatCard
                    label="Posição média"
                    value={current.position.toFixed(1)}
                    hint={delta(current.position, previous.position, true)}
                />
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                <RowsTable title="Consultas" rows={result.data.queries} />
                <RowsTable title="Páginas" rows={result.data.pages} />
            </div>
        </div>
    );
}

function AnalyticsSection({
    result,
    ga4Events,
}: {
    result: Result<AnalyticsReport>;
    ga4Events: Record<string, string>;
}) {
    if (!result) {
        return null;
    }

    if (result.error || !result.data) {
        return <Notice tone="danger">{result.error}</Notice>;
    }

    const { current, previous } = result.data.organic;

    return (
        <div className="space-y-3">
            <p className="text-xs text-muted-foreground">
                Até ontem, comparado com o período anterior de mesmo tamanho.
                Sessões e usuários são do canal Organic Search; os eventos
                comerciais somam todos os canais. Só conta visitantes que
                aceitaram os cookies. Consultado em{' '}
                {formatDateTime(result.data.fetchedAt)} (cache de 3 h).
            </p>
            <div className="grid gap-3 sm:grid-cols-3">
                <StatCard
                    label="Sessões orgânicas"
                    value={number(current.sessions)}
                    hint={delta(current.sessions, previous.sessions)}
                />
                <StatCard
                    label="Usuários orgânicos"
                    value={number(current.users)}
                    hint={delta(current.users, previous.users)}
                />
                <StatCard
                    label="Taxa de engajamento"
                    value={`${current.engagementRate.toFixed(1)}%`}
                    hint={delta(
                        current.engagementRate,
                        previous.engagementRate,
                    )}
                />
            </div>
            {result.data.events.length > 0 && (
                <ul className="divide-y rounded-md border text-sm">
                    {result.data.events.map((event) => (
                        <li
                            key={event.name}
                            className="flex flex-wrap items-center justify-between gap-2 px-4 py-2"
                        >
                            <span>
                                <code>{event.name}</code>{' '}
                                <span className="text-xs text-muted-foreground">
                                    {ga4Events[event.name]}
                                </span>
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                <span className="text-sm font-semibold text-foreground">
                                    {number(event.current)}
                                </span>{' '}
                                · {delta(event.current, event.previous)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
