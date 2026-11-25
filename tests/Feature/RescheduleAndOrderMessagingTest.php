<?php

use App\Mail\OrderMessageNotificationMail;
use App\Mail\RfqScheduleRespondedAdminMail;
use App\Mail\RfqSupplyNoticeMail;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(LazilyRefreshDatabase::class);

it('handles admin supply reschedule proposal, guest confirmation, and carries confirmed delivery date to quotation and order', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create(['name' => 'Alex Cruz', 'email' => 'admin@islasource.test']);
    $token = $admin->createToken('admin', ['admin'])->plainTextToken;

    $rfq = Rfq::factory()->create([
        'customer_name' => 'Maia Reyes',
        'customer_email' => 'maia@example.com',
        'requested_delivery_date' => '2026-11-28',
        'status' => 'New',
    ]);
    RfqItem::factory()->for($rfq)->create([
        'product_name' => 'Alimasag Blue Crab',
        'unit' => 'kg',
        'quantity' => '3.000',
    ]);

    $propose = $this->withToken($token)->postJson("/api/v1/admin/rfqs/{$rfq->id}/supply-notice", [
        'mode' => 'reschedule',
        'proposed_available_date' => '2026-12-01',
        'proposed_delivery_date' => '2026-12-02',
        'notice' => 'Rough seas today; fresh catch expected Tuesday.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'Waiting')
        ->assertJsonPath('data.schedule_status', 'Proposed')
        ->assertJsonPath('data.proposed_available_date', '2026-12-01')
        ->assertJsonPath('data.proposed_delivery_date', '2026-12-02')
        ->assertJsonPath('data.email_sent', true);

    Mail::assertSent(RfqSupplyNoticeMail::class, fn (RfqSupplyNoticeMail $mail): bool => $mail->hasTo('maia@example.com') && $mail->mode === 'reschedule');

    $scheduleUrl = (string) $propose->json('data.schedule_url');
    $guestToken = basename($scheduleUrl);
    expect(strlen($guestToken))->toBe(64);

    $publicView = $this->getJson("/api/v1/quote-requests/schedule/{$guestToken}")
        ->assertOk()
        ->assertJsonPath('data.reference', $rfq->reference)
        ->assertJsonPath('data.schedule_status', 'Proposed')
        ->assertJsonPath('data.proposed_available_date', '2026-12-01')
        ->assertJsonPath('data.proposed_delivery_date', '2026-12-02')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.phone');

    expect($publicView->json('data.items'))->toHaveCount(1);

    $this->postJson("/api/v1/quote-requests/schedule/{$guestToken}/respond", [
        'decision' => 'accept',
        'note' => 'December 2 works for our family dinner!',
    ])->assertOk()
        ->assertJsonPath('data.status', 'Waiting')
        ->assertJsonPath('data.schedule_status', 'Accepted')
        ->assertJsonPath('data.schedule_customer_note', 'December 2 works for our family dinner!');

    Mail::assertSent(RfqScheduleRespondedAdminMail::class, fn (RfqScheduleRespondedAdminMail $mail): bool => $mail->hasTo('admin@islasource.test'));

    // Duplicate response returns 409 conflict
    $this->postJson("/api/v1/quote-requests/schedule/{$guestToken}/respond", [
        'decision' => 'cancel',
    ])->assertStatus(409);

    // Preparing quotation automatically pre-fills confirmed_delivery_date from proposed_delivery_date
    $draftRes = $this->withToken($token)->postJson("/api/v1/admin/rfqs/{$rfq->id}/quotations", [])
        ->assertCreated()
        ->assertJsonPath('data.confirmed_delivery_date', '2026-12-02');

    $quotationId = (int) $draftRes->json('data.id');
    $itemId = (int) $draftRes->json('data.items.0.id');

    // Admin updates quotation with pricing and adjusted confirmed_delivery_date
    $this->withToken($token)->patchJson("/api/v1/admin/quotations/{$quotationId}", [
        'handling_centavos' => 5000,
        'markup_percent' => '20.00',
        'delivery_centavos' => 15000,
        'confirmed_delivery_date' => '2026-12-03',
        'validity_hours' => 48,
        'terms' => 'Full QR Ph payment upon acceptance.',
        'items' => [[
            'id' => $itemId,
            'quantity' => '3',
            'specs' => 'Live medium',
            'is_available' => true,
            'unit_cost_centavos' => 45000,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.confirmed_delivery_date', '2026-12-03');

    $sentRes = $this->withToken($token)->postJson("/api/v1/admin/quotations/{$quotationId}/send")
        ->assertOk();
    $quoteToken = basename((string) $sentRes->json('data.share_url'));

    $acceptedQuote = $this->postJson("/api/v1/quotations/{$quoteToken}/accept", [
        'terms_accepted' => true,
    ])->assertOk()
        ->assertJsonPath('data.confirmed_delivery_date', '2026-12-03');

    $orderToken = (string) $acceptedQuote->json('data.order_token');
    $this->getJson("/api/v1/orders/{$orderToken}")
        ->assertOk()
        ->assertJsonPath('data.confirmed_delivery_date', '2026-12-03');
});

it('closes RFQ when guest cancels schedule or when admin marks request unavailable', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create(['email' => 'admin@islasource.test']);
    $token = $admin->createToken('admin', ['admin'])->plainTextToken;

    $rfq = Rfq::factory()->create(['customer_email' => 'guest1@example.com', 'status' => 'New']);
    RfqItem::factory()->for($rfq)->create();

    $res = $this->withToken($token)->postJson("/api/v1/admin/rfqs/{$rfq->id}/supply-notice", [
        'mode' => 'reschedule',
        'proposed_available_date' => '2026-12-05',
        'proposed_delivery_date' => '2026-12-06',
        'notice' => 'Next boat arrives Friday.',
    ])->assertOk();

    $guestToken = basename((string) $res->json('data.schedule_url'));
    $this->postJson("/api/v1/quote-requests/schedule/{$guestToken}/respond", [
        'decision' => 'cancel',
        'note' => 'Needed strictly this weekend.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'Closed')
        ->assertJsonPath('data.schedule_status', 'Declined');

    $rfqTwo = Rfq::factory()->create(['customer_email' => 'guest2@example.com', 'status' => 'Reviewing']);
    RfqItem::factory()->for($rfqTwo)->create();

    $this->withToken($token)->postJson("/api/v1/admin/rfqs/{$rfqTwo->id}/supply-notice", [
        'mode' => 'unavailable',
        'notice' => 'Out of season until next quarter.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'Closed')
        ->assertJsonPath('data.schedule_status', 'ClosedUnavailable')
        ->assertJsonPath('data.schedule_url', '');

    Mail::assertSent(RfqSupplyNoticeMail::class, fn (RfqSupplyNoticeMail $mail): bool => $mail->hasTo('guest2@example.com') && $mail->mode === 'unavailable');
});

it('supports two-way order messaging with email notifications and locks completed or cancelled orders', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create(['name' => 'IslaSource Admin', 'email' => 'admin@islasource.test']);
    $adminToken = $admin->createToken('admin', ['admin'])->plainTextToken;

    $rfq = Rfq::factory()->create(['customer_name' => 'Carlo Lim', 'customer_email' => 'carlo@example.com']);
    $quotation = Quotation::factory()->for($rfq)->create([
        'created_by' => $admin->id,
        'status' => 'Accepted',
        'subtotal_centavos' => 100000,
        'delivery_centavos' => 15000,
        'total_centavos' => 115000,
    ]);
    QuotationItem::factory()->for($quotation)->create([
        'is_available' => true,
        'line_selling_centavos' => 100000,
    ]);

    $plainOrderToken = str_repeat('k', 64);
    $order = Order::factory()->for($quotation)->for($rfq)->create([
        'status' => 'Processing',
        'payment_status' => 'Paid',
        'customer_name' => 'Carlo Lim',
        'customer_email' => 'carlo@example.com',
        'access_token_hash' => hash('sha256', $plainOrderToken),
        'access_token_encrypted' => $plainOrderToken,
    ]);

    // Customer posts message -> Admin emailed
    $this->postJson("/api/v1/orders/{$plainOrderToken}/messages", [
        'body' => 'Please pack in a styrofoam cooler for Ceres bus.',
    ])->assertCreated()
        ->assertJsonPath('data.sender_type', 'customer')
        ->assertJsonPath('data.sender_name', 'Carlo Lim')
        ->assertJsonPath('data.body', 'Please pack in a styrofoam cooler for Ceres bus.');

    Mail::assertSent(OrderMessageNotificationMail::class, fn (OrderMessageNotificationMail $mail): bool => $mail->hasTo('admin@islasource.test') && $mail->forAdmin === true && str_contains($mail->replyUrl, '#messages'));

    // Admin replies -> Customer emailed
    $this->withToken($adminToken)->postJson("/api/v1/admin/orders/{$order->id}/messages", [
        'body' => 'Noted! Packed with ice gel packs, waybill #CB-8891.',
    ])->assertCreated()
        ->assertJsonPath('data.sender_type', 'admin')
        ->assertJsonPath('data.sender_name', 'IslaSource Admin');

    Mail::assertSent(OrderMessageNotificationMail::class, fn (OrderMessageNotificationMail $mail): bool => $mail->hasTo('carlo@example.com') && $mail->forAdmin === false && str_contains($mail->replyUrl, "{$plainOrderToken}#messages"));

    // Both messages appear chronologically on guest and admin order detail
    $this->getJson("/api/v1/orders/{$plainOrderToken}")
        ->assertOk()
        ->assertJsonCount(2, 'data.messages')
        ->assertJsonPath('data.messages.0.sender_type', 'customer')
        ->assertJsonPath('data.messages.1.sender_type', 'admin');

    $this->withToken($adminToken)->getJson("/api/v1/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.messages');

    // Lock messaging when order is Completed
    $order->update(['status' => 'Completed', 'completed_at' => now()]);

    $this->postJson("/api/v1/orders/{$plainOrderToken}/messages", [
        'body' => 'Can I add another kilo?',
    ])->assertStatus(409);

    $this->withToken($adminToken)->postJson("/api/v1/admin/orders/{$order->id}/messages", [
        'body' => 'Closed test',
    ])->assertUnprocessable();
});
