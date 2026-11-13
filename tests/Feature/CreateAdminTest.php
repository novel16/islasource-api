<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

it('creates an active admin with a normalized email and hashed hidden password', function () {
    $this->artisan('admin:create', [
        '--name' => 'IslaSource Admin',
        '--email' => ' ADMIN@EXAMPLE.TEST ',
    ])
        ->expectsQuestion('Password (at least 12 characters)', 'a-safe-admin-passphrase')
        ->expectsQuestion('Confirm password', 'a-safe-admin-passphrase')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'name' => 'IslaSource Admin',
        'email' => 'admin@example.test',
        'is_admin' => true,
        'is_active' => true,
    ]);
    $admin = User::where('email', 'admin@example.test')->firstOrFail();
    expect(Hash::check('a-safe-admin-passphrase', $admin->password))->toBeTrue();
    expect($admin->password)->not->toBe('a-safe-admin-passphrase');
});

it('rejects weak or unconfirmed admin passwords without creating an account', function (string $password, string $confirmation) {
    $this->artisan('admin:create', [
        '--name' => 'IslaSource Admin',
        '--email' => 'admin@example.test',
    ])
        ->expectsQuestion('Password (at least 12 characters)', $password)
        ->expectsQuestion('Confirm password', $confirmation)
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
})->with([
    'too short' => ['short', 'short'],
    'confirmation mismatch' => ['a-safe-admin-passphrase', 'different-passphrase'],
    'bcrypt byte limit' => [str_repeat('🌊', 20), str_repeat('🌊', 20)],
]);

it('does not elevate or overwrite an existing user', function () {
    $user = User::factory()->create(['email' => 'existing@example.test']);
    $oldPassword = $user->password;

    $this->artisan('admin:create', [
        '--name' => 'Replacement Admin',
        '--email' => 'existing@example.test',
    ])
        ->expectsQuestion('Password (at least 12 characters)', 'a-safe-admin-passphrase')
        ->expectsQuestion('Confirm password', 'a-safe-admin-passphrase')
        ->assertFailed();

    expect($user->fresh())->is_admin->toBeFalse()
        ->password->toBe($oldPassword)
        ->name->toBe($user->name);
    $this->assertDatabaseCount('users', 1);
});

it('fails safely in non-interactive mode instead of creating a default admin', function () {
    $this->artisan('admin:create', ['--no-interaction' => true])->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

it('hashes hash-shaped plaintext instead of accepting it as the stored password hash', function () {
    $enteredPassword = Hash::make('short');

    $this->artisan('admin:create', [
        '--name' => 'IslaSource Admin',
        '--email' => 'admin@example.test',
    ])
        ->expectsQuestion('Password (at least 12 characters)', $enteredPassword)
        ->expectsQuestion('Confirm password', $enteredPassword)
        ->assertSuccessful();

    $admin = User::where('email', 'admin@example.test')->firstOrFail();
    expect(Hash::check($enteredPassword, $admin->password))->toBeTrue();
    expect(Hash::check('short', $admin->password))->toBeFalse();
});
