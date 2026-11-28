<?php

namespace App\Http\Controllers\Api\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    public function __construct(private readonly SiteSettingService $settings) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->settings->all()]);
    }

    public function update(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->update($request->all(), $request->user()),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->reset($request->user()),
        ]);
    }
}
