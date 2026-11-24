<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
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
            'reference' => 'QT-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
            'version' => 1,
            'status' => 'Draft',
            'seafood_cost_centavos' => 250_000,
            'handling_centavos' => 20_000,
            'markup_percent' => '20.00',
            'markup_centavos' => 54_000,
            'subtotal_centavos' => 324_000,
            'delivery_centavos' => 35_000,
            'total_centavos' => 359_000,
            'deposit_percent' => Quotation::DEFAULT_DEPOSIT_PERCENT,
            'deposit_centavos' => 359_000,
            'balance_centavos' => 0,
            'validity_hours' => Quotation::DEFAULT_VALIDITY_HOURS,
            'valid_until' => null,
            'terms' => Quotation::DEFAULT_TERMS,
            'created_by' => User::factory()->admin(),
        ];
    }

    public function sent(?string $plainToken = null): static
    {
        $token = $plainToken ?? Str::random(64);

        return $this->state(fn (): array => [
            'status' => 'Sent',
            'sent_at' => now(),
            'valid_until' => now()->addHours(Quotation::DEFAULT_VALIDITY_HOURS),
            'access_token_hash' => hash('sha256', $token),
            'access_token_encrypted' => $token,
            'last_emailed_at' => now(),
        ]);
    }
}
