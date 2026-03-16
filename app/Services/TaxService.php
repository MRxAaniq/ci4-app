<?php

namespace App\Services;

class TaxService
{
    public function calculateLineTax(float $unitPrice, int $quantity, float $taxPercentage): float
    {
        $lineSubtotal = $unitPrice * $quantity;
        return round($lineSubtotal * ($taxPercentage / 100), 2);
    }

    /**
     * @param array<int, array{quantity:int, price:float, tax:float}> $items
     * @return array{subtotal:float, tax_total:float, grand_total:float}
     */
    public function calculateTotals(array $items): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $item) {
            $subtotal += ((float)$item['price']) * ((int)$item['quantity']);
            $taxTotal += (float)$item['tax'];
        }

        $subtotal = round($subtotal, 2);
        $taxTotal = round($taxTotal, 2);

        return [
            'subtotal'    => $subtotal,
            'tax_total'   => $taxTotal,
            'grand_total' => round($subtotal + $taxTotal, 2),
        ];
    }
}
