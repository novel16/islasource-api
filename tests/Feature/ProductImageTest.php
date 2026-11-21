<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

function productRasterUpload(string $name = 'squid.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7m0AAAAASUVORK5CYII='));
}

it('uploads raster contents with a generated safe name and accepts the returned path on products', function () {
    Storage::fake('public');
    $token = User::factory()->admin()->create()->createToken('products', ['admin'])->plainTextToken;

    $upload = $this->withToken($token)->post('/api/v1/admin/product-images', [
        'image' => productRasterUpload('../unsafe-original.png'),
    ], ['Accept' => 'application/json'])->assertCreated();
    $path = $upload->json('data.image');

    expect($path)->toMatch('#^/storage/products/[A-Za-z0-9]{40}\.png$#');
    Storage::disk('public')->assertExists(substr($path, strlen('/storage/')));
    expect(Storage::disk('public')->allFiles())->toHaveCount(1);
    $product = Product::factory()->create();
    $this->withToken($token)->patchJson('/api/v1/admin/products/'.$product->id, ['image' => $path, 'og_image' => $path])
        ->assertOk()->assertJsonPath('data.image', $path)->assertJsonPath('data.og_image', $path);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'image' => $path, 'og_image' => $path]);
    $this->withToken($token)->deleteJson('/api/v1/admin/products/'.$product->id)->assertNoContent();
    Storage::disk('public')->assertExists(substr($path, strlen('/storage/')));
});

it('returns 422 for missing or unsafe raster uploads and stores no files', function (string $kind) {
    Storage::fake('public');
    $token = User::factory()->admin()->create()->createToken('products', ['admin'])->plainTextToken;
    $spoofed = UploadedFile::fake()->createWithContent('squid.jpg', '<?php echo "not an image";');
    $image = match ($kind) {
        'missing' => null,
        'SVG' => UploadedFile::fake()->createWithContent('squid.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'spoofed JPEG' => new UploadedFile($spoofed->getPathname(), 'squid.jpg', 'image/jpeg', null, true),
        'GIF' => UploadedFile::fake()->createWithContent('squid.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==')),
        'over limit' => productRasterUpload()->size(5121),
    };

    $this->withToken($token)->post('/api/v1/admin/product-images', $image ? ['image' => $image] : [], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('image');

    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with(['missing', 'SVG', 'spoofed JPEG', 'GIF', 'over limit']);

it('accepts an image at the exact 5 MiB limit', function () {
    Storage::fake('public');
    $token = User::factory()->admin()->create()->createToken('products', ['admin'])->plainTextToken;

    $response = $this->withToken($token)->post('/api/v1/admin/product-images', [
        'image' => productRasterUpload()->size(5120),
    ], ['Accept' => 'application/json'])->assertCreated();

    Storage::disk('public')->assertExists(substr($response->json('data.image'), strlen('/storage/')));
});

it('returns 403 on uploads for non admins', function () {
    Storage::fake('public');
    $token = User::factory()->create(['is_admin' => false, 'is_active' => true])->createToken('products', ['admin'])->plainTextToken;

    $this->withToken($token)->post('/api/v1/admin/product-images', ['image' => productRasterUpload()], ['Accept' => 'application/json'])
        ->assertForbidden();

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('rejects arbitrary product and OG image paths with 422', function (string $path) {
    Storage::fake('public');
    $product = Product::factory()->create();
    $token = User::factory()->admin()->create()->createToken('products', ['admin'])->plainTextToken;

    $this->withToken($token)->patchJson('/api/v1/admin/products/'.$product->id, ['image' => $path, 'og_image' => $path])
        ->assertUnprocessable()->assertJsonValidationErrors(['image', 'og_image']);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'image' => '/images/squid.jpg']);
})->with([
    'remote' => 'https://example.test/squid.jpg', 'protocol relative' => '//example.test/squid.jpg',
    'unapproved asset' => '/images/random.jpg', 'traversal' => '/storage/products/../../secret.jpg',
    'encoded traversal' => '/storage/products/%2e%2e%2fsecret.jpg', 'backslash traversal' => '/storage/products/..\\secret.jpg',
    'query suffix' => '/images/squid.jpg?x=1', 'missing generated upload' => '/storage/products/'.str_repeat('a', 40).'.jpg',
    'arbitrary upload name' => '/storage/products/unsafe.jpg', 'SVG upload path' => '/storage/products/'.str_repeat('b', 40).'.svg',
]);

it('rejects non raster contents even when the generated storage path exists', function () {
    Storage::fake('public');
    $relative = 'products/'.str_repeat('c', 40).'.jpg';
    Storage::disk('public')->put($relative, 'not raster contents');
    $product = Product::factory()->create();
    $token = User::factory()->admin()->create()->createToken('products', ['admin'])->plainTextToken;

    $this->withToken($token)->patchJson('/api/v1/admin/products/'.$product->id, ['image' => '/storage/'.$relative])
        ->assertUnprocessable()->assertJsonValidationErrors('image');

    Storage::disk('public')->assertExists($relative);
});

it('returns 500 rather than a successful path when image storage fails', function () {
    Storage::fake('public');
    Storage::disk('public')->put('products', 'An existing file prevents directory creation.');
    $token = User::factory()->admin()->create()->createToken('products', ['admin'])->plainTextToken;

    $this->withToken($token)->post('/api/v1/admin/product-images', ['image' => productRasterUpload()], ['Accept' => 'application/json'])
        ->assertStatus(500)->assertJsonMissingPath('data.image');

    Storage::disk('public')->assertExists('products');
});
