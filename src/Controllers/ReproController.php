<?php
namespace Portal\Controllers;

use Portal\Auth;
use Portal\Database;
use Portal\View;
use Portal\Services\PdfAnalyzer;
use Portal\Services\Pricing;

/**
 * Repro flow (AEC blueprint orders, the highest-volume use case):
 *   GET  /repro            — show upload screen
 *   POST /repro/upload     — accept uploaded PDF(s), analyze, store in session draft
 *   POST /repro/update     — replace/edit the ranges array of one file
 *   POST /repro/place      — finalize the order, write to DB, redirect to /orders/{id}
 *   POST /repro/remove     — remove a file from the draft
 *   POST /repro/clear      — clear the entire draft
 */
class ReproController
{
    private const DRAFT_KEY = 'repro_draft';

    public function start(): void
    {
        Auth::requireLogin();
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }

        $draft = $_SESSION[self::DRAFT_KEY] ?? ['files' => [], 'shipTo' => null, 'project' => '', 'showRules' => false];

        // Default ship-to if none chosen
        if (empty($draft['shipTo'])) {
            $defAddr = Database::one(
                "SELECT id FROM addresses WHERE customer_id = ? AND is_default_ship = 1 LIMIT 1",
                [$customer['id']]
            );
            $draft['shipTo'] = $defAddr ? (int)$defAddr['id'] : null;
        }

        $addresses = Database::all(
            "SELECT id, label FROM addresses WHERE customer_id = ? ORDER BY is_default_ship DESC, id",
            [$customer['id']]
        );
        $rules = Pricing::rulesForCustomer($customer['id']);
        $quote = Pricing::reprintQuote((int)$customer['id'], $draft['files']);

        View::render('repro/index', [
            'user'      => Auth::user(),
            'customer'  => $customer,
            'draft'     => $draft,
            'addresses' => $addresses,
            'rules'     => $rules,
            'quote'     => $quote,
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function upload(): void
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/repro'); return; }
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }

        $draft = $_SESSION[self::DRAFT_KEY] ?? ['files' => [], 'shipTo' => null, 'project' => '', 'showRules' => false];

        // Multiple files: <input name="pdfs[]" multiple>
        $uploaded = $_FILES['pdfs'] ?? null;
        if (!$uploaded || empty($uploaded['name'][0])) { View::redirect('/repro'); return; }

        $count = is_array($uploaded['name']) ? count($uploaded['name']) : 1;
        for ($i = 0; $i < $count; $i++) {
            $name = is_array($uploaded['name'])     ? $uploaded['name'][$i]     : $uploaded['name'];
            $tmp  = is_array($uploaded['tmp_name']) ? $uploaded['tmp_name'][$i] : $uploaded['tmp_name'];
            $err  = is_array($uploaded['error'])    ? $uploaded['error'][$i]    : $uploaded['error'];
            $size = is_array($uploaded['size'])     ? $uploaded['size'][$i]     : $uploaded['size'];
            if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) continue;
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') continue;

            $fileId   = bin2hex(random_bytes(8));
            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
            $destDir  = PORTAL_UPLOADS . '/draft/' . session_id();
            if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
            $destPath = "$destDir/{$fileId}_{$safeName}";
            move_uploaded_file($tmp, $destPath);

            try {
                $analysis = PdfAnalyzer::analyze($destPath);
            } catch (\Throwable $e) {
                @unlink($destPath);
                continue;
            }

            $draft['files'][] = [
                'id'         => $fileId,
                'name'       => $name,
                'path'       => $destPath,
                'size_bytes' => $size,
                'totalPages' => $analysis['pageCount'],
                'sizes'      => $analysis['sizes'],
                'ranges'     => $analysis['ranges'],
                'sides'      => 'single',
                'qty'        => 1,
                'finishing'  => 'none',
                'notes'      => '',
            ];
        }

