import { AlertTriangle, CheckCircle2, QrCode, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    connect as connectRoute,
    disconnect as disconnectRoute,
    qr as qrRoute,
    status as statusRoute,
} from '@/routes/lead-settings/whatsapp';

const POLL_INTERVAL = 3000;
const QR_REFRESH_INTERVAL = 20000;

type ConnectionState =
    | 'not_configured'
    | 'connected'
    | 'scan_qr_code'
    | 'connecting'
    | 'failed'
    | 'disconnected'
    | 'unknown'
    | 'error';

type ConnectionStatus = {
    state: ConnectionState;
    label: string;
    message: string | null;
    session: string | null;
    configured_number: string | null;
    waha_status: string | null;
    account: {
        id: string;
        number: string | null;
        name: string | null;
        lid: string | null;
    } | null;
    number_matches: boolean | null;
};

const stateVariants: Record<
    ConnectionState,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    connected: 'default',
    scan_qr_code: 'secondary',
    connecting: 'secondary',
    disconnected: 'outline',
    not_configured: 'outline',
    unknown: 'outline',
    failed: 'destructive',
    error: 'destructive',
};

export function formatWhatsapp(number: string | null): string {
    if (!number) {
        return '—';
    }

    const local = number.startsWith('55') ? number.slice(2) : number;
    const match = local.match(/^(\d{2})(\d{4,5})(\d{4})$/);

    return match ? `+55 (${match[1]}) ${match[2]}-${match[3]}` : number;
}

async function request(
    url: string,
    method: 'GET' | 'POST',
): Promise<ConnectionStatus> {
    const csrfToken = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');

    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
    });
    const result = (await response.json().catch(() => ({}))) as Partial<
        ConnectionStatus & { message: string }
    >;

    if (!response.ok || !result.state) {
        throw new Error(
            result.message ??
                'Não foi possível consultar a conexão do WhatsApp.',
        );
    }

    return result as ConnectionStatus;
}

