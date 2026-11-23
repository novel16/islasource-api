<?php

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(LazilyRefreshDatabase::class);

it('authorizes quotation actions only for active admins and restricts delete to Draft quotations', function (bool $admin, bool $active, bool $allowed) {
    $user = User::factory()->create(['is_admin' => $admin, 'is_active' => $active]);
    $draft = Quotation::factory()->make(['status' => 'Draft']);
    $sent = Quotation::factory()->make(['status' => 'Sent']);

    foreach (['viewAny', 'create'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, Quotation::class))->toBe($allowed);
    }

    foreach (['view', 'update'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $draft))->toBe($allowed);
        expect(Gate::forUser($user)->allows($ability, $sent))->toBe($allowed);
    }

    expect(Gate::forUser($user)->allows('delete', $draft))->toBe($allowed);
    expect(Gate::forUser($user)->allows('delete', $sent))->toBeFalse();
    expect(Gate::forUser($user)->allows('forceDelete', $draft))->toBeFalse();
})->with([
    'active admin' => [true, true, true],
    'disabled admin' => [true, false, false],
    'active customer' => [false, true, false],
    'disabled customer' => [false, false, false],
]);
