<?php

use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('requires an active admin bearer token to view sales reports', function () {
    $this->getJson('/api/v1/admin/reports/sales')->assertUnauthorized();

    $nonAdmin = User::factory()->create(['is_admin' => false, 'is_active' => true]);
    $token = $nonAdmin->createToken('web', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/reports/sales')->assertForbidden();
});

it('calculates gross sales, net profit markup, and separated handling and delivery across paid orders with date filtering', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('admin', ['admin'])->plainTextToken;

    // Paid Order #1 on Nov 10:
    // seafood_cost = 1000.00 (100_000), handling = 100.00 (10_000), markup 20% = 220.00 (22_000) -> subtotal = 132_000, delivery = 150.00 (15_000) -> total = 147_000
    $rfq1 = Rfq::factory()->create(['customer_name' => 'Maia Reyes', 'delivery_location' => 'Cebu City']);
    $quote1 = Quotation::factory()->for($rfq1)->create([
        'created_by' => $admin->id,
        'status' => 'Accepted',
        'seafood_cost_centavos' => 100_000,
        'handling_centavos' => 10_000,
        'markup_percent' => '20.00',
        'markup_centavos' => 22_000,
        'subtotal_centavos' => 132_000,
        'delivery_centavos' => 15_000,
        'total_centavos' => 147_000,
    ]);
    QuotationItem::factory()->for($quote1)->create([
        'product_name' => 'Alimasag Blue Crab',
        'unit' => 'kg',
        'quantity' => '2.500',
        'is_available' => true,
        'unit_cost_centavos' => 40_000,
        'line_cost_centavos' => 100_000,
        'line_selling_centavos' => 132_000,
    ]);
    QuotationItem::factory()->for($quote1)->create([
        'product_name' => 'Unavailable Shell',
        'unit' => 'kg',
        'quantity' => '5.000',
        'is_available' => false,
        'unit_cost_centavos' => 0,
        'line_cost_centavos' => 0,
        'line_selling_centavos' => 0,
    ]);
    Order::factory()->for($quote1)->for($rfq1)->create([
        'status' => 'Completed',
        'payment_status' => 'Paid',
        'customer_name' => 'Maia Reyes',
        'delivery_location' => 'Cebu City',
        'subtotal_centavos' => 132_000,
        'delivery_centavos' => 15_000,
        'total_centavos' => 147_000,
        'paid_centavos' => 147_000,
        'paid_at' => '2026-11-10 04:00:00',
        'completed_at' => '2026-11-11 04:00:00',
    ]);

    // Paid Order #2 on Nov 25:
    // seafood_cost = 50_000, handling = 5_000, markup 20% = 11_000 -> subtotal = 66_000, delivery = 10_000 -> total = 76_000
    $rfq2 = Rfq::factory()->create(['customer_name' => 'Carlo Lim', 'delivery_location' => 'Mandaue']);
    $quote2 = Quotation::factory()->for($rfq2)->create([
        'created_by' => $admin->id,
        'status' => 'Accepted',
        'seafood_cost_centavos' => 50_000,
        'handling_centavos' => 5_000,
        'markup_percent' => '20.00',
        'markup_centavos' => 11_000,
        'subtotal_centavos' => 66_000,
        'delivery_centavos' => 10_000,
        'total_centavos' => 76_000,
    ]);
    QuotationItem::factory()->for($quote2)->create([
        'product_name' => 'Alimasag Blue Crab',
        'unit' => 'kg',
        'quantity' => '1.500',
        'is_available' => true,
        'unit_cost_centavos' => 33_333,
        'line_cost_centavos' => 50_000,
        'line_selling_centavos' => 66_000,
    ]);
    Order::factory()->for($quote2)->for($rfq2)->create([
        'status' => 'Processing',
        'payment_status' => 'Paid',
        'customer_name' => 'Carlo Lim',
        'delivery_location' => 'Mandaue',
        'subtotal_centavos' => 66_000,
        'delivery_centavos' => 10_000,
        'total_centavos' => 76_000,
        'paid_centavos' => 76_000,
        'paid_at' => '2026-11-25 06:00:00',
    ]);

    // Unpaid Order (must NOT be counted in sales!)
    $rfq3 = Rfq::factory()->create();
    $quote3 = Quotation::factory()->for($rfq3)->create([
        'created_by' => $admin->id,
        'status' => 'Accepted',
        'seafood_cost_centavos' => 90_000,
        'handling_centavos' => 10_000,
        'markup_centavos' => 20_000,
        'subtotal_centavos' => 120_000,
        'delivery_centavos' => 10_000,
        'total_centavos' => 130_000,
    ]);
    Order::factory()->for($quote3)->for($rfq3)->create([
        'status' => 'Awaiting Payment',
        'payment_status' => 'Unpaid',
        'total_centavos' => 130_000,
        'paid_centavos' => 0,
    ]);

    // All-time report
    $all = $this->withToken($token)->getJson('/api/v1/admin/reports/sales')
        ->assertOk()
        ->assertJsonPath('data.summary.paid_orders_count', 2)
        ->assertJsonPath('data.summary.completed_orders_count', 1)
        ->assertJsonPath('data.summary.gross_sales_centavos', 223_000)
        ->assertJsonPath('data.summary.seafood_cost_centavos', 150_000)
        ->assertJsonPath('data.summary.handling_centavos', 15_000)
        ->assertJsonPath('data.summary.delivery_centavos', 25_000)
        ->assertJsonPath('data.summary.net_profit_centavos', 33_000)
        ->assertJsonCount(2, 'data.orders')
        ->assertJsonCount(1, 'data.top_products')
        ->assertJsonPath('data.top_products.0.product_name', 'Alimasag Blue Crab')
        ->assertJsonPath('data.top_products.0.quantity', '4')
        ->assertJsonPath('data.top_products.0.sales_centavos', 198_000);

    expect($all->headers->get('Cache-Control'))->toContain('no-store');

    // Filtered to Nov 20 - Nov 30 (only Paid Order #2)
    $this->withToken($token)->getJson('/api/v1/admin/reports/sales?from=2026-11-20&to=2026-11-30')
        ->assertOk()
        ->assertJsonPath('data.summary.paid_orders_count', 1)
        ->assertJsonPath('data.summary.gross_sales_centavos', 76_000)
        ->assertJsonPath('data.summary.handling_centavos', 5_000)
        ->assertJsonPath('data.summary.delivery_centavos', 10_000)
        ->assertJsonPath('data.summary.net_profit_centavos', 11_000);
});
