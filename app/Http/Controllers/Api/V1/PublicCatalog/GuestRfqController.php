<?php

namespace App\Http\Controllers\Api\V1\PublicCatalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublicCatalog\StoreGuestRfqRequest;
use App\Http\Resources\Api\V1\PublicCatalog\GuestRfqResource;
use App\Http\Resources\Api\V1\PublicCatalog\PublicRfqScheduleResource;
use App\Services\RfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuestRfqController extends Controller
{
    public function __construct(private readonly RfqService $rfqs) {}

    public function store(StoreGuestRfqRequest $request): JsonResponse
    {
        return (new GuestRfqResource($this->rfqs->submit($request->validated())))
            ->response()->setStatusCode(201)->header('Cache-Control', 'no-store');
    }

    public function showSchedule(string $token): PublicRfqScheduleResource
    {
        return new PublicRfqScheduleResource($this->rfqs->findByScheduleToken($token));
    }

    public function respondSchedule(Request $request, string $token): PublicRfqScheduleResource|JsonResponse
    {
        $validated = validator([
            'decision' => $request->input('decision'),
            'note' => is_string($request->input('note')) ? trim($request->input('note')) : $request->input('note'),
        ], [
            'decision' => ['required', 'string', Rule::in(['accept', 'cancel'])],
            'note' => ['nullable', 'string', 'max:500'],
        ])->validate();

        try {
            return new PublicRfqScheduleResource($this->rfqs->respondToScheduleByGuest(
                $token,
                $validated['decision'],
                $validated['note'] ?? null,
            ));
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 409);
        }
    }
}
