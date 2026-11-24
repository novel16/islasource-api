<?php

use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Rfq;
use App\Services\PayMongoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

it('creates a PayMongo QR Ph only hosted checkout session with exact line item centavos', function () {
    config()->set('services.paymongo', [
        'public_key' => 'pk_test_123456',
        'secret_key' => 'sk_test_123456',
        'webhook_secret' => 'whsk_test_secret',
        'base_url' => 'https://api.paymongo.com/v1',
    ]);

    $rfq = Rfq::factory()->create([
        'customer_name' => 'Maia Reyes',
        'customer_email' => 'maia@example.com',
        'customer_phone' => '+63 917 555 0148',
        'delivery_location' => 'Cebu City',
    ]);
    $quotation = Quotation::factory()->for($rfq)->create([
        'reference' => 'QT-2026-TEST0001',
        'status' => 'Accepted',
        'subtotal_centavos' => 150000,
        'delivery_centavos' => 25000,
        'total_centavos' => 175000,
    ]);
    QuotationItem::factory()->for($quotation)->create([
        'product_name' => 'Danggit Buwad (Dried)',
        'unit' => 'kg',
        'quantity' => '2.500',
        'is_available' => true,
        'line_selling_centavos' => 150000,
    ]);
    QuotationItem::factory()->for($quotation)->create([
        'product_name' => 'Unavailable Crab',
        'unit' => 'kg',
        'quantity' => '1.000',
        'is_available' => false,
        'line_selling_centavos' => 0,
    ]);
    $order = Order::factory()->for($quotation)->for($rfq)->create([
        'reference' => 'ORD-2026-TEST0001',
        'customer_name' => 'Maia Reyes',
        'customer_email' => 'maia@example.com',
        'customer_phone' => '+63 917 555 0148',
        'delivery_location' => 'Cebu City',
        'subtotal_centavos' => 150000,
        'delivery_centavos' => 25000,
        'total_centavos' => 175000,
    ]);

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_test_abc123',
                'type' => 'checkout_session',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/cs_test_abc123',
                    'payment_intent' => ['id' => 'pi_test_xyz789'],
                    'status' => 'active',
                ],
            ],
        ], 200),
    ]);

    $result = app(PayMongoService::class)->createQrPhCheckoutSession(
        $order->load('quotation.items'),
        'http://localhost:3000/order/tok?payment=success',
        'http://localhost:3000/order/tok?payment=cancelled',
    );

    expect($result)->toBe([
        'id' => 'cs_test_abc123',
        'checkout_url' => 'https://checkout.paymongo.com/cs_test_abc123',
        'payment_intent_id' => 'pi_test_xyz789',
    ]);

    Http::assertSent(function (Request $request): bool {
        $attributes = $request->data()['data']['attributes'] ?? [];
        $lineItems = $attributes['line_items'] ?? [];

        return $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_123456:'))
            && $attributes['payment_method_types'] === ['qrph']
            && $attributes['reference_number'] === 'ORD-2026-TEST0001'
            && count($lineItems) === 2
            && $lineItems[0]['amount'] === 150000
            && $lineItems[0]['quantity'] === 1
            && $lineItems[0]['currency'] === 'PHP'
            && str_contains($lineItems[0]['name'], 'Danggit Buwad (Dried)')
            && $lineItems[1]['amount'] === 25000
            && $lineItems[1]['quantity'] === 1;
    });
});

it('verifies PayMongo HMAC-SHA256 webhook signatures using te for test keys and li for live keys', function () {
    $rawBody = '{"data":{"id":"evt_1","attributes":{"type":"checkout_session.payment.paid"}}}';
    $timestamp = '1732412400';
    $secret = 'whsk_test_signing_secret';
    $validSig = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

    config()->set('services.paymongo', [
        'secret_key' => 'sk_test_123456',
        'webhook_secret' => $secret,
    ]);
    $service = app(PayMongoService::class);

    expect($service->verifyWebhookSignature($rawBody, "t={$timestamp},te={$validSig},li="))->toBeTrue();
    expect($service->verifyWebhookSignature($rawBody, "t={$timestamp},te=wrongsig,li={$validSig}"))->toBeFalse();
    expect($service->verifyWebhookSignature($rawBody, null))->toBeFalse();

    config()->set('services.paymongo.secret_key', 'sk_live_999999');
    expect($service->verifyWebhookSignature($rawBody, "t={$timestamp},te=,li={$validSig}"))->toBeTrue();
    expect($service->verifyWebhookSignature($rawBody, "t={$timestamp},te={$validSig},li="))->toBeFalse();

    config()->set('services.paymongo.webhook_secret', '');
    expect($service->verifyWebhookSignature($rawBody, "t={$timestamp},te={$validSig},li={$validSig}"))->toBeFalse();
});
