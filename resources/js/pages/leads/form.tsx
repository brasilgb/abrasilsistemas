import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type Lead = {
    id: number;
    product: string;
    company_name: string;
    address: string | null;
    contact_name: string | null;
    industry: string | null;
    category: string | null;
    city: string | null;
    state: string | null;
    phone: string | null;
    whatsapp: string | null;
    email: string | null;
    website: string | null;
    has_website: boolean;
    site_status: string | null;
    can_improve: boolean;
    opportunity: string | null;
    maps_url: string | null;
    rating: string | null;
    reviews: number | null;
    captured_at: string | null;
    instagram: string | null;
    source: string | null;
    status: string;
    lost_reason: string | null;
    lead_score: number;
    priority: 'high' | 'medium' | 'low';
    next_follow_up_at: string | null;
    last_contacted_at: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
    activities?: LeadActivity[];
    user?: {
        id: number;
        name: string;
    } | null;
};

export type LeadActivity = {
    id: number;
    type: string;
    status: string | null;
    contacted_at: string | null;
    next_follow_up_at: string | null;
    description: string;
    created_at: string;
    updated_at: string;
    user?: {
        id: number;
        name: string;
    } | null;
};

type Props = {
    products: Record<string, string>;
    lostReasons: Record<string, string>;
    statuses: Record<string, string>;
    lead?: Lead;
    processing: boolean;
    errors: Record<string, string | undefined>;
};

