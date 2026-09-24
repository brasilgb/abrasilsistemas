<?php

namespace App\Services;

use App\Models\Setting;

/**
 * WhatsApp da própria empresa (remetente das mensagens do CRM): número, provedor e sessão.
 *
 * Fica na tabela `settings` (chave/valor), a mesma do token de integração. Não guarda
 * segredos: a API key do WAHA e o token do webhook do n8n continuam no ambiente.
 * Não tem relação com o WhatsApp dos leads (destinatários), que fica em `leads.whatsapp`.
 */
class CompanyWhatsappSettings
{
    public const PROVIDER_WAHA = 'waha';

    public const PROVIDERS = [self::PROVIDER_WAHA];

    /** Nome de sessão aceito pelo WAHA (ex.: default, vetoros1-1). */
    public const SESSION_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    /**
     * @return array{configured: bool, enabled: bool, number: string|null, provider: string|null, session: string|null}
     */
    public function current(): array
    {
        $values = Setting::query()
            ->whereIn('key', [
                Setting::COMPANY_WHATSAPP_ENABLED,
                Setting::COMPANY_WHATSAPP_NUMBER,
                Setting::COMPANY_WHATSAPP_PROVIDER,
                Setting::COMPANY_WHATSAPP_SESSION,
            ])
            ->get()
            ->pluck('value', 'key');

        return [
            // Sem registro salvo, o envio segue o fluxo anterior (sessão definida no n8n).
            'configured' => $values->has(Setting::COMPANY_WHATSAPP_ENABLED),
            'enabled' => $values->get(Setting::COMPANY_WHATSAPP_ENABLED) === '1',
            'number' => $values->get(Setting::COMPANY_WHATSAPP_NUMBER) ?: null,
            'provider' => $values->get(Setting::COMPANY_WHATSAPP_PROVIDER) ?: null,
            'session' => $values->get(Setting::COMPANY_WHATSAPP_SESSION) ?: null,
        ];
    }

    /**
     * @param  array{enabled: bool, number: string|null, provider: string, session: string|null}  $data
     */
    public function save(array $data): void
    {
        $values = [
            Setting::COMPANY_WHATSAPP_ENABLED => $data['enabled'] ? '1' : '0',
            Setting::COMPANY_WHATSAPP_NUMBER => LeadWhatsappService::normalizeNumber($data['number']),
            Setting::COMPANY_WHATSAPP_PROVIDER => $data['provider'],
            Setting::COMPANY_WHATSAPP_SESSION => filled($data['session']) ? trim($data['session']) : null,
        ];

        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
