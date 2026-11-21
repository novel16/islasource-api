<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $product->slugs()->create(['slug' => $product->slug]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Fresh squid',
            'category' => 'Fresh seafood',
            'unit' => 'kg',
            'status' => 'Draft',
            'description' => 'Seafood sourced from Bantayan Island on request.',
            'slug' => fake()->unique()->slug(),
            'meta_title' => 'Fresh squid | IslaSource Seafoods',
            'meta_description' => 'Request a fresh squid quote.',
            'image_alt' => 'Fresh squid',
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => ['deleted_at' => now()]);
    }
}
