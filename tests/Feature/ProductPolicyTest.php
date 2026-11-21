<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(LazilyRefreshDatabase::class);

it('authorizes every product action only for active admins', function (bool $admin, bool $active, bool $allowed) {
    $user = User::factory()->create(['is_admin' => $admin, 'is_active' => $active]);
    $product = Product::factory()->make();

    foreach (['viewAny', 'create'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, Product::class))->toBe($allowed);
    }
    foreach (['view', 'update', 'delete', 'restore'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $product))->toBe($allowed);
    }
    expect(Gate::forUser($user)->allows('forceDelete', $product))->toBeFalse();
})->with([
    'active admin' => [true, true, true], 'disabled admin' => [true, false, false],
    'active customer' => [false, true, false], 'disabled customer' => [false, false, false],
]);
