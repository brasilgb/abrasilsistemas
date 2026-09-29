<?php

namespace App\Http\Requests\Leads;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('product')) {
            $this->merge(['product' => strtolower(trim((string) $this->input('product')))]);
        }

        $this->merge([
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => $this->filled('phone') ? (preg_replace('/\D+/', '', (string) $this->input('phone')) ?? '') : null,
            'state' => $this->filled('state') ? strtoupper(trim((string) $this->input('state'))) : null,
            'whatsapp' => $this->filled('whatsapp') ? (preg_replace('/\D+/', '', (string) $this->input('whatsapp')) ?? '') : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Dados para o Lead. `product` vazio fica de fora: com o produto do catálogo, o texto
     * legado é derivado no model; sem ele, continua o que já estava gravado.
     *
     * @return array<string, mixed>
     */
    public function leadData(): array
    {
        return array_filter($this->validated(), fn ($value, string $key) => $key !== 'product' || $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'exists:users,id'],
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            // Produto/Serviço do catálogo: ativo, ou o que o lead já tem (mesmo inativo).
            'prospect_product_id' => [
                'nullable',
                'required_without:product',
                'integer',
                Rule::exists('prospect_products', 'id')->where(fn ($query) => $query
                    ->where('active', true)
                    ->when($this->currentLead()?->prospect_product_id, fn ($query, int $id) => $query->orWhere('id', $id))),
            ],
            // Texto legado: aceito sem prospect_product_id (integrações e lead ainda sem vínculo).
            'product' => [
                'nullable',
                'required_without:prospect_product_id',
                'string',
                Rule::in([...array_keys(Lead::PRODUCTS), ...array_filter([$this->currentLead()?->product])]),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:2048'],
            'has_website' => ['sometimes', 'boolean'],
            'site_status' => ['nullable', 'string', 'max:255'],
            'can_improve' => ['sometimes', 'boolean'],
            'opportunity' => ['nullable', 'string'],
            'maps_url' => ['nullable', 'url:http,https', 'max:2048'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'reviews' => ['nullable', 'integer', 'min:0'],
            'captured_at' => ['nullable', 'date'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(array_keys(Lead::STATUSES))],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', Rule::in(array_keys(Lead::LOST_REASONS))],
            'next_follow_up_at' => ['nullable', 'date'],
            // Sem trial_started_at/trial_ends_at: o teste só começa pela ação "Iniciar período de
            // teste" (CRM-WA-23); datas enviadas pelo formulário ficam fora de leadData().
            'notes' => ['nullable', 'string'],
        ];
    }

    private function currentLead(): ?Lead
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead ? $lead : null;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'prospect_product_id.required_without' => 'Selecione o produto/serviço.',
            'prospect_product_id.exists' => 'Selecione um produto/serviço ativo.',
        ];
    }
}
