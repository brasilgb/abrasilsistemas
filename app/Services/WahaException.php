<?php

namespace App\Services;

use RuntimeException;

/**
 * Falha ao falar com o WAHA ou operação de conexão não permitida no estado atual.
 * A mensagem é amigável e pode ir ao painel; detalhes técnicos ficam só no log.
 */
class WahaException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502)
    {
        parent::__construct($message);
    }

    public static function unavailable(): self
    {
        return new self('Não foi possível falar com o serviço de WhatsApp (WAHA). Tente novamente em instantes.');
    }
}
