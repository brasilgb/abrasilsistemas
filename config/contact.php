<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Contato público da ABrasil Sistemas
    |--------------------------------------------------------------------------
    |
    | Centraliza o número de WhatsApp e o e-mail exibidos no site público,
    | para não ficarem espalhados (hardcoded) em vários componentes React.
    |
    */

    'whatsapp_number' => env('CONTACT_WHATSAPP_NUMBER', '5551998931325'),

    'whatsapp_display' => env('CONTACT_WHATSAPP_DISPLAY', '(51) 99893-1325'),

    'email' => env('CONTACT_EMAIL', 'contato@abrasilsistemas.com.br'),
];
