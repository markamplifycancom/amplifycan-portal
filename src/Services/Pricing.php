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
     * Parse a size label (e.g. "24×36", "8.5×11", "8.5×14 (legal)") into [width, height] in inches.
     */
    private static function parseSize(string $size): ?array
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)/u', $size, $m)) {
            return [(float)$m[1], (float)$m[2]];
        }
        return null;
    }

    /** Square-foot area for a size string. Falls back to letter (~0.65 sqft) if unparseable. */
    private static function sqftFor(string $size): float
    {
        $dims = self::parseSize($size);
        if (!$dims) return 0.65;
        return ($dims[0] * $dims[1]) / 144.0;
    }

    /**
     * Map a single range (size + color + stock) to a per-page rate.
     *
     * Letter-ish (8.5×11, legal) and tabloid (11×17, 12×18) use saved per-customer rates.
     * Anything bigger (17×22, 18×24, 22×34, 24×36, 30×42, 34×44, 36×48) is large-format
     * blueprint repro, priced as the tabloid rate scaled by sqft ratio (tabloid baseline = 1.30 sqft).
     * Stock surcharges only apply to letter-ish.
     */
    public static function rateForRange(int $customerId, string $size, string $color, string $stock): float
    {
        $sqft = self::sqftFor($size);
        $isLetter   = $sqft <= 1.0;                                      // 8.5×11, legal
        $isTabloid  = !$isLetter && $sqft <= 1.7;                        // 11×17, 12×18
        $isLargeFmt = !$isLetter && !$isTabloid;                         // anything 17×22+

        if ($isLargeFmt) {
            $tabloidKey = $color === 'color' ? 'color-tabloid' : 'bw-tabloid';
            $base = self::ruleFor($customerId, $tabloidKey);
            return round($base * ($sqft / 1.30), 4);
        }

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
  