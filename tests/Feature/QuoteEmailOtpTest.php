<?php

use App\Mail\QuoteEmailVerificationMail;
use App\Mail\RfqReceivedCustomerMail;
use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

uses(LazilyRefreshDatabase::class);

function otpRfqPayload(string $email, string $code, array $overrides = []): array
{
    return array_replace([
        'customer_name' => 'Juan Dela Cruz',
        'email' => $email,
        'phone' => '+63 917 555 0188',
        'location' => 'Bantayan Island (Bantayan) — Poblacion',
        'requested_delivery_date' => null,
        'notes' => 'Fresh catch please.',
        'terms_accepted' => true,
        'verification_code' => $code,
        'website' => '',
        'items' => [[
            'product_id' => null,
            'name' => 'Fresh pusit',
            'quantity' => 3,
            'unit' => 'kg',
            'notes' => '',
        ]],
    ], $overrides);
}

it('sends a hashed 6-digit email code, verifies case-insensitively with trimmed code, and enforces single-use consumption', function () {
    Mail::fake();

    $this->postJson('/api/v1/quote-requests/send-code', [
        'customer_name' => 'Juan Dela Cruz',
        'email' => '  JUAN.CRUZ@EXAMPLE.TEST  ',
        'website' => '',
        'elapsed_ms' => 4200,
    ])->assertOk()
        ->assertJsonPath('data.cooldown_seconds', 60)
        ->assertJsonPath('data.expires_in_seconds', 600);

    $sentCode = null;
    Mail::assertSent(QuoteEmailVerificationMail::class, function (QuoteEmailVerificationMail $mail) use (&$sentCode): bool {
        $sentCode = $mail->code;
        $html = $mail->render();

        return $mail->hasTo('juan.cruz@example.test')
            && preg_match('/\A[0-9]{6}\z/', $mail->code) === 1
            && str_contains($html, $mail->code)
            && str_contains($html, '10 minutes');
    });

    $cached = Cache::get('quote_otp:code:'.hash('sha256', 'juan.cruz@example.test'));
    expect($cached)->toBeArray()
        ->and($cached['hash'])->not->toBe($sentCode);

    $this->postJson('/api/v1/quote-requests', otpRfqPayload('juan.cruz@example.test', " {$sentCode} "))
        ->assertCreated();

    Mail::assertSent(RfqReceivedCustomerMail::class, 1);

    // Replay with the same consumed single-use code must fail
    $this->postJson('/api/v1/quote-requests', otpRfqPayload('juan.cruz@example.test', $sentCode))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('verification_code');
});

it('returns a decoy 200 OK without sending mail when honeypot is filled or elapsed_ms is too fast', function (array $botPayload) {
    Mail::fake();
    Log::spy();

    $this->postJson('/api/v1/quote-requests/send-code', array_replace([
        'customer_name' => 'Bot User',
        'email' => 'bot@example.test',
        'website' => '',
        'elapsed_ms' => 5000,
    ], $botPayload))->assertOk()
        ->assertJsonPath('data.cooldown_seconds', 60);

    Mail::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context): bool => $message === 'Quote OTP suppressed by bot filter.'
            && array_key_exists('honeypot_filled', $context)
            && array_key_exists('elapsed_ms', $context),
    )->once();
    expect(Cache::get('quote_otp:code:'.hash('sha256', 'bot@example.test')))->toBeNull();
})->with([
    'honeypot filled' => [['website' => 'https://spam.example']],
    'submitted under 1.2s' => [['elapsed_ms' => 350]],
]);

it('enforces a 60-second resend cooldown for the same email', function () {
    Mail::fake();

    $this->postJson('/api/v1/quote-requests/send-code', [
        'customer_name' => 'Juan Dela Cruz',
        'email' => 'cooldown@example.test',
        'website' => '',
        'elapsed_ms' => 3000,
    ])->assertOk();

    $this->postJson('/api/v1/quote-requests/send-code', [
        'customer_name' => 'Juan Dela Cruz',
        'email' => 'cooldown@example.test',
        'website' => '',
        'elapsed_ms' => 6000,
    ])->assertTooManyRequests()
        ->assertHeader('Retry-After');

    Mail::assertSent(QuoteEmailVerificationMail::class, 1);
});

it('invalidates the code after 5 wrong guesses and clears cooldown so the buyer can request a fresh code', function () {
    Mail::fake();

    $this->postJson('/api/v1/quote-requests/send-code', [
        'customer_name' => 'Juan Dela Cruz',
        'email' => 'lockout@example.test',
        'website' => '',
        'elapsed_ms' => 3500,
    ])->assertOk();

    $sentCode = null;
    Mail::assertSent(QuoteEmailVerificationMail::class, function (QuoteEmailVerificationMail $mail) use (&$sentCode): bool {
        $sentCode = $mail->code;

        return true;
    });

    $wrongCode = $sentCode === '000000' ? '999999' : '000000';

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$attempt}"])
            ->postJson('/api/v1/quote-requests', otpRfqPayload('lockout@example.test', $wrongCode))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verification_code');
    }

    // Even the previously valid code is now invalidated after 5 failed attempts
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
        ->postJson('/api/v1/quote-requests', otpRfqPayload('lockout@example.test', $sentCode))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('verification_code');

    // Cooldown was cleared on lockout so the customer can immediately request a new code
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
        ->postJson('/api/v1/quote-requests/send-code', [
            'customer_name' => 'Juan Dela Cruz',
            'email' => 'lockout@example.test',
            'website' => '',
            'elapsed_ms' => 9000,
        ])->assertOk();
});

it('preserves a valid OTP if downstream product validation fails so the buyer can fix quantity without re-sending', function () {
    Mail::fake();
    $product = Product::factory()->create([
        'status' => 'Published',
        'unit' => 'kg',
        'min_order_quantity' => '5.000',
    ]);

    $this->postJson('/api/v1/quote-requests/send-code', [
        'customer_name' => 'Juan Dela Cruz',
        'email' => 'retry@example.test',
        'website' => '',
        'elapsed_ms' => 3200,
    ])->assertOk();

    $sentCode = null;
    Mail::assertSent(QuoteEmailVerificationMail::class, function (QuoteEmailVerificationMail $mail) use (&$sentCode): bool {
        $sentCode = $mail->code;

        return true;
    });

    // Fails downstream min_order_quantity (2 kg < 5 kg)
    $this->postJson('/api/v1/quote-requests', otpRfqPayload('retry@example.test', $sentCode, [
        'items' => [[
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 2,
            'unit' => 'kg',
            'notes' => '',
        ]],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.quantity');

    // Retry with corrected 5 kg using the same valid code succeeds
    $this->postJson('/api/v1/quote-requests', otpRfqPayload('retry@example.test', $sentCode, [
        'items' => [[
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 5,
            'unit' => 'kg',
            'notes' => '',
        ]],
    ]))->assertCreated();
});
