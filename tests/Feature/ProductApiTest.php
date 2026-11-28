<?php

use App\Models\Product;
use App\Models\ProductSlug;
use App\Models\User;
use App\Policies\ProductPolicy;
use App\Services\Admin\ProductService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

function productPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Fresh squid', 'category' => 'Fresh seafood', 'unit' => 'kg', 'status' => 'Draft',
        'description' => 'Fresh squid sourced from Bantayan Island on request.',
        'slug' => 'fresh-squid', 'meta_title' => 'Fresh squid | IslaSource Seafoods',
        'meta_description' => 'Request a quotation for fresh squid sourced from Bantayan Island.',
    ], $overrides);
}

function productAdminToken(): string
{
    return User::factory()->admin()->create()->createToken('products', ['admin'])->plainTextToken;
}

it('returns JSON 401 without a bearer token on every product endpoint', function (string $method, string $path) {
    $response = $this->json($method, '/api/v1/admin'.$path)
        ->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with([
    'list' => ['GET', '/products'], 'create' => ['POST', '/products'],
    'show' => ['GET', '/products/1'], 'update' => ['PATCH', '/products/1'],
    'archive' => ['DELETE', '/products/1'], 'restore' => ['POST', '/products/1/restore'],
    'upload' => ['POST', '/product-images'],
]);

it('returns 403 for an account or token without active admin access', function (array $flags, array $abilities) {
    $user = User::factory()->create($flags);
    $token = $user->createToken('products', $abilities)->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/products', productPayload())->assertForbidden();

    $this->assertDatabaseCount('products', 0);
})->with([
    'non admin' => [['is_admin' => false, 'is_active' => true], ['admin']],
    'disabled admin' => [['is_admin' => true, 'is_active' => false], ['admin']],
    'wrong ability' => [['is_admin' => true, 'is_active' => true], ['catalog:read']],
]);

it('returns 401 for web session access and invalid bearer authentication', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web')
        ->getJson('/api/v1/admin/products')->assertUnauthorized();

    $this->withToken('invalid')->getJson('/api/v1/admin/products')->assertUnauthorized();
});

it('calls the product policy for protected writes', function () {
    $this->instance(ProductPolicy::class, new class extends ProductPolicy
    {
        public function create(User $user): bool
        {
            return false;
        }
    });

    $this->withToken(productAdminToken())->postJson('/api/v1/admin/products', productPayload())->assertForbidden();

    $this->assertDatabaseCount('products', 0);
});

it('creates a persisted product with exact safe resource fields and defaults', function () {
    $this->travelTo('2026-11-13 12:00:00');

    $response = $this->withToken(productAdminToken())->postJson('/api/v1/admin/products', productPayload([
        'slug' => ' FRESH-SQUID ', 'id' => 999, 'deleted_at' => '2020-01-01',
        'created_at' => '2020-01-01', 'supplier_cost' => 1, 'stock' => 100, 'previous_slugs' => ['client-history'],
    ]))->assertCreated()->assertJsonPath('data.slug', 'fresh-squid')
        ->assertJsonPath('data.specs', '')->assertJsonPath('data.og_title', '')
        ->assertJsonPath('data.og_description', '')->assertJsonPath('data.og_image', '')
        ->assertJsonPath('data.canonical_url', '')->assertJsonPath('data.image', '/images/squid.jpg')
        ->assertJsonPath('data.image_alt', 'Fresh squid')->assertJsonPath('data.indexable', true)
        ->assertJsonPath('data.min_order_quantity', '1.000')
        ->assertJsonPath('data.availability_note', 'Sourced on request. Availability and final pricing are confirmed after your quote request.')
        ->assertJsonPath('data.previous_slugs', [])->assertJsonPath('data.deleted_at', null)
        ->assertJsonPath('data.created_at', '2026-11-13T12:00:00.000000Z');

    expect($response->json('data.id'))->toBeInt()->not->toBe(999);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect(array_keys($response->json('data')))->toEqualCanonicalizing([
        'id', 'name', 'category', 'unit', 'status', 'description', 'specs', 'slug',
        'meta_title', 'meta_description', 'og_title', 'og_description', 'image', 'image_alt',
        'og_image', 'canonical_url', 'indexable', 'min_order_quantity', 'availability_note',
        'gallery_images', 'previous_slugs', 'created_at', 'updated_at', 'deleted_at',
    ]);
    $this->assertDatabaseHas('products', ['id' => $response->json('data.id'), 'slug' => 'fresh-squid', 'deleted_at' => null]);
    $this->assertDatabaseHas('product_slugs', ['product_id' => $response->json('data.id'), 'slug' => 'fresh-squid']);
    $this->assertDatabaseMissing('product_slugs', ['slug' => 'client-history']);
});

it('returns 422 with required product fields on an empty create', function () {
    $this->withToken(productAdminToken())->postJson('/api/v1/admin/products', [])
        ->assertUnprocessable()->assertJsonValidationErrors([
            'name', 'category', 'unit', 'status', 'description', 'slug', 'meta_title', 'meta_description',
        ])->assertJsonPath('errors.name.0', 'The name field is required.');

    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('product_slugs', 0);
});

it('returns 422 for invalid product values without persisting changes', function (string $field, mixed $value) {
    $this->withToken(productAdminToken())->postJson('/api/v1/admin/products', productPayload([$field => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('product_slugs', 0);
})->with([
    'long name' => ['name', str_repeat('a', 101)], 'invalid category' => ['category', 'Fish'],
    'invalid unit' => ['unit', 'box'], 'invalid status' => ['status', 'public'],
    'long description' => ['description', str_repeat('a', 20001)], 'long specs' => ['specs', str_repeat('a', 5001)],
    'slug spaces' => ['slug', 'fresh squid'], 'slug underscore' => ['slug', 'fresh_squid'],
    'slug repeated hyphen' => ['slug', 'fresh--squid'], 'slug leading hyphen' => ['slug', '-fresh-squid'],
    'slug trailing hyphen' => ['slug', 'fresh-squid-'], 'long slug' => ['slug', str_repeat('a', 161)],
    'long meta title' => ['meta_title', str_repeat('a', 71)], 'long meta description' => ['meta_description', str_repeat('a', 161)],
    'long OG title' => ['og_title', str_repeat('a', 101)], 'long OG description' => ['og_description', str_repeat('a', 201)],
    'long alt' => ['image_alt', str_repeat('a', 256)], 'FTP canonical' => ['canonical_url', 'ftp://example.test/fresh-squid'],
    'relative canonical' => ['canonical_url', '/products/fresh-squid'], 'long canonical' => ['canonical_url', 'https://example.test/'.str_repeat('a', 2030)],
    'invalid indexability' => ['indexable', 'yes'], 'zero minimum' => ['min_order_quantity', '0'],
    'negative minimum' => ['min_order_quantity', '-1'], 'over maximum minimum' => ['min_order_quantity', '10000000'],
    'excess decimal scale' => ['min_order_quantity', '1.0001'], 'scientific minimum' => ['min_order_quantity', '1e3'],
    'null minimum' => ['min_order_quantity', null], 'long availability' => ['availability_note', str_repeat('a', 501)],
    'array optional text' => ['specs', ['not text']],
]);

it('persists complete editor data and preserves omitted fields in partial PATCH', function () {
    $product = Product::factory()->create(['name' => 'Original squid', 'slug' => 'original-squid']);
    $token = productAdminToken();

    $this->withToken($token)->patchJson('/api/v1/admin/products/'.$product->id, productPayload([
        'name' => 'Whole squid', 'slug' => 'whole-squid', 'category' => 'Frozen seafood', 'status' => 'Published',
        'unit' => 'pack', 'specs' => 'Whole, cleaned.', 'og_title' => 'Whole squid', 'og_description' => 'Get a quote.',
        'image' => '/images/crab.jpg', 'image_alt' => 'Whole squid on ice', 'og_image' => '/images/scallops.jpg',
        'canonical_url' => 'https://example.test/products/whole-squid', 'indexable' => false,
        'min_order_quantity' => '2.125', 'availability_note' => 'Seasonal sourcing.',
    ]))->assertOk()->assertJsonPath('data.previous_slugs', ['original-squid'])->assertJsonPath('data.min_order_quantity', '2.125');

    $this->withToken($token)->patchJson('/api/v1/admin/products/'.$product->id, [
        'name' => 'Cleaned squid', 'image_alt' => ' ', 'id' => 777, 'deleted_at' => now()->toJSON(),
    ])->assertOk()->assertJsonPath('data.name', 'Cleaned squid')->assertJsonPath('data.image_alt', 'Cleaned squid')
        ->assertJsonPath('data.status', 'Published')->assertJsonPath('data.specs', 'Whole, cleaned.')
        ->assertJsonPath('data.indexable', false)->assertJsonPath('data.previous_slugs', ['original-squid']);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Cleaned squid', 'slug' => 'whole-squid',
        'status' => 'Published', 'min_order_quantity' => '2.125', 'deleted_at' => null]);
    expect($product->fresh()->canonical_url)->toBe('https://example.test/products/whole-squid');
});

it('clears optional text in PATCH and serializes null text as empty strings', function () {
    $product = Product::factory()->create(['specs' => 'Old specs', 'og_title' => 'Old title', 'canonical_url' => 'https://example.test/old']);

    $this->withToken(productAdminToken())->patchJson('/api/v1/admin/products/'.$product->id, [
        'specs' => '', 'og_title' => null, 'canonical_url' => ' ', 'min_order_quantity' => '0.001',
    ])->assertOk()->assertJsonPath('data.specs', '')->assertJsonPath('data.og_title', '')
        ->assertJsonPath('data.canonical_url', '')->assertJsonPath('data.min_order_quantity', '0.001');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'specs' => null, 'canonical_url' => null]);
});

