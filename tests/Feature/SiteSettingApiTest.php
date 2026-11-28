<?php

use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function settingsAdminToken(?User $admin = null): string
{
    $user = $admin ?? User::factory()->admin()->create();

    return $user->createToken('settings', ['admin'])->plainTextToken;
}

it('requires active admin authentication on admin settings endpoints', function (string $method, string $path) {
    $response = $this->json($method, '/api/v1/admin'.$path)
        ->assertUnauthorized();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with([
    'show' => ['GET', '/settings'],
    'update' => ['PUT', '/settings'],
    'reset' => ['POST', '/settings/reset'],
]);

it('exposes only public storefront settings on GET /api/v1/site-settings and hides private pricing defaults', function () {
    $response = $this->getJson('/api/v1/site-settings')
        ->assertOk()
        ->assertJsonPath('data.business_name', 'IslaSource Seafoods')
        ->assertJsonPath('data.island_min_kg', 3)
        ->assertJsonPath('data.cebu_min_kg', 5)
        ->assertJsonPath('data.homepage.hero_image', '/images/hero-seafood.jpg');

    $data = $response->json('data');
    expect($data)->not->toHaveKeys([
        'default_markup_percent',
        'default_handling_centavos',
        'default_cebu_delivery_centavos',
        'default_validity_hours',
        'default_quotation_terms',
    ]);
    expect(collect($data['terms_sections'])->pluck('id')->all())->toBe([
        'delivery-pickup',
        'sourcing-quotes',
        'payments-refunds',
        'inspection',
        'weather',
        'privacy',
    ]);
});

it('persists updated DB settings, reflects public changes on storefront endpoint, and resets to defaults', function () {
    $token = settingsAdminToken();

    $initial = $this->withToken($token)
        ->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->json('data');

    $payload = $initial;
    $payload['business_name'] = 'IslaSource Bantayan Direct';
    $payload['contact_phone'] = '+63 917 888 1234';
    $payload['island_min_kg'] = 4;
    $payload['cebu_min_kg'] = 6.5;
    $payload['default_markup_percent'] = '25.50';
    $payload['default_handling_centavos'] = 12_000;
    $payload['default_cebu_delivery_centavos'] = 35_000;
    $payload['default_validity_hours'] = 72;
    $payload['default_quotation_terms'] = 'Custom DB default terms note.';
    $payload['homepage']['advisory_enabled'] = true;
    $payload['homepage']['advisory_text'] = 'Rough seas notice: fresh catch orders ship tomorrow morning.';
    $payload['homepage']['hero_headline'] = "Fresh from Baod.\nDelivered with care.";
    $payload['homepage']['hero_image'] = '/storage/products/custom_hero_123.jpg';
    $payload['terms_sections'][0]['title'] = 'Updated coverage & pickup policy';

    $this->withToken($token)
        ->putJson('/api/v1/admin/settings', $payload)
        ->assertOk()
        ->assertJsonPath('data.default_markup_percent', '25.50')
        ->assertJsonPath('data.default_cebu_delivery_centavos', 35_000)
        ->assertJsonPath('data.homepage.hero_image', '/storage/products/custom_hero_123.jpg');

    $this->getJson('/api/v1/site-settings')
        ->assertOk()
        ->assertJsonPath('data.business_name', 'IslaSource Bantayan Direct')
        ->assertJsonPath('data.island_min_kg', 4)
        ->assertJsonPath('data.cebu_min_kg', 6.5)
        ->assertJsonPath('data.homepage.advisory_enabled', true)
        ->assertJsonPath('data.homepage.hero_image', '/storage/products/custom_hero_123.jpg')
        ->assertJsonPath('data.terms_sections.0.title', 'Updated coverage & pickup policy')
        ->assertJsonMissingPath('data.default_markup_percent');

    $this->withToken($token)
        ->postJson('/api/v1/admin/settings/reset')
        ->assertOk()
        ->assertJsonPath('data.default_markup_percent', '20.00')
        ->assertJsonPath('data.island_min_kg', 3)
        ->assertJsonPath('data.homepage.advisory_enabled', false);
});

it('applies DB pricing defaults and smart Bantayan Free vs Cebu delivery fee only to new draft quotations', function () {
    $token = settingsAdminToken();

    $payload = $this->withToken($token)->getJson('/api/v1/admin/settings')->json('data');
    $payload['default_markup_percent'] = '25.00';
    $payload['default_handling_centavos'] = 10_000;
    $payload['default_cebu_delivery_centavos'] = 30_000;
    $payload['default_validity_hours'] = 72;
    $payload['default_quotation_terms'] = 'Updated default quote terms.';
    $this->withToken($token)->putJson('/api/v1/admin/settings', $payload)->assertOk();

    $islandRfq = Rfq::factory()->create([
        'status' => 'New',
        'delivery_location' => 'Bantayan Island (Madridejos) — Poblacion',
    ]);
    RfqItem::factory()->for($islandRfq)->create();

    $cebuRfq = Rfq::factory()->create([
        'status' => 'New',
        'delivery_location' => 'Mainland Cebu (Ceres Bus — Cebu North Bus Terminal)',
    ]);
    RfqItem::factory()->for($cebuRfq)->create();

    $islandDraft = $this->withToken($token)
        ->postJson("/api/v1/admin/rfqs/{$islandRfq->id}/quotations")
        ->assertCreated()
        ->assertJsonPath('data.markup_percent', '25.00')
        ->assertJsonPath('data.handling_centavos', 10_000)
        ->assertJsonPath('data.delivery_centavos', 0)
        ->assertJsonPath('data.validity_hours', 72)
        ->assertJsonPath('data.terms', 'Updated default quote terms.')
        ->json('data');

    $this->withToken($token)
        ->postJson("/api/v1/admin/rfqs/{$cebuRfq->id}/quotations")
        ->assertCreated()
        ->assertJsonPath('data.markup_percent', '25.00')
        ->assertJsonPath('data.handling_centavos', 10_000)
        ->assertJsonPath('data.delivery_centavos', 30_000)
        ->assertJsonPath('data.total_centavos', 42_500);

    // Changing settings afterward must NOT mutate existing drafts
    $payload['default_markup_percent'] = '30.00';
    $this->withToken($token)->putJson('/api/v1/admin/settings', $payload)->assertOk();

    $this->withToken($token)
        ->getJson("/api/v1/admin/quotations/{$islandDraft['id']}")
        ->assertOk()
        ->assertJsonPath('data.markup_percent', '25.00');
});

it('rejects untrusted image URLs or tampered policy section IDs on PUT /api/v1/admin/settings', function () {
    $token = settingsAdminToken();
    $payload = $this->withToken($token)->getJson('/api/v1/admin/settings')->json('data');

    $badImage = $payload;
    $badImage['homepage']['hero_image'] = 'https://evil.example/malicious.jpg';
    $this->withToken($token)
        ->putJson('/api/v1/admin/settings', $badImage)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('homepage.hero_image');

    $badTerms = $payload;
    $badTerms['terms_sections'][2]['id'] = 'wrong-anchor-id';
    $this->withToken($token)
        ->putJson('/api/v1/admin/settings', $badTerms)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('terms_sections.2.id');
});
