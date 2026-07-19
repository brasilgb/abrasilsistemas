<?php

namespace App\Http\Controllers;

use App\Models\Ebook;
use App\Models\EbookOrder;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use MercadoPago\Exceptions\MPApiException;
use Throwable;

class EbookPixCheckoutController extends Controller
{
    public function __construct(private readonly MercadoPagoService $mercadoPago) {}

    public function __invoke(Request $request, Ebook $ebook): Response
    {
        $data = $request->validate([
            'document' => ['required', 'string', 'regex:/^(\d{11}|\d{14})$/'],
        ], [
            'document.regex' => 'Informe um CPF ou CNPJ válido, somente com números.',
        ]);

        abort_unless($ebook->status === 'published' && $ebook->price_cents !== null && $ebook->price_cents > 0, 404);
        abort_if(! config('services.mercadopago.token') || ! config('services.mercadopago.webhook_token'), 503, 'Pagamento temporariamente indisponível.');

        $user = $request->user();
        $idempotencyKey = (string) Str::uuid();
        $order = EbookOrder::query()->create([
            'user_id' => $user->id,
            'ebook_id' => $ebook->id,
            'amount_cents' => $ebook->price_cents,
            'currency' => $ebook->currency,
            'status' => 'pending',
            'payment_provider' => 'mercadopago',
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            $payment = $this->mercadoPago->createPixPayment([
                'transaction_amount' => $ebook->price_cents / 100,
                'description' => 'E-book '.$ebook->title,
                'payment_method_id' => 'pix',
                'payer' => [
                    'email' => $user->email,
                    'first_name' => str($user->name)->before(' ')->toString(),
                    'identification' => [
                        'type' => strlen($data['document']) === 14 ? 'CNPJ' : 'CPF',
                        'number' => $data['document'],
                    ],
                ],
                'external_reference' => json_encode([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'ebook_id' => $ebook->id,
                ], JSON_THROW_ON_ERROR),
                'notification_url' => str_replace('http://', 'https://', route('webhooks.mercadopago.ebooks', ['token' => config('services.mercadopago.webhook_token')])),
            ], $idempotencyKey);

            $order->update([
                'external_reference' => (string) $payment->external_reference,
                'payment_id' => (string) $payment->id,
                'status' => (string) $payment->status,
                'expires_at' => $payment->date_of_expiration,
                'raw_response' => json_decode(json_encode($payment, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR),
            ]);

            return Inertia::render('knowledge/checkout', [
                'ebook' => $ebook->only(['id', 'title', 'volume_slug', 'price_cents', 'currency']),
                'order' => $order->only(['id', 'status', 'expires_at']),
                'pix' => [
                    'qr_code' => data_get($payment, 'point_of_interaction.transaction_data.qr_code_base64'),
                    'copy_paste' => data_get($payment, 'point_of_interaction.transaction_data.qr_code'),
                ],
            ]);
        } catch (MPApiException $exception) {
            $order->update(['status' => 'failed']);
            Log::error('Falha do Mercado Pago ao gerar PIX do e-book.', [
                'order_id' => $order->id,
                'response' => $exception->getApiResponse()->getContent(),
            ]);

            abort(502, 'Não foi possível gerar o PIX. Tente novamente.');
        } catch (Throwable $exception) {
            $order->update(['status' => 'failed']);
            report($exception);

            abort(500, 'Não foi possível iniciar o pagamento.');
        }
    }
}
