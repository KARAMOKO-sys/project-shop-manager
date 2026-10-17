<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\FlutterwavePaymentException;
use App\Services\Payments\FlutterwavePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FlutterwavePaymentController extends Controller
{
    public function webhook(Request $request, FlutterwavePaymentService $payments): JsonResponse
    {
        $payload = $request->validate([
            'data.id' => ['required', 'integer', 'min:1'],
            'data.tx_ref' => ['required', 'string', 'max:255'],
        ]);

        try {
            $payment = $payments->handleWebhook(
                $payload['data']['tx_ref'],
                (string) $payload['data']['id'],
                $request->header('verif-hash'),
            );
        } catch (FlutterwavePaymentException $exception) {
            return response()->json([
                'message' => 'Le paiement Flutterwave n’a pas pu être vérifié.',
            ], $exception->httpStatus);
        }

        return response()->json(['payment_status' => $payment->status]);
    }

    public function callback(Request $request, FlutterwavePaymentService $payments): RedirectResponse
    {
        $transactionReference = $request->query('tx_ref');

        abort_unless(is_string($transactionReference) && $transactionReference !== '', 404);

        $payment = Payment::query()
            ->where('provider', 'flutterwave')
            ->where('transaction_reference', $transactionReference)
            ->with('order.shop')
            ->firstOrFail();
        $transactionId = $request->query('transaction_id');

        if (is_string($transactionId) && $transactionId !== '') {
            try {
                $payment = $payments->processCallback($transactionReference, $transactionId);
            } catch (FlutterwavePaymentException) {
                $payment->refresh();
            }
        }

        $trackingUrl = rtrim((string) config('shopai.frontend_url'), '/')
            ."/boutique/{$payment->order->shop->slug}/suivi?"
            .http_build_query(['commande' => $payment->order->order_number]);

        return redirect()->away($trackingUrl);
    }
}
