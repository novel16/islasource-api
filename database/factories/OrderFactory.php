<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Quotation;
use App\Models\Rfq;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $plainToken = Str::random(64);

        return [
            'quotation_id' => Quotation::factory(),
            'rfq_id' => Rfq::factory(),
            'reference' => 'ORD-2026-'.Str::upper(Str::random(8)),
            'status' => 'Awaiting Payment',
            'payment_status' => 'Unpaid',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '+63 917 555 0148',
            'delivery_location' => 'Cebu City, Cebu',
            'requested_delivery_date' => null,
            'subtotal_centavos' => 150000,
            'delivery_centavos' => 25000,
            'total_centavos' => 175000,
            'paid_centavos' => 0,
            'access_token_hash' => hash('sha256', $plainToken),
            'access_token_encrypted' => $plainToken,
        ];
    }
}
