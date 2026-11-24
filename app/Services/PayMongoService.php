<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayMongoService
{
    /**
     * Create a PayMongo Hosted Checkout Session restricted to QR Ph only.
     *
     * @return array{id: string, checkout_url: string, payment_intent_id: ?string}
     */
    public function createQrPhCheckoutSession(Order $order, string $successUrl, string $cancelUrl): array
    {
        $secretKey = $this->secretKey();
        $order->loadMissing('quotation.items');

        $lineItems = [];

        foreach ($order->quotation->items->where('is_available', true) as $item) {
            if ($item->line_selling_centavos <= 0) {
                continue;
            }

            $qty = rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.');
            $lineItems[] = [
                'name' => "{$item->product_name} ({$qty} {$item->unit})",
                'amount' => (int) $item->line_selling_centavos,
                'currency' => 'PHP',
                'quantity' => 1,
            ];
        }

        if ($order->delivery_centavos > 0) {
            $lineItems[] = [
                'name' => "Delivery to {$order->delivery_location}",
                'amount' => (int) $order->delivery_centavos,
                'currency' => 'PHP',
                'quantity' => 1,
            ];
        }

        if ($lineItems === []) {
            throw new RuntimeException('Order has no payable line items.');
        }

        $response = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->post($this->baseUrl().'/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name' => $order->customer_name,
                            'email' => $order->customer_email,
                            'phone' => $order->customer_phone,
                        ],
                        'description' => "IslaSource Seafoods Order {$order->reference}",
                        'line_items' => $lineItems,
                        'payment_method_types' => ['qrph'],
                        'reference_number' => $order->reference,
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                        'metadata' => [
                            'order_id' => (string) $order->id,
                            'order_reference' => $order->reference,
                            'quotation_reference' => $order->quotation->reference,
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            $detail = (string) ($response->json('errors.0.detail') ?? 'Unable to create PayMongo QR Ph checkout session.');
            throw new RuntimeException($detail);
        }

        $id = $response->json('data.id');
        $checkoutUrl = $response->json('data.attributes.checkout_url');
        $paymentIntentId = $response->json('data.attributes.payment_intent.id');

        if (! is_string($id) || $id === '' || ! is_string($checkoutUrl) || $checkoutUrl === '') {
            throw new RuntimeException('PayMongo returned an unexpected checkout session response.');
        }

        return [
            'id' => $id,
            'checkout_url' => $checkoutUrl,
            'payment_intent_id' => is_string($paymentIntentId) && $paymentIntentId !== '' ? $paymentIntentId : null,
        ];
    }

    /** @return array<string, mixed> */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        $response = Http::withBasicAuth($this->secretKey(), '')
            ->acceptJson()
            ->timeout(15)
            ->get($this->baseUrl().'/checkout_sessions/'.urlencode($sessionId));

        if (! $response->successful()) {
            $detail = (string) ($response->json('errors.0.detail') ?? 'Unable to verify PayMongo checkout session.');
            throw new RuntimeException($detail);
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('PayMongo returned an unexpected checkout session payload.');
        }

        return $data;
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        $webhookSecret = (string) config('services.paymongo.webhook_secret', '');

        if ($webhookSecret === '' || ! is_string($signatureHeader) || trim($signatureHeader) === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signatureHeader) as $segment) {
            $pair = explode('=', trim($segment), 2);

            if (count($pair) === 2) {
                $parts[trim($pair[0])] = trim($pair[1]);
            }
        }

        $timestamp = $parts['t'] ?? '';
        $isLive = str_starts_with((string) config('services.paymongo.secret_key', ''), 'sk_live_');
        $expectedKey = $isLive ? 'li' : 'te';
        $receivedSignature = $parts[$expectedKey] ?? '';

        if ($timestamp === '' || $receivedSignature === '') {
            return false;
        }

        $computedSignature = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $webhookSecret);

        return hash_equals($computedSignature, $receivedSignature);
    }

    private function secretKey(): string
    {
        $secretKey = (string) config('services.paymongo.secret_key', '');

        if ($secretKey === '') {
            throw new RuntimeException('PayMongo secret key is not configured.');
        }

        return $secretKey;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.paymongo.base_url', 'https://api.paymongo.com/v1'), '/');
    }
}
