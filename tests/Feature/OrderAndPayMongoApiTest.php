<?php

use App\Mail\OrderPaidAdminMail;
use App\Mail\OrderPaidCustomerMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function orderAdminToken(?User $admin = null): string
{
    $user = $admin ?? User::factory()->admin()->create();

    return $user->createToken('orders', ['admin'])->plainTextToken;
}

function payMongoSignature(string $rawBody, string $secret, string $timestamp = '1732412400'): string
{
    $sig = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

    return "t={$timestamp},te={$sig},li=";
}

it('automatically creates an unpaid full-payment order when a guest accepts a quotation', function () {
    $quoteToken = Str::random(64);
    $rfq = Rfq::factory()->create([
        'status' => 'Quoted',
        'customer_name' => 'Maia Reyes',
        'customer_email' => 'maia@example.com',
        'delivery_location' => 'Cebu City',
    ]);
    $quotation = Quotation::factory()->sent($quoteToken)->for($rfq)->create([
        'subtotal_centavos' => 324_000,
        'delivery_centavos' => 35_000,
        'total_centavos' => 359_000,
        'deposit_percent' => 100,
        'deposit_centavos' => 359_000,
        'balance_centavos' => 0,
    ]);
    QuotationItem::factory()->for($quotation)->create([
        'product_name' => 'Dried squid',
        'is_available' => true,
        'line_selling_centavos' => 324_000,
    ]);

    $accepted = $this->postJson('/api/v1/quotations/'.$quoteToken.'/accept', [
        'terms_accepted' => true,
    ])->assertOk()
        ->assertJsonPath('data.status', 'Accepted');

    $orderToken = $accepted->json('data.order_token');
    $orderReference = $accepted->json('data.order_reference');

    expect($orderToken)->toMatch('/^[A-Za-z0-9]{64}$/');
    expect($orderReference)->toStartWith('ORD-');
    $this->assertDatabaseHas('orders', [
        'quotation_id' => $quotation->id,
        'reference' => $orderReference,
        'status' => 'Awaiting Payment',
        'payment_status' => 'Unpaid',
        'total_centavos' => 359_000,
        'paid_centavos' => 0,
    ]);

    $publicOrder = $this->getJson('/api/v1/orders/'.$orderToken)
        ->assertOk()
        ->assertJsonPath('data.reference', $orderReference)
        ->assertJsonPath('data.status', 'Awaiting Payment')
        ->assertJsonPath('data.payment_status', 'Unpaid')
        ->assertJsonPath('data.total_centavos', 359_000);

    expect($publicOrder->headers->get('Cache-Control'))->toContain('no-store');
    expect($publicOrder->getContent())->not->toContain('maia@example.com');
    expect($publicOrder->getContent())->not->toContain('unit_cost_centavos');
});