        $_SESSION[self::DRAFT_KEY] = $draft;
        View::redirect('/repro');
    }

    /**
     * Update a single file's metadata (ranges, qty, finishing, etc.)
     * Body: csrf, fileId, ranges[][pages|size|color|stock], qty, finishing, sides, notes
     */
    public function updateFile(): void
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/repro'); return; }
        $draft = $_SESSION[self::DRAFT_KEY] ?? null;
        if (!$draft) { View::redirect('/repro'); return; }

        $fileId = $_POST['fileId'] ?? '';
        foreach ($draft['files'] as &$f) {
            if ($f['id'] !== $fileId) continue;
            $f['qty']       = max(1, (int)($_POST['qty'] ?? 1));
            $f['finishing'] = $_POST['finishing'] ?? 'none';
            $f['sides']     = $_POST['sides'] ?? 'single';
            $f['notes']     = trim($_POST['notes'] ?? '');
            $rangesIn = $_POST['ranges'] ?? [];
            $f['ranges'] = [];
            foreach ($rangesIn as $r) {
                if (empty($r['pages'])) continue;
                $f['ranges'][] = [
                    'pages' => trim($r['pages']),
                    'size'  => $r['size'] ?? '8.5×11',
                    'color' => $r['color'] ?? 'bw',
                    'stock' => $r['stock'] ?? 'bond',
                ];
            }
            break;
        }
        unset($f);
        $draft['shipTo']  = (int)($_POST['shipTo'] ?? $draft['shipTo']);
        $draft['project'] = trim($_POST['project'] ?? '');
        $_SESSION[self::DRAFT_KEY] = $draft;

        View::redirect('/repro');
    }

    public function removeFile(): void
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/repro'); return; }
        $draft = $_SESSION[self::DRAFT_KEY] ?? null;
        if (!$draft) { View::redirect('/repro'); return; }
        $fileId = $_POST['fileId'] ?? '';
        $draft['files'] = array_values(array_filter($draft['files'], function ($f) use ($fileId) {
            if ($f['id'] === $fileId) { @unlink($f['path']); return false; }
            return true;
        }));
        $_SESSION[self::DRAFT_KEY] = $draft;
        View::redirect('/repro');
    }

    public function clearDraft(): void
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/repro'); return; }
        $draft = $_SESSION[self::DRAFT_KEY] ?? null;
        if ($draft) foreach ($draft['files'] as $f) @unlink($f['path']);
        unset($_SESSION[self::DRAFT_KEY]);
        View::redirect('/repro');
    }

    public function place(): void
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/repro'); return; }
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }
        $draft = $_SESSION[self::DRAFT_KEY] ?? null;
        if (!$draft || empty($draft['files'])) { View::redirect('/repro'); return; }

        $quote = Pricing::reprintQuote((int)$customer['id'], $draft['files']);
        if (empty($quote['lines'])) { View::redirect('/repro'); return; }

        $name = count($draft['files']) === 1
            ? pathinfo($draft['files'][0]['name'], PATHINFO_FILENAME)
            : 'Reprint — ' . count($draft['files']) . ' files';

        $orderId = Database::insert('orders', [
            'customer_id' => $customer['id'],
            'user_id'     => Auth::user()['id'],
            'type'        => 'Repro',
            'name'        => $name,
            'project'     => $draft['project'] ?: null,
            'status'      => 'Submitted',
            'subtotal'    => $quote['subtotal'],
            'tax'         => $quote['tax'],
            'total'       => $quote['total'],
            'ship_to_id'  => $draft['shipTo'],
            'bill_to_id'  => $draft['shipTo'],
            'notes'       => json_encode([
                'files' => array_map(fn($f) => [
                    'name' => $f['name'], 'qty' => $f['qty'], 'sides' => $f['sides'],
                    'finishing' => $f['finishing'], 'ranges' => $f['ranges'], 'notes' => $f['notes'],
                ], $draft['files']),
            ]),
        ]);

        foreach ($quote['lines'] as $line) {
            Database::insert('order_lines', [
                'order_id'    => $orderId,
                'description' => $line['description'],
                'amount'      => $line['amount'],
            ]);
        }

        // Move files to a permanent per-order storage location
        $orderDir = PORTAL_UPLOADS . '/orders/' . $orderId;
        if (!is_dir($orderDir)) @mkdir($orderDir, 0755, true);
        foreach ($draft['files'] as $f) {
            $dest = "$orderDir/" . basename($f['path']);
            @rename($f['path'], $dest);
            Database::insert('order_files', [
                'order_id'   => $orderId,
                'filename'   => $f['name'],
                'path'       => $dest,
                'size_bytes' => $f['size_bytes'] ?? null,
            ]);
        }

        // Push to Monday (or dry-run if no API key configured)
        try { \Portal\Services\Monday::pushOrder($orderId); } catch (\Throwable $e) { error_log("Monday push failed: " . $e->getMessage()); }
        try { \Portal\Services\Notify::maybeNotifyStatusChange($orderId, '', 'Submitted'); } catch (\Throwable $e) { /* ignore */ }

        unset($_SESSION[self::DRAFT_KEY]);
        View::redirect('/orders/' . $orderId);
    }
}
