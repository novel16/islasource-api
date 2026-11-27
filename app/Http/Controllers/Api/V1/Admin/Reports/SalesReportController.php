<?php

namespace App\Http\Controllers\Api\V1\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('viewAny', Order::class) ?? false, 403);

        $rules = [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
        if (is_string($request->query('from')) && $request->query('from') !== '') {
            $rules['to'][] = 'after_or_equal:from';
        }
        $validated = validator($request->only(['from', 'to']), $rules)->validate();

        $query = Order::query()
            ->with(['quotation.items'])
            ->where('payment_status', 'Paid')
            ->where('status', '!=', 'Cancelled');

        if (! empty($validated['from'])) {
            $startUtc = Carbon::createFromFormat('Y-m-d', $validated['from'], 'Asia/Manila')
                ->startOfDay()
                ->utc();
            $query->where(function (Builder $q) use ($startUtc): void {
                $q->where('paid_at', '>=', $startUtc)
                    ->orWhere(fn (Builder $sub): Builder => $sub->whereNull('paid_at')->where('created_at', '>=', $startUtc));
            });
        }

        if (! empty($validated['to'])) {
            $endUtc = Carbon::createFromFormat('Y-m-d', $validated['to'], 'Asia/Manila')
                ->endOfDay()
                ->utc();
            $query->where(function (Builder $q) use ($endUtc): void {
                $q->where('paid_at', '<=', $endUtc)
                    ->orWhere(fn (Builder $sub): Builder => $sub->whereNull('paid_at')->where('created_at', '<=', $endUtc));
            });
        }

        $orders = $query->orderByDesc('paid_at')->orderByDesc('id')->get();

        $grossSales = 0;
        $seafoodCost = 0;
        $handling = 0;
        $delivery = 0;
        $netProfit = 0;
        $completedCount = 0;
        $productBuckets = [];
        $orderRows = [];

        foreach ($orders as $order) {
            $quotation = $order->quotation;
            if ($quotation === null) {
                continue;
            }

            $orderSeafoodCost = (int) $quotation->seafood_cost_centavos;
            $orderHandling = (int) $quotation->handling_centavos;
            $orderDelivery = (int) $order->delivery_centavos;
            $orderNetProfit = (int) $quotation->markup_centavos;
            $orderGross = (int) $order->total_centavos;

            $grossSales += $orderGross;
            $seafoodCost += $orderSeafoodCost;
            $handling += $orderHandling;
            $delivery += $orderDelivery;
            $netProfit += $orderNetProfit;

            if ($order->status === 'Completed') {
                $completedCount++;
            }

            foreach ($quotation->items->where('is_available', true) as $item) {
                $key = mb_strtolower($item->product_name).'|'.$item->unit;
                if (! isset($productBuckets[$key])) {
                    $productBuckets[$key] = [
                        'product_name' => $item->product_name,
                        'unit' => $item->unit,
                        'orders_count' => 0,
                        'quantity_milli' => 0,
                        'sales_centavos' => 0,
                    ];
                }
                $productBuckets[$key]['orders_count']++;
                $productBuckets[$key]['quantity_milli'] += (int) round((float) $item->quantity * 1000);
                $productBuckets[$key]['sales_centavos'] += (int) $item->line_selling_centavos;
            }

            if (count($orderRows) < 100) {
                $orderRows[] = [
                    'id' => (int) $order->id,
                    'reference' => $order->reference,
                    'quotation_reference' => $quotation->reference,
                    'customer_name' => $order->customer_name,
                    'location' => $order->delivery_location,
                    'status' => $order->status,
                    'paid_at' => ($order->paid_at ?? $order->created_at)?->toISOString(),
                    'seafood_cost_centavos' => $orderSeafoodCost,
                    'handling_centavos' => $orderHandling,
                    'delivery_centavos' => $orderDelivery,
                    'markup_percent' => (string) $quotation->markup_percent,
                    'net_profit_centavos' => $orderNetProfit,
                    'gross_sales_centavos' => $orderGross,
                ];
            }
        }

        usort($productBuckets, static fn (array $a, array $b): int => $b['sales_centavos'] <=> $a['sales_centavos']);

        $topProducts = array_map(static function (array $bucket): array {
            $qty = rtrim(rtrim(number_format($bucket['quantity_milli'] / 1000, 3, '.', ''), '0'), '.');

            return [
                'product_name' => $bucket['product_name'],
                'unit' => $bucket['unit'],
                'orders_count' => $bucket['orders_count'],
                'quantity' => $qty === '' ? '0' : $qty,
                'sales_centavos' => $bucket['sales_centavos'],
            ];
        }, array_slice($productBuckets, 0, 10));

        return response()->json([
            'data' => [
                'summary' => [
                    'paid_orders_count' => $orders->count(),
                    'completed_orders_count' => $completedCount,
                    'gross_sales_centavos' => $grossSales,
                    'seafood_cost_centavos' => $seafoodCost,
                    'handling_centavos' => $handling,
                    'delivery_centavos' => $delivery,
                    'net_profit_centavos' => $netProfit,
                ],
                'top_products' => $topProducts,
                'orders' => $orderRows,
            ],
        ]);
    }
}
