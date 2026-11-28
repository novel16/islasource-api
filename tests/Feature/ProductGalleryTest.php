<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

function galleryAdminToken(): string
{
    return User::factory()->admin()->create()->createToken('gallery', ['admin'])->plainTextToken;
}

function baseGalleryProductPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Gahot Dried Fish',
        'category' => 'Dried seafood',
        'unit' => 'kg',
        'status' => 'Published',
        'description' => 'Traditional sun-dried gahot from Bantayan Island.',
        'slug' => 'gahot-dried-fish',
        'meta_title' => 'Gahot Dried Fish | IslaSource Seafoods',
        'meta_description' => 'Request a quote for sun-dried gahot from Bantayan Island.',
        'image' => '/images/dried-fish.jpg',
        'image_alt' => 'Sun-dried gahot on drying rack',
    ], $overrides);
}

it('persists up to 7 gallery images on product detail while keeping public and admin list endpoints single-image without extra queries', function () {
    Storage::fake('public');
    $token = galleryAdminToken();

    $uploadedPath = $this->withToken($token)
        ->post('/api/v1/admin/product-images', [
            'image' => UploadedFile::fake()->createWithContent(
                'gahot_closeup.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7m0AAAAASUVORK5CYII='),
            ),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.image');

    $created = $this->withToken($token)
        ->postJson('/api/v1/admin/products', baseGalleryProductPayload([
            'gallery_images' => [
                ['image' => $uploadedPath, 'image_alt' => 'Close-up of salted gahot'],
                ['image' => '/images/squid.jpg', 'image_alt' => ''],
            ],
        ]))
        ->assertCreated()
        ->assertJsonCount(2, 'data.gallery_images')
        ->assertJsonPath('data.gallery_images.0.image', $uploadedPath)
        ->assertJsonPath('data.gallery_images.0.image_alt', 'Close-up of salted gahot')
        ->assertJsonPath('data.gallery_images.1.image', '/images/squid.jpg')
        ->assertJsonPath('data.gallery_images.1.image_alt', 'Gahot Dried Fish')
        ->json('data');

    // Public catalog list must NOT load product_images or expose gallery_images
    $listQueries = [];
    DB::listen(function ($query) use (&$listQueries): void {
        $listQueries[] = $query->sql;
    });

    $this->getJson('/api/v1/seafood')
        ->assertOk()
        ->assertJsonPath('data.0.image', '/images/dried-fish.jpg')
        ->assertJsonMissingPath('data.0.gallery_images');

    expect(implode(' ', $listQueries))->not->toContain('product_images');

    // Public product detail MUST expose gallery_images in sort_order
    $this->getJson('/api/v1/seafood/gahot-dried-fish')
        ->assertOk()
        ->assertJsonPath('data.image', '/images/dried-fish.jpg')
        ->assertJsonCount(2, 'data.gallery_images')
        ->assertJsonPath('data.gallery_images.0.image', $uploadedPath)
        ->assertJsonPath('data.gallery_images.1.image', '/images/squid.jpg');

    // Partial PATCH omitting gallery_images preserves existing gallery
    $this->withToken($token)
        ->patchJson('/api/v1/admin/products/'.$created['id'], ['specs' => 'Packed per 1 kg'])
        ->assertOk()
        ->assertJsonCount(2, 'data.gallery_images');

    // Reordering and clearing gallery_images works cleanly
    $this->withToken($token)
        ->patchJson('/api/v1/admin/products/'.$created['id'], [
            'gallery_images' => [
                ['image' => '/images/crab.jpg', 'image_alt' => 'Packed view'],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data.gallery_images')
        ->assertJsonPath('data.gallery_images.0.image', '/images/crab.jpg');

    $this->assertDatabaseCount('product_images', 1);
});

it('rejects more than 7 extra gallery images or invalid image paths with 422', function () {
    $token = galleryAdminToken();

    $eightExtra = array_fill(0, 8, ['image' => '/images/squid.jpg', 'image_alt' => 'Extra']);
    $this->withToken($token)
        ->postJson('/api/v1/admin/products', baseGalleryProductPayload(['gallery_images' => $eightExtra]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('gallery_images');

    $this->withToken($token)
        ->postJson('/api/v1/admin/products', baseGalleryProductPayload([
            'gallery_images' => [['image' => 'https://evil.example/photo.jpg', 'image_alt' => 'Bad']],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('gallery_images.0.image');

    $this->assertDatabaseCount('products', 0);
});
