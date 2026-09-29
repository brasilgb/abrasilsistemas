<?php

namespace App\Http\Requests\Leads;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadActivityRequest extends FormRequest
{
    /**
     * O que a atividade faz com o próximo contato do lead. Sem ação informada, uma data
     * preenchida altera e campo vazio mantém: remover exige `clear` explícito.
     */
    public const FOLLOW_UP_ACTIONS = ['keep', 'set', 'clear'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(LeadActivity::TYPES))],
            'status' => ['nullable', 'string', Rule::in(array_keys(Lead::STATUSES))],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', Rule::in(array_keys(Lead::LOST_REASONS))],
            'contacted_at' => ['nullable', 'date'],
            'follow_up_action' => ['nullable', 'string', Rule::in(self::FOLLOW_UP_ACTIONS)],
            'next_follow_up_at' => ['nullable', 'required_if:follow_up_action,set', 'date'],
            'description' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'next_follow_up_at.required_if' => 'Informe a data e hora do novo próximo contato.',
        ];
    }

    public function followUpAction(): string
    {
        return $this->validated('follow_up_action')
            ?? ($this->validated('next_follow_up_at') !== null ? 'set' : 'keep');
    }
}
