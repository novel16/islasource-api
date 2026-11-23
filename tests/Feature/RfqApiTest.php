<?php

use App\Mail\RfqReceivedAdminMail;
use App\Mail\RfqReceivedCustomerMail;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use App\Policies\RfqPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(LazilyRefreshDatabase::class);

function rfqAdminToken(?User $admin = null): string
{
    $user = $admin ?? User::factory()->admin()->create();

    return $user->createToken('rfqs', ['admin'])->plainTextToken;
}

it('returns JSON 401 with no-store without a bearer token on every admin RFQ endpoint', function (string $method, string $path) {
    $response = $this->json($method, '/api/v1/admin'.$path)
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with([
    'list' => ['GET', '/rfqs'],
    'show' => ['GET', '/rfqs/1'],
    'update' => ['PATCH', '/rfqs/1'],
    'add note' => ['POST', '/rfqs/1/notes'],
]);

it('returns 403 for an account or token without active admin access on RFQ writes', function (array $flags, array $abilities) {
    $rfq = Rfq::factory()->create(['status' => 'New']);
    $user = User::factory()->create($flags);
    $token = $user->createToken('rfqs', $abilities)->plainTextToken;

    $this->withToken($token)->patchJson('/api/v1/admin/rfqs/'.$rfq->id, ['status' => 'Reviewing'])->assertForbidden();
    $this->withToken($token)->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/notes', ['body' => 'Private check.'])->assertForbidden();

    $this->assertDatabaseHas('rfqs', ['id' => $rfq->id, 'status' => 'New']);
    $this->assertDatabaseCount('rfq_notes', 0);
})->with([
    'non admin' => [['is_admin' => false, 'is_active' => true], ['admin']],
    'disabled admin' => [['is_admin' => true, 'is_active' => false], ['admin']],
    'wrong ability' => [['is_admin' => true, 'is_active' => true], ['rfqs:read']],
]);

it('calls the RFQ policy for status updates and private notes', function () {
    $rfq = Rfq::factory()->create(['status' => 'New']);
    $this->instance(RfqPolicy::class, new class extends RfqPolicy
    {
        public function update(User $user, Rfq $rfq): bool
        {
            return false;
        }
    });
    $token = rfqAdminToken();

    $this->withToken($token)->patchJson('/api/v1/admin/rfqs/'.$rfq->id, ['status' => 'Ready'])->assertForbidden();
    $this->withToken($token)->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/notes', ['body' => 'Blocked note.'])->assertForbidden();

    $this->assertDatabaseHas('rfqs', ['id' => $rfq->id, 'status' => 'New']);
    $this->assertDatabaseCount('rfq_notes', 0);
});

it('filters live RFQs by any valid status without loading internal notes on list', function () {
    $newRfq = Rfq::factory()->create(['status' => 'New', 'customer_name' => 'Maia Reyes']);
    $reviewingRfq = Rfq::factory()->create(['status' => 'Reviewing', 'customer_name' => 'Carlo Lim']);
    $closedRfq = Rfq::factory()->create(['status' => 'Closed', 'customer_name' => 'Elena Cruz']);
    RfqItem::factory()->for($reviewingRfq)->create(['product_name' => 'Dried squid']);

    $token = rfqAdminToken();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->withToken($token)
        ->getJson('/api/v1/admin/rfqs?status=Reviewing&search=squid')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reviewingRfq->id)
        ->assertJsonPath('data.0.status', 'Reviewing')
        ->assertJsonMissingPath('data.0.internal_notes');

    expect(implode(' ', $queries))->not->toContain('rfq_notes');

    $this->withToken($token)->getJson('/api/v1/admin/rfqs?status=Closed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $closedRfq->id);

    $this->withToken($token)->getJson('/api/v1/admin/rfqs?status=InvalidStatus')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($newRfq->id)->not->toBe($reviewingRfq->id);
});

it('updates live RFQ status and ignores non-updatable fields', function () {
    $rfq = Rfq::factory()->create([
        'status' => 'New',
        'customer_email' => 'guest@example.com',
        'reference' => 'RFQ-2026-ABCDEF12',
    ]);
    RfqItem::factory()->for($rfq)->create(['product_name' => 'Blue crab']);
    $token = rfqAdminToken();

    foreach (['Reviewing', 'Ready', 'Quoted', 'Closed', 'New'] as $status) {
        $response = $this->withToken($token)->patchJson('/api/v1/admin/rfqs/'.$rfq->id, [
            'status' => $status,
            'customer_email' => 'tampered@example.com',
            'reference' => 'RFQ-2026-TAMPERED',
            'id' => 99999,
        ])->assertOk()
            ->assertJsonPath('data.id', $rfq->id)
            ->assertJsonPath('data.status', $status)
            ->assertJsonPath('data.email', 'guest@example.com')
            ->assertJsonPath('data.reference', 'RFQ-2026-ABCDEF12')
            ->assertJsonPath('data.internal_notes', []);

        expect($response->headers->get('Cache-Control'))->toContain('no-store');
        $this->assertDatabaseHas('rfqs', [
            'id' => $rfq->id,
            'status' => $status,
            'customer_email' => 'guest@example.com',
            'reference' => 'RFQ-2026-ABCDEF12',
        ]);
    }
});

it('returns 422 for missing or invalid RFQ status updates', function (array $payload) {
    $rfq = Rfq::factory()->create(['status' => 'New']);

    $this->withToken(rfqAdminToken())
        ->patchJson('/api/v1/admin/rfqs/'.$rfq->id, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->assertDatabaseHas('rfqs', ['id' => $rfq->id, 'status' => 'New']);
})->with([
    'missing status' => [[]],
    'blank status' => [['status' => '']],
    'unknown status' => [['status' => 'Sourcing']],
    'lowercase status' => [['status' => 'ready']],
]);

it('stores private internal admin notes and returns them chronologically on RFQ detail', function () {
    $this->travelTo('2026-11-23 10:00:00');
    $admin = User::factory()->admin()->create(['name' => 'Alex Cruz']);
    $rfq = Rfq::factory()->create(['status' => 'New']);
    RfqItem::factory()->for($rfq)->create(['product_name' => 'Dried danggit']);
    $token = rfqAdminToken($admin);

    $first = $this->withToken($token)->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/notes', [
        'body' => '  Checked morning market availability, ₱480/kg.  ',
    ])->assertCreated()
        ->assertJsonPath('data.body', 'Checked morning market availability, ₱480/kg.')
        ->assertJsonPath('data.author_name', 'Alex Cruz')
        ->assertJsonPath('data.created_at', '2026-11-23T10:00:00.000000Z');

    expect($first->headers->get('Cache-Control'))->toContain('no-store');
    expect(array_keys($first->json('data')))->toEqualCanonicalizing([
        'id', 'body', 'author_name', 'created_at',
    ]);

    $this->travel(5)->minutes();
    $second = $this->withToken($token)->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/notes', [
        'body' => 'Customer prefers Friday delivery.',
    ])->assertCreated();

    $this->withToken($token)->getJson('/api/v1/admin/rfqs/'.$rfq->id)
        ->assertOk()
        ->assertJsonCount(2, 'data.internal_notes')
        ->assertJsonPath('data.internal_notes.0.id', $first->json('data.id'))
        ->assertJsonPath('data.internal_notes.0.body', 'Checked morning market availability, ₱480/kg.')
        ->assertJsonPath('data.internal_notes.0.author_name', 'Alex Cruz')
        ->assertJsonPath('data.internal_notes.1.id', $second->json('data.id'))
        ->assertJsonPath('data.internal_notes.1.body', 'Customer prefers Friday delivery.');

    $this->assertDatabaseHas('rfq_notes', [
        'rfq_id' => $rfq->id,
        'user_id' => $admin->id,
        'body' => 'Checked morning market availability, ₱480/kg.',
    ]);
});

