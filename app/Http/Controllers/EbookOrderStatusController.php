<?php

namespace App\Http\Controllers;

use App\Models\EbookOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EbookOrderStatusController extends Controller
{
    public function __invoke(Request $request, EbookOrder $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return response()->json([
            'status' => $order->status,
            'paid' => $order->status === 'approved',
        ]);
    }
}
