<?php
namespace Portal\Controllers;

use Portal\Auth;
use Portal\Database;
use Portal\View;

class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $customer = Auth::customer();

        // Admins go to /admin (Slice 6); for now redirect to dashboard with a banner.
        if (!$customer) {
            View::render('dashboard/admin', ['user' => $user], 'layout');
            return;
        }

        $custId = (int)$customer['id'];

        $orders = Database::all(
            "SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20",
            [$custId]
        );

        // Stats
        $openCount = (int) Database::value(
            "SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status NOT IN ('Delivered','Invoiced')",
            [$custId]
        );
        $ytdTotal = (float) Database::value(
            "SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_id = ? AND created_at >= ?",
            [$custId, date('Y') . '-01-01']
        );
        $orderCount = (int) Database::value(
            "SELECT COUNT(*) FROM orders WHERE customer_id = ?",
            [$custId]
        );

        $products = Database::all(
            "SELECT * FROM products WHERE customer_id = ? AND active = 1 ORDER BY last_ordered_at DESC NULLS LAST LIMIT 4",
            [$custId]
        );

        View::render('dashboard/index', [
            'user'       => $user,
            'customer'   => $customer,
            'orders'     => $orders,
            'openCount'  => $openCount,
            'ytdTotal'   => $ytdTotal,
            'orderCount' => $orderCount,
            'products'   => $products,
        ]);
    }
}
