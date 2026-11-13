<?php

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\Auth\TokenCollection;
use App\Services\Admin\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TokenController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function index(Request $request): TokenCollection
    {
        return new TokenCollection($this->auth->tokens($request->user()));
    }

    public function destroy(Request $request, string $token): Response
    {
        $this->auth->revokeToken($request->user(), $token);

        return response()->noContent();
    }
}
