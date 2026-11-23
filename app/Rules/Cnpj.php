<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** CNPJ numérico ou alfanumérico (formato emitido a partir de julho de 2026), com ou sem pontuação. */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('Informe um CNPJ válido.');
        }
    }

    public static function normalize(string $value): string
    {
        return strtoupper(preg_replace('/[.\/\-\s]/', '', $value) ?? '');
    }

    public static function format(string $value): string
    {
        $cnpj = self::normalize($value);

        return strlen($cnpj) === 14 ? preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $cnpj) ?? $cnpj : $cnpj;
    }

    public static function isValid(string $value): bool
    {
        $cnpj = self::normalize($value);

        if (preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj) !== 1 || preg_match('/^(.)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        return substr($cnpj, -2) === self::digit(substr($cnpj, 0, 12)).self::digit(substr($cnpj, 0, 13));
    }

    /** Módulo 11 com pesos de 2 a 9; cada caractere vale o código ASCII menos 48. */
    private static function digit(string $base): string
    {
        $sum = 0;

        foreach (array_reverse(str_split($base)) as $index => $char) {
            $sum += (ord($char) - 48) * (2 + $index % 8);
        }

        $rest = $sum % 11;

        return (string) ($rest < 2 ? 0 : 11 - $rest);
    }
}
