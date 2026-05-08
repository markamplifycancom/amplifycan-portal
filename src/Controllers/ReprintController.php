<?php
namespace Portal\Controllers;

use Portal\Auth;
use Portal\Database;
use Portal\View;
use Portal\Services\Monday;
use Portal\Services\Notify;

/**
 * Reprint flow: re-order an old order, exactly or with a tweaked quantity.
 * Reuses the same files and specs from the original.
 *
 *   GET  /reprint                    — list past orders, "reprint this" buttons
 *   GET  /reprint/{id}               — confirm screen for one past order, optional qty change
 *   POST /reprint/{id}/place         — clone the order with new id, push to Monday, notify
 */
class ReprintController
{
    public function index(): void
    {
        Auth::requireLogin();
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }

        $orders = Database::all(
            "SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 30",
            [$customer['id']]
        );
        View::render('reprint/index', [
            'user' => Auth::user(), 'customer' => $customer, 'orders' => $orders,
            'csrf' => Auth::csrfToken(),
        ]);
    }

    public function show(array $args): void
    {
        Auth::requireLogin();
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }
        $id = (int)$args['id'];
        $order = Database::one("SELECT * FROM orders WHERE id = ? AND customer_id = ?", [$id, $customer['id']]);
        if (!$order) { View::redirect('/reprint'); return; }

        $lines = Database::all("SELECT * FROM order_lines WHERE order_id = ?", [$id]);
        $files = Database::all("SELECT * FROM order_files WHERE order_id = ?", [$id]);

        View::render('reprint/show', [
            'user' => Auth::user(), 'customer' => $customer,
            'order' => $order, 'lines' => $lines, 'files' => $files,
            'csrf' => Auth::csrfToken(),
        ]);
    }

    public function place(array $args): void
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/reprint'); return; }
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }

        $id = (int)$args['id'];
        $original = Database::one("SELECT * FROM orders WHERE id = ? AND customer_id = ?", [$id, $customer['id']]);
        if (!$original) { View::redirect('/reprint'); return; }

        $multiplier = max(0.01, (float)($_POST['multiplier'] ?? 1.0)); // qty multiplier (1.0 = exact reorder)

        $newName = 'Reprint of ' . $original['name'];
        $newSubtotal = round($original['subtotal'] * $multiplier, 2);
        $newTax      = round($newSubtotal * PORTAL_TAX_RATE, 2);
        $newTotal    = round($newSubtotal + $newTax, 2);

        $newOrderId = Database::insert('orders', [
            'customer_id' => $customer['id'],
            'user_id'     => Auth::user()['id'],
            'type'        => 'Reprint',
            'name'        => $newName,
            'project'     => $original['project'],
            'status'      => 'Submitted',
            'subtotal'    => $newSubtotal,
            'tax'         => $newTax,
            'total'       => $newTotal,
            'ship_to_id'  => $original['ship_to_id'],
            'bill_to_id'  => $original['bill_to_id'],
            'notes'       => 'Reprint of order #' . $original['id']
                              . ($multiplier === 1.0 ? '' : sprintf(' (x%.2g)', $multiplier)),
        ]);

        // Clone line items, scaled by multiplier
        $lines = Database::all("SELECT * FROM order_lines WHERE order_id = ?", [$id]);
        foreach ($lines as $l) {
            Database::insert('order_lines', [
                'order_id' => $newOrderId,
                'description' => $l['description'],
                'amount' => round($l['amount'] * $multiplier, 2),
            ]);
        }

        // Clone file references — point at the original files (still in storage from prior order)
        $files = Database::all("SELECT * FROM order_files WHERE order_id = ?", [$id]);
        foreach ($files as $f) {
            Database::insert('order_files', [
                'order_id' => $newOrderId,
                'filename' => $f['filename'],
                'path'     => $f['path'],         // reuse the original path
                'size_bytes' => $f['size_bytes'] ?? null,
            ]);
        }

        try { Monday::pushOrder($newOrderId); } catch (\Throwable $e) { error_log("Monday push failed: " . $e->getMessage()); }
        try { Notify::maybeNotifyStatusChange($newOrderId, '', 'Submitted'); } catch (\Throwable $e) { /* ignore */ }

        View::redirect('/orders/' . $newOrderId);
    }
}
