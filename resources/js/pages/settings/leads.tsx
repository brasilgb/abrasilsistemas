import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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

export default function LeadSettings() {
    const [product, setProduct] = useState<WhatsappProduct>('vetoros');
    const [messages, setMessages] = useState(whatsappMessageTemplates);
    const [saved, setSaved] = useState(false);

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
                    title="Leads"
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
