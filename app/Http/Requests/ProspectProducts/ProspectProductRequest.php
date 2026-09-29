<?php

namespace App\Http\Requests\ProspectProducts;

use App\Http\Requests\Leads\SendLeadWhatsappRequest;
use Illuminate\Foundation\Http\FormRequest;

class ProspectProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // max: teto técnico da coluna unsignedInteger (MySQL), não regra comercial.
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            // Mesmo limite do envio manual pela conversa (4000).
            'whatsapp_text' => ['nullable', 'string', 'max:'.SendLeadWhatsappRequest::MESSAGE_MAX_LENGTH],
            'trial_expiration_text' => ['nullable', 'string', 'max:5000'],
            'active' => ['boolean'],
        ];
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
        ];
    }
}
