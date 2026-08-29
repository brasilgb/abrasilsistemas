<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublicContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'whatsapp' => $this->filled('whatsapp') ? (preg_replace('/\D+/', '', (string) $this->input('whatsapp')) ?? '') : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'product' => ['required', 'string', Rule::in(array_keys(Lead::PRODUCTS))],
            'notes' => ['required', 'string', 'max:5000'],
            // Honeypot: real visitors never fill this hidden field.
            'website_hp' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_name.required' => 'Informe seu nome.',
            'company_name.required' => 'Informe o nome da empresa.',
            'product.required' => 'Selecione o serviço de interesse.',
            'notes.required' => 'Escreva uma mensagem.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('whatsapp') && ! $this->filled('email')) {
                $validator->errors()->add('email', 'Informe um e-mail ou WhatsApp para contato.');
            }
        });
    }
}
