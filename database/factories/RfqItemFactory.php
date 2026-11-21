<?php

namespace Database\Factories;

use App\Models\Rfq;
use App\Models\RfqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqItem>
 */
class RfqItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfq_id' => Rfq::factory(),
            'product_id' => null,
            'product_name' => fake()->words(2, true),
            'product_slug' => null,
            'unit' => 'kg',
            'quantity' => '1.000',
            'customer_notes' => null,
        ];
    }
}
