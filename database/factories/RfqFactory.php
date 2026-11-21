<?php

namespace Database\Factories;

use App\Models\Rfq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rfq>
 */
class RfqFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'RFQ-'.now()->format('Y').'-'.fake()->unique()->bothify('????????'),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '+63 917 000 0000',
            'delivery_location' => fake()->city().', Cebu',
            'requested_delivery_date' => null,
            'customer_notes' => null,
            'status' => 'New',
        ];
    }
}
