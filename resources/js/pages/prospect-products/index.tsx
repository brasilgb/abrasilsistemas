import { Head, router, useForm } from '@inertiajs/react';
import { Package, Pencil, Plus, Power } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    active as activeRoute,
    index,
    store,
    update,
} from '@/routes/prospect-products';

type ProspectProduct = {
    id: number;
    name: string;
    whatsapp_text: string | null;
    trial_days: number | null;
    trial_expiration_text: string | null;
    active: boolean;
    registration_check_mode: RegistrationCheckMode;
    registration_api_url: string | null;
    registration_lookup_field: string | null;
    /** O token nunca vem do servidor: só se há um salvo. */
    registration_api_token_set: boolean;
};

type RegistrationCheckMode = 'manual' | 'api';

type Props = {
    products: ProspectProduct[];
    registrationCheckModes: Record<RegistrationCheckMode, string>;
    registrationLookupFields: Record<string, string>;
};

const textareaClassName =
    'min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none';

function formatTrial(days: number | null) {
    if (days === null) {
        return 'Sem teste';
    }

    return days === 1 ? '1 dia' : `${days} dias`;
}

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

function ProductDialog({
    product,
    open,
    onOpenChange,
    modes,
    lookupFields,
}: {
    product: ProspectProduct | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    modes: Props['registrationCheckModes'];
    lookupFields: Props['registrationLookupFields'];
}) {
    const form = useForm({
        name: product?.name ?? '',
        trial_days: product ? (product.trial_days?.toString() ?? '') : '14',
        whatsapp_text: product?.whatsapp_text ?? '',
        trial_expiration_text: product?.trial_expiration_text ?? '',
        active: product?.active ?? true,
        registration_check_mode: (product?.registration_check_mode ??
            'manual') as RegistrationCheckMode,
        registration_api_url: product?.registration_api_url ?? '',
        registration_lookup_field: product?.registration_lookup_field ?? '',
        registration_api_token: '',
        registration_api_token_clear: false,
    });
    const tokenSet = product?.registration_api_token_set ?? false;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {product ? 'Editar produto' : 'Novo produto'}
                    </DialogTitle>
                    <DialogDescription>
                        Os textos ficam só armazenados. Nenhuma mensagem é
                        enviada a partir deste cadastro.
                    </DialogDescription>
                </DialogHeader>

                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.submit(
                            product
                                ? update({ prospect_product: product.id })
                                : store(),
                            {
                                preserveScroll: true,
                                onSuccess: () => onOpenChange(false),
                            },
                        );
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="product-name">Nome</Label>
                        <Input
                            id="product-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            maxLength={120}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="product-trial-days">
                            Dias de teste
                        </Label>
                        <Input
                            id="product-trial-days"
                            type="number"
                            min={0}
                            value={form.data.trial_days}
                            onChange={(event) =>
                                form.setData('trial_days', event.target.value)
                            }
                            placeholder="Deixe vazio se não houver teste"
                        />
                        <InputError message={form.errors.trial_days} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="product-whatsapp-text">
                            Texto WhatsApp
                        </Label>
                        <textarea
                            id="product-whatsapp-text"
                            className={textareaClassName}
                            value={form.data.whatsapp_text}
                            onChange={(event) =>
                                form.setData(
                                    'whatsapp_text',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.whatsapp_text} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="product-trial-expiration-text">
                            Texto de vencimento do teste
                        </Label>
                        <textarea
                            id="product-trial-expiration-text"
                            className={textareaClassName}
                            value={form.data.trial_expiration_text}
                            onChange={(event) =>
                                form.setData(
                                    'trial_expiration_text',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError
                            message={form.errors.trial_expiration_text}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="product-registration-mode">
                            Acompanhamento de cadastro
                        </Label>
                        <select
                            id="product-registration-mode"
                            className={selectClassName}
                            value={form.data.registration_check_mode}
                            onChange={(event) =>
                                form.setData(
                                    'registration_check_mode',
                                    event.target.value as RegistrationCheckMode,
                                )
                            }
                        >
                            {Object.entries(modes).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-muted-foreground">
                            {form.data.registration_check_mode === 'api'
                                ? 'O CRM consulta a API do produto e inicia o teste quando ela confirmar o cadastro. O início manual continua disponível.'
                                : 'O início do teste é informado pela tela do lead.'}
                        </p>
                        <InputError
                            message={form.errors.registration_check_mode}
                        />
                    </div>

                    {form.data.registration_check_mode === 'api' && (
                        <div className="space-y-4 rounded-md border p-3">
                            <div className="grid gap-2">
                                <Label htmlFor="product-registration-url">
                                    URL da API (HTTPS)
                                </Label>
                                <Input
                                    id="product-registration-url"
                                    type="url"
                                    value={form.data.registration_api_url}
                                    onChange={(event) =>
                                        form.setData(
                                            'registration_api_url',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="https://produto.com.br/api/crm/registration"
                                    maxLength={2048}
                                />
                                <InputError
                                    message={form.errors.registration_api_url}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="product-registration-lookup">
                                    Identificador enviado
                                </Label>
                                <select
                                    id="product-registration-lookup"
                                    className={selectClassName}
                                    value={form.data.registration_lookup_field}
                                    onChange={(event) =>
                                        form.setData(
                                            'registration_lookup_field',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Selecione</option>
                                    {Object.entries(lookupFields).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError
                                    message={
                                        form.errors.registration_lookup_field
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="product-registration-token">
                                    Token da API
                                </Label>
                                <Input
                                    id="product-registration-token"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.registration_api_token}
                                    onChange={(event) =>
                                        form.setData(
                                            'registration_api_token',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={
                                        tokenSet
                                            ? 'Token salvo. Deixe vazio para manter.'
                                            : 'Enviado como Bearer'
                                    }
                                />
                                {tokenSet && (
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="product-registration-token-clear"
                                            checked={
                                                form.data
                                                    .registration_api_token_clear
                                            }
                                            onCheckedChange={(checked) =>
                                                form.setData(
                                                    'registration_api_token_clear',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label htmlFor="product-registration-token-clear">
                                            Remover o token salvo
                                        </Label>
                                    </div>
                                )}
                                <InputError
                                    message={form.errors.registration_api_token}
                                />
                            </div>
                        </div>
                    )}

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="product-active"
                            checked={form.data.active}
                            onCheckedChange={(checked) =>
                                form.setData('active', checked === true)
                            }
                        />
                        <Label htmlFor="product-active">Ativo</Label>
                        <InputError message={form.errors.active} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Salvar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ProspectProductsIndex({
    products,
    registrationCheckModes,
    registrationLookupFields,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<ProspectProduct | null>(null);
    const [dialogKey, setDialogKey] = useState(0);

    function openDialog(product: ProspectProduct | null) {
        setEditing(product);
        setDialogKey((key) => key + 1);
        setDialogOpen(true);
    }

    function toggleActive(product: ProspectProduct) {
        router.patch(
            activeRoute.url({ prospect_product: product.id }),
            { active: !product.active },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Produtos/Serviços" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Produtos/Serviços"
                        description="Cadastre o que é oferecido nas prospecções."
                    />

                    <Button onClick={() => openDialog(null)}>
                        <Plus className="size-4" />
                        Novo produto
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="size-5" />
                            Produtos cadastrados
                        </CardTitle>
                        <CardDescription>
                            Produtos inativos continuam cadastrados para
                            preservar o histórico.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-hidden rounded-md border">
                            <div className="hidden grid-cols-[1.6fr_0.8fr_0.6fr_auto] gap-4 border-b bg-muted/50 px-4 py-3 text-xs font-medium text-muted-foreground md:grid">
                                <span>Produto</span>
                                <span>Período de teste</span>
                                <span>Status</span>
                                <span className="text-right">Ações</span>
                            </div>

                            {products.length === 0 ? (
                                <div className="px-4 py-10 text-center text-sm text-muted-foreground">
                                    Nenhum produto cadastrado.
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {products.map((product) => (
                                        <div
                                            key={product.id}
                                            className="grid gap-3 px-4 py-4 md:grid-cols-[1.6fr_0.8fr_0.6fr_auto] md:items-center md:gap-4"
                                        >
                                            <p className="truncate text-sm font-medium">
                                                {product.name}
                                            </p>

                                            <p className="text-sm text-muted-foreground">
                                                {formatTrial(
                                                    product.trial_days,
                                                )}
                                                {product.registration_check_mode ===
                                                    'api' &&
                                                    ' · Cadastro via API'}
                                            </p>

                                            <div>
                                                <Badge
                                                    variant={
                                                        product.active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {product.active
                                                        ? 'Ativo'
                                                        : 'Inativo'}
                                                </Badge>
                                            </div>

                                            <div className="flex gap-2 md:justify-end">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        openDialog(product)
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                    Editar
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        toggleActive(product)
                                                    }
                                                >
                                                    <Power className="size-4" />
                                                    {product.active
                                                        ? 'Desativar'
                                                        : 'Ativar'}
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <ProductDialog
                key={dialogKey}
                product={editing}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                modes={registrationCheckModes}
                lookupFields={registrationLookupFields}
            />
        </>
    );
}

ProspectProductsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Produtos/Serviços',
            href: index(),
        },
    ],
};
