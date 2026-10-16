<?php

namespace App\Services;

use App\Models\Setting;

/**
 * WhatsApp institucional da empresa (atendimento): número, provedor e sessão do WAHA.
 *
 * Fica na tabela `settings` (chave/valor). Não guarda segredos: a API key do WAHA e a chave
 * HMAC do webhook ficam só no ambiente do servidor. A sessão nunca é fixada no código: o
 * WAHA é compartilhado com outras aplicações (VetorLeads), e cada uma usa a sua sessão. A
 * sugerida para o site é `abrasil-atendimento`.
 */
class CompanyWhatsappSettings
{
    public const PROVIDER_WAHA = 'waha';

    public const PROVIDERS = [self::PROVIDER_WAHA];

    /** Nome de sessão aceito pelo WAHA (ex.: abrasil-atendimento). */
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
            'configured' => $values->has(Setting::COMPANY_WHATSAPP_ENABLED),
            'enabled' => $values->get(Setting::COMPANY_WHATSAPP_ENABLED) === '1',
            'number' => $values->get(Setting::COMPANY_WHATSAPP_NUMBER) ?: null,
            'provider' => $values->get(Setting::COMPANY_WHATSAPP_PROVIDER) ?: null,
            'session' => $values->get(Setting::COMPANY_WHATSAPP_SESSION) ?: null,
        ];
    }

    /**
     * Normaliza um número de WhatsApp para o formato do WAHA (55 + DDD + número), só com dígitos.
     * Retorna null quando não pode ser um celular/fixo brasileiro válido.
     */
    public static function normalizeNumber(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        // DDD + número (10 ou 11 dígitos): acrescenta o código do país.
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return '55'.$digits;
        }

        if ((strlen($digits) === 12 || strlen($digits) === 13) && str_starts_with($digits, '55')) {
            return $digits;
        }

        return null;
    }

    /**
     * @param  array{enabled: bool, number: string|null, provider: string, session: string|null}  $data
     */
    public function save(array $data): void
    {
        $values = [
            Setting::COMPANY_WHATSAPP_ENABLED => $data['enabled'] ? '1' : '0',
            Setting::COMPANY_WHATSAPP_NUMBER => self::normalizeNumber($data['number']),
            Setting::COMPANY_WHATSAPP_PROVIDER => $data['provider'],
            Setting::COMPANY_WHATSAPP_SESSION => filled($data['session']) ? trim($data['session']) : null,
        ];

        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
