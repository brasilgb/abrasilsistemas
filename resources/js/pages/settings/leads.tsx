import { Head } from '@inertiajs/react';
import { Copy, Download } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    readWhatsappMessages,
    type WhatsappProduct,
    whatsappMessageTemplates,
    writeWhatsappMessages,
} from '@/lib/lead-whatsapp-messages';
import { edit as editLeadSettings } from '@/routes/lead-settings';

const products: Record<WhatsappProduct, string> = {
    vetoros: 'VetorOS',
    vetorpet: 'VetorPet',
};

type Props = {
    prospectApiToken: string | null;
    prospectApiEndpoint: string;
    prospectExtensionUrl: string | null;
    prospectExtensionFilename: string | null;
    prospectExtensionSigned: boolean;
};

export default function LeadSettings({
    prospectApiToken,
    prospectApiEndpoint,
    prospectExtensionUrl,
    prospectExtensionFilename,
    prospectExtensionSigned,
}: Props) {
    const [product, setProduct] = useState<WhatsappProduct>('vetoros');
    const [messages, setMessages] = useState(whatsappMessageTemplates);
    const [saved, setSaved] = useState(false);
    const [tokenCopied, setTokenCopied] = useState(false);
    const [endpointCopied, setEndpointCopied] = useState(false);

    function copyToken() {
        if (!prospectApiToken) return;
        navigator.clipboard.writeText(prospectApiToken);
        setTokenCopied(true);
        window.setTimeout(() => setTokenCopied(false), 2500);
    }

    function copyEndpoint() {
        navigator.clipboard.writeText(prospectApiEndpoint);
        setEndpointCopied(true);
        window.setTimeout(() => setEndpointCopied(false), 2500);
    }

    useEffect(() => {
        setMessages(readWhatsappMessages());
    }, []);

    function saveMessages() {
        writeWhatsappMessages(messages);
        setSaved(true);
        window.setTimeout(() => setSaved(false), 2500);
    }

    function restoreMessage() {
        setMessages((currentMessages) => ({
            ...currentMessages,
            [product]: whatsappMessageTemplates[product],
        }));
        setSaved(false);
    }

    return (
        <>
            <Head title="Configurações de leads" />

            <h1 className="sr-only">Configurações de leads</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Captura via extensão (Google Maps)"
                    description="Configure a extensão de navegador que captura prospects no Google Maps e envia para este CRM."
                />

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="prospect_extension">
                            Extensão do Firefox
                        </Label>
                        {prospectExtensionUrl ? (
                            <Button asChild className="w-fit">
                                <a href={prospectExtensionUrl} download>
                                    <Download />
                                    Baixar {prospectExtensionFilename}
                                </a>
                            </Button>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Nenhum arquivo da extensão foi encontrado em
                                public/files.
                            </p>
                        )}
                        <div className="text-xs text-muted-foreground">
                            {prospectExtensionSigned ? (
                                <>
                                    <p className="font-medium text-foreground">
                                        Como instalar (extensão assinada
                                        pela Mozilla, instalação
                                        permanente):
                                    </p>
                                    <ol className="mt-1 list-decimal space-y-1 pl-4">
                                        <li>
                                            Baixe o arquivo acima
                                            (.xpi).
                                        </li>
                                        <li>
                                            Abra{' '}
                                            <code className="rounded bg-muted px-1 py-0.5">
                                                about:addons
                                            </code>{' '}
                                            no Firefox.
                                        </li>
                                        <li>
                                            Clique na engrenagem (⚙) →
                                            &quot;Instalar extensão a
                                            partir de um arquivo&quot;.
                                        </li>
                                        <li>
                                            Selecione o arquivo baixado.
                                        </li>
                                    </ol>
                                    <p className="mt-2">
                                        Assim instalada, a extensão fica
                                        disponível permanentemente — não
                                        some ao fechar o navegador.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <p className="font-medium text-foreground">
                                        Como instalar (extensão não
                                        assinada, precisa ser carregada
                                        como temporária):
                                    </p>
                                    <ol className="mt-1 list-decimal space-y-1 pl-4">
                                        <li>
                                            No Firefox, abra{' '}
                                            <code className="rounded bg-muted px-1 py-0.5">
                                                about:debugging
                                            </code>{' '}
                                            e clique em &quot;Este
                                            Firefox&quot;.
                                        </li>
                                        <li>
                                            Se já houver uma versão
                                            carregada, clique em
                                            &quot;Remover&quot; ou
                                            &quot;Descarregar&quot; antes
                                            de carregar a nova.
                                        </li>
                                        <li>
                                            Extraia o ZIP baixado em uma
                                            pasta.
                                        </li>
                                        <li>
                                            Clique em &quot;Carregar
                                            extensão temporária&quot;.
                                        </li>
                                        <li>
                                            Selecione o arquivo{' '}
                                            <code className="rounded bg-muted px-1 py-0.5">
                                                manifest.json
                                            </code>{' '}
                                            dentro da pasta extraída.
                                        </li>
                                    </ol>
                                    <p className="mt-2">
                                        Por ser temporária, ela some
                                        quando o Firefox é fechado —
                                        repita esses passos toda vez que
                                        abrir o navegador para usar a
                                        extensão novamente.
                                    </p>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="prospect_api_endpoint">
                            Endpoint da API
                        </Label>
                        <div className="flex gap-2">
                            <Input
                                id="prospect_api_endpoint"
                                readOnly
                                value={prospectApiEndpoint}
                                onFocus={(event) => event.target.select()}
                                className="font-mono"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={copyEndpoint}
                            >
                                <Copy />
                                {endpointCopied ? 'Copiado' : 'Copiar'}
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="prospect_api_token">
                            Token de integração
                        </Label>
                        <div className="flex gap-2">
                            <Input
                                id="prospect_api_token"
                                readOnly
                                value={prospectApiToken ?? ''}
                                onFocus={(event) => event.target.select()}
                                className="font-mono"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={copyToken}
                                disabled={!prospectApiToken}
                            >
                                <Copy />
                                {tokenCopied ? 'Copiado' : 'Copiar'}
                            </Button>
                        </div>
                        <div className="text-xs text-muted-foreground">
                            <p className="font-medium text-foreground">
                                Como configurar na extensão:
                            </p>
                            <ol className="mt-1 list-decimal space-y-1 pl-4">
                                <li>
                                    Clique no ícone da extensão e abra a
                                    aba &quot;Configurações&quot; no popup.
                                </li>
                                <li>
                                    Cole a URL acima no campo
                                    &quot;Endpoint do CRM&quot;.
                                </li>
                                <li>
                                    Cole o token acima no campo &quot;Token
                                    Bearer opcional&quot;.
                                </li>
                                <li>
                                    Clique em &quot;Salvar
                                    configurações&quot;.
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>

                <Heading
                    variant="small"
                    title="Mensagens de WhatsApp"
                    description="Configure as mensagens usadas no primeiro contato por WhatsApp."
                />

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="whatsapp_product">Produto</Label>
                        <select
                            id="whatsapp_product"
                            value={product}
                            onChange={(event) =>
                                setProduct(
                                    event.target.value as WhatsappProduct,
                                )
                            }
                            className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            {Object.entries(products).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="whatsapp_message">
                            Mensagem do WhatsApp
                        </Label>
                        <textarea
                            id="whatsapp_message"
                            value={messages[product]}
                            onChange={(event) =>
                                setMessages((currentMessages) => ({
                                    ...currentMessages,
                                    [product]: event.target.value,
                                }))
                            }
                            rows={12}
                            className="min-h-72 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        />
                        <p className="text-xs text-muted-foreground">
                            A tela de leads usa automaticamente esta mensagem de
                            acordo com o produto marcado no lead.
                        </p>
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={restoreMessage}
                        >
                            Restaurar padrão
                        </Button>
                        <Button type="button" onClick={saveMessages}>
                            {saved ? 'Salvo' : 'Salvar mensagens'}
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

LeadSettings.layout = {
    breadcrumbs: [
        {
            title: 'Configurações de leads',
            href: editLeadSettings(),
        },
    ],
};
