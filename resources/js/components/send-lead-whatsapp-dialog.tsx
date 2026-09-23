import { Form } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as sendLeadWhatsapp } from '@/routes/leads/whatsapp';

const MESSAGE_MAX_LENGTH = 4000;

type Props = {
    leadId: number;
    leadName: string;
    destination: string | null;
};

function formatDestination(destination: string) {
    const local = destination.startsWith('55')
        ? destination.slice(2)
        : destination;
    const match = local.match(/^(\d{2})(\d{4,5})(\d{4})$/);

    return match ? `(${match[1]}) ${match[2]}-${match[3]}` : destination;
}

export default function SendLeadWhatsappDialog({
    leadId,
    leadName,
    destination,
}: Props) {
    const [open, setOpen] = useState(false);
    const [message, setMessage] = useState('');

    if (!destination) {
        return (
            <Button
                type="button"
                variant="outline"
                disabled
                title="Cadastre um WhatsApp válido para enviar mensagens"
            >
                <MessageCircle className="size-4" />
                Enviar WhatsApp
            </Button>
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button">
                    <MessageCircle className="size-4" />
                    Enviar WhatsApp
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Enviar WhatsApp</DialogTitle>
                <DialogDescription>
                    A mensagem é enviada pela integração do CRM e registrada no
                    histórico do prospect.
                </DialogDescription>

                <dl className="grid gap-3 rounded-md border bg-muted/40 p-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Cliente
                        </dt>
                        <dd className="font-medium">{leadName}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Destino
                        </dt>
                        <dd className="font-medium">
                            {formatDestination(destination)}
                        </dd>
                    </div>
                </dl>

                <Form
                    action={sendLeadWhatsapp({ lead: leadId })}
                    method="post"
                    options={{
                        preserveScroll: true,
                        preserveState: true,
                        only: ['lead'],
                    }}
                    onSuccess={() => {
                        setMessage('');
                        setOpen(false);
                    }}
                    disableWhileProcessing
                    className="space-y-4"
                >
                    {({ processing, errors, clearErrors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="whatsapp_message">
                                    Mensagem
                                </Label>
                                <textarea
                                    id="whatsapp_message"
                                    name="message"
                                    required
                                    maxLength={MESSAGE_MAX_LENGTH}
                                    value={message}
                                    onChange={(event) =>
                                        setMessage(event.target.value)
                                    }
                                    placeholder="Olá, tudo bem? Somos da ABrasil Sistemas..."
                                    className="min-h-36 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                />
                                <p className="text-right text-xs text-muted-foreground">
                                    {message.length}/{MESSAGE_MAX_LENGTH}
                                </p>
                                <InputError message={errors.message} />
                                <InputError message={errors.whatsapp} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={processing}
                                        onClick={() => clearErrors()}
                                    >
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || message.trim() === ''
                                    }
                                >
                                    {processing ? (
                                        <>
                                            <Spinner />
                                            Enviando...
                                        </>
                                    ) : (
                                        'Enviar WhatsApp'
                                    )}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
