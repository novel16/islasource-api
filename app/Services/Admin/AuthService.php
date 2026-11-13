<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use SensitiveParameter;

class AuthService
{
    public function login(string $email, #[SensitiveParameter] string $password, string $deviceName): NewAccessToken
    {
        return (new Timebox)->call(function () use ($email, $password, $deviceName): NewAccessToken {
            $user = User::query()->where('email', $email)->first();

            if (! $user || ! Hash::check($password, $user->password) || ! $user->is_admin || ! $user->is_active) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            if (Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }

            return $user->createToken(
                $deviceName,
                [User::ADMIN_TOKEN_ABILITY],
                now()->addMinutes((int) config('sanctum.expiration')),
            );
        }, 200_000);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }

    public function tokens(User $user): LengthAwarePaginator
    {
        return $user->tokens()->latest('id')->paginate(15);
    }

    public function revokeToken(User $user, string $tokenId): void
    {
        $token = $user->tokens()->findOrFail($tokenId);
        Gate::forUser($user)->authorize('delete', $token);
        $token->delete();
    }
}
