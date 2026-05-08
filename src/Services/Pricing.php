<?php
namespace Portal\Services;

use Portal\Database;

/**
 * Pricing engine. Applies customer-specific reprint rates to ranges,
 * computes line items, applies volume discounts, taxes, and returns
 * a structured quote.
 */
class Pricing
{
    /** Look up a customer's reprint rule price by its key. Returns 0 if not found. */
    public static function ruleFor(int $customerId, string $key): float
    {
        $row = Database::one(
            "SELECT price FROM pricing_rules WHERE customer_id = ? AND rule_key = ?",
            [$customerId, $key]
        );
        return $row ? (float)$row['price'] : 0.0;
    }

    /** Get all reprint rates for the customer (for display in the quote rail). */
    public static function rulesForCustomer(int $customerId): array
    {
        return Database::all(
            "SELECT rule_key, label, price FROM pricing_rules WHERE customer_id = ? ORDER BY id",
            [$customerId]
        );
    }

    /**
     * Map a single range (size + color + stock) to a per-page rate.
     * Sizes:  "8.5×11", "11×17", or any size starting with those numbers (handles 8.5×14 as letter for now).
     * Color:  'bw' or 'color'.
     * Stock:  'bond', 'cardstock', or 'gloss'.
     */
    public static function rateForRange(int $customerId, string $size, string $color, string $stock): float
    {
        $isTabloid = str_contains($size, '11×17') || str_contains($size, '12×18');
        $key = $isTabloid
            ? ($color === 'color' ? 'color-tabloid'    : 'bw-tabloid')
            : ($color === 'color' ? 'color-letter-bond' : 'bw-letter-bond');

        $rate = self::ruleFor($customerId, $key);

        if ($stock === 'cardstock') $rate += self::ruleFor($customerId, 'cardstock-add');
        if ($stock === 'gloss')     $rate += self::ruleFor($customerId, 'gloss-add');

        return $rate;
    }

    /**
     * Compute a reprint quote from an array of "files" (each with ranges, qty, finishing).
     * Returns: ['lines' => [...], 'subtotal' => float, 'discount' => float, 'tax' => float, 'total' => float, 'totalPages' => int]
     */
    public static function reprintQuote(int $customerId, array $files, bool $freeDelivery = true): array
    {
        $lines = [];
        $totalPages = 0;

        foreach ($files as $file) {
            $sets = max(1, (int)($file['qty'] ?? 1));
            foreach (($file['ranges'] ?? []) as $r) {
                $pageList = PdfAnalyzer::expandPages($r['pages'] ?? '');
                $pages = count($pageList);
                if ($pages === 0) continue;
                $rate = self::rateForRange($customerId, $r['size'] ?? '8.5×11', $r['color'] ?? 'bw', $r['stock'] ?? 'bond');
                $count = $pages * $sets;
                $totalPages += $count;
                $amount = round($rate * $count, 2);
                $colorLabel = ($r['color'] ?? 'bw') === 'color' ? 'Color' : 'B&W';
                $stockLabel = ($r['stock'] ?? 'bond') === 'bond' ? '' : ' ' . $r['stock'];
                $sizeLabel = $r['size'] ?? '8.5×11';
                $setsLabel = $sets > 1 ? " ({$pages}pp × {$sets} sets)" : '';
                $lines[] = [
                    'description' => "{$count} pp {$colorLabel} {$sizeLabel}{$stockLabel}{$setsLabel}",
                    'amount'      => $amount,
                ];
            }
            // Finishing per file
            $finishing = $file['finishing'] ?? 'none';
            if ($finishing && $finishing !== 'none') {
                $perSet = match ($finishing) {
                    'staple' => 0.50,
                    'punch'  => 0.75,
                    'bind'   => 4.50,
                    default  => 0.0,
                };
                if ($perSet > 0) {
                    $lines[] = [
                        'description' => "Finishing: $finishing × {$sets}",
                        'amount'      => round($perSet * $sets, 2),
                    ];
                }
            }
        }

        $subBeforeDiscount = array_sum(array_column($lines, 'amount'));
        $discount = $totalPages > 500 ? round($subBeforeDiscount * 0.05, 2) : 0.0;
        $subtotal = round($subBeforeDiscount - $discount, 2);
        $tax = round($subtotal * PORTAL_TAX_RATE, 2);
        $total = round($subtotal + $tax, 2);

        return compact('lines', 'subtotal', 'discount', 'tax', 'total', 'totalPages');
    }
}
