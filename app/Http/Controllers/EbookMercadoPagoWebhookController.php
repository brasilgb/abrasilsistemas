<?php

namespace App\Http\Controllers;

use App\Models\EbookEntitlement;
use App\Models\EbookOrder;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EbookMercadoPagoWebhookController extends Controller
{
    public function __construct(private readonly MercadoPagoService $mercadoPago) {}

    public function __invoke(Request $request, string $token): JsonResponse
    {
        if (! hash_equals((string) config('services.mercadopago.webhook_token'), $token)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $type = $request->input('type') ?? $request->input('topic');

        if ($type !== 'payment') {
            return response()->json(['status' => 'ignored']);
        }

        $paymentId = $request->input('data.id') ?? $request->input('id');

        if (! is_numeric($paymentId)) {
            return response()->json(['error' => 'Payment ID missing'], 400);
        }

        try {
            $payment = $this->mercadoPago->getPayment((int) $paymentId);
            $reference = json_decode((string) $payment->external_reference, true);

            if (! is_array($reference) || ! isset($reference['order_id'], $reference['user_id'], $reference['ebook_id'])) {
                return response()->json(['error' => 'Invalid reference'], 422);
            }

            DB::transaction(function () use ($payment, $reference): void {
                $orderId = (int) $reference['order_id'];
                $order = EbookOrder::query()
                    ->with('ebook:id,price_cents')
                    ->lockForUpdate()
                    ->whereKey($orderId)
                    ->first();

                if (! $order
                    || $order->user_id !== (int) $reference['user_id']
                    || $order->ebook_id !== (int) $reference['ebook_id']
                    || $order->amount_cents !== (int) round(((float) $payment->transaction_amount) * 100)) {
                    throw new \RuntimeException('Pagamento não corresponde ao pedido informado.');
                }

                $order->update([
                    'payment_id' => (string) $payment->id,
                    'status' => (string) $payment->status,
                    'paid_at' => $payment->status === 'approved' ? ($payment->date_approved ?? now()) : null,
                    'expires_at' => $payment->date_of_expiration,
                    'raw_response' => json_decode(json_encode($payment, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR),
                ]);

                if ($payment->status === 'approved') {
                    EbookEntitlement::query()->firstOrCreate(
                        ['user_id' => $order->user_id, 'ebook_id' => $order->ebook_id],
                        ['ebook_order_id' => $order->id, 'granted_at' => now()],
                    );
                }
            });

            return response()->json(['status' => 'processed']);
        } catch (Throwable $exception) {
            Log::error('Falha ao processar webhook de e-book do Mercado Pago.', [
                'payment_id' => $paymentId,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
}
