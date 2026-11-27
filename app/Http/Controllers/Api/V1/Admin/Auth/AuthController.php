<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Admin\Auth\AuthResource;
use App\Http\Resources\Api\V1\Admin\Auth\UserResource;
use App\Services\Admin\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(LoginRequest $request): AuthResource
    {
        return new AuthResource($this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name') ?? 'admin-web',
        ));
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request->user());

        return response()->noContent();
    }

    public function logoutAll(Request $request): Response
    {
        $this->auth->logoutAll($request->user());

        return response()->noContent();
    }

    public function changePassword(Request $request): Response
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:1024'],
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
        ]);

        $this->auth->changePassword(
            $request->user(),
            $validated['current_password'],
            $validated['password'],
        );

        return response()->noContent();
    }
}
