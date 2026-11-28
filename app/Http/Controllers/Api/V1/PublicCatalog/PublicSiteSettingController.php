<?php

namespace App\Http\Controllers\Api\V1\PublicCatalog;

use App\Http\Controllers\Controller;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;

class PublicSiteSettingController extends Controller
{
    public function __construct(private readonly SiteSettingService $settings) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->settings->publicSettings()]);
    }
}