it('creates a QR Ph hosted checkout session and processes a signed PayMongo webhook idempotently', function () {
    Mail::fake();
    config()->set('services.paymongo', [
        'public_key' => 'pk_test_123456',
        'secret_key' => 'sk_test_123456',
        'webhook_secret' => 'whsk_test_secret_123',
        'base_url' => 'https://api.paymongo.com/v1',
    ]);

    $activeAdmin = User::factory()->admin()->create(['email' => 'boss@islasource.test']);
    $inactiveAdmin = User::factory()->admin()->create(['email' => 'inactive@islasource.test', 'is_active' => false]);
    $nonAdmin = User::factory()->create(['email' => 'guest@islasource.test', 'is_admin' => false]);

    $orderToken = Str::random(64);
    $rfq = Rfq::factory()->create(['status' => 'Quoted']);
    $quotation = Quotation::factory()->for($rfq)->create([
        'status' => 'Accepted',
        'subtotal_centavos' => 200_000,
        'delivery_centavos' => 25_000,
        'total_centavos' => 225_000,
        'created_by' => $activeAdmin->id,
    ]);
    QuotationItem::factory()->for($quotation)->create([
        'product_name' => 'Alimasag (Blue Crabs)',
        'is_available' => true,
        'line_selling_centavos' => 200_000,
    ]);
    $order = Order::factory()->for($quotation)->for($rfq)->create([
        'customer_email' => 'buyer@example.com',
        'subtotal_centavos' => 200_000,
        'delivery_centavos' => 25_000,
        'total_centavos' => 225_000,
        'access_token_hash' => hash('sha256', $orderToken),
        'access_token_encrypted' => $orderToken,
    ]);

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_test_session_01',
                'type' => 'checkout_session',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/cs_test_session_01',
                    'payment_intent' => ['id' => 'pi_test_01'],
                    'status' => 'active',
                ],
            ],
        ], 200),
    ]);

    $this->postJson('/api/v1/orders/'.$orderToken.'/checkout')
        ->assertOk()
        ->assertJsonPath('data.checkout_url', 'https://checkout.paymongo.com/cs_test_session_01');

    $this->assertDatabaseHas('order_payments', [
        'order_id' => $order->id,
        'payment_method' => 'qrph',
        'status' => 'Pending',
        'paymongo_checkout_session_id' => 'cs_test_session_01',
    ]);

    $webhookBody = json_encode([
        'data' => [
            'id' => 'evt_test_01',
            'type' => 'event',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => [
                    'id' => 'cs_test_session_01',
                    'type' => 'checkout_session',
                    'attributes' => [
                        'metadata' => ['order_id' => (string) $order->id],
                        'payments' => [[
                            'id' => 'pay_test_qrph_01',
                            'type' => 'payment',
                            'attributes' => [
                                'amount' => 225_000,
                                'currency' => 'PHP',
                                'status' => 'paid',
                            ],
                        ]],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    // Invalid signature is rejected with 400.
    $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => 't=1732412400,te=invalid,li=',
    ], $webhookBody)->assertBadRequest();

    // Valid signed webhook marks order Paid + Processing and sends emails.
    $sig = payMongoSignature($webhookBody, 'whsk_test_secret_123');
    $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => $sig,
    ], $webhookBody)->assertOk()->assertJsonPath('received', true);

    // Duplicate delivery is strictly idempotent.
    $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => $sig,
    ], $webhookBody)->assertOk();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'Processing',
        'payment_status' => 'Paid',
        'paid_centavos' => 225_000,
    ]);
    $this->assertDatabaseHas('order_payments', [
        'order_id' => $order->id,
        'status' => 'Paid',
        'paymongo_payment_id' => 'pay_test_qrph_01',
    ]);

    Mail::assertSent(OrderPaidCustomerMail::class, 1);
    Mail::assertSent(OrderPaidCustomerMail::class, fn (OrderPaidCustomerMail $m): bool => $m->hasTo('buyer@example.com'));
    Mail::assertSent(OrderPaidAdminMail::class, 1);
    Mail::assertSent(OrderPaidAdminMail::class, fn (OrderPaidAdminMail $m): bool => $m->hasTo($activeAdmin->email));
    Mail::assertNotSent(OrderPaidAdminMail::class, fn (OrderPaidAdminMail $m): bool => $m->hasTo($inactiveAdmin->email));
    Mail::assertNotSent(OrderPaidAdminMail::class, fn (OrderPaidAdminMail $m): bool => $m->hasTo($nonAdmin->email));

    // Trying to start another checkout after paid returns 409.
    $this->postJson('/api/v1/orders/'.$orderToken.'/checkout')->assertStatus(409);
});