it('returns 422 for invalid partial PATCH and leaves the product unchanged', function () {
    $product = Product::factory()->create(['name' => 'Unchanged', 'slug' => 'unchanged']);

    $this->withToken(productAdminToken())->patchJson('/api/v1/admin/products/'.$product->id, [
        'name' => null, 'slug' => 'changed', 'min_order_quantity' => '0',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'min_order_quantity']);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Unchanged', 'slug' => 'unchanged']);
    $this->assertDatabaseMissing('product_slugs', ['slug' => 'changed']);
});

it('reserves current historical and archived slugs and lets their owner reuse them', function () {
    $owner = Product::factory()->create(['slug' => 'original-squid']);
    $other = Product::factory()->create(['slug' => 'other-squid']);
    $token = productAdminToken();

    $this->withToken($token)->patchJson('/api/v1/admin/products/'.$owner->id, ['slug' => 'new-squid'])
        ->assertOk()->assertJsonPath('data.previous_slugs', ['original-squid']);
    $this->withToken($token)->postJson('/api/v1/admin/products', productPayload(['slug' => 'original-squid']))
        ->assertUnprocessable()->assertJsonValidationErrors('slug');
    $this->withToken($token)->patchJson('/api/v1/admin/products/'.$other->id, ['slug' => 'new-squid', 'name' => 'Should roll back'])
        ->assertUnprocessable()->assertJsonValidationErrors('slug');
    $this->withToken($token)->patchJson('/api/v1/admin/products/'.$owner->id, ['slug' => 'original-squid'])
        ->assertOk()->assertJsonPath('data.previous_slugs', ['new-squid']);
    $this->withToken($token)->deleteJson('/api/v1/admin/products/'.$owner->id)->assertNoContent();
    $this->withToken($token)->postJson('/api/v1/admin/products', productPayload(['slug' => 'new-squid']))
        ->assertUnprocessable()->assertJsonValidationErrors('slug');

    $this->assertDatabaseCount('products', 2);
    $this->assertDatabaseCount('product_slugs', 3);
    $this->assertDatabaseHas('products', ['id' => $other->id, 'slug' => 'other-squid']);
});

