<?php

use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\User;
use App\Services\QuoteOtpService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function validQuotePayload(string $email = 'customer@example.test'): array
{
    $code = app(QuoteOtpService::class)->issueCode($email);

    return [
        'customer_name' => 'Maria Santos',
        'email' => $email,
        'phone' => '+63 917 555 0199',
        'location' => 'Bantayan Island — Bantayan (Brgy. Suba)',
        'requested_delivery_date' => null,
        'notes' => '',
        'terms_accepted' => true,
        'verification_code' => $code,
        'items' => [[
            'product_id' => null,
            'name' => 'Dried danggit',
            'quantity' => 3,
            'unit' => 'kg',
            'notes' => '',
        ]],
    ];
}

it('isolates public catalog browsing from guest quote submission rate limits', function () {
    Mail::fake();

    for ($i = 0; $i < 12; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->getJson('/api/v1/seafood')
            ->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->postJson('/api/v1/quote-requests', validQuotePayload())
        ->assertCreated();
});

it('throttles guest quote submissions at 3 per normalized email across IPs and 6 per IP', function () {
    Mail::fake();

    foreach (['203.0.113.21', '203.0.113.22', '203.0.113.23'] as $index => $ip) {
        $email = $index === 0 ? '  SAME@EXAMPLE.TEST  ' : 'same@example.test';

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/quote-requests', validQuotePayload($email))
            ->assertCreated();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.24'])
        ->postJson('/api/v1/quote-requests', validQuotePayload('SAME@example.test'))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');

    for ($i = 1; $i <= 6; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
            ->postJson('/api/v1/quote-requests', validQuotePayload("buyer{$i}@example.test"))
            ->assertCreated();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
        ->postJson('/api/v1/quote-requests', validQuotePayload('buyer7@example.test'))
        ->assertTooManyRequests();
});

it('isolates guest token actions per token and from catalog browsing', function () {
    $tokenA = Str::random(64);
    $tokenB = Str::random(64);
    $rfqA = Rfq::factory()->create(['status' => 'Quoted']);
    $rfqB = Rfq::factory()->create(['status' => 'Quoted']);
    Quotation::factory()->sent($tokenA)->for($rfqA)->create();
    Quotation::factory()->sent($tokenB)->for($rfqB)->create();

    for ($i = 0; $i < 15; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])
            ->getJson('/api/v1/seafood')
            ->assertOk();
    }

    for ($i = 0; $i < 10; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])
            ->postJson("/api/v1/quotations/{$tokenA}/accept", ['terms_accepted' => false])
            ->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])
        ->postJson("/api/v1/quotations/{$tokenA}/accept", ['terms_accepted' => false])
        ->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])
        ->postJson("/api/v1/quotations/{$tokenB}/accept", ['terms_accepted' => false])
        ->assertUnprocessable();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])
        ->getJson("/api/v1/quotations/{$tokenA}")
        ->assertOk();
});

it('throttles admin password changes at 5 per minute without blocking normal admin reads', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('web', ['admin'])->plainTextToken;

    for ($i = 0; $i < 5; $i++) {
        $this->withToken($token)
            ->postJson('/api/v1/admin/auth/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertUnprocessable();
    }

    $this->withToken($token)
        ->postJson('/api/v1/admin/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])
        ->assertTooManyRequests();

    $this->withToken($token)
        ->getJson('/api/v1/admin/auth/me')
        ->assertOk();
});

it('throttles heavy admin upload and mail endpoints at 20 per minute without blocking normal admin reads', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('web', ['admin'])->plainTextToken;

    for ($i = 0; $i < 20; $i++) {
        $this->withToken($token)
            ->postJson('/api/v1/admin/product-images', [])
            ->assertUnprocessable();
    }

    $this->withToken($token)
        ->postJson('/api/v1/admin/product-images', [])
        ->assertTooManyRequests();

    $this->withToken($token)
        ->getJson('/api/v1/admin/products')
        ->assertOk();
});

it('ignores spoofed X-Forwarded-For by default and honors it when trustedproxy.proxies is configured', function () {
    Mail::fake();
    expect(file_exists(config_path('trustedproxy.php')))->toBeTrue();
    expect(config('trustedproxy.proxies'))->toBeNull();

    for ($i = 1; $i <= 6; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => "198.51.100.{$i}"])
            ->postJson('/api/v1/quote-requests', validQuotePayload("untrusted{$i}@example.test"))
            ->assertCreated();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
        ->postJson('/api/v1/quote-requests', validQuotePayload('untrusted7@example.test'))
        ->assertTooManyRequests();

    config(['trustedproxy.proxies' => '127.0.0.1']);

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['X-Forwarded-For' => '198.51.100.99'])
        ->postJson('/api/v1/quote-requests', validQuotePayload('trusted99@example.test'))
        ->assertCreated();
});
