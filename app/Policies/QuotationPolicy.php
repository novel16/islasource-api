<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin && $user->is_active;
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->is_admin && $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_admin && $user->is_active;
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->is_admin && $user->is_active;
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->is_admin && $user->is_active && $quotation->status === 'Draft';
    }

    public function restore(User $user, Quotation $quotation): bool
    {
        return false;
    }

    public function forceDelete(User $user, Quotation $quotation): bool
    {
        return false;
    }
}
