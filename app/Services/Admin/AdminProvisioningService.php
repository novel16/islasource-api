<?php

namespace App\Services\Admin;

use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class AdminProvisioningService
{
    public function create(string $name, string $email, #[SensitiveParameter] string $password, #[SensitiveParameter] string $passwordConfirmation): User
    {
        $data = Validator::make([
            'name' => trim($name),
            'email' => Str::lower(trim($email)),
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique(User::class)],
            'password' => [
                'required', 'string', 'confirmed', Password::min(12), 'max:1024',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (config('hashing.driver') === 'bcrypt' && strlen($value) > 72) {
                        $fail('The password must not exceed 72 bytes when using bcrypt.');
                    }
                },
            ],
        ])->validate();

        $admin = new User;
        $admin->name = $data['name'];
        $admin->email = $data['email'];
        $admin->password = Hash::make($data['password']);
        $admin->is_admin = true;
        $admin->is_active = true;

        try {
            $admin->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => ['The email has already been taken.']]);
        }

        return $admin;
    }
}
