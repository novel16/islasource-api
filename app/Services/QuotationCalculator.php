<?php

namespace App\Services;

class QuotationCalculator
{
    /**
     * @param  array<int, array{quantity: float|int|string, unit_cost_centavos: int, is_available: bool}>  $items
     * @return array{
     *     items: array<int, array{line_cost_centavos: int, line_selling_centavos: int}>,
     *     seafood_cost_centavos: int,
     *     handling_centavos: int,
     *     markup_centavos: int,
     *     subtotal_centavos: int,
     *     delivery_centavos: int,
     *     total_centavos: int,
     *     deposit_centavos: int,
     *     balance_centavos: int
     * }
     */
    public function calculate(
        array $items,
        int $handlingCentavos,
        float|int|string $markupPercent,
        int $deliveryCentavos,
        int $depositPercent,
    ): array {
        $lineCosts = [];
        $seafoodCostCentavos = 0;

        foreach ($items as $index => $item) {
            if (! $item['is_available']) {
                $lineCosts[$index] = 0;

                continue;
            }

            $qtyMilli = (int) round((float) $item['quantity'] * 1000);
            $cost = (int) round(($qtyMilli * (int) $item['unit_cost_centavos']) / 1000);
            $lineCosts[$index] = $cost;
            $seafoodCostCentavos += $cost;
        }

        $markupBps = (int) round((float) $markupPercent * 100);
        $markupCentavos = (int) round((($seafoodCostCentavos + $handlingCentavos) * $markupBps) / 10000);
        $subtotalCentavos = $seafoodCostCentavos + $handlingCentavos + $markupCentavos;
        $totalCentavos = $subtotalCentavos + $deliveryCentavos;
        $depositCentavos = (int) round(($totalCentavos * $depositPercent) / 100);
        $balanceCentavos = $totalCentavos - $depositCentavos;

        $overhead = $handlingCentavos + $markupCentavos;
        $lineSellings = array_fill(0, count($items), 0);

        if ($seafoodCostCentavos > 0) {
            $remainders = [];
            $allocatedTotal = 0;

            foreach ($lineCosts as $index => $cost) {
                if ($cost <= 0) {
                    continue;
                }

                $numerator = $overhead * $cost;
                $floorShare = intdiv($numerator, $seafoodCostCentavos);
                $lineSellings[$index] = $cost + $floorShare;
                $allocatedTotal += $floorShare;
                $remainders[] = [
                    'index' => $index,
                    'remainder' => $numerator % $seafoodCostCentavos,
                ];
            }

            $leftover = $overhead - $allocatedTotal;

            if ($leftover > 0) {
                usort($remainders, static fn (array $a, array $b): int => ($b['remainder'] <=> $a['remainder']) ?: ($a['index'] <=> $b['index']));

                for ($i = 0; $i < $leftover; $i++) {
                    $lineSellings[$remainders[$i]['index']]++;
                }
            }
        }

        $calculatedItems = [];

        foreach ($lineCosts as $index => $cost) {
            $calculatedItems[$index] = [
                'line_cost_centavos' => $cost,
                'line_selling_centavos' => $lineSellings[$index],
            ];
        }

        return [
            'items' => $calculatedItems,
            'seafood_cost_centavos' => $seafoodCostCentavos,
            'handling_centavos' => $handlingCentavos,
            'markup_centavos' => $markupCentavos,
            'subtotal_centavos' => $subtotalCentavos,
            'delivery_centavos' => $deliveryCentavos,
            'total_centavos' => $totalCentavos,
            'deposit_centavos' => $depositCentavos,
            'balance_centavos' => $balanceCentavos,
        ];
    }
}
