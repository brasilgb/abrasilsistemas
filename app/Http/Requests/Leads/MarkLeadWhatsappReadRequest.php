<?php

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;

class MarkLeadWhatsappReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'up_to_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'up_to_id.required' => 'Informe até qual mensagem marcar como lida.',
            'up_to_id.integer' => 'A mensagem informada é inválida.',
            'up_to_id.min' => 'A mensagem informada é inválida.',
        ];
    }
}
