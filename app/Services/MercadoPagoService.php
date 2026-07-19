<?php

namespace App\Services;

use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\Payment;

class MercadoPagoService
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken((string) config('services.mercadopago.token'));
    }

    /** @param array<string, mixed> $request */
    public function createPixPayment(array $request, string $idempotencyKey): Payment
    {
        $options = new RequestOptions;
        $options->setCustomHeaders(['x-idempotency-key' => $idempotencyKey]);

        return (new PaymentClient)->create($request, $options);
    }

    public function getPayment(int $paymentId): Payment
    {
        return (new PaymentClient)->get($paymentId);
    }
}
