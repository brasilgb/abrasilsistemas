<?php

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;

class SendLeadWhatsappRequest extends FormRequest
{
    public const MESSAGE_MAX_LENGTH = 4000;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'message.required' => 'Escreva a mensagem que será enviada.',
            'message.string' => 'A mensagem deve ser um texto.',
            'message.max' => 'A mensagem deve ter no máximo '.self::MESSAGE_MAX_LENGTH.' caracteres.',
        ];
    }
}