export default function CompanyWhatsappConnection() {
    const [status, setStatus] = useState<ConnectionStatus | null>(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [qrOpen, setQrOpen] = useState(false);
    const [qrVersion, setQrVersion] = useState(0);
    const [qrError, setQrError] = useState<string | null>(null);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const lastState = useRef<ConnectionState | null>(null);

    // Aplica um status novo e trata as transições: QR novo ao entrar em SCAN_QR_CODE e
    // fechamento do modal quando a sessão fica WORKING.
    const applyStatus = useCallback((next: ConnectionStatus) => {
        const previous = lastState.current;
        lastState.current = next.state;

        setStatus(next);
        setError(null);

        if (next.state === 'scan_qr_code' && previous !== next.state) {
            setQrError(null);
            setQrVersion((version) => version + 1);
        }

        if (next.state === 'connected') {
            setQrOpen(false);

            if (previous !== null && previous !== next.state) {
                toast.success('WhatsApp conectado.');
            }
        }
    }, []);

    const refresh = useCallback(
        () =>
            request(statusRoute.url(), 'GET')
                .then(applyStatus)
                .catch((exception: Error) => setError(exception.message))
                .finally(() => setLoading(false)),
        [applyStatus],
    );

    useEffect(() => {
        refresh();
    }, [refresh]);

    // Enquanto o modal do QR está aberto, acompanha o status pelo Laravel (nunca pelo WAHA direto).
    useEffect(() => {
        if (!qrOpen) {
            return;
        }

        const poll = window.setInterval(refresh, POLL_INTERVAL);
        const qrRefresh = window.setInterval(() => {
            setQrError(null);
            setQrVersion((version) => version + 1);
        }, QR_REFRESH_INTERVAL);

        return () => {
            window.clearInterval(poll);
            window.clearInterval(qrRefresh);
        };
    }, [qrOpen, refresh]);

    async function connect() {
        setBusy(true);
        setQrError(null);

        try {
            const next = await request(connectRoute.url(), 'POST');
            applyStatus(next);

            if (next.state !== 'connected') {
                setQrVersion((version) => version + 1);
                setQrOpen(true);
            }
        } catch (exception) {
            toast.error((exception as Error).message);
        } finally {
            setBusy(false);
        }
    }

    async function disconnect() {
        setBusy(true);

        try {
            applyStatus(await request(disconnectRoute.url(), 'POST'));
            setConfirmOpen(false);
            toast.success('WhatsApp desconectado.');
        } catch (exception) {
            toast.error((exception as Error).message);
        } finally {
            setBusy(false);
        }
    }

    const state = status?.state;
    const account = status?.account;
    const canConnect =
        !!status && state !== 'not_configured' && state !== 'connected';

    return (
        <div className="grid gap-3 rounded-md border p-3 text-sm">
            <div className="flex items-center justify-between gap-2">
                <p className="font-medium">Conexão</p>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => refresh()}
                    disabled={loading}
                >
                    <RefreshCw className="size-4" />
                    Atualizar
                </Button>
            </div>

            {loading && !status ? (
                <p className="flex items-center gap-2 text-muted-foreground">
                    <Spinner /> Consultando a sessão no WAHA...
                </p>
            ) : (
                <>
                    <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">Status:</span>
                        {status && (
                            <Badge variant={stateVariants[status.state]}>
                                {status.label}
                            </Badge>
                        )}
                        {status?.number_matches === false && (
                            <Badge variant="destructive">
                                <AlertTriangle />
                                Número divergente
                            </Badge>
                        )}
                    </div>

                    {account && (
                        <>
                            <p>
                                <span className="text-muted-foreground">
                                    Conta:
                                </span>{' '}
                                {account.name ?? '—'}
                            </p>
                            <p>
                                <span className="text-muted-foreground">
                                    Número conectado:
                                </span>{' '}
                                {formatWhatsapp(account.number)}
                            </p>
                        </>
                    )}

                    {status?.number_matches === true && (
                        <p className="flex items-center gap-1 text-xs text-green-700 dark:text-green-400">
                            <CheckCircle2 className="size-4" />
                            Número conectado corretamente.
                        </p>
                    )}

                    {status?.number_matches === false && (
                        <div className="rounded-md border border-destructive/50 bg-destructive/5 p-3 text-xs">
                            <p className="font-medium text-destructive">
                                Atenção
                            </p>
                            <p>
                                Número configurado:{' '}
                                {formatWhatsapp(status.configured_number)}
                            </p>
                            <p>
                                Número conectado:{' '}
                                {formatWhatsapp(account?.number ?? null)}
                            </p>
                            <p className="mt-1 text-muted-foreground">
                                A sessão WAHA está conectada a um número
                                diferente do configurado para esta empresa.
                                Corrija o número acima ou desconecte e conecte o
                                WhatsApp certo.
                            </p>
                        </div>
                    )}

                    {state === 'unknown' && status?.waha_status && (
                        <p className="text-xs text-muted-foreground">
                            Status informado pelo WAHA:{' '}
                            <span className="font-mono">
                                {status.waha_status}
                            </span>
                        </p>
                    )}

                    {(error ?? status?.message) && (
                        <p className="text-xs text-destructive">
                            {error ?? status?.message}
                        </p>
                    )}

                    <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        {state === 'connected' ? (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => setConfirmOpen(true)}
                                disabled={busy}
                            >
                                Desconectar WhatsApp
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={connect}
                                disabled={!canConnect || busy}
                            >
                                {busy ? <Spinner /> : <QrCode />}
                                Conectar WhatsApp
                            </Button>
                        )}
                    </div>
                </>
            )}

            <Dialog open={qrOpen} onOpenChange={setQrOpen}>
                <DialogContent>
                    <DialogTitle>Conectar WhatsApp</DialogTitle>
                    <DialogDescription>
                        Abra o WhatsApp no celular da empresa e leia o QR Code.
                    </DialogDescription>

                    <div className="flex flex-col items-center gap-3">
                        {state === 'scan_qr_code' && !qrError ? (
                            <img
                                key={qrVersion}
                                src={`${qrRoute.url()}?v=${qrVersion}`}
                                alt="QR Code para conectar o WhatsApp"
                                width={292}
                                height={292}
                                className="rounded-md border bg-white p-2"
                                onError={() =>
                                    setQrError(
                                        'O QR Code expirou ou não está disponível.',
                                    )
                                }
                            />
                        ) : (
                            <div className="flex size-[292px] flex-col items-center justify-center gap-2 rounded-md border p-4 text-center text-muted-foreground">
                                {qrError ? (
                                    <p>{qrError}</p>
                                ) : state === 'failed' ||
                                  state === 'disconnected' ||
                                  state === 'error' ? (
                                    <p>
                                        {status?.message ??
                                            `A sessão está: ${status?.label}.`}
                                    </p>
                                ) : (
                                    <>
                                        <Spinner />
                                        <p>Preparando o QR Code...</p>
                                    </>
                                )}
                            </div>
                        )}

                        <ol className="list-decimal space-y-1 pl-4 text-xs text-muted-foreground">
                            <li>Abra o WhatsApp no celular.</li>
                            <li>Toque em Aparelhos conectados.</li>
                            <li>Toque em Conectar aparelho e leia o código.</li>
                        </ol>

                        <p className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Spinner /> Aguardando conexão...
                        </p>
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Fechar
                            </Button>
                        </DialogClose>
                        <Button type="button" onClick={connect} disabled={busy}>
                            <RefreshCw className="size-4" />
                            Gerar novo QR Code
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent>
                    <DialogTitle>Desconectar WhatsApp?</DialogTitle>
                    <DialogDescription>
                        A conta{' '}
                        {account
                            ? `${account.name ?? ''} ${formatWhatsapp(account.number)}`.trim()
                            : ''}{' '}
                        será desconectada da sessão{' '}
                        <span className="font-mono">{status?.session}</span>. O
                        CRM deixa de enviar mensagens até um novo QR Code ser
                        lido. A sessão, os webhooks e as configurações são
                        mantidos.
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={disconnect}
                            disabled={busy}
                        >
                            {busy && <Spinner />}
                            Desconectar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
