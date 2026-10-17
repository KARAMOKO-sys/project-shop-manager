<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\OrderService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FlutterwavePaymentService
{
    public function __construct(private OrderService $orders) {}

    public function startCheckout(Order $order): string
    {
        $order->loadMissing('shop', 'customer');
        $shop = $order->shop;

        if (! $shop->hasActiveFlutterwaveConfiguration()) {
            throw new FlutterwavePaymentException('Flutterwave is not configured for this shop.', 422);
        }

        $payment = $order->payment()->create([
            'provider' => 'flutterwave',
            'status' => 'pending',
            'amount' => $order->total,
            'currency' => $order->currency,
            'transaction_reference' => $order->order_number,
        ]);

        try {
            $response = Http::withToken($shop->flutterwave_secret_key)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->post(rtrim((string) config('services.flutterwave.base_url'), '/').'/payments', [
                    'tx_ref' => $order->order_number,
                    'amount' => (float) $order->total,
                    'currency' => $order->currency,
                    'redirect_url' => route('v1.payments.flutterwave.callback'),
                    'customer' => [
                        'email' => $order->customer->email,
                        'name' => trim($order->customer->first_name.' '.$order->customer->last_name),
                        'phonenumber' => $order->customer->phone,
                    ],
                    'customizations' => [
                        'title' => $shop->name,
                        'description' => "Commande {$order->order_number}",
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $payment->update(['status' => 'failed']);

            throw new FlutterwavePaymentException('Flutterwave could not be reached.', previous: $exception);
        }

        if (! $response->successful()) {
            $payment->update(['status' => 'failed']);

            throw new FlutterwavePaymentException('Flutterwave rejected the checkout request.');
        }

        $checkoutUrl = $response->json('data.link');
        $checkoutHost = is_string($checkoutUrl) ? parse_url($checkoutUrl, PHP_URL_HOST) : null;

        if (
            ! is_string($checkoutUrl)
            || ! filter_var($checkoutUrl, FILTER_VALIDATE_URL)
            || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https'
            || ! is_string($checkoutHost)
            || ($checkoutHost !== 'flutterwave.com' && ! str_ends_with($checkoutHost, '.flutterwave.com'))
        ) {
            $payment->update(['status' => 'failed']);

            throw new FlutterwavePaymentException('Flutterwave returned an invalid checkout link.');
        }

        return $checkoutUrl;
    }

    public function handleWebhook(string $transactionReference, string $transactionId, ?string $signature): Payment
    {
        $payment = $this->findPayment($transactionReference);
        $secretHash = $payment->order->shop->flutterwave_webhook_secret_hash;

        abort_unless(
            filled($signature) && filled($secretHash) && hash_equals($secretHash, $signature),
            401,
        );

        return $this->processTransaction($payment, $transactionId);
    }

    public function processCallback(string $transactionReference, string $transactionId): Payment
    {
        return $this->processTransaction($this->findPayment($transactionReference), $transactionId);
    }

    private function processTransaction(Payment $payment, string $transactionId): Payment
    {
        $shop = $payment->order->shop;
        $verified = $this->verifyTransaction($shop, $transactionId);

        if (
            (string) ($verified['id'] ?? '') !== $transactionId
            || (string) ($verified['tx_ref'] ?? '') !== $payment->transaction_reference
            || strtoupper((string) ($verified['currency'] ?? '')) !== strtoupper($payment->currency)
            || ! is_numeric($verified['amount'] ?? null)
            || number_format((float) $verified['amount'], 2, '.', '') !== number_format((float) $payment->amount, 2, '.', '')
        ) {
            throw new FlutterwavePaymentException('The verified transaction does not match the order.', 422);
        }

        $transactionStatus = strtolower((string) ($verified['status'] ?? ''));

        if (! in_array($transactionStatus, ['successful', 'failed', 'cancelled'], true)) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $transactionId, $transactionStatus): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status !== 'pending') {
                return $lockedPayment->load('order.shop', 'order.customer', 'order.items');
            }

            $order = Order::query()
                ->with('shop', 'customer', 'items')
                ->lockForUpdate()
                ->findOrFail($lockedPayment->order_id);

            if ($transactionStatus === 'successful') {
                if ($order->status === OrderStatus::Cancelled) {
                    throw new FlutterwavePaymentException('A cancelled order cannot be marked as paid.', 409);
                }

                $lockedPayment->update([
                    'status' => 'paid',
                    'provider_transaction_id' => $transactionId,
                ]);

                if ($order->status !== OrderStatus::Paid) {
                    $this->orders->changeStatus($order, OrderStatus::Paid, 'paid');
                }
            } else {
                $lockedPayment->update([
                    'status' => 'failed',
                    'provider_transaction_id' => $transactionId,
                ]);

                if ($order->status->canTransitionTo(OrderStatus::Cancelled)) {
                    $this->orders->changeStatus($order, OrderStatus::Cancelled, 'failed');
                }
            }

            return $lockedPayment->fresh(['order.shop', 'order.customer', 'order.items']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyTransaction(Shop $shop, string $transactionId): array
    {
        if (! ctype_digit($transactionId) || ! $shop->hasActiveFlutterwaveConfiguration()) {
            throw new FlutterwavePaymentException('The transaction cannot be verified.', 422);
        }

        try {
            $response = Http::withToken($shop->flutterwave_secret_key)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->get(rtrim((string) config('services.flutterwave.base_url'), '/')."/transactions/{$transactionId}/verify");
        } catch (ConnectionException $exception) {
            throw new FlutterwavePaymentException('Flutterwave could not be reached for verification.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new FlutterwavePaymentException('Flutterwave could not verify the transaction.');
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new FlutterwavePaymentException('Flutterwave returned an invalid verification response.');
        }

        return $data;
    }

    private function findPayment(string $transactionReference): Payment
    {
        return Payment::query()
            ->where('provider', 'flutterwave')
            ->where('transaction_reference', $transactionReference)
            ->with('order.shop', 'order.customer', 'order.items')
            ->firstOrFail();
    }
}
