<?php

namespace App\Policies;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class PersonalAccessTokenPolicy
{
    public function delete(User $user, PersonalAccessToken $token): bool
    {
        return $user->is_admin && $user->is_active
            && $token->tokenable_type === $user->getMorphClass()
            && (string) $token->tokenable_id === (string) $user->getKey();
    }
}
