<?php

namespace App\Http\Requests\Settings;

use App\Services\CompanyWhatsappSettings;
use App\Services\LeadWhatsappService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyWhatsappRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $enabled = fn (): bool => $this->boolean('enabled');

        return [
            'enabled' => ['required', 'boolean'],
            'provider' => ['required', 'string', Rule::in(CompanyWhatsappSettings::PROVIDERS)],
            'number' => [
                Rule::requiredIf($enabled),
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (LeadWhatsappService::normalizeNumber($value) === null) {
                        $fail('Informe o WhatsApp da empresa com DDD, ex.: (51) 99999-8888.');
                    }
                },
            ],
            'session' => [
                Rule::requiredIf($enabled),
                'nullable',
                'string',
                'regex:'.CompanyWhatsappSettings::SESSION_PATTERN,
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'enabled.required' => 'Informe se a integração está habilitada.',
            'enabled.boolean' => 'Informe se a integração está habilitada.',
            'provider.required' => 'Selecione o provedor de WhatsApp.',
            'provider.in' => 'Provedor de WhatsApp não suportado.',
            'number.required' => 'Informe o WhatsApp da empresa para habilitar a integração.',
            'number.max' => 'O WhatsApp da empresa deve ter no máximo 30 caracteres.',
            'session.required' => 'Informe a sessão do WAHA para habilitar a integração.',
            'session.regex' => 'A sessão deve ter até 64 caracteres: letras, números, hífen ou sublinhado.',
        ];
    }
}
