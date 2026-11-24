<?php

namespace App\Http\Controllers\Api\V1\PublicCatalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicCatalog\PublicOrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GuestOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function show(string $token): PublicOrderResource
    {
        return new PublicOrderResource($this->orders->findForGuest($token));
    }

    public function checkout(string $token): JsonResponse
    {
        try {
            $result = $this->orders->startCheckout($token);

            return response()->json([
                'data' => [
                    'checkout_url' => $result['checkout_url'],
                ],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 409);
        } catch (RuntimeException $exception) {
            Log::error('PayMongo checkout session creation failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'We could not start QR Ph checkout right now. Please try again shortly.',
            ], 502);
        }
    }

    public function verifyPayment(string $token): PublicOrderResource|JsonResponse
    {
        try {
            return new PublicOrderResource($this->orders->verifyCheckoutPayment($token));
        } catch (RuntimeException $exception) {
            Log::error('PayMongo checkout session verification failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'We could not verify payment status right now. Please refresh shortly.',
            ], 502);
        }
    }
}
