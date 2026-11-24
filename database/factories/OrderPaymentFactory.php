<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OrderPayment> */
class OrderPaymentFactory extends Factory
{
    protected $model = OrderPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $sessionId = 'cs_test_'.Str::random(16);

        return [
            'order_id' => Order::factory(),
            'provider' => 'paymongo',
            'payment_method' => 'qrph',
            'status' => 'Pending',
            'amount_centavos' => 175000,
            'paymongo_checkout_session_id' => $sessionId,
            'paymongo_payment_id' => null,
            'paymongo_payment_intent_id' => 'pi_test_'.Str::random(16),
            'checkout_url' => "https://checkout.paymongo.com/{$sessionId}",
            'paid_at' => null,
            'failure_reason' => null,
        ];
    }
}
