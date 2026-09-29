<?php

namespace App\Http\Requests\Api;

use App\Models\LeadActivity;
use App\Models\LeadAutomationExecution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogWhatsappActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('direction'))) {
            $this->merge(['direction' => strtolower(trim($this->input('direction')))]);
        }
    }

    public function rules(): array
    {
        return [
            'message_id' => ['nullable', 'string', 'max:255'],
            // Opcional: o workflow de envio do n8n não manda direction, e ausência significa outbound.
            'direction' => ['nullable', 'string', Rule::in(LeadActivity::DIRECTIONS)],
            // Mensagem recebida não tem ACK de entrega; o status só é exigido para outbound.
            'status' => ['nullable', 'required_unless:direction,'.LeadActivity::DIRECTION_INBOUND, 'string', 'max:50'],
            'message' => ['required', 'string', 'max:5000'],
            // CRM-WA-28: envio feito por uma automação reservada (ex.: trial_expired:2026-10-05).
            // Só outbound; vincula a mensagem à execução do lead com esta chave.
            'automation_key' => [
                'nullable',
                'prohibited_if:direction,'.LeadActivity::DIRECTION_INBOUND,
                'string',
                'max:'.LeadAutomationExecution::KEY_MAX_LENGTH,
                'regex:'.LeadAutomationExecution::KEY_PATTERN,
            ],
        ];
    }
}
