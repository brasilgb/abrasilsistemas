<?php

namespace App\Http\Requests\ProspectProducts;

use App\Http\Requests\Leads\SendLeadWhatsappRequest;
use App\Models\ProspectProduct;
use App\Services\RegistrationApiUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProspectProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        /** @var ProspectProduct|null $product */
        $product = $this->route('prospect_product');
        $hasToken = filled($product?->registration_api_token) && ! $this->boolean('registration_api_token_clear');

        return [
            'name' => ['required', 'string', 'max:120'],
            // max: teto técnico da coluna unsignedInteger (MySQL), não regra comercial.
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            // Mesmo limite do envio manual pela conversa (4000).
            'whatsapp_text' => ['nullable', 'string', 'max:'.SendLeadWhatsappRequest::MESSAGE_MAX_LENGTH],
            'trial_expiration_text' => ['nullable', 'string', 'max:5000'],
            'active' => ['boolean'],
            // Acompanhamento de cadastro (CRM-WA-29). Ausente: o modo atual (manual em produto novo).
            'registration_check_mode' => ['sometimes', 'string', Rule::in(array_keys(ProspectProduct::REGISTRATION_CHECK_MODES))],
            'registration_api_url' => [
                'nullable',
                'required_if:registration_check_mode,api',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && ($error = RegistrationApiUrl::formatError((string) $value)) !== null) {
                        $fail($error);
                    }
                },
            ],
            'registration_lookup_field' => ['nullable', 'required_if:registration_check_mode,api', 'string', Rule::in(array_keys(ProspectProduct::REGISTRATION_LOOKUP_FIELDS))],
            // Vazio mantém o token salvo; o token nunca volta para a tela.
            'registration_api_token' => ['nullable', Rule::requiredIf(fn () => $this->input('registration_check_mode') === 'api' && ! $hasToken), 'string', 'max:2000'],
            'registration_api_token_clear' => ['boolean'],
        ];
    }

    /**
     * Dados para gravar: o token só entra quando foi digitado (troca) ou quando pediram para
     * removê-lo; em branco, o token salvo fica como está.
     *
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        $data = collect($this->validated())->except(['registration_api_token', 'registration_api_token_clear'])->all();

        if (filled($this->validated('registration_api_token'))) {
            $data['registration_api_token'] = trim((string) $this->validated('registration_api_token'));
        } elseif ($this->boolean('registration_api_token_clear')) {
            $data['registration_api_token'] = null;
        }

        if (array_key_exists('registration_api_url', $data)) {
            $data['registration_api_url'] = filled($data['registration_api_url']) ? trim((string) $data['registration_api_url']) : null;
        }

        return $data;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'trial_days' => 'dias de teste',
            'whatsapp_text' => 'texto WhatsApp',
            'trial_expiration_text' => 'texto de vencimento do teste',
            'active' => 'ativo',
            'registration_check_mode' => 'acompanhamento de cadastro',
            'registration_api_url' => 'URL da API',
            'registration_lookup_field' => 'identificador',
            'registration_api_token' => 'token da API',
        ];
    }
}
