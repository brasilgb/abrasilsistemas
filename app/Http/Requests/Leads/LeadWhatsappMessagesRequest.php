<?php

namespace App\Http\Requests\Leads;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadWhatsappMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        /** @var Lead $lead */
        $lead = $this->route('lead');

        return [
            // Página anterior: mensagens mais antigas que esta, que precisa ser da conversa do lead da rota.
            'before_id' => [
                'nullable',
                'integer',
                'min:1',
                'prohibits:since',
                Rule::exists('lead_activities', 'id')->where(fn (Builder $query) => $query
                    ->where('lead_id', $lead->id)
                    ->where('type', 'whatsapp')
                    ->whereIn('direction', LeadActivity::DIRECTIONS)),
            ],
            // Polling incremental: o cursor devolvido pela consulta anterior (timestamp Unix).
            'since' => ['nullable', 'integer', 'min:0', 'prohibits:before_id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'before_id.integer' => 'A mensagem informada é inválida.',
            'before_id.min' => 'A mensagem informada é inválida.',
            'before_id.exists' => 'A mensagem informada não pertence a esta conversa.',
            'since.integer' => 'O cursor de atualização é inválido.',
            'since.min' => 'O cursor de atualização é inválido.',
        ];
    }
}
