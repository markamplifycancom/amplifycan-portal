<?php
namespace Portal\Controllers;

use Portal\Auth;
use Portal\Database;
use Portal\View;
use Portal\Services\Monday;
use Portal\Services\Notify;

class OrderController
{
    public function index(): void
    {
        Auth::requireLogin();
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }
        $orders = Database::all(
            "SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC",
            [$customer['id']]
        );
        View::render('orders/index', [
            'customer' => $customer, 'orders' => $orders, 'user' => Auth::user(),
        ]);
    }

    public function show(array $args): void
    {
        Auth::requireLogin();
        $customer = Auth::customer();
        if (!$customer) { View::redirect('/dashboard'); return; }
        $id = (int)($args['id'] ?? 0);
        $order = Database::one(
            "SELECT * FROM orders WHERE id = ? AND customer_id = ?",
            [$id, $customer['id']]
        );
        if (!$order) { http_response_code(404); echo '<h1>Order not found</h1>'; return; }

        // Refresh status from Monday on view (best-effort, silent failures).
        $oldStatus = $order['status'];
        if (!empty($order['monday_item_id']) && PORTAL_MONDAY_API_KEY) {
            try { Monday::pullStatus($id); } catch (\Throwable $e) { /* ignore */ }
            $order = Database::one("SELECT * FROM orders WHERE id = ?", [$id]);
            if ($order['status'] !== $oldStatus) {
                try { Notify::maybeNotifyStatusChange($id, $oldStatus, $order['status']); } catch (\Throwable $e) { /* ignore */ }
            }
        }

        $lines = Database::all("SELECT * FROM order_lines WHERE order_id = ?", [$id]);
        $files = Database::all("SELECT * FROM order_files WHERE order_id = ?", [$id]);

        View::render('orders/show', [
            'order' => $order, 'lines' => $lines, 'files' => $files,
            'customer' => $customer, 'user' => Auth::user(), 'csrf' => Auth::csrfToken(),
        ]);
    }
}