it('rolls back a create when an atomic slug claim belongs to another product', function () {
    $owner = Product::factory()->create(['slug' => 'reserved-squid']);

    expect(fn () => app(ProductService::class)->create(productPayload(['slug' => 'reserved-squid'])))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('products', 1);
    $this->assertDatabaseCount('product_slugs', 1);
    $this->assertDatabaseHas('product_slugs', ['product_id' => $owner->id, 'slug' => 'reserved-squid']);
});

it('rolls back a newly reserved slug if saving the product fails', function () {
    $product = Product::factory()->create(['slug' => 'before-failure']);
    Product::updating(function (Product $updating): void {
        if ($updating->slug === 'after-failure') {
            throw new RuntimeException('Persistence failed.');
        }
    });

    try {
        expect(fn () => app(ProductService::class)->update($product, ['slug' => 'after-failure']))
            ->toThrow(RuntimeException::class, 'Persistence failed.');
    } finally {
        Product::flushEventListeners();
    }

    $this->assertDatabaseHas('products', ['id' => $product->id, 'slug' => 'before-failure']);
    $this->assertDatabaseMissing('product_slugs', ['slug' => 'after-failure']);
});

it('enforces the unique slug registry at the database boundary', function () {
    $owner = Product::factory()->create(['slug' => 'registry-squid']);
    $other = Product::factory()->create(['slug' => 'second-squid']);

    expect(fn () => DB::transaction(fn () => ProductSlug::query()->create([
        'slug' => $owner->slug, 'product_id' => $other->id,
    ])))->toThrow(UniqueConstraintViolationException::class);

    $this->assertDatabaseHas('product_slugs', ['slug' => 'registry-squid', 'product_id' => $owner->id]);
});