export default function LeadForm({
    products,
    lostReasons,
    statuses,
    lead,
    processing,
    errors,
}: Props) {
    return (
        <>
            <div className="grid gap-5 md:grid-cols-2">
                <div className="grid gap-2 md:col-span-2">
                    <Label htmlFor="company_name">Empresa</Label>
                    <Input
                        id="company_name"
                        name="company_name"
                        required
                        autoFocus
                        defaultValue={lead?.company_name}
                        placeholder="Nome da empresa"
                    />
                    <InputError message={errors.company_name} />
                </div>

                <div className="grid gap-2 md:col-span-2">
                    <Label htmlFor="address">Endereço</Label>
                    <Input
                        id="address"
                        name="address"
                        defaultValue={lead?.address ?? ''}
                        placeholder="Rua, número e bairro"
                    />
                    <InputError message={errors.address} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="product">Produto</Label>
                    <select
                        id="product"
                        name="product"
                        defaultValue={lead?.product ?? 'vetoros'}
                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        {Object.entries(products).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.product} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="contact_name">Contato</Label>
                    <Input
                        id="contact_name"
                        name="contact_name"
                        defaultValue={lead?.contact_name ?? ''}
                        placeholder="Nome do responsável"
                    />
                    <InputError message={errors.contact_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="industry">Ramo</Label>
                    <Input
                        id="industry"
                        name="industry"
                        defaultValue={lead?.industry ?? ''}
                        placeholder="Assistência técnica, pet shop..."
                    />
                    <InputError message={errors.industry} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="category">Categoria no Google Maps</Label>
                    <Input
                        id="category"
                        name="category"
                        defaultValue={lead?.category ?? ''}
                        placeholder="Assistência técnica"
                    />
                    <InputError message={errors.category} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="city">Cidade</Label>
                    <Input
                        id="city"
                        name="city"
                        defaultValue={lead?.city ?? ''}
                        placeholder="Cidade"
                    />
                    <InputError message={errors.city} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="state">UF</Label>
                    <Input
                        id="state"
                        name="state"
                        maxLength={2}
                        defaultValue={lead?.state ?? ''}
                        placeholder="RS"
                        className="uppercase"
                    />
                    <InputError message={errors.state} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="whatsapp">WhatsApp</Label>
                    <Input
                        id="whatsapp"
                        name="whatsapp"
                        defaultValue={lead?.whatsapp ?? ''}
                        placeholder="51999999999"
                    />
                    <InputError message={errors.whatsapp} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="phone">Telefone</Label>
                    <Input
                        id="phone"
                        name="phone"
                        defaultValue={lead?.phone ?? ''}
                        placeholder="Telefone alternativo"
                    />
                    <InputError message={errors.phone} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">E-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        defaultValue={lead?.email ?? ''}
                        placeholder="contato@empresa.com"
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="website">Site</Label>
                    <Input
                        id="website"
                        name="website"
                        defaultValue={lead?.website ?? ''}
                        placeholder="https://..."
                    />
                    <InputError message={errors.website} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="maps_url">Link do Google Maps</Label>
                    <Input
                        id="maps_url"
                        name="maps_url"
                        defaultValue={lead?.maps_url ?? ''}
                        placeholder="https://www.google.com/maps/..."
                    />
                    <InputError message={errors.maps_url} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="site_status">Situação do site</Label>
                    <Input
                        id="site_status"
                        name="site_status"
                        defaultValue={lead?.site_status ?? ''}
                        placeholder="Com site - pode melhorar"
                    />
                    <InputError message={errors.site_status} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="captured_at">Capturado em</Label>
                    <Input
                        id="captured_at"
                        type="datetime-local"
                        name="captured_at"
                        defaultValue={lead?.captured_at?.slice(0, 16) ?? ''}
                    />
                    <InputError message={errors.captured_at} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="rating">Avaliação</Label>
                    <Input
                        id="rating"
                        type="number"
                        name="rating"
                        min="0"
                        max="5"
                        step="0.01"
                        defaultValue={lead?.rating ?? ''}
                        placeholder="4.7"
                    />
                    <InputError message={errors.rating} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="reviews">Quantidade de avaliações</Label>
                    <Input
                        id="reviews"
                        type="number"
                        name="reviews"
                        min="0"
                        defaultValue={lead?.reviews ?? ''}
                        placeholder="132"
                    />
                    <InputError message={errors.reviews} />
                </div>

                <div className="grid gap-3 rounded-md border p-4 md:col-span-2 sm:grid-cols-2">
                    <label className="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="has_website" value="0" />
                        <input
                            type="checkbox"
                            name="has_website"
                            value="1"
                            defaultChecked={lead?.has_website ?? false}
                            className="size-4 rounded border-input"
                        />
                        Possui site
                    </label>
                    <label className="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="can_improve" value="0" />
                        <input
                            type="checkbox"
                            name="can_improve"
                            value="1"
                            defaultChecked={lead?.can_improve ?? false}
                            className="size-4 rounded border-input"
                        />
                        Possui oportunidade de melhoria
                    </label>
                    <InputError message={errors.has_website} />
                    <InputError message={errors.can_improve} />
                </div>

                <div className="grid gap-2 md:col-span-2">
                    <Label htmlFor="opportunity">Oportunidade identificada</Label>
                    <textarea
                        id="opportunity"
                        name="opportunity"
                        defaultValue={lead?.opportunity ?? ''}
                        placeholder="Descreva a oportunidade encontrada no estabelecimento"
                        className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    />
                    <InputError message={errors.opportunity} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="instagram">Instagram</Label>
                    <Input
                        id="instagram"
                        name="instagram"
                        defaultValue={lead?.instagram ?? ''}
                        placeholder="@empresa"
                    />
                    <InputError message={errors.instagram} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="source">Origem</Label>
                    <Input
                        id="source"
                        name="source"
                        defaultValue={lead?.source ?? ''}
                        placeholder="Indicação, lista, pesquisa..."
                    />
                    <InputError message={errors.source} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="status">Status</Label>
                    <select
                        id="status"
                        name="status"
                        defaultValue={lead?.status ?? 'new'}
                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        {Object.entries(statuses).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.status} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="lost_reason">Motivo de perda</Label>
                    <select
                        id="lost_reason"
                        name="lost_reason"
                        defaultValue={lead?.lost_reason ?? ''}
                        className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <option value="">
                            Selecione se o lead foi perdido
                        </option>
                        {Object.entries(lostReasons).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.lost_reason} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="next_follow_up_at">Próximo follow-up</Label>
                    <Input
                        id="next_follow_up_at"
                        type="date"
                        name="next_follow_up_at"
                        defaultValue={
                            lead?.next_follow_up_at
                                ? lead.next_follow_up_at.slice(0, 10)
                                : ''
                        }
                    />
                    <InputError message={errors.next_follow_up_at} />
                </div>

                <div className="grid gap-2 md:col-span-2">
                    <Label htmlFor="notes">Observações</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        defaultValue={lead?.notes ?? ''}
                        placeholder="Histórico, contexto e próximos passos"
                        className="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    />
                    <InputError message={errors.notes} />
                </div>
            </div>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button disabled={processing} type="submit">
                    {processing && <Spinner />}
                    {lead ? 'Salvar alterações' : 'Criar lead'}
                </Button>
            </div>
        </>
    );
}
