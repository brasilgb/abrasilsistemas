<?php

namespace App\Http\Requests\Api;

use App\Services\CompanyWhatsappConnection;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWhatsappExternalOutboundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session' => ['required', 'string', 'regex:'.CompanyWhatsappSettings::SESSION_PATTERN],
            // Id curto do WAHA (_data.key.id), o mesmo gravado pelo /log e usado pelo ACK.
            'provider_message_id' => ['required', 'string', 'max:255'],
            // Telefone ou JID de telefone (@c.us / @s.whatsapp.net). Endereçamento por LID deve chegar
            // já resolvido pelo n8n (remoteJidAlt); @lid, grupos, broadcast e newsletter são recusados.
            'to' => ['required', 'string', 'max:64', 'regex:/^\+?[\d\s().-]+(@(c\.us|s\.whatsapp\.net))?$/i'],
            // Só texto nesta etapa.
            'message' => ['required', 'string', 'max:5000'],
            // ackName do WAHA; um valor fora de LeadActivity::MESSAGE_STATUSES vira SERVER no controller.
            'status' => ['nullable', 'string', 'max:50'],
            'timestamp' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('to')) {
                    return;
                }

                $length = strlen($this->recipientNumber());

                if ($length < 10 || $length > 15) {
                    $validator->errors()->add('to', 'O destinatário não é um número de telefone válido.');
                }
            },
        ];
    }

    /**
     * Destinatário só com dígitos (55 + DDD + número, como vem do WAHA).
     */
    public function recipientNumber(): string
    {
        return CompanyWhatsappConnection::normalizeJid((string) $this->input('to')) ?? '';
    }
}