it('rejects blank or oversized internal notes with 422', function (mixed $body) {
    $rfq = Rfq::factory()->create();

    $this->withToken(rfqAdminToken())
        ->postJson('/api/v1/admin/rfqs/'.$rfq->id.'/notes', ['body' => $body])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    $this->assertDatabaseCount('rfq_notes', 0);
})->with([
    'empty' => [''],
    'whitespace only' => ['   '],
    'null' => [null],
    'over 2000 chars' => [str_repeat('a', 2001)],
]);

it('returns 404 for unknown RFQ identifiers on update and notes endpoints', function () {
    $token = rfqAdminToken();

    $this->withToken($token)->patchJson('/api/v1/admin/rfqs/999999', ['status' => 'Ready'])->assertNotFound();
    $this->withToken($token)->postJson('/api/v1/admin/rfqs/999999/notes', ['body' => 'Note'])->assertNotFound();
});

it('keeps guest RFQ submission public response free of internal fields', function () {
    $product = Product::factory()->create(['status' => 'Published', 'min_order_quantity' => '1.000']);

    $response = $this->postJson('/api/v1/quote-requests', [
        'customer_name' => 'Maia Reyes',
        'email' => 'maia@example.com',
        'phone' => '+63 917 555 0148',
        'location' => 'Cebu City',
        'requested_delivery_date' => null,
        'notes' => 'Medium size please.',
        'terms_accepted' => true,
        'items' => [[
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 2,
            'unit' => $product->unit,
            'notes' => '',
        ]],
    ])->assertCreated();

    expect(array_keys($response->json('data')))->toBe(['reference']);
    $this->assertDatabaseHas('rfqs', ['reference' => $response->json('data.reference'), 'status' => 'New']);
});

