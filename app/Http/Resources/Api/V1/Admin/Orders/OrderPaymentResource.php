<?php

namespace App\Http\Resources\Api\V1\Admin\Orders;

use App\Models\OrderPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderPayment */
class OrderPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'provider' => $this->provider,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'amount_centavos' => (int) $this->amount_centavos,
            'paymongo_checkout_session_id' => $this->paymongo_checkout_session_id,
            'paymongo_payment_id' => $this->paymongo_payment_id,
            'paid_at' => $this->paid_at?->toISOString(),
            'failure_reason' => $this->failure_reason ?? '',
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