it('returns slug validation and rolls back a create after a database reservation race', function () {
    ProductSlug::creating(function (): void {
        throw new UniqueConstraintViolationException('sqlite', 'insert into product_slugs (slug) values (?)', ['race-squid'], new PDOException('Duplicate slug reservation'));
    });

    try {
        $this->withToken(productAdminToken())->postJson('/api/v1/admin/products', productPayload(['slug' => 'race-squid']))
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
    } finally {
        ProductSlug::flushEventListeners();
    }

    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('product_slugs', 0);
});

it('keeps product details unchanged after a database reservation race during update', function () {
    $product = Product::factory()->create(['slug' => 'original-squid', 'name' => 'Original name']);
    ProductSlug::creating(function (): void {
        throw new UniqueConstraintViolationException('sqlite', 'insert into product_slugs (slug) values (?)', ['race-squid'], new PDOException('Duplicate slug reservation'));
    });

    try {
        $this->withToken(productAdminToken())->patchJson('/api/v1/admin/products/'.$product->id, ['slug' => 'race-squid', 'name' => 'Changed name'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
    } finally {
        ProductSlug::flushEventListeners();
    }

    $this->assertDatabaseHas('products', ['id' => $product->id, 'slug' => 'original-squid', 'name' => 'Original name']);
    $this->assertDatabaseCount('product_slugs', 1);
});

it('shows detail history and excludes archived products until restored', function () {
    $product = Product::factory()->create(['slug' => 'first-squid']);
    $token = productAdminToken();
    $this->withToken($token)->patchJson('/api/v1/admin/products/'.$product->id, ['slug' => 'second-squid'])->assertOk();

    $this->withToken($token)->getJson('/api/v1/admin/products/'.$product->id)->assertOk()->assertJsonPath('data.previous_slugs', ['first-squid']);
    $this->withToken($token)->deleteJson('/api/v1/admin/products/'.$product->id)->assertNoContent();
    $this->assertSoftDeleted($product);
    $this->withToken($token)->getJson('/api/v1/admin/products/'.$product->id)->assertNotFound();
    $this->withToken($token)->patchJson('/api/v1/admin/products/'.$product->id, ['name' => 'Hidden edit'])->assertNotFound();
    $this->withToken($token)->deleteJson('/api/v1/admin/products/'.$product->id)->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/admin/products')->assertOk()->assertJsonCount(0, 'data');
    $this->withToken($token)->getJson('/api/v1/admin/products?archived=1')->assertOk()->assertJsonPath('data.0.id', $product->id);
    $this->withToken($token)->postJson('/api/v1/admin/products/'.$product->id.'/restore')
        ->assertOk()->assertJsonPath('data.id', $product->id)->assertJsonPath('data.deleted_at', null)
        ->assertJsonPath('data.previous_slugs', ['first-squid']);
    $this->withToken($token)->postJson('/api/v1/admin/products/'.$product->id.'/restore')->assertNotFound();

    $this->assertNotSoftDeleted($product->fresh());
    $this->assertDatabaseCount('product_slugs', 2);
});

it('returns 404 for unknown or invalid product identifiers', function (string $id) {
    $this->withToken(productAdminToken())->getJson('/api/v1/admin/products/'.$id)->assertNotFound();
})->with(['999999', 'not-an-id', '999999999999999999999999999999999999999999999999']);

it('filters and paginates by newest ID without loading histories', function () {
    Product::factory()->create(['name' => 'Dried fish', 'slug' => 'dried-fish', 'category' => 'Dried seafood', 'status' => 'Draft']);
    $first = Product::factory()->create(['name' => 'Fresh squid', 'slug' => 'squid-one', 'category' => 'Fresh seafood', 'status' => 'Published']);
    $second = Product::factory()->create(['name' => 'Fresh crab', 'slug' => 'squid-two', 'category' => 'Fresh seafood', 'status' => 'Published']);
    Product::factory()->create(['name' => 'Archived squid', 'category' => 'Fresh seafood', 'status' => 'Published', 'deleted_at' => now()]);
    $token = productAdminToken();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->withToken($token)->getJson('/api/v1/admin/products?search=squid&category=Fresh%20seafood&status=Published&per_page=1&page=2')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.last_page', 2)
        ->assertJsonMissingPath('data.0.previous_slugs')->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);

    expect(implode(' ', $queries))->not->toContain('product_slugs');
    $this->withToken($token)->getJson('/api/v1/admin/products?search=Fresh%20seafood')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $second->id);
});

