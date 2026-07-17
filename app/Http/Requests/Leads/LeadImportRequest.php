<?php

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;

class LeadImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv.required' => 'Selecione um arquivo CSV para importar.',
            'csv.file' => 'O arquivo enviado nao e valido.',
            'csv.mimes' => 'Envie um arquivo CSV.',
            'csv.max' => 'O CSV deve ter no maximo 5 MB.',
        ];
    }
}
