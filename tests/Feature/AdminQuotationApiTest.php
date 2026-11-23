<?php

use App\Mail\QuotationSentMail;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use App\Policies\QuotationPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(LazilyRefreshDatabase::class);

function quotationAdminToken(?User $admin = null): string
{
    $user = $admin ?? User::factory()->admin()->create();

    return $user->createToken('quotations', ['admin'])->plainTextToken;
}

it('returns JSON 401 with no-store without a bearer token on every admin quotation endpoint', function (string $method, string $path) {
    $response = $this->json($method, '/api/v1/admin'.$path)
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with([
    'list' => ['GET', '/quotations'],
    'create from rfq' => ['POST', '/rfqs/1/quotations'],
    'show' => ['GET', '/quotations/1'],
    'update' => ['PATCH', '/quotations/1'],
    'discard' => ['DELETE', '/quotations/1'],
    'send' => ['POST', '/quotations/1/send'],
    'resend' => ['POST', '/quotations/1/resend'],
    'cancel' => ['POST', '/quotations/1/cancel'],
    'revise' => ['POST', '/quotations/1/revise'],
]);

it('enforces QuotationPolicy for protected quotation writes', function () {
    $rfq = Rfq::factory()->create();
    RfqItem::factory()->for($rfq)->create();
    $this->instance(QuotationPolicy::class, new class extends QuotationPolicy
    {
        public function create(User $user): bool
        {
            return false;
        }
    });

    $this->withToken(quotationAdminToken())
        ->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/quotations')
        ->assertForbidden();
});

it('creates a Draft quotation from a live RFQ, reuses an existing Draft, and shows linked quotations on RFQ detail', function () {
    $rfq = Rfq::factory()->create(['status' => 'Ready']);
    $itemA = RfqItem::factory()->for($rfq)->create(['product_name' => 'Dried squid', 'unit' => 'kg', 'quantity' => '5.000', 'customer_notes' => 'Medium size']);
    $itemB = RfqItem::factory()->for($rfq)->create(['product_name' => 'Alimasag', 'unit' => 'kg', 'quantity' => '2.500', 'customer_notes' => 'Live if possible']);
    $token = quotationAdminToken();

    $created = $this->withToken($token)
        ->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/quotations')
        ->assertCreated()
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.status', 'Draft')
        ->assertJsonPath('data.validity_hours', 48)
        ->assertJsonPath('data.deposit_percent', 50)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.rfq_item_id', $itemA->id)
        ->assertJsonPath('data.items.0.product_name', 'Dried squid')
        ->assertJsonPath('data.items.0.specs', 'Medium size')
        ->assertJsonPath('data.items.1.rfq_item_id', $itemB->id);

    $quotationId = $created->json('data.id');

    $this->withToken($token)
        ->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/quotations')
        ->assertOk()
        ->assertJsonPath('data.id', $quotationId);

    $this->assertDatabaseCount('quotations', 1);

    $this->withToken($token)
        ->getJson('/api/v1/admin/rfqs/'.$rfq->id)
        ->assertOk()
        ->assertJsonCount(1, 'data.quotations')
        ->assertJsonPath('data.quotations.0.id', $quotationId)
        ->assertJsonPath('data.quotations.0.status', 'Draft');
});

it('updates Draft pricing with server-owned centavo calculation and allows discarding Drafts only', function () {
    $rfq = Rfq::factory()->create(['status' => 'Ready']);
    RfqItem::factory()->for($rfq)->create(['product_name' => 'Dried squid', 'unit' => 'kg', 'quantity' => '5.000']);
    RfqItem::factory()->for($rfq)->create(['product_name' => 'Wasay-wasay', 'unit' => 'kg', 'quantity' => '2.000']);
    $token = quotationAdminToken();

    $draft = $this->withToken($token)
        ->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/quotations')
        ->assertCreated()
        ->json('data');

    $updated = $this->withToken($token)
        ->patchJson('/api/v1/admin/quotations/'.$draft['id'], [
            'handling_centavos' => 20_000,
            'markup_percent' => '20.00',
            'delivery_centavos' => 35_000,
            'deposit_percent' => 50,
            'validity_hours' => 24,
            'terms' => '50% deposit confirms your order.',
            'subtotal_centavos' => 1,
            'total_centavos' => 1,
            'items' => [
                [
                    'id' => $draft['items'][0]['id'],
                    'quantity' => '5.000',
                    'specs' => 'Medium dried squid, vacuum packed',
                    'is_available' => true,
                    'unit_cost_centavos' => 50_000,
                ],
                [
                    'id' => $draft['items'][1]['id'],
                    'quantity' => '2.000',
                    'specs' => 'No catch today',
                    'is_available' => false,
                    'unit_cost_centavos' => 40_000,
                ],
            ],
        ])->assertOk()
        ->assertJsonPath('data.seafood_cost_centavos', 250_000)
        ->assertJsonPath('data.markup_centavos', 54_000)
        ->assertJsonPath('data.subtotal_centavos', 324_000)
        ->assertJsonPath('data.total_centavos', 359_000)
        ->assertJsonPath('data.deposit_centavos', 179_500)
        ->assertJsonPath('data.balance_centavos', 179_500)
        ->assertJsonPath('data.validity_hours', 24)
        ->assertJsonPath('data.items.0.line_selling_centavos', 324_000)
        ->assertJsonPath('data.items.1.line_cost_centavos', 0)
        ->assertJsonPath('data.items.1.line_selling_centavos', 0);

    $this->withToken($token)
        ->deleteJson('/api/v1/admin/quotations/'.$updated->json('data.id'))
        ->assertNoContent();

    $this->assertDatabaseCount('quotations', 0);
    $this->assertDatabaseCount('quotation_items', 0);
});

it('rejects sending a Draft when any available item has zero unit cost or terms are blank', function () {
    Mail::fake();
    $rfq = Rfq::factory()->create();
    RfqItem::factory()->for($rfq)->create(['product_name' => 'Dried squid']);
    RfqItem::factory()->for($rfq)->create(['product_name' => 'Binga shell']);
    $token = quotationAdminToken();

    $draft = $this->withToken($token)
        ->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/quotations')
        ->assertCreated()
        ->json('data');

    $this->withToken($token)
        ->patchJson('/api/v1/admin/quotations/'.$draft['id'], [
            'handling_centavos' => 0,
            'markup_percent' => '15.00',
            'delivery_centavos' => 25_000,
            'deposit_percent' => 50,
            'validity_hours' => 48,
            'terms' => '50% deposit required.',
            'items' => [
                ['id' => $draft['items'][0]['id'], 'quantity' => '2.000', 'specs' => '', 'is_available' => true, 'unit_cost_centavos' => 50_000],
                ['id' => $draft['items'][1]['id'], 'quantity' => '1.000', 'specs' => '', 'is_available' => true, 'unit_cost_centavos' => 0],
            ],
        ])->assertOk();

    $this->withToken($token)
        ->postJson('/api/v1/admin/quotations/'.$draft['id'].'/send')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    Mail::assertNothingSent();
    $this->assertDatabaseHas('quotations', ['id' => $draft['id'], 'status' => 'Draft']);
});

it('sends quotation via Gmail mail without leaking private cost or markup, locks edits, supports resend, revision superseding, and cancellation', function () {
    Mail::fake();
    config(['app.frontend_url' => 'http://localhost:3000']);
    $this->travelTo('2026-11-23 14:00:00');

    $rfq = Rfq::factory()->create([
        'customer_name' => 'Maia Reyes',
        'customer_email' => 'maia@example.com',
        'status' => 'Ready',
    ]);
    RfqItem::factory()->for($rfq)->create(['product_name' => 'Dried squid', 'unit' => 'kg', 'quantity' => '5.000']);
    $token = quotationAdminToken();

    $draft = $this->withToken($token)->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/quotations')->assertCreated()->json('data');
    $this->withToken($token)->patchJson('/api/v1/admin/quotations/'.$draft['id'], [
        'handling_centavos' => 20_000,
        'markup_percent' => '20.00',
        'delivery_centavos' => 35_000,
        'deposit_percent' => 50,
        'validity_hours' => 48,
        'terms' => '50% deposit confirms your order.',
        'items' => [
            ['id' => $draft['items'][0]['id'], 'quantity' => '5.000', 'specs' => 'Medium size', 'is_available' => true, 'unit_cost_centavos' => 50_000],
        ],
    ])->assertOk();

    $sentResponse = $this->withToken($token)
        ->postJson('/api/v1/admin/quotations/'.$draft['id'].'/send')
        ->assertOk()
        ->assertJsonPath('data.status', 'Sent')
        ->assertJsonPath('data.email_sent', true)
        ->assertJsonPath('data.valid_until', '2026-11-25T14:00:00.000000Z');

    $shareUrl = $sentResponse->json('data.share_url');
    expect($shareUrl)->toMatch('#^http://localhost:3000/quote/[A-Za-z0-9]{64}$#');
    $this->assertDatabaseHas('rfqs', ['id' => $rfq->id, 'status' => 'Quoted']);

    Mail::assertSent(QuotationSentMail::class, function (QuotationSentMail $mail) use ($shareUrl): bool {
        $html = $mail->render();

        return $mail->hasTo('maia@example.com')
            && str_contains($html, $shareUrl)
            && str_contains($html, '3,590.00')
            && ! str_contains($html, '2,500.00')
            && ! str_contains($html, '540.00')
            && ! str_contains(strtolower($html), 'markup');
    });

    // Sent quotation cannot be edited or discarded
    $this->withToken($token)->patchJson('/api/v1/admin/quotations/'.$draft['id'], [
        'handling_centavos' => 0, 'markup_percent' => '10.00', 'delivery_centavos' => 0,
        'deposit_percent' => 50, 'validity_hours' => 48, 'terms' => 'Changed',
        'items' => [['id' => $draft['items'][0]['id'], 'quantity' => '5.000', 'specs' => '', 'is_available' => true, 'unit_cost_centavos' => 50_000]],
    ])->assertUnprocessable();
    $this->withToken($token)->deleteJson('/api/v1/admin/quotations/'.$draft['id'])->assertForbidden();

    // Closing parent RFQ while active Sent quote exists is blocked
    $this->withToken($token)->patchJson('/api/v1/admin/rfqs/'.$rfq->id, ['status' => 'Closed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    // Resend keeps the exact same share URL
    $this->withToken($token)->postJson('/api/v1/admin/quotations/'.$draft['id'].'/resend')
        ->assertOk()
        ->assertJsonPath('data.share_url', $shareUrl)
        ->assertJsonPath('data.email_sent', true);

    // Revise creates Draft v2, and sending v2 supersedes v1
    $v2 = $this->withToken($token)->postJson('/api/v1/admin/quotations/'.$draft['id'].'/revise')
        ->assertCreated()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.status', 'Draft')
        ->json('data');

    $this->withToken($token)->postJson('/api/v1/admin/quotations/'.$v2['id'].'/send')
        ->assertOk()
        ->assertJsonPath('data.status', 'Sent');

    $this->assertDatabaseHas('quotations', ['id' => $draft['id'], 'status' => 'Superseded']);
    $this->assertDatabaseHas('quotations', ['id' => $v2['id'], 'status' => 'Sent']);

    // Cancelling active v2 reverts RFQ status back to Ready
    $this->withToken($token)->postJson('/api/v1/admin/quotations/'.$v2['id'].'/cancel', [
        'reason' => 'Morning catch sold out.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'Cancelled')
        ->assertJsonPath('data.cancel_reason', 'Morning catch sold out.');

    $this->assertDatabaseHas('rfqs', ['id' => $rfq->id, 'status' => 'Ready']);
});

it('keeps quotation Sent with copyable link when SMTP fails and recovers on resend', function () {
    $rfq = Rfq::factory()->create(['customer_email' => 'maia@example.com']);
    RfqItem::factory()->for($rfq)->create();
    $token = quotationAdminToken();

    $draft = $this->withToken($token)->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/quotations')->assertCreated()->json('data');
    $this->withToken($token)->patchJson('/api/v1/admin/quotations/'.$draft['id'], [
        'handling_centavos' => 0, 'markup_percent' => '10.00', 'delivery_centavos' => 0,
        'deposit_percent' => 50, 'validity_hours' => 48, 'terms' => 'Standard terms.',
        'items' => [['id' => $draft['items'][0]['id'], 'quantity' => '1.000', 'specs' => '', 'is_available' => true, 'unit_cost_centavos' => 30_000]],
    ])->assertOk();

    $originalMailer = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP connection refused'));

    $failed = $this->withToken($token)->postJson('/api/v1/admin/quotations/'.$draft['id'].'/send')
        ->assertOk()
        ->assertJsonPath('data.status', 'Sent')
        ->assertJsonPath('data.email_sent', false);

    expect($failed->json('data.share_url'))->not->toBeEmpty();
    expect($failed->json('data.last_email_error'))->not->toBeEmpty();

    Mail::swap($originalMailer);
    Mail::fake();
    $this->withToken($token)->postJson('/api/v1/admin/quotations/'.$draft['id'].'/resend')
        ->assertOk()
        ->assertJsonPath('data.email_sent', true)
        ->assertJsonPath('data.last_email_error', '');
});

it('lazily expires overdue Sent quotations and blocks quoting Closed RFQs', function () {
    $rfq = Rfq::factory()->create(['status' => 'Quoted']);
    $overdue = Quotation::factory()->sent()->for($rfq)->create([
        'valid_until' => now()->subMinute(),
    ]);
    QuotationItem::factory()->for($overdue)->create();
    $closedRfq = Rfq::factory()->create(['status' => 'Closed']);
    RfqItem::factory()->for($closedRfq)->create();
    $token = quotationAdminToken();

    $this->withToken($token)->getJson('/api/v1/admin/quotations/'.$overdue->id)
        ->assertOk()
        ->assertJsonPath('data.status', 'Expired');

    $this->assertDatabaseHas('quotations', ['id' => $overdue->id, 'status' => 'Expired']);

    $this->withToken($token)->postJson('/api/v1/admin/rfqs/'.$closedRfq->id.'/quotations')
        ->assertUnprocessable();
});
