<?php

namespace App\Http\Requests\Api;

use App\Services\CompanyWhatsappConnection;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWhatsappInboundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session' => ['required', 'string', 'regex:'.CompanyWhatsappSettings::SESSION_PATTERN],
            'provider_message_id' => ['required', 'string', 'max:255'],
            // Telefone ou JID de telefone (@c.us / @s.whatsapp.net). JID @lid não é telefone e é recusado.
            'from' => ['required', 'string', 'max:64', 'regex:/^\+?[\d\s().-]+(@(c\.us|s\.whatsapp\.net))?$/i'],
            'push_name' => ['nullable', 'string', 'max:255'],
            // Só texto nesta etapa; mídia terá campos próprios quando for suportada.
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('from')) {
                    return;
                }

                $length = strlen($this->senderNumber());

                if ($length < 10 || $length > 15) {
                    $validator->errors()->add('from', 'O remetente não é um número de telefone válido.');
                }
            },
        ];
    }

    /**
     * Remetente só com dígitos (55 + DDD + número, como vem do WAHA).
     */
    public function senderNumber(): string
    {
        return CompanyWhatsappConnection::normalizeJid((string) $this->input('from')) ?? '';
    }
}
