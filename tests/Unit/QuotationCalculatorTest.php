<?php

use App\Services\QuotationCalculator;

it('matches exact single-item centavo math including handling, markup, delivery, and deposit', function () {
    $result = (new QuotationCalculator)->calculate(
        items: [
            ['quantity' => '5.000', 'unit_cost_centavos' => 50_000, 'is_available' => true],
        ],
        handlingCentavos: 20_000,
        markupPercent: '20.00',
        deliveryCentavos: 35_000,
        depositPercent: 50,
    );

    expect($result)->toMatchArray([
        'seafood_cost_centavos' => 250_000,
        'handling_centavos' => 20_000,
        'markup_centavos' => 54_000,
        'subtotal_centavos' => 324_000,
        'delivery_centavos' => 35_000,
        'total_centavos' => 359_000,
        'deposit_centavos' => 179_500,
        'balance_centavos' => 179_500,
    ]);
    expect($result['items'][0])->toBe([
        'line_cost_centavos' => 250_000,
        'line_selling_centavos' => 324_000,
    ]);
});

it('allocates handling and markup across multiple available items with zero centavo drift and excludes unavailable items', function () {
    $result = (new QuotationCalculator)->calculate(
        items: [
            ['quantity' => '1.000', 'unit_cost_centavos' => 10_000, 'is_available' => true],
            ['quantity' => '1.000', 'unit_cost_centavos' => 10_000, 'is_available' => true],
            ['quantity' => '1.000', 'unit_cost_centavos' => 10_000, 'is_available' => true],
            ['quantity' => '2.500', 'unit_cost_centavos' => 45_000, 'is_available' => false],
        ],
        handlingCentavos: 100,
        markupPercent: '0.00',
        deliveryCentavos: 25_000,
        depositPercent: 33,
    );

    expect($result['seafood_cost_centavos'])->toBe(30_000);
    expect($result['subtotal_centavos'])->toBe(30_100);
    expect($result['total_centavos'])->toBe(55_100);
    expect($result['deposit_centavos'] + $result['balance_centavos'])->toBe($result['total_centavos']);

    $sellingSum = array_sum(array_column($result['items'], 'line_selling_centavos'));
    expect($sellingSum)->toBe($result['subtotal_centavos']);
    expect($result['items'][0]['line_selling_centavos'])->toBe(10_034);
    expect($result['items'][1]['line_selling_centavos'])->toBe(10_033);
    expect($result['items'][2]['line_selling_centavos'])->toBe(10_033);
    expect($result['items'][3])->toBe([
        'line_cost_centavos' => 0,
        'line_selling_centavos' => 0,
    ]);
});

it('handles fractional quantities, odd markup percentages, and zero-cost incomplete drafts cleanly', function () {
    $fractional = (new QuotationCalculator)->calculate(
        items: [
            ['quantity' => '1.250', 'unit_cost_centavos' => 10_001, 'is_available' => true],
        ],
        handlingCentavos: 1,
        markupPercent: '12.50',
        deliveryCentavos: 0,
        depositPercent: 33,
    );

    expect($fractional['total_centavos'])->toBe(14_065);
    expect($fractional['deposit_centavos'] + $fractional['balance_centavos'])->toBe(14_065);
    expect($fractional['items'][0]['line_selling_centavos'])->toBe($fractional['subtotal_centavos']);

    $draft = (new QuotationCalculator)->calculate(
        items: [
            ['quantity' => '2.000', 'unit_cost_centavos' => 0, 'is_available' => true],
        ],
        handlingCentavos: 20_000,
        markupPercent: '20.00',
        deliveryCentavos: 35_000,
        depositPercent: 50,
    );

    expect($draft['seafood_cost_centavos'])->toBe(0);
    expect($draft['items'][0]['line_selling_centavos'])->toBe(0);
});
