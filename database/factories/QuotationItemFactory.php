<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationItem>
 */
class QuotationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'rfq_item_id' => null,
            'product_name' => 'Dried squid',
            'unit' => 'kg',
            'quantity' => '5.000',
            'specs' => 'Medium size, packed clean.',
            'is_available' => true,
            'unit_cost_centavos' => 50_000,
            'line_cost_centavos' => 250_000,
            'line_selling_centavos' => 324_000,
        ];
    }
}
