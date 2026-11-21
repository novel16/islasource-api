<?php

namespace App\Http\Controllers\Api\V1\PublicCatalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublicCatalog\StoreGuestRfqRequest;
use App\Http\Resources\Api\V1\PublicCatalog\GuestRfqResource;
use App\Services\RfqService;
use Illuminate\Http\JsonResponse;

class GuestRfqController extends Controller
{
    public function __construct(private readonly RfqService $rfqs) {}

    public function store(StoreGuestRfqRequest $request): JsonResponse
    {
        return (new GuestRfqResource($this->rfqs->submit($request->validated())))
            ->response()->setStatusCode(201)->header('Cache-Control', 'no-store');
    }
}
