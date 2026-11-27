<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(LazilyRefreshDatabase::class);

it('returns 422 for missing admin login credentials', function () {
    $this->postJson('/api/v1/admin/auth/login')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('returns 422 for malformed login input', function (array $input, string $field) {
    $this->postJson('/api/v1/admin/auth/login', array_replace([
        'email' => 'admin@example.test',
        'password' => 'a-valid-password',
    ], $input))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'email is not an email' => [['email' => 'invalid'], 'email'],
    'email is an array' => [['email' => ['admin@example.test']], 'email'],
    'password is an array' => [['password' => ['invalid']], 'password'],
    'password is too long' => [['password' => str_repeat('x', 1025)], 'password'],
    'device is an array' => [['device_name' => ['browser']], 'device_name'],
    'device is too long' => [['device_name' => str_repeat('x', 101)], 'device_name'],
]);

it('issues an expiring hashed bearer token to an active admin', function () {
    $this->travelTo(now()->setDate(2026, 11, 13)->startOfDay());
    config(['sanctum.expiration' => 720]);
    $admin = User::factory()->admin()->create(['email' => 'admin@example.test']);

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => '  ADMIN@EXAMPLE.TEST  ',
        'password' => 'password',
        'device_name' => 'Office browser',
        'is_admin' => false,
        'abilities' => ['*'],
    ])->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.expires_at', '2026-11-13T12:00:00.000000Z')
        ->assertJsonPath('data.user', [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => 'admin@example.test',
        ])
        ->assertHeaderMissing('Set-Cookie');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $token = PersonalAccessToken::findToken($response->json('data.access_token'));
    expect($token)->not->toBeNull()
        ->name->toBe('Office browser')
        ->abilities->toBe(['admin'])
        ->tokenable_id->toBe($admin->id);
    expect($token->token)->not->toBe($response->json('data.access_token'));
    $this->assertDatabaseCount('personal_access_tokens', 1);

    $this->withToken($response->json('data.access_token'))
        ->getJson('/api/v1/admin/auth/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
        ]])
        ->assertHeaderMissing('Set-Cookie');
});

it('preserves significant whitespace in passwords', function () {
    User::factory()->admin()->create([
        'email' => 'admin@example.test',
        'password' => '  a long secret passphrase  ',
    ]);

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => '  a long secret passphrase  ',
    ])->assertOk();
});

it('returns the same 422 error without issuing tokens for rejected logins', function (array $attributes, string $email, string $password) {
    User::factory()->create(array_replace([
        'email' => 'admin@example.test',
        'is_admin' => true,
        'is_active' => true,
    ], $attributes));

    $this->postJson('/api/v1/admin/auth/login', compact('email', 'password'))
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');

    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with([
    'wrong password' => [[], 'admin@example.test', 'wrong-password'],
    'unknown account' => [[], 'unknown@example.test', 'password'],
    'non-admin account' => [['is_admin' => false], 'admin@example.test', 'password'],
    'disabled account' => [['is_active' => false], 'admin@example.test', 'password'],
]);

it('rehashes an older admin password after a successful login', function () {
    $oldHash = Hash::make('a-secure-passphrase', ['rounds' => 4]);
    $admin = User::factory()->admin()->create(['password' => $oldHash]);
    config(['hashing.bcrypt.rounds' => 5]);
    Hash::forgetDrivers();

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => $admin->email,
        'password' => 'a-secure-passphrase',
    ])->assertOk();

    expect($admin->fresh()->password)->not->toBe($oldHash);
    expect(Hash::check('a-secure-passphrase', $admin->fresh()->password))->toBeTrue();
});

it('returns JSON 401 without a bearer token on every protected auth endpoint', function (string $method, string $path) {
    $this->json($method, '/api/v1/admin/auth'.$path)
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
})->with([
    'profile' => ['GET', '/me'],
    'logout' => ['POST', '/logout'],
    'logout all' => ['POST', '/logout-all'],
    'change password' => ['POST', '/password'],
    'tokens' => ['GET', '/tokens'],
    'revoke token' => ['DELETE', '/tokens/1'],
]);

