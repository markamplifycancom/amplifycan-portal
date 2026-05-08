<?php
namespace Portal\Services;

/**
 * Validates an uploaded PDF against a product's expected specs.
 * Returns ['status' => 'pass'|'warn'|'fail', 'checks' => [...], 'action' => string|null].
 *
 * Spec is an array like:
 *   ['expected_size' => '3.5×2', 'min_dpi' => 300, 'product' => 'Business Card']
 *
 * Reuses PdfAnalyzer for the underlying inspection plus does specific checks.
 */
class Preflight
{
    public static function check(string $pdfPath, array $spec): array
    {
        $checks = [];
        $hasFail = false;
        $hasWarn = false;
        $action = null;

        $info = PdfAnalyzer::analyze($pdfPath);
        $page1 = $info['pages'][0] ?? null;
        if (!$page1) {
            return [
                'status' => 'fail',
                'checks' => [['label' => 'PDF appears to be empty', 'value' => null, 'ok' => false]],
                'action' => 'Re-export the PDF and try again.',
            ];
        }

        $w = $page1['width_in'];
        $h = $page1['height_in'];
        $shorter = min($w, $h);
        $longer  = max($w, $h);

        // 1. Page size matches
        if (!empty($spec['expected_size'])) {
            $expected = $spec['expected_size'];
            // parse expected like "3.5×2" or "30×42" or "8.5×11"
            if (preg_match('#([\d.]+)\s*[x×]\s*([\d.]+)#u', $expected, $m)) {
                $eW = min((float)$m[1], (float)$m[2]);
                $eH = max((float)$m[1], (float)$m[2]);
                $tolerance = 0.5; // half-inch tolerance for bleed
                $matches = abs($shorter - $eW) <= $tolerance && abs($longer - $eH) <= $tolerance;
                $checks[] = [
                    'label' => "Page size matches " . $spec['expected_size'] . " (with bleed)",
                    'value' => sprintf('file is %.2f×%.2f', $shorter, $longer),
                    'ok' => $matches,
                ];
                if (!$matches) {
                    $hasFail = true;
                    $action = "This file is the wrong size for " . ($spec['product'] ?? 'this product')
                        . ". Re-export at " . $spec['expected_size'] . " (with bleed) and re-upload.";
                }
            }
        }

        // 2. Color check (warn only — we'll convert RGB to CMYK)
        $hasColorPages = false;
        foreach ($info['pages'] as $p) if ($p['is_color']) { $hasColorPages = true; break; }
        $checks[] = [
            'label' => 'Color check',
            'value' => $hasColorPages ? 'color pages detected' : 'B&W only',
            'ok' => true,
        ];

        // 3. Multi-page note
        if ($info['pageCount'] > 1) {
            $checks[] = [
                'label' => 'Page count',
                'value' => $info['pageCount'] . ' pages',
                'ok' => true,
            ];
        }

        // 4. Min DPI check (skip for vector-only files)
        if (!empty($spec['min_dpi'])) {
            $minDpi = self::estimateMinImageDpi($pdfPath);
            if ($minDpi === null) {
                $checks[] = ['label' => 'Resolution check (vector artwork)', 'value' => 'no raster images', 'ok' => true];
            } else {
                $threshold = (int)$spec['min_dpi'];
                $ok = $minDpi >= $threshold;
                $checks[] = [
                    'label' => "Image resolution ≥ {$threshold} DPI",
                    'value' => "lowest detected: " . round($minDpi) . " DPI",
                    'ok' => $ok ? true : 'warn',
                ];
                if (!$ok) {
                    $hasWarn = true;
                    if (!$action) $action = "Some images may print soft at this size. You can approve as-is or re-export at higher resolution.";
                }
            }
        }

        $status = $hasFail ? 'fail' : ($hasWarn ? 'warn' : 'pass');
        return ['status' => $status, 'checks' => $checks, 'action' => $action];
    }

    /** Use pdfimages -list to get the lowest effective DPI of any raster image. */
    private static function estimateMinImageDpi(string $pdfPath): ?float
    {
        $cmd = PORTAL_PDFIMAGES . ' -list ' . escapeshellarg($pdfPath) . ' 2>&1';
        $out = shell_exec($cmd);
        if ($out === null) return null;
        $lines = explode("\n", trim($out));
        // skip header lines
        $minDpi = null;
        foreach ($lines as $line) {
            // Match the x-res / y-res columns; pdfimages -list output ends with x-ppi y-ppi size ratio
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 12) continue;
            // The columns near the end include x-ppi and y-ppi
            $xppi = (float)$parts[count($parts) - 4];
            $yppi = (float)$parts[count($parts) - 3];
            $ppi = min($xppi, $yppi);
            if ($ppi <= 0) continue;
            if ($minDpi === null || $ppi < $minDpi) $minDpi = $ppi;
        }
        return $minDpi;
    }
}
