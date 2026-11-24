<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\PayMongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayMongoWebhookController extends Controller
{
    public function __construct(
        private readonly PayMongoService $payMongo,
        private readonly OrderService $orders,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $signature = $request->header('Paymongo-Signature');

        if (! $this->payMongo->verifyWebhookSignature($rawBody, is_string($signature) ? $signature : null)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON payload.'], 400);
        }

        $this->orders->handlePayMongoWebhook($payload);

        return response()->json(['received' => true]);
    }
}
