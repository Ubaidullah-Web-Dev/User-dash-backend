<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;

class FinancialCalculatorService
{
    /**
     * Parses the multi-unit quantity from a pack_size string.
     * Examples:
     * - "25 Test / Box"    => 25
     * - "100 Strips / Box" => 100
     * - "500 Pcs / Packt"  => 500
     * - "100 test"         => 100
     * - "Box of 25"        => 25
     * Returns null if no valid multi-unit count (> 1) is found (e.g. volumes "500ml", single "1").
     */
    public function parsePackUnits(?string $packSize): ?int
    {
        if ($packSize === null) {
            return null;
        }

        $packSize = trim($packSize);
        if ($packSize === '' || str_starts_with($packSize, '-')) {
            return null;
        }

        // Match leading count followed by test/strip/tube/pc/vial/sheet or "/" e.g. "25 Test / Box", "20 / Box"
        if (preg_match('/^(\d+)\s*(?:test|strip|tube|pc|pice|piece|vial|sheet|\/)/i', $packSize, $m)) {
            $n = (int) $m[1];
            return $n > 1 ? $n : null;
        }

        // Match phrases like "box of 25", "pack of 100"
        if (preg_match('/(?:box|pack|kit)\s+of\s+(\d+)/i', $packSize, $m)) {
            $n = (int) $m[1];
            return $n > 1 ? $n : null;
        }

        // Match discrete unit count anywhere e.g. "100 test", "25 Strips", not preceded by minus
        if (preg_match('/(?<!-)\b(\d+)\s*(?:tests?|strips?|tubes?|pcs?|pieces?|vials?|sheets?)\b/i', $packSize, $m)) {
            $n = (int) $m[1];
            return $n > 1 ? $n : null;
        }

        return null;
    }

    /**
     * Calculates the true unit cost of an order item, taking into account:
     * 1. Catalog purchase price vs selling price
     * 2. Pack size vs individual unit sales (e.g. 1 test sold from a 25-test kit)
     * 3. Pack sales when catalog is priced per unit (e.g. 1 box sold when catalog is per test)
     * 4. Normal discounts, loss sales, giveaways, and missing/null values
     */
    public function calculateOrderItemUnitCost(float $itemPrice, ?Product $product): float
    {
        if ($product === null) {
            return 0.0;
        }

        $catalogCost = (float) ($product->getPurchasePrice() ?? 0);
        if ($catalogCost <= 0.0) {
            return 0.0;
        }

        $catalogPrice = (float) ($product->getPrice() ?? 0);
        $packUnits = $this->parsePackUnits($product->getPackSize());

        // Case 1: Multi-unit pack product (e.g. 25 Test / Box)
        if ($packUnits !== null && $packUnits > 1 && $catalogPrice > 0.0) {
            $unitExpectedPrice = $catalogPrice / $packUnits;
            $unitExpectedCost = $catalogCost / $packUnits;

            // Subcase 1A: Item was sold at/near the individual test/unit price
            // The catalog price/cost is for the entire pack, but the sold item is an individual unit.
            if ($itemPrice < $catalogCost && ($itemPrice <= $unitExpectedPrice * 1.5 || $itemPrice <= $catalogCost * 0.5)) {
                return round($unitExpectedCost, 4);
            }

            // Subcase 1B: Catalog was priced per unit, but the item was sold as a full pack
            $packExpectedPrice = $catalogPrice * $packUnits;
            if ($itemPrice >= $catalogPrice * 2.0 && abs($itemPrice - $packExpectedPrice) / max(1.0, $packExpectedPrice) <= 0.35) {
                return round($catalogCost * $packUnits, 4);
            }
        }

        // Case 2: Heuristic fallback for unparsed pack sizes or manual bulk/unit entries
        if ($catalogPrice > 0.0) {
            $costRatio = $catalogCost / $catalogPrice;
            $effectiveRatio = min(1.0, max(0.0, $costRatio));

            // If selling price is less than half the purchase price, it's virtually guaranteed to be a unit from a pack
            if ($itemPrice < $catalogCost * 0.5 && $itemPrice > 0.0) {
                return round($itemPrice * $effectiveRatio, 4);
            }

            // If selling price is more than double catalog price, it's a multi-pack or bulk sale
            if ($itemPrice > $catalogPrice * 2.0) {
                return round($itemPrice * $effectiveRatio, 4);
            }
        }

        // Case 3: Standard sale (item price is near catalog price, or normal clearance/discount)
        return round($catalogCost, 4);
    }

    /**
     * Calculates the total COGS for a specific order item.
     */
    public function calculateOrderItemCogs(float $itemPrice, float|int $quantity, ?Product $product): float
    {
        if ($quantity == 0) {
            return 0.0;
        }

        $unitCost = $this->calculateOrderItemUnitCost($itemPrice, $product);
        return round($quantity * $unitCost, 2);
    }

