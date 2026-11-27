<?php

use App\Mail\OrderPaidCustomerMail;
use App\Mail\QuotationSentMail;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Rfq;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

it('returns 404 for unknown, malformed, or draft quotation tokens', function () {
    $rfq = Rfq::factory()->create();
    $draftToken = Str::random(64);
    Quotation::factory()->for($rfq)->create([
        'status' => 'Draft',
        'access_token_hash' => hash('sha256', $draftToken),
        'access_token_encrypted' => $draftToken,
    ]);

    $this->getJson('/api/v1/quotations/'.Str::random(64))->assertNotFound();
    $this->getJson('/api/v1/quotations/RFQ-2026-001')->assertNotFound();
    $this->getJson('/api/v1/quotations/'.$draftToken)->assertNotFound();
    $this->postJson('/api/v1/quotations/'.Str::random(64).'/accept', ['terms_accepted' => true])->assertNotFound();
    $this->postJson('/api/v1/quotations/'.Str::random(64).'/decline', ['reason' => 'Too expensive'])->assertNotFound();
});

it('exposes only customer-facing quotation fields with Cache-Control no-store', function () {
    $token = Str::random(64);
    $rfq = Rfq::factory()->create([
        'reference' => 'RFQ-2026-GUEST001',
        'customer_name' => 'Maia Reyes',
        'customer_email' => 'maia@example.com',
        'customer_phone' => '+63 917 555 0148',
        'delivery_location' => 'Lahug, Cebu City',
        'status' => 'Quoted',
    ]);
    $rfq->notes()->create(['body' => 'Private sourcing note']);
    $quotation = Quotation::factory()->sent($token)->for($rfq)->create([
        'seafood_cost_centavos' => 250_000,
        'handling_centavos' => 20_000,
        'markup_percent' => '20.00',
        'markup_centavos' => 54_000,
        'subtotal_centavos' => 324_000,
        'delivery_centavos' => 35_000,
        'total_centavos' => 359_000,
        'deposit_percent' => 50,
        'deposit_centavos' => 179_500,
        'balance_centavos' => 179_500,
        'cancel_reason' => 'Internal note',
        'last_email_error' => 'Internal SMTP detail',
    ]);
    QuotationItem::factory()->for($quotation)->create([
        'product_name' => 'Dried squid',
        'unit' => 'kg',
        'quantity' => '5.000',
        'is_available' => true,
        'unit_cost_centavos' => 50_000,
        'line_cost_centavos' => 250_000,
        'line_selling_centavos' => 324_000,
    ]);

    $response = $this->getJson('/api/v1/quotations/'.$token)
        ->assertOk()
        ->assertJsonPath('data.reference', $quotation->reference)
        ->assertJsonPath('data.rfq_reference', 'RFQ-2026-GUEST001')
        ->assertJsonPath('data.customer_name', 'Maia Reyes')
        ->assertJsonPath('data.subtotal_centavos', 324_000)
        ->assertJsonPath('data.total_centavos', 359_000)
        ->assertJsonPath('data.items.0.line_selling_centavos', 324_000);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect(array_keys($response->json('data')))->toEqualCanonicalizing([
        'reference', 'rfq_reference', 'version', 'status',
        'customer_name', 'delivery_location', 'requested_delivery_date', 'confirmed_delivery_date',
        'items', 'subtotal_centavos', 'delivery_centavos', 'total_centavos',
        'deposit_percent', 'deposit_centavos', 'balance_centavos',
        'validity_hours', 'valid_until', 'terms',
        'sent_at', 'accepted_at', 'declined_at', 'decline_reason', 'cancelled_at',
        'order_reference', 'order_token',
    ]);
    expect(array_keys($response->json('data.items.0')))->toEqualCanonicalizing([
        'id', 'product_name', 'unit', 'quantity', 'specs', 'is_available', 'line_selling_centavos',
    ]);
    expect($response->getContent())->not->toContain('maia@example.com');
    expect($response->getContent())->not->toContain('Private sourcing note');
    expect($response->getContent())->not->toContain('unit_cost_centavos');
    expect($response->getContent())->not->toContain('markup');
});