it('verifies PayMongo checkout status server-to-server when the guest returns from checkout', function () {
    Mail::fake();
    config()->set('services.paymongo', [
        'secret_key' => 'sk_test_123456',
        'base_url' => 'https://api.paymongo.com/v1',
    ]);
    $admin = User::factory()->admin()->create(['email' => 'admin@islasource.test']);

    $orderToken = Str::random(64);
    $rfq = Rfq::factory()->create();
    $quotation = Quotation::factory()->for($rfq)->create([
        'status' => 'Accepted',
        'total_centavos' => 150_000,
        'created_by' => $admin->id,
    ]);
    QuotationItem::factory()->for($quotation)->create(['is_available' => true, 'line_selling_centavos' => 150_000]);
    $order = Order::factory()->for($quotation)->for($rfq)->create([
        'total_centavos' => 150_000,
        'access_token_hash' => hash('sha256', $orderToken),
        'access_token_encrypted' => $orderToken,
    ]);
    OrderPayment::factory()->for($order)->create([
        'status' => 'Pending',
        'amount_centavos' => 150_000,
        'paymongo_checkout_session_id' => 'cs_test_verify_01',
    ]);

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions/cs_test_verify_01' => Http::response([
            'data' => [
                'id' => 'cs_test_verify_01',
                'type' => 'checkout_session',
                'attributes' => [
                    'payments' => [[
                        'id' => 'pay_verified_01',
                        'attributes' => ['status' => 'paid', 'amount' => 150_000],
                    ]],
                ],
            ],
        ], 200),
    ]);

    $this->postJson('/api/v1/orders/'.$orderToken.'/verify-payment')
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'Paid')
        ->assertJsonPath('data.status', 'Processing');

    Mail::assertSent(OrderPaidCustomerMail::class, 1);
    Mail::assertSent(OrderPaidAdminMail::class, 1);
});

it('blocks unauthenticated and non-admin requests on admin order endpoints', function () {
    $this->getJson('/api/v1/admin/orders')->assertUnauthorized();

    $nonAdminToken = User::factory()->create(['is_admin' => false])->createToken('x', ['admin'])->plainTextToken;
    $this->withToken($nonAdminToken)->getJson('/api/v1/admin/orders')->assertForbidden();
});

it('enforces paid guard, linear fulfillment transitions, and status emails on admin orders', function () {
    Mail::fake();

    $rfq = Rfq::factory()->create();
    $quotation = Quotation::factory()->for($rfq)->create(['status' => 'Accepted']);
    QuotationItem::factory()->for($quotation)->create(['is_available' => true]);
    $unpaidOrder = Order::factory()->for($quotation)->for($rfq)->create([
        'status' => 'Awaiting Payment',
        'payment_status' => 'Unpaid',
    ]);

    $token = orderAdminToken();

    // Unpaid order cannot be marked Packed.
    $this->withToken($token)
        ->patchJson('/api/v1/admin/orders/'.$unpaidOrder->id, ['status' => 'Packed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    // Unpaid order can be cancelled.
    $this->withToken($token)
        ->patchJson('/api/v1/admin/orders/'.$unpaidOrder->id, [
            'status' => 'Cancelled',
            'cancel_reason' => 'Customer did not complete payment.',
        ])->assertOk()
        ->assertJsonPath('data.status', 'Cancelled');

    // Paid order advances Processing -> Packed -> Dispatched -> Completed and blocks cancelling.
    $paidRfq = Rfq::factory()->create();
    $paidQuotation = Quotation::factory()->for($paidRfq)->create(['status' => 'Accepted']);
    QuotationItem::factory()->for($paidQuotation)->create(['is_available' => true]);
    $paidOrder = Order::factory()->for($paidQuotation)->for($paidRfq)->create([
        'status' => 'Processing',
        'payment_status' => 'Paid',
        'paid_centavos' => 175_000,
        'paid_at' => now(),
        'customer_email' => 'customer@example.com',
    ]);

    $this->withToken($token)
        ->patchJson('/api/v1/admin/orders/'.$paidOrder->id, ['status' => 'Cancelled'])
        ->assertUnprocessable();

    $this->withToken($token)
        ->patchJson('/api/v1/admin/orders/'.$paidOrder->id, ['status' => 'Dispatched'])
        ->assertUnprocessable();

    foreach (['Packed', 'Dispatched', 'Completed'] as $nextStatus) {
        $this->withToken($token)
            ->patchJson('/api/v1/admin/orders/'.$paidOrder->id, ['status' => $nextStatus])
            ->assertOk()
            ->assertJsonPath('data.status', $nextStatus);
    }

    Mail::assertSent(OrderStatusUpdatedMail::class, 3);
});
