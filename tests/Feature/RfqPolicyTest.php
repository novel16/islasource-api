<?php

use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(LazilyRefreshDatabase::class);

it('authorizes RFQ read and update actions only for active admins', function (bool $admin, bool $active, bool $allowed) {
    $user = User::factory()->create(['is_admin' => $admin, 'is_active' => $active]);
    $rfq = Rfq::factory()->make();

    expect(Gate::forUser($user)->allows('viewAny', Rfq::class))->toBe($allowed);

    foreach (['view', 'update'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $rfq))->toBe($allowed);
    }

    expect(Gate::forUser($user)->allows('create', Rfq::class))->toBeFalse();

    foreach (['delete', 'restore', 'forceDelete'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $rfq))->toBeFalse();
    }
})->with([
    'active admin' => [true, true, true],
    'disabled admin' => [true, false, false],
    'active customer' => [false, true, false],
    'disabled customer' => [false, false, false],
]);
