<?php

namespace App\Http\Controllers\Api\V1\PublicCatalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublicCatalog\StoreGuestRfqRequest;
use App\Http\Resources\Api\V1\PublicCatalog\GuestRfqResource;
use App\Http\Resources\Api\V1\PublicCatalog\PublicRfqScheduleResource;
use App\Services\QuoteOtpService;
use App\Services\RfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuestRfqController extends Controller
{
    public function __construct(
        private readonly RfqService $rfqs,
        private readonly QuoteOtpService $otp,
    ) {}

    public function sendCode(Request $request): JsonResponse
    {
        $validated = validator([
            'customer_name' => is_string($request->input('customer_name')) ? trim($request->input('customer_name')) : $request->input('customer_name'),
            'email' => is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : $request->input('email'),
            'website' => $request->input('website'),
            'elapsed_ms' => $request->input('elapsed_ms'),
        ], [
            'customer_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'website' => ['nullable', 'string', 'max:500'],
            'elapsed_ms' => ['required', 'integer', 'min:0'],
        ])->validate();

        $result = $this->otp->sendCode(
            customerName: $validated['customer_name'],
            email: $validated['email'],
            honeypot: $validated['website'] ?? null,
            elapsedMs: (int) $validated['elapsed_ms'],
        );

        return response()->json(['data' => $result]);
    }

    public function store(StoreGuestRfqRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->otp->verify($validated['email'], $validated['verification_code']);
        $rfq = $this->rfqs->submit($validated);
        $this->otp->consume($validated['email']);

        return (new GuestRfqResource($rfq))
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
