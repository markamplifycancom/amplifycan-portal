<?php
namespace Portal\Services;

/**
 * Analyzes a PDF file using poppler's pdfinfo and Ghostscript's inkcov device.
 * Returns page count, per-page sizes, per-page color-or-bw, and grouped ranges.
 *
 * For the v1 reprint workflow this is enough — most customer reprint PDFs are uniform
 * size with mixed B&W and color pages. True multi-size detection is deferred.
 */
class PdfAnalyzer
{
    public static function analyze(string $pdfPath): array
    {
        if (!is_file($pdfPath)) {
            throw new \RuntimeException("PDF not found: $pdfPath");
        }

        $info = self::pdfInfo($pdfPath);
        $pageCount = (int)($info['Pages'] ?? 0);
        $sizeStr = $info['Page size'] ?? '';
        // Page size example: "612 x 792 pts (letter)"
        $pageSize = self::parseSize($sizeStr);

        // Color detection per page via Ghostscript inkcov
        $colorPages = self::detectColorPages($pdfPath, $pageCount);

        // Build pages array
        $pages = [];
        for ($i = 1; $i <= $pageCount; $i++) {
            $pages[] = [
                'index'   => $i,
                'width_in' => $pageSize['width_in'],
                'height_in' => $pageSize['height_in'],
                'size_label' => $pageSize['label'],
                'is_color' => in_array($i, $colorPages, true),
            ];
        }

        // Group consecutive same-(size, color) pages into ranges
        $ranges = self::groupIntoRanges($pages);

        return [
            'pageCount' => $pageCount,
            'pages'     => $pages,
            'sizes'     => array_values(array_unique(array_column($pages, 'size_label'))),
            'ranges'    => $ranges,
            'raw'       => $info,
        ];
    }

    /** Run pdfinfo and parse its key:value output. */
    private static function pdfInfo(string $pdfPath): array
    {
        $cmd = PORTAL_PDFINFO . ' ' . escapeshellarg($pdfPath) . ' 2>&1';
        $output = shell_exec($cmd);
        if ($output === null) throw new \RuntimeException("pdfinfo failed");
        $info = [];
        foreach (explode("\n", $output) as $line) {
            if (strpos($line, ':') === false) continue;
            [$k, $v] = explode(':', $line, 2);
            $info[trim($k)] = trim($v);
        }
        return $info;
    }

    /** Parse "612 x 792 pts (letter)" -> width_in, height_in, label like "8.5×11". */
    private static function parseSize(string $s): array
    {
        if (preg_match('#([\d.]+)\s*x\s*([\d.]+)\s*pts#', $s, $m)) {
            $w = (float)$m[1] / 72.0;
            $h = (float)$m[2] / 72.0;
            return [
                'width_in' => round($w, 2),
                'height_in' => round($h, 2),
                'label' => self::sizeLabel($w, $h),
            ];
        }
        return ['width_in' => 0, 'height_in' => 0, 'label' => 'unknown'];
    }

    /** Map a (w,h) in inches to a friendly label, normalizing to common print sizes. */
    public static function sizeLabel(float $w, float $h): string
    {
        // Always orient so we report shorter × longer for matching common stock sizes
        $a = min($w, $h);
        $b = max($w, $h);
        $candidates = [
            [3.5, 2.0,   '3.5×2 (business card)'],
            [4.0, 6.0,   '4×6'],
            [4.0, 9.0,   '4×9 (rack card)'],
            [5.5, 8.5,   '5.5×8.5 (half-letter)'],
            [8.5, 11.0,  '8.5×11'],
            [8.5, 14.0,  '8.5×14 (legal)'],
            [11.0, 17.0, '11×17'],
            [12.0, 18.0, '12×18'],
            [13.0, 19.0, '13×19'],
            [18.0, 24.0, '18×24'],
            [24.0, 36.0, '24×36'],
            [30.0, 42.0, '30×42'],
            [48.0, 96.0, '4×8 ft'],
        ];
        foreach ($candidates as [$ca, $cb, $label]) {
            if (abs($a - $ca) < 0.25 && abs($b - $cb) < 0.25) return $label;
        }
        return rtrim(rtrim(number_format($a, 2), '0'), '.') . '×' . rtrim(rtrim(number_format($b, 2), '0'), '.');
    }

    /**
     * Use Ghostscript's inkcov device to detect color pages.
     * Returns an array of 1-based page indices that contain non-trivial CMY ink.
     * Threshold: any of C/M/Y > 0.001 means "color".
     */
    private static function detectColorPages(string $pdfPath, int $pageCount): array
    {
        if ($pageCount <= 0) return [];
        $cmd = PORTAL_GHOSTSCRIPT . ' -q -o - -sDEVICE=inkcov '
             . escapeshellarg($pdfPath) . ' 2>&1';
        $output = shell_exec($cmd);
        if ($output === null) return [];

        $color = [];
        $page = 0;
        foreach (explode("\n", trim($output)) as $line) {
            // Each non-blank line is "C M Y K CMYK OK" per page
            if (!preg_match('#^\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+CMYK\s+OK#', $line, $m)) continue;
            $page++;
            $c = (float)$m[1]; $mC = (float)$m[2]; $y = (float)$m[3];
            if ($c > 0.001 || $mC > 0.001 || $y > 0.001) {
                $color[] = $page;
            }
        }
        return $color;
    }

    /** Group consecutive pages with the same size+color into editable ranges. */
    private static function groupIntoRanges(array $pages): array
    {
        $ranges = [];
        if (empty($pages)) return $ranges;

        $start = $pages[0]['index'];
        $current = $pages[0];
        $end = $start;

        for ($i = 1; $i < count($pages); $i++) {
            $p = $pages[$i];
            if ($p['size_label'] === $current['size_label'] && $p['is_color'] === $current['is_color']) {
                $end = $p['index'];
            } else {
                $ranges[] = [
                    'pages' => $start === $end ? "$start" : "$start-$end",
                    'size'  => $current['size_label'],
                    'color' => $current['is_color'] ? 'color' : 'bw',
                    'stock' => 'bond',
                ];
                $start = $p['index'];
                $end = $start;
                $current = $p;
            }
        }
        $ranges[] = [
            'pages' => $start === $end ? "$start" : "$start-$end",
            'size'  => $current['size_label'],
            'color' => $current['is_color'] ? 'color' : 'bw',
            'stock' => 'bond',
        ];
        return $ranges;
    }

    /** Parse "1-5,8,10-12" into an array of page numbers. Returns [] if invalid. */
    public static function expandPages(string $rangeStr): array
    {
        $out = [];
        foreach (explode(',', $rangeStr) as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (strpos($part, '-') !== false) {
                [$a, $b] = explode('-', $part, 2);
                $a = (int)trim($a); $b = (int)trim($b);
                if ($a <= 0 || $b < $a) return [];
                for ($i = $a; $i <= $b; $i++) $out[] = $i;
            } else {
                $n = (int)$part;
                if ($n <= 0) return [];
                $out[] = $n;
            }
        }
        return $out;
    }
}