    /**
     * Calculates total COGS across an iterable collection of OrderItem entities.
     *
     * @param iterable<OrderItem> $orderItems
     */
    public function calculateTotalCogs(iterable $orderItems): float
    {
        $totalCogs = 0.0;

        foreach ($orderItems as $item) {
            if (!$item instanceof OrderItem) {
                continue;
            }

            $qty = $item->getQuantity();
            $price = (float) $item->getPrice();
            $product = $item->getProduct();

            $totalCogs += $this->calculateOrderItemCogs($price, $qty, $product);
        }

        return round($totalCogs, 2);
    }

    /**
     * Calculates Sales Profit (Revenue - COGS).
     */
    public function calculateSalesProfit(float $revenue, float $cogs): float
    {
        return round($revenue - $cogs, 2);
    }

    /**
     * Calculates Total Pending from an iterable collection of Order entities or amounts.
     *
     * @param iterable<Order> $orders
     */
    public function calculateTotalPending(iterable $orders): float
    {
        $totalPending = 0.0;

        foreach ($orders as $order) {
            if (!$order instanceof Order) {
                continue;
            }

            $changeDue = $order->getChangeDue();
            if ($changeDue !== null && $changeDue < 0.0) {
                $totalPending += abs($changeDue);
            }
        }

        return round($totalPending, 2);
    }

    /**
     * Calculates Received Payments on Sales (Revenue - Total Pending).
     */
    public function calculateReceivedPayments(float $revenue, float $totalPending): float
    {
        return round(max(0.0, $revenue - $totalPending), 2);
    }

    /**
     * Calculates Net Income (Cash Basis: Received Payments - Total Expenses).
     */
    public function calculateNetIncome(float $receivedPayments, float $totalExpenses): float
    {
        return round($receivedPayments - $totalExpenses, 2);
    }

    /**
     * Safely resolves and validates a report date range for any report type (daily, monthly, yearly, custom).
     * Guarantees that startDate <= endDate, handles invalid strings, leap years, and edge cases gracefully.
     *
     * @return array{startDate: \DateTime, endDate: \DateTime, periodLabel: string, type: string}
     */
    public function resolveDateRange(
        ?string $type = 'daily',
        ?string $dateStr = null,
        ?int $year = null,
        ?int $month = null,
        ?string $startStr = null,
        ?string $endStr = null
    ): array {
        $type = strtolower(trim($type ?? ''));
        if ($type === '') {
            $type = 'daily';
        }
        $now = new \DateTime();

        if ($type === 'daily') {
            $parsedDate = $this->parseDateString($dateStr, $now);
            $startDate = (clone $parsedDate)->setTime(0, 0, 0);
            $endDate = (clone $parsedDate)->setTime(23, 59, 59);
            $periodLabel = $startDate->format('d M Y');
        } elseif ($type === 'monthly') {
            $safeYear = ($year !== null && $year >= 1970 && $year <= 2100) ? $year : (int) $now->format('Y');
            $safeMonth = ($month !== null && $month >= 1 && $month <= 12) ? $month : (int) $now->format('n');
            $startDate = new \DateTime(sprintf('%04d-%02d-01 00:00:00', $safeYear, $safeMonth));
            $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);
            $periodLabel = $startDate->format('F Y');
        } elseif ($type === 'yearly') {
            $safeYear = ($year !== null && $year >= 1970 && $year <= 2100) ? $year : (int) $now->format('Y');
            $startDate = new \DateTime(sprintf('%04d-01-01 00:00:00', $safeYear));
            $endDate = new \DateTime(sprintf('%04d-12-31 23:59:59', $safeYear));
            $periodLabel = (string) $safeYear;
        } else {
            // custom range
            $type = 'custom';
            $parsedStart = $this->parseDateString($startStr, $now);
            $parsedEnd = $this->parseDateString($endStr, $now);

            $startDate = (clone $parsedStart)->setTime(0, 0, 0);
            $endDate = (clone $parsedEnd)->setTime(23, 59, 59);

            // Swap if start date is after end date
            if ($startDate > $endDate) {
                $temp = $startDate;
                $startDate = (clone $endDate)->setTime(0, 0, 0);
                $endDate = (clone $temp)->setTime(23, 59, 59);
            }

            $periodLabel = $startDate->format('d M Y') . ' to ' . $endDate->format('d M Y');
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'periodLabel' => $periodLabel,
            'type' => $type,
        ];
    }

    private function parseDateString(?string $str, \DateTime $default): \DateTime
    {
        if ($str === null || trim($str) === '') {
            return clone $default;
        }

        try {
            $d = new \DateTime(trim($str));
            // Sanity check year range
            $y = (int) $d->format('Y');
            if ($y < 1970 || $y > 2100) {
                return clone $default;
            }
            return $d;
        } catch (\Throwable) {
            return clone $default;
        }
    }
}