it('returns JSON 401 even without an Accept header', function () {
    $this->get('/api/v1/admin/auth/me')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json');
});

it('prevents caching of rejected authenticated requests', function () {
    $response = $this->getJson('/api/v1/admin/auth/me')->assertUnauthorized();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('rejects a web authenticated admin when no bearer token is supplied', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/auth/me')
        ->assertUnauthorized();
});

it('rejects tokens supplied in query parameters instead of the bearer header', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('browser', ['admin'])->plainTextToken;

    $this->getJson('/api/v1/admin/auth/me?access_token='.urlencode($token))
        ->assertUnauthorized();
});

it('returns 401 for malformed bearer tokens', function (string $token) {
    $this->withToken($token)->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
})->with(['invalid', '999|does-not-exist']);

it('returns 401 when a bearer token has expired', function () {
    $this->freezeTime();
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('browser', ['admin'], now()->addMinutes(1));
    $this->travel(61)->seconds();

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
});

it('enforces the configured lifetime even for tokens without explicit expiry', function () {
    $this->freezeTime();
    config(['sanctum.expiration' => 720]);
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('legacy', ['admin']);
    $this->travel(721)->minutes();

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
});

it('returns 403 when an issued token no longer belongs to an active admin', function (array $change) {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('browser', ['admin']);
    $admin->forceFill($change)->save();

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/admin/auth/me')->assertForbidden();
})->with([
    'admin disabled' => [['is_active' => false]],
    'admin demoted' => [['is_admin' => false]],
]);

it('returns 403 for an admin token without the admin ability', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('read-only', ['catalog:read']);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/admin/auth/me')->assertForbidden();
});

it('revokes only the current token on logout', function () {
    $admin = User::factory()->admin()->create();
    $current = $admin->createToken('current', ['admin']);
    $other = $admin->createToken('other browser', ['admin']);

    $this->withToken($current->plainTextToken)
        ->postJson('/api/v1/admin/auth/logout')->assertNoContent();

    $this->assertModelMissing($current->accessToken);
    $this->assertModelExists($other->accessToken);
    $this->app['auth']->forgetGuards();
    $this->withToken($current->plainTextToken)
        ->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
});

it('revokes all of the current admin tokens without affecting other admins', function () {
    $admin = User::factory()->admin()->create();
    $current = $admin->createToken('current', ['admin']);
    $admin->createToken('other browser', ['admin']);
    $other = User::factory()->admin()->create()->createToken('unrelated', ['admin']);

    $this->withToken($current->plainTextToken)
        ->postJson('/api/v1/admin/auth/logout-all')->assertNoContent();

    expect($admin->tokens()->count())->toBe(0);
    $this->assertModelExists($other->accessToken);
    $this->app['auth']->forgetGuards();
    $this->withToken($current->plainTextToken)
        ->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
});

it('lists only the current admin tokens through a safe paginated collection', function () {
    $admin = User::factory()->admin()->create();
    $current = $admin->createToken('current', ['admin']);
    $otherBrowser = $admin->createToken('other browser', ['admin']);
    User::factory()->admin()->create()->createToken('private other admin', ['admin']);

    $this->withToken($current->plainTextToken)
        ->getJson('/api/v1/admin/auth/tokens')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('data.0.id', $otherBrowser->accessToken->id)
        ->assertJsonPath('data.0.is_current', false)
        ->assertJsonPath('data.1.is_current', true)
        ->assertJsonMissing(['device_name' => 'private other admin'])
        ->assertJsonMissingPath('data.0.token')
        ->assertJsonMissingPath('data.0.tokenable_id')
        ->assertJsonMissingPath('data.0.plainTextToken');
});

it('revokes a selected owned token', function () {
    $admin = User::factory()->admin()->create();
    $current = $admin->createToken('current', ['admin']);
    $other = $admin->createToken('other browser', ['admin']);

    $this->withToken($current->plainTextToken)
        ->deleteJson('/api/v1/admin/auth/tokens/'.$other->accessToken->id)
        ->assertNoContent();

    $this->assertModelMissing($other->accessToken);
    $this->assertModelExists($current->accessToken);
});

