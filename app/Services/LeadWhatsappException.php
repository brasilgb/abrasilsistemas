<?php

namespace App\Services;

use RuntimeException;

/**
 * Falha no envio manual de WhatsApp. A mensagem é amigável e pode ser
 * exibida ao operador; detalhes técnicos ficam apenas no log.
 */
class LeadWhatsappException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'whatsapp')
    {
        parent::__construct($message);
    }
}
