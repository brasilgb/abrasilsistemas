import { Head, useForm } from '@inertiajs/react';
import CompanyWhatsappConnection, {
    formatWhatsapp,
} from '@/components/company-whatsapp-connection';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    edit as editWhatsappSettings,
    update as updateWhatsappSettings,
} from '@/routes/whatsapp-settings';

type CompanyWhatsapp = {
    configured: boolean;
    enabled: boolean;
    number: string | null;
    provider: string | null;
    session: string | null;
};

type Props = {
    companyWhatsapp: CompanyWhatsapp;
    whatsappProviders: string[];
    webhookEnabled: boolean;
    webhookUrl: string;
};

const providerLabels: Record<string, string> = {
    waha: 'WAHA',
};

/** Sessão sugerida para o atendimento do site, separada da sessão comercial do VetorLeads. */
const SUGGESTED_SESSION = 'abrasil-atendimento';

export default function WhatsappSettings({
    companyWhatsapp,
    whatsappProviders,
    webhookEnabled,
    webhookUrl,
}: Props) {
    const form = useForm({
        enabled: companyWhatsapp.enabled,
        number: companyWhatsapp.number ?? '',
        provider: companyWhatsapp.provider ?? whatsappProviders[0],
        session: companyWhatsapp.session ?? '',
    });
    const status = !companyWhatsapp.configured
        ? {
              label: 'Não configurado',
              variant: 'outline' as const,
              hint: 'Salve o número e a sessão para o atendimento receber e responder mensagens.',
          }
        : companyWhatsapp.enabled
          ? {
                label: 'Habilitado',
                variant: 'default' as const,
                hint: 'O atendimento recebe e responde pelas mensagens desta sessão.',
            }
          : {
                label: 'Desabilitado',
                variant: 'secondary' as const,
                hint: 'A configuração está salva, mas marcada como desabilitada.',
            };

    return (
        <>
            <Head title="WhatsApp do atendimento" />

            <h1 className="sr-only">WhatsApp do atendimento</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="WhatsApp do atendimento"
                    description="Número institucional e sessão do WAHA usados em Atendimento. As mensagens chegam direto do WAHA, sem n8n."
                />

                <div className="grid gap-1 rounded-md border p-3 text-sm">
                    <p className="font-medium">Configuração</p>
                    <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">
                            Integração:
                        </span>
                        <Badge variant={status.variant}>{status.label}</Badge>
                    </div>
                    <p>
                        <span className="text-muted-foreground">Número:</span>{' '}
                        {formatWhatsapp(companyWhatsapp.number)}
                    </p>
                    <p>
                        <span className="text-muted-foreground">Sessão:</span>{' '}
                        <span className="font-mono">
                            {companyWhatsapp.session ?? '—'}
                        </span>{' '}
                        {companyWhatsapp.provider && (
                            <span className="text-muted-foreground">
                                (
                                {providerLabels[companyWhatsapp.provider] ??
                                    companyWhatsapp.provider}
                                )
                            </span>
                        )}
                    </p>
                    <p className="flex items-center gap-2">
                        <span className="text-muted-foreground">
                            Recebimento (webhook):
                        </span>
                        <Badge variant={webhookEnabled ? 'default' : 'outline'}>
                            {webhookEnabled ? 'Ligado' : 'Desligado'}
                        </Badge>
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {status.hint}
                    </p>
                </div>

                {/* Remonta ao salvar outra sessão/número, para reconsultar a conexão. */}
                <CompanyWhatsappConnection
                    key={`${companyWhatsapp.session}:${companyWhatsapp.number}`}
                />

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="company_whatsapp_enabled"
                        checked={form.data.enabled}
                        onCheckedChange={(checked) =>
                            form.setData('enabled', checked === true)
                        }
                    />
                    <Label htmlFor="company_whatsapp_enabled">
                        Integração habilitada
                    </Label>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="company_whatsapp_number">
                        WhatsApp do atendimento
                    </Label>
                    <Input
                        id="company_whatsapp_number"
                        inputMode="tel"
                        placeholder="(51) 99999-8888"
                        value={form.data.number}
                        onChange={(event) =>
                            form.setData('number', event.target.value)
                        }
                    />
                    <p className="text-xs text-muted-foreground">
                        O mesmo número autenticado na sessão do WAHA e exibido
                        no site. É salvo só com dígitos (55 + DDD + número).
                    </p>
                    {form.errors.number && (
                        <p className="text-sm text-destructive">
                            {form.errors.number}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="company_whatsapp_provider">Provedor</Label>
                    <select
                        id="company_whatsapp_provider"
                        value={form.data.provider}
                        onChange={(event) =>
                            form.setData('provider', event.target.value)
                        }
                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        {whatsappProviders.map((provider) => (
                            <option key={provider} value={provider}>
                                {providerLabels[provider] ?? provider}
                            </option>
                        ))}
                    </select>
                    {form.errors.provider && (
                        <p className="text-sm text-destructive">
                            {form.errors.provider}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="company_whatsapp_session">
                        Sessão do WAHA
                    </Label>
                    <Input
                        id="company_whatsapp_session"
                        placeholder={`ex.: ${SUGGESTED_SESSION}`}
                        value={form.data.session}
                        onChange={(event) =>
                            form.setData('session', event.target.value)
                        }
                        className="font-mono"
                    />
                    <p className="text-xs text-muted-foreground">
                        O WAHA é compartilhado com o VetorLeads, mas cada
                        sistema usa a própria sessão. Para o atendimento do
                        site, use uma sessão dedicada (sugestão:{' '}
                        <code className="rounded bg-muted px-1 py-0.5">
                            {SUGGESTED_SESSION}
                        </code>
                        ), não a sessão comercial.
                    </p>
                    {form.errors.session && (
                        <p className="text-sm text-destructive">
                            {form.errors.session}
                        </p>
                    )}
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <Button
                        type="button"
                        onClick={() =>
                            form.put(updateWhatsappSettings().url, {
                                preserveScroll: true,
                            })
                        }
                        disabled={form.processing}
                    >
                        {form.processing ? 'Salvando...' : 'Salvar WhatsApp'}
                    </Button>
                </div>

                <div className="grid gap-2 rounded-md border p-3 text-xs text-muted-foreground">
                    <p className="font-medium text-foreground">
                        Webhook da sessão no WAHA
                    </p>
                    <p>
                        URL:{' '}
                        <code className="rounded bg-muted px-1 py-0.5 break-all">
                            {webhookUrl}
                        </code>
                    </p>
                    <p>
                        Eventos: <code>message</code>, <code>message.any</code>{' '}
                        e <code>message.ack</code>, com a mesma chave HMAC de{' '}
                        <code>WAHA_WEBHOOK_HMAC_KEY</code> no servidor. A chave
                        e a API key do WAHA nunca aparecem nesta tela.
                    </p>
                </div>
            </div>
        </>
    );
}

WhatsappSettings.layout = {
    breadcrumbs: [
        {
            title: 'WhatsApp do atendimento',
            href: editWhatsappSettings(),
        },
    ],
};