it('returns 404 when revoking another admin token', function () {
    $admin = User::factory()->admin()->create();
    $current = $admin->createToken('current', ['admin']);
    $other = User::factory()->admin()->create()->createToken('private', ['admin']);

    $this->withToken($current->plainTextToken)
        ->deleteJson('/api/v1/admin/auth/tokens/'.$other->accessToken->id)
        ->assertNotFound();

    $this->assertModelExists($other->accessToken);
});

it('returns 404 for missing or invalid token identifiers', function (string $id) {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('current', ['admin']);

    $this->withToken($token->plainTextToken)
        ->deleteJson('/api/v1/admin/auth/tokens/'.$id)->assertNotFound();
    $this->assertModelExists($token->accessToken);
})->with(['999999', 'not-an-id', '999999999999999999999999999999999999999999999999']);

it('throttles repeated login attempts for the same normalized email and IP', function () {
    $this->freezeTime();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@example.test', 'password' => 'wrong',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => ' ADMIN@EXAMPLE.TEST ', 'password' => 'wrong',
    ])->assertTooManyRequests()->assertHeader('Retry-After');

    $this->travel(61)->seconds();
    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.test', 'password' => 'wrong',
    ])->assertUnprocessable();
});

it('throttles password spraying across different emails from one IP', function () {
    $this->freezeTime();
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $this->postJson('/api/v1/admin/auth/login', [
            'email' => "account{$attempt}@example.test", 'password' => 'wrong',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'another@example.test', 'password' => 'wrong',
    ])->assertTooManyRequests();
});

it('allows bearer CORS preflight from the configured admin origin without credentials', function () {
    config(['cors.allowed_origins' => ['http://localhost:3001']]);

    $this->options('/api/v1/admin/auth/login', [], [
        'Origin' => 'http://localhost:3001',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'authorization,content-type',
    ])->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3001')
        ->assertHeaderMissing('Access-Control-Allow-Credentials');
});

it('does not echo an untrusted CORS origin', function () {
    config(['cors.allowed_origins' => ['http://localhost:3001']]);

    $this->options('/api/v1/admin/auth/login', [], [
        'Origin' => 'https://untrusted.example',
        'Access-Control-Request-Method' => 'POST',
    ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3001');
});

it('does not expose public account registration', function () {
    $this->postJson('/api/v1/admin/auth/register', [
        'email' => 'outsider@example.test',
        'password' => 'a-long-passphrase',
        'is_admin' => true,
    ])->assertNotFound();
});

it('does not expose the Sanctum cookie bootstrap endpoint', function () {
    $this->get('/sanctum/csrf-cookie')->assertNotFound()->assertHeaderMissing('Set-Cookie');
});

it('changes admin password after verifying current password and revokes other device tokens', function () {
    $admin = User::factory()->admin()->create(['password' => 'old-secret-pass']);
    $currentToken = $admin->createToken('Current laptop', ['admin'])->plainTextToken;
    $otherToken = $admin->createToken('Old phone', ['admin'])->plainTextToken;

    $this->withToken($currentToken)->postJson('/api/v1/admin/auth/password', [
        'current_password' => 'wrong-current',
        'password' => 'new-secret-pass',
        'password_confirmation' => 'new-secret-pass',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->withToken($currentToken)->postJson('/api/v1/admin/auth/password', [
        'current_password' => 'old-secret-pass',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->withToken($currentToken)->postJson('/api/v1/admin/auth/password', [
        'current_password' => 'old-secret-pass',
        'password' => '  new-secret-passphrase  ',
        'password_confirmation' => '  new-secret-passphrase  ',
    ])->assertNoContent();

    expect(Hash::check('  new-secret-passphrase  ', $admin->fresh()->password))->toBeTrue();
    expect($admin->tokens()->pluck('name')->all())->toBe(['Current laptop']);
    expect($otherToken)->not->toBe($currentToken);
});
