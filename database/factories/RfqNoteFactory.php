<?php

namespace Database\Factories;

use App\Models\Rfq;
use App\Models\RfqNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqNote>
 */
class RfqNoteFactory extends Factory
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
            'user_id' => User::factory()->admin(),
            'body' => fake()->sentence(),
        ];
    }
}