it('enforces a maximum of 10 items on guest quote requests', function () {
    $items = collect(range(1, 11))->map(fn (int $index): array => [
        'product_id' => null,
        'name' => "Seafood item {$index}",
        'quantity' => 1,
        'unit' => 'kg',
        'notes' => '',
    ])->all();

    $this->postJson('/api/v1/quote-requests', [
        'customer_name' => 'Maia Reyes',
        'email' => 'maia@example.com',
        'phone' => '+63 917 555 0148',
        'location' => 'Cebu City',
        'requested_delivery_date' => null,
        'notes' => '',
        'terms_accepted' => true,
        'items' => $items,
    ])->assertUnprocessable()->assertJsonValidationErrors('items');

    $this->assertDatabaseCount('rfqs', 0);
});

it('emails the customer with their reference number and notifies only active admins when a guest submits a quote request', function () {
    Mail::fake();

    $activeAdminOne = User::factory()->admin()->create(['email' => 'admin1@islasource.test']);
    $activeAdminTwo = User::factory()->admin()->create(['email' => 'admin2@islasource.test']);
    $inactiveAdmin = User::factory()->admin()->create(['email' => 'inactive@islasource.test', 'is_active' => false]);
    $regularUser = User::factory()->create(['email' => 'regular@islasource.test', 'is_admin' => false, 'is_active' => true]);

    $product = Product::factory()->create([
        'name' => 'Danggit Buwad (Dried)',
        'status' => 'Published',
        'unit' => 'kg',
        'min_order_quantity' => '1.000',
    ]);

    $response = $this->postJson('/api/v1/quote-requests', [
        'customer_name' => 'Maia Reyes',
        'email' => 'maia@example.com',
        'phone' => '+63 917 555 0148',
        'location' => 'Cebu City, Cebu',
        'requested_delivery_date' => '2026-12-05',
        'notes' => 'Please pack per 1 kg.',
        'terms_accepted' => true,
        'items' => [[
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 3.5,
            'unit' => 'kg',
            'notes' => 'Medium size',
        ]],
    ])->assertCreated();

    $reference = $response->json('data.reference');
    $rfq = Rfq::query()->where('reference', $reference)->firstOrFail();

    Mail::assertSent(RfqReceivedCustomerMail::class, 1);
    Mail::assertSent(RfqReceivedCustomerMail::class, function (RfqReceivedCustomerMail $mail) use ($reference): bool {
        $html = $mail->render();

        return $mail->hasTo('maia@example.com')
            && str_contains($mail->envelope()->subject, $reference)
            && str_contains($html, $reference)
            && str_contains($html, 'Danggit Buwad (Dried)')
            && str_contains($html, '3.5 kg');
    });

    Mail::assertSent(RfqReceivedAdminMail::class, 2);
    Mail::assertSent(RfqReceivedAdminMail::class, function (RfqReceivedAdminMail $mail) use ($activeAdminOne, $rfq, $reference): bool {
        $html = $mail->render();

        return $mail->hasTo($activeAdminOne->email)
            && str_contains($mail->envelope()->subject, $reference)
            && str_contains($html, 'Maia Reyes')
            && str_contains($html, 'maia@example.com')
            && str_contains($html, '/rfqs/'.$rfq->id);
    });
    Mail::assertSent(RfqReceivedAdminMail::class, fn (RfqReceivedAdminMail $mail): bool => $mail->hasTo($activeAdminTwo->email));
    Mail::assertNotSent(RfqReceivedAdminMail::class, fn (RfqReceivedAdminMail $mail): bool => $mail->hasTo($inactiveAdmin->email));
    Mail::assertNotSent(RfqReceivedAdminMail::class, fn (RfqReceivedAdminMail $mail): bool => $mail->hasTo($regularUser->email));
});

it('still saves the guest quote request if email delivery fails', function () {
    $originalMailer = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP connection refused'));

    User::factory()->admin()->create(['email' => 'admin@islasource.test']);
    $product = Product::factory()->create(['status' => 'Published', 'min_order_quantity' => '1.000']);

    try {
        $response = $this->postJson('/api/v1/quote-requests', [
            'customer_name' => 'Maia Reyes',
            'email' => 'maia@example.com',
            'phone' => '+63 917 555 0148',
            'location' => 'Cebu City',
            'requested_delivery_date' => null,
            'notes' => '',
            'terms_accepted' => true,
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => 2,
                'unit' => $product->unit,
                'notes' => '',
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('rfqs', [
            'reference' => $response->json('data.reference'),
            'status' => 'New',
        ]);
    } finally {
        Mail::swap($originalMailer);
    }
});
