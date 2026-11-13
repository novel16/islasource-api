<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(LazilyRefreshDatabase::class);

it('authorizes token deletion only for its active admin owner', function (bool $admin, bool $active, bool $owner, bool $allowed) {
    $user = User::factory()->create(['is_admin' => $admin, 'is_active' => $active]);
    $tokenOwner = $owner ? $user : User::factory()->admin()->create();
    $token = $tokenOwner->createToken('browser', ['admin'])->accessToken;

    expect(Gate::forUser($user)->allows('delete', $token))->toBe($allowed);
})->with([
    'active admin owns token' => [true, true, true, true],
    'different admin owns token' => [true, true, false, false],
    'owner is disabled' => [true, false, true, false],
    'owner is not admin' => [false, true, true, false],
]);