it('returns an empty page for a SQL injection shaped search', function () {
    Product::factory()->create();

    $this->withToken(productAdminToken())->getJson('/api/v1/admin/products?'.http_build_query(['search' => "' OR 1=1 --"]))
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
});

it('treats zero as meaningful search and image alt text', function () {
    Product::factory()->create(['name' => 'Squid 10', 'slug' => 'squid-10']);
    Product::factory()->create(['name' => 'Crab', 'slug' => 'crab']);
    $token = productAdminToken();

    $this->withToken($token)->getJson('/api/v1/admin/products?search=0')->assertOk()->assertJsonCount(1, 'data');
    $this->withToken($token)->postJson('/api/v1/admin/products', productPayload(['image_alt' => '0']))
        ->assertCreated()->assertJsonPath('data.image_alt', '0');
});

it('returns 422 for invalid list parameters', function (string $key, mixed $value) {
    $this->withToken(productAdminToken())->getJson('/api/v1/admin/products?'.http_build_query([$key => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($key);
})->with([
    'long search' => ['search', str_repeat('a', 101)], 'search array' => ['search', ['squid']],
    'unknown category' => ['category', 'Shellfish'], 'unknown status' => ['status', 'Live'],
    'invalid archive flag' => ['archived', 'all'], 'zero page' => ['page', 0],
    'negative page' => ['page', -1], 'fractional page' => ['page', '1.5'],
    'zero per page' => ['per_page', 0], 'over maximum per page' => ['per_page', 101],
]);

it('accepts maximum validation boundaries and a page size of 100', function () {
    $this->withToken(productAdminToken())->postJson('/api/v1/admin/products', productPayload([
        'name' => str_repeat('n', 100), 'slug' => str_repeat('s', 160), 'description' => str_repeat('d', 20000),
        'specs' => str_repeat('s', 5000), 'meta_title' => str_repeat('t', 70), 'meta_description' => str_repeat('m', 160),
        'og_title' => str_repeat('o', 100), 'og_description' => str_repeat('g', 200), 'image_alt' => str_repeat('a', 255),
        'availability_note' => str_repeat('v', 500), 'min_order_quantity' => '9999999.999',
        'canonical_url' => 'http://example.test/'.str_repeat('c', 2028),
    ]))->assertCreated()->assertJsonPath('data.min_order_quantity', '9999999.999');

    $this->getJson('/api/v1/admin/products?per_page=100')->assertOk()->assertJsonPath('meta.per_page', 100);
});

it('paginates public seafood at 12 per page by default and includes published category counts', function () {
    Product::factory()->count(10)->create(['category' => 'Fresh seafood', 'status' => 'Published']);
    Product::factory()->count(5)->create(['category' => 'Dried seafood', 'status' => 'Published']);
    Product::factory()->create(['category' => 'Frozen seafood', 'status' => 'Draft']);

    $this->getJson('/api/v1/seafood')
        ->assertOk()
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 12)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 15)
        ->assertJsonPath('meta.category_counts', [
            'Dried seafood' => 5,
            'Fresh seafood' => 10,
        ]);

    $this->getJson('/api/v1/seafood?page=2')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 12)
        ->assertJsonPath('meta.total', 15);
});

it('uses the existing admin API rate limit for product requests', function () {
    $this->freezeTime();
    $this->withToken(productAdminToken());
    for ($request = 0; $request < 120; $request++) {
        $this->getJson('/api/v1/admin/products')->assertOk();
    }

    $this->getJson('/api/v1/admin/products')->assertTooManyRequests()->assertHeader('Retry-After');
});

it('allows PATCH and PUT CORS preflight from the configured admin origin', function (string $method) {
    config(['cors.allowed_origins' => ['http://localhost:3001']]);

    $response = $this->options('/api/v1/admin/products/1', [], [
        'Origin' => 'http://localhost:3001', 'Access-Control-Request-Method' => $method,
        'Access-Control-Request-Headers' => 'authorization,content-type',
    ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3001')
        ->assertHeaderMissing('Access-Control-Allow-Credentials');

    expect($response->headers->get('Access-Control-Allow-Methods'))->toContain($method);
})->with(['PATCH', 'PUT']);
