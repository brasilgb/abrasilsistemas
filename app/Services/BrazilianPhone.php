<?php

namespace App\Services;

/**
 * Tipo provável de linha de um telefone brasileiro, só pelo formato (CRM-LEAD-02). É um sinal,
 * não prova: celular não garante WhatsApp, mas fixo não deve ser presumido como WhatsApp.
 */
final class BrazilianPhone
{
    public const MOBILE = 'mobile';

    public const LANDLINE = 'landline';

    public const UNKNOWN = 'unknown';

    public const LINE_TYPES = [
        self::MOBILE => 'Celular provável',
        self::LANDLINE => 'Telefone fixo',
        self::UNKNOWN => 'Formato não reconhecido',
    ];

    /** null quando não há número; senão mobile, landline ou unknown. */
    public static function lineType(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        // 0800/0300/0500/0900 e afins: número de serviço, nunca um celular.
        if (str_starts_with($digits, '0')) {
            return self::LANDLINE;
        }

        if ((strlen($digits) === 12 || strlen($digits) === 13) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        $local = match (strlen($digits)) {
            10, 11 => (int) $digits[0] > 0 && (int) $digits[1] > 0 ? substr($digits, 2) : null,
            8, 9 => $digits,
            default => null,
        };

        if ($local === null) {
            return self::UNKNOWN;
        }

        // Celular: 9 + 8 dígitos. Com 8 dígitos, 6 a 9 é celular sem o nono dígito (grafia antiga)
        // e 2 a 5 é fixo; 4003/4004/3003… (sem DDD) também são fixos.
        if (strlen($local) === 9) {
            return $local[0] === '9' ? self::MOBILE : self::UNKNOWN;
        }

        return (int) $local[0] >= 6 ? self::MOBILE : ((int) $local[0] >= 2 ? self::LANDLINE : self::UNKNOWN);
    }

    public static function isProbableMobile(?string $value): bool
    {
        return self::lineType($value) === self::MOBILE;
    }
}
