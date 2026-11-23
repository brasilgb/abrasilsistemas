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
            // Só caminhos do próprio site ("/automacao-de-processos"); qualquer outra coisa é descartada
            // em vez de recusar o contato do visitante.
            'source_page' => is_string($page = $this->input('source_page')) && preg_match('#^/(?!/)\S{0,254}$#', $page) === 1 ? $page : null,
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
            'contact_name' => ['required', 'string', 'max:255', 'not_regex:#https?://|www\.#i'],
            'company_name' => ['nullable', 'string', 'max:255'],
            // Só dígitos (prepareForValidation): DDD + número, com ou sem o código do país.
            'whatsapp' => ['nullable', 'string', 'regex:/^\d{10,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'product' => ['required', 'string', Rule::in(array_keys(Lead::CONTACT_PROJECT_TYPES))],
            'notes' => ['required', 'string', 'max:5000', $this->rejectLinkSpam(...)],
            'marketing_consent' => ['nullable', 'boolean'],
            // Página do site de onde a pessoa veio (caminho relativo, enviado pelo formulário).
            'source_page' => ['nullable', 'string', 'max:255'],
            // Honeypot: real visitors never fill this hidden field.
            'website_hp' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_name.required' => 'Informe seu nome.',
            'contact_name.not_regex' => 'Informe apenas o seu nome.',
            'whatsapp.regex' => 'Informe o telefone com DDD, só números.',
            'email.email' => 'Informe um e-mail válido.',
            'product.required' => 'Selecione o serviço de interesse.',
            'product.in' => 'Selecione um serviço da lista.',
            'notes.required' => 'Descreva a necessidade da sua empresa.',
        ];
    }

    /** Mensagens de robôs costumam ser listas de links; um pedido real raramente passa de três. */
    private function rejectLinkSpam(string $attribute, mixed $value, \Closure $fail): void
    {
        if (is_string($value) && preg_match_all('#https?://|www\.#i', $value) > 3) {
            $fail('A mensagem tem links demais. Descreva a necessidade e envie no máximo três links.');
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('whatsapp') && ! $this->filled('email')) {
                $validator->errors()->add('email', 'Informe um e-mail ou telefone para contato.');
            }
        });
    }
}
