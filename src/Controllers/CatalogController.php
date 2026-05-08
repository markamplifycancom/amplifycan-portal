<?php
namespace Portal\Controllers;

use Portal\Auth;
use Portal\Database;
use Portal\View;
use Portal\Services\Preflight;

/**
 * Catalog flow:
 *   GET  /catalog                        — list saved products
 *   GET  /catalog/{id}                   — start/continue order for a product
 *   POST /catalog/{id}/upload            — upload artwork (or saved-art toggle)
 *   POST /catalog/{id}/update            — update qty/recipients/notes/ship-to
 *   POST /catalog/{id}/place             — run preflight; if pass, place order; if fail, redirect back
 *   POST /catalog/{id}/clear             — discard draft
 */
class CatalogController
{
    private const DRAFT_KEY = 'catalog_drafts'; // keyed by productId

    public function index(): void
    {
        Auth::requireLogin();
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }
        $products = Database::all(
            "SELECT * FROM products WHERE customer_id = ? AND active = 1 ORDER BY name",
            [$customer['id']]
        );
        View::render('catalog/index', [
            'user' => Auth::user(), 'customer' => $customer, 'products' => $products,
        ]);
    }

    public function show(array $args): void
    {
        Auth::requireLogin();
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }

        $product = Database::one(
            "SELECT * FROM products WHERE id = ? AND customer_id = ?",
            [(int)$args['id'], $customer['id']]
        );
        if (!$product) { View::redirect('/catalog'); return; }

        $draft = $_SESSION[self::DRAFT_KEY][$product['id']] ?? null;
        if (!$draft) {
            $draft = $this->newDraft($product, $customer);
            $_SESSION[self::DRAFT_KEY][$product['id']] = $draft;
        }

        $addresses = Database::all(
            "SELECT id, label FROM addresses WHERE customer_id = ? ORDER BY is_default_ship DESC, id",
            [$customer['id']]
        );

        $quote = $this->computeQuote($product, $draft, $customer);
        $preflight = $_SESSION[self::DRAFT_KEY][$product['id']]['_preflight'] ?? null;

        View::render('catalog/order', [
            'user'      => Auth::user(),
            'customer'  => $customer,
            'product'   => $product,
            'draft'     => $draft,
            'addresses' => $addresses,
            'quote'     => $quote,
            'preflight' => $preflight,
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function upload(array $args): void
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/catalog'); return; }
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }
        $productId = (int)$args['id'];
        $product = Database::one("SELECT * FROM products WHERE id = ? AND customer_id = ?", [$productId, $customer['id']]);
        if (!$product) { View::redirect('/catalog'); return; }
        $draft = $_SESSION[self::DRAFT_KEY][$productId] ?? $this->newDraft($product, $customer);

        $lineIdx = (int)($_POST['lineIdx'] ?? 0);
        $f = $_FILES['pdf'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            View::redirect("/catalog/$productId");
            return;
        }

        $name = $f['name'];
        $tmp  = $f['tmp_name'];
        if (!is_uploaded_file($tmp) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') {
            View::redirect("/catalog/$productId");
            return;
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        $destDir = PORTAL_UPLOADS . '/draft/' . session_id();
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        $fileId  = bin2hex(random_bytes(8));
        $destPath = "$destDir/{$fileId}_{$safeName}";
        move_uploaded_file($tmp, $destPath);

        if (!empty($product['multi_line'])) {
            // Save under the specific recipient line
            $draft['lines'][$lineIdx]['file'] = ['id' => $fileId, 'name' => $name, 'path' => $destPath];
        } else {
            // Single-file product
            $draft['file'] = ['id' => $fileId, 'name' => $name, 'path' => $destPath];
        }
        // Clear any previous preflight result, since file changed
        unset($draft['_preflight']);
        $_SESSION[self::DRAFT_KEY][$productId] = $draft;
        View::redirect("/catalog/$productId");
    }

    public function update(array $args): void
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/catalog'); return; }
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }
        $productId = (int)$args['id'];
        $product = Database::one("SELECT * FROM products WHERE id = ? AND customer_id = ?", [$productId, $customer['id']]);
        if (!$product) { View::redirect('/catalog'); return; }
        $draft = $_SESSION[self::DRAFT_KEY][$productId] ?? $this->newDraft($product, $customer);

        $draft['shipTo']  = (int)($_POST['shipTo'] ?? $draft['shipTo']);
        $draft['billTo']  = (int)($_POST['billTo'] ?? $draft['billTo']);
        $draft['project'] = trim($_POST['project'] ?? '');
        $draft['notes']   = trim($_POST['notes'] ?? '');

        if (!empty($product['multi_line'])) {
            $linesIn = $_POST['lines'] ?? [];
            $newLines = [];
            foreach ($linesIn as $idx => $l) {
                $existing = $draft['lines'][$idx] ?? [];
                $newLines[] = [
                    'name' => trim($l['name'] ?? ''),
                    'qty'  => max(1, (int)($l['qty'] ?? $product['unit_qty'])),
                    'file' => $existing['file'] ?? null,
                ];
            }
            // Add an empty line on demand
            if (!empty($_POST['addLine'])) {
                $newLines[] = ['name' => '', 'qty' => (int)$product['unit_qty'], 'file' => null];
            }
            // Remove a line by index
            if (isset($_POST['removeLine']) && $_POST['removeLine'] !== '') {
                $rIdx = (int)$_POST['removeLine'];
                if (isset($newLines[$rIdx])) {
                    if (!empty($newLines[$rIdx]['file']['path'])) @unlink($newLines[$rIdx]['file']['path']);
                    unset($newLines[$rIdx]);
                    $newLines = array_values($newLines);
                }
            }
            $draft['lines'] = $newLines ?: $draft['lines'];
        } else {
            $draft['qty'] = max(1, (int)($_POST['qty'] ?? 1));
            // Saved-artwork toggle for products like ICSI banners
            if (!empty($_POST['useSavedArt'])) {
                $draft['file'] = ['id' => 'saved', 'name' => 'On-file artwork', 'path' => null];
            }
        }
        unset($draft['_preflight']);
        $_SESSION[self::DRAFT_KEY][$productId] = $draft;
        View::redirect("/catalog/$productId");
    }

    public function place(array $args): void
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/catalog'); return; }
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }
        $productId = (int)$args['id'];
        $product = Database::one("SELECT * FROM products WHERE id = ? AND customer_id = ?", [$productId, $customer['id']]);
        if (!$product) { View::redirect('/catalog'); return; }
        $draft = $_SESSION[self::DRAFT_KEY][$productId] ?? null;
        if (!$draft) { View::redirect("/catalog/$productId"); return; }

        // Run preflight on all files
        $spec = $this->preflightSpecFor($product);
        $preflightResults = [];
        $hasFail = false;
        if (!empty($product['multi_line'])) {
            foreach ($draft['lines'] as $idx => $line) {
                if (empty($line['file']['path']) || !is_file($line['file']['path'])) continue;
                $r = Preflight::check($line['file']['path'], $spec);
                $r['file'] = $line['file']['name'];
                $r['recipient'] = ($line['name'] ?: '(unnamed)') . " — " . $line['qty'] . " " . $product['name'];
                if ($r['status'] === 'fail') $hasFail = true;
                $preflightResults[] = $r;
            }
        } else {
            if (!empty($draft['file']['path']) && is_file($draft['file']['path'])) {
                $r = Preflight::check($draft['file']['path'], $spec);
                $r['file'] = $draft['file']['name'];
                $r['recipient'] = $product['name'] . ' × ' . ($draft['qty'] ?? 1);
                if ($r['status'] === 'fail') $hasFail = true;
                $preflightResults[] = $r;
            } elseif (!empty($draft['file']['id']) && $draft['file']['id'] === 'saved') {
                $preflightResults[] = [
                    'file' => 'On-file artwork',
                    'recipient' => $product['name'] . ' × ' . ($draft['qty'] ?? 1),
                    'status' => 'pass',
                    'checks' => [
                        ['label' => 'Using approved on-file artwork', 'value' => null, 'ok' => true],
                        ['label' => 'Specs match saved product', 'value' => null, 'ok' => true],
                    ],
                    'action' => null,
                ];
            }
        }

        $draft['_preflight'] = $preflightResults;
        $_SESSION[self::DRAFT_KEY][$productId] = $draft;

        // Show results inline; only proceed if user confirms (separate confirm button) — but for simplicity,
        // if no failures, place immediately. If there are failures, redirect back with results visible.
        if ($hasFail || empty($preflightResults)) {
            View::redirect("/catalog/$productId");
            return;
        }

        // Confirmation step: only place if explicit confirm came in
        if (empty($_POST['confirm'])) {
            View::redirect("/catalog/$productId");
            return;
        }

        $quote = $this->computeQuote($product, $draft, $customer);

        $orderId = Database::insert('orders', [
            'customer_id' => $customer['id'],
            'user_id'     => Auth::user()['id'],
            'placed_by_admin_id' => Auth::isImpersonating() ? Auth::adminUser()['id'] : null,
            'type'        => 'Catalog',
            'name'        => $product['name'] . (!empty($product['multi_line']) && count($draft['lines']) > 0
                              ? ' — ' . implode(', ', array_filter(array_map(fn($l) => $l['name'] ?: null, $draft['lines'])))
                              : ''),
            'project'     => $draft['project'] ?: null,
            'status'      => 'Submitted',
            'subtotal'    => $quote['subtotal'],
            'tax'         => $quote['tax'],
            'total'       => $quote['total'],
            'ship_to_id'  => $draft['shipTo'],
            'bill_to_id'  => $draft['billTo'] ?: $draft['shipTo'],
            'notes'       => $draft['notes'] ?: null,
        ]);
        foreach ($quote['lines'] as $line) {
            Database::insert('order_lines', [
                'order_id'    => $orderId,
                'description' => $line['description'],
                'amount'      => $line['amount'],
            ]);
        }

        // Move files
        $orderDir = PORTAL_UPLOADS . '/orders/' . $orderId;
        if (!is_dir($orderDir)) @mkdir($orderDir, 0755, true);
        if (!empty($product['multi_line'])) {
            foreach ($draft['lines'] as $line) {
                if (empty($line['file']['path'])) continue;
                $dest = "$orderDir/" . basename($line['file']['path']);
                @rename($line['file']['path'], $dest);
                Database::insert('order_files', [
                    'order_id' => $orderId, 'filename' => $line['file']['name'], 'path' => $dest,
                ]);
            }
        } else {
            if (!empty($draft['file']['path'])) {
                $dest = "$orderDir/" . basename($draft['file']['path']);
                @rename($draft['file']['path'], $dest);
                Database::insert('order_files', [
                    'order_id' => $orderId, 'filename' => $draft['file']['name'], 'path' => $dest,
                ]);
            } elseif (!empty($draft['file']['id']) && $draft['file']['id'] === 'saved') {
                Database::insert('order_files', [
                    'order_id' => $orderId, 'filename' => 'Saved artwork (on file)', 'path' => 'saved',
                ]);
            }
        }

        // Push to Monday (or dry-run if no API key configured)
        try { \Portal\Services\Monday::pushOrder($orderId); } catch (\Throwable $e) { error_log("Monday push failed: " . $e->getMessage()); }
        try { \Portal\Services\Notify::maybeNotifyStatusChange($orderId, '', 'Submitted'); } catch (\Throwable $e) { /* ignore */ }

        unset($_SESSION[self::DRAFT_KEY][$productId]);
        View::redirect('/orders/' . $orderId);
    }

    public function clearDraft(array $args): void
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/catalog'); return; }
        $productId = (int)$args['id'];
        $draft = $_SESSION[self::DRAFT_KEY][$productId] ?? null;
        if ($draft) {
            if (!empty($draft['file']['path'])) @unlink($draft['file']['path']);
            foreach (($draft['lines'] ?? []) as $line) {
                if (!empty($line['file']['path'])) @unlink($line['file']['path']);
            }
            unset($_SESSION[self::DRAFT_KEY][$productId]);
        }
        View::redirect('/catalog');
    }

    // ----- helpers -----
    private function newDraft(array $product, array $customer): array
    {
        $defAddr = Database::one(
            "SELECT id FROM addresses WHERE customer_id = ? AND is_default_ship = 1 LIMIT 1",
            [$customer['id']]
        );
        $shipTo = $defAddr ? (int)$defAddr['id'] : null;
        if (!empty($product['multi_line'])) {
            return [
                'shipTo' => $shipTo, 'billTo' => $shipTo, 'project' => '', 'notes' => '',
                'lines' => [['name' => '', 'qty' => (int)$product['unit_qty'], 'file' => null]],
            ];
        }
        return [
            'shipTo' => $shipTo, 'billTo' => $shipTo, 'project' => '', 'notes' => '',
            'qty' => (int)($product['unit_qty'] === 1 ? 1 : 1),
            'file' => null,
        ];
    }

    private function computeQuote(array $product, array $draft, array $customer): array
    {
        $lines = [];
        if (!empty($product['multi_line'])) {
            foreach ($draft['lines'] as $line) {
                if (empty($line['name'])) continue;
                $sets = max(1, (int)($line['qty'] / max(1, (int)$product['unit_qty'])));
                $amount = round((float)$product['unit_price'] * $sets, 2);
                $lines[] = [
                    'description' => $line['name'] . ' — ' . $line['qty'],
                    'amount'      => $amount,
                ];
            }
        } else {
            $qty = max(1, (int)($draft['qty'] ?? 1));
            $amount = round((float)$product['unit_price'] * $qty, 2);
            $lines[] = ['description' => $product['name'] . ' × ' . $qty, 'amount' => $amount];
        }
        $delivery = empty($customer['free_delivery']) ? 22.0 : 0.0;
        if ($delivery > 0) $lines[] = ['description' => 'Delivery', 'amount' => $delivery];
        $subtotal = round(array_sum(array_column($lines, 'amount')), 2);
        $tax = round($subtotal * PORTAL_TAX_RATE, 2);
        $total = round($subtotal + $tax, 2);
        return compact('lines', 'subtotal', 'tax', 'total');
    }

    private function preflightSpecFor(array $product): array
    {
        // Map a product spec string like "14pt matte, 3.5×2, 4/4 color" or "30×42" into a preflight spec.
        $spec = ['product' => $product['name']];
        if (preg_match('#([\d.]+)\s*[x×]\s*([\d.]+)#u', $product['spec'] ?? '', $m)) {
            $spec['expected_size'] = $m[1] . '×' . $m[2];
        } elseif (preg_match('#(\d+)\s*(?:x|×|′|\')?\s*(\d+)#u', $product['name'], $m)) {
            $spec['expected_size'] = $m[1] . '×' . $m[2];
        }
        $spec['min_dpi'] = match (true) {
            stripos($product['name'], 'banner') !== false || stripos($product['name'], 'foam') !== false || stripos($product['name'], 'sign') !== false => 150,
            default => 300,
        };
        return $spec;
    }
}