it('lets a guest accept an active Sent quotation after confirming terms and blocks duplicate decisions', function () {
    $token = Str::random(64);
    $rfq = Rfq::factory()->create(['status' => 'Quoted']);
    $quotation = Quotation::factory()->sent($token)->for($rfq)->create();
    QuotationItem::factory()->for($quotation)->create();

    $this->postJson('/api/v1/quotations/'.$token.'/accept', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('terms_accepted');

    $accepted = $this->postJson('/api/v1/quotations/'.$token.'/accept', [
        'terms_accepted' => true,
    ])->assertOk()
        ->assertJsonPath('data.status', 'Accepted');

    expect($accepted->json('data.accepted_at'))->not->toBeNull();
    expect($accepted->headers->get('Cache-Control'))->toContain('no-store');
    $this->assertDatabaseHas('quotations', ['id' => $quotation->id, 'status' => 'Accepted']);
    $this->assertDatabaseHas('rfqs', ['id' => $rfq->id, 'status' => 'Quoted']);

    $this->postJson('/api/v1/quotations/'.$token.'/accept', ['terms_accepted' => true])->assertUnprocessable();
    $this->postJson('/api/v1/quotations/'.$token.'/decline', ['reason' => 'Changed mind'])->assertUnprocessable();
});

it('lets a guest decline an active Sent quotation with an optional trimmed reason', function () {
    $token = Str::random(64);
    $rfq = Rfq::factory()->create(['status' => 'Quoted']);
    $quotation = Quotation::factory()->sent($token)->for($rfq)->create();
    QuotationItem::factory()->for($quotation)->create();

    $declined = $this->postJson('/api/v1/quotations/'.$token.'/decline', [
        'reason' => '  Delivery date moved to next month.  ',
    ])->assertOk()
        ->assertJsonPath('data.status', 'Declined')
        ->assertJsonPath('data.decline_reason', 'Delivery date moved to next month.');

    expect($declined->json('data.declined_at'))->not->toBeNull();
    $this->assertDatabaseHas('quotations', [
        'id' => $quotation->id,
        'status' => 'Declined',
        'decline_reason' => 'Delivery date moved to next month.',
    ]);

    $this->postJson('/api/v1/quotations/'.$token.'/accept', ['terms_accepted' => true])->assertUnprocessable();
});

it('blocks accepting or declining expired, superseded, or cancelled quotations', function (string $status, bool $overdue) {
    $token = Str::random(64);
    $rfq = Rfq::factory()->create(['status' => 'Quoted']);
    $quotation = Quotation::factory()->sent($token)->for($rfq)->create([
        'status' => $status,
        'valid_until' => $overdue ? now()->subMinute() : now()->addHours(24),
        'cancelled_at' => $status === 'Cancelled' ? now() : null,
    ]);
    QuotationItem::factory()->for($quotation)->create();

    $expectedStatus = $overdue ? 'Expired' : $status;

    $this->getJson('/api/v1/quotations/'.$token)
        ->assertOk()
        ->assertJsonPath('data.status', $expectedStatus);

    $this->postJson('/api/v1/quotations/'.$token.'/accept', ['terms_accepted' => true])->assertUnprocessable();
    $this->postJson('/api/v1/quotations/'.$token.'/decline', ['reason' => 'No thanks'])->assertUnprocessable();
})->with([
    'overdue sent' => ['Sent', true],
    'superseded' => ['Superseded', false],
    'cancelled' => ['Cancelled', false],
]);

it('renders Free instead of PHP 0.00 in quotation and order receipt emails when delivery is zero', function () {
    $rfq = Rfq::factory()->create(['delivery_location' => 'Bantayan Island (Madridejos) — Poblacion']);
    $quotation = Quotation::factory()->for($rfq)->create([
        'subtotal_centavos' => 150_000,
        'delivery_centavos' => 0,
        'total_centavos' => 150_000,
    ]);
    QuotationItem::factory()->for($quotation)->create([
        'is_available' => true,
        'line_selling_centavos' => 150_000,
    ]);
    $quotation->load(['rfq', 'items']);

    $quoteHtml = (new QuotationSentMail($quotation, 'http://localhost:3000/quote/'.str_repeat('a', 64)))->render();
    expect($quoteHtml)->toContain('Free');
    expect($quoteHtml)->not->toContain('PHP 0.00');

    $order = Order::factory()->for($quotation)->for($rfq)->create([
        'subtotal_centavos' => 150_000,
        'delivery_centavos' => 0,
        'total_centavos' => 150_000,
        'paid_centavos' => 150_000,
    ]);
    $order->load(['quotation.items']);

    $orderHtml = (new OrderPaidCustomerMail($order, 'http://localhost:3000/order/'.str_repeat('b', 64)))->render();
    expect($orderHtml)->toContain('Free');
    expect($orderHtml)->not->toContain('PHP 0.00');
});
