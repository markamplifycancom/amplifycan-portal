<?php
namespace Portal\Controllers;

use Portal\Auth;
use Portal\Database;
use Portal\View;

/**
 * Admin: lets you set up customers, users, addresses, saved products, and pricing rules
 * without touching the database directly.
 *
 *   GET  /admin                              — list customers + system stats
 *   GET  /admin/customers/new                — new customer form
 *   POST /admin/customers/new                — create customer
 *   GET  /admin/customers/{id}               — customer detail (users, addresses, products, pricing)
 *   POST /admin/customers/{id}/save          — update customer fields
 *   POST /admin/customers/{id}/users/new     — add a user
 *   POST /admin/customers/{id}/addresses/new — add an address
 *   POST /admin/customers/{id}/products/new  — add a saved product
 *   POST /admin/customers/{id}/products/{pid}/save   — update product
 *   POST /admin/customers/{id}/products/{pid}/delete — soft-delete product
 *   POST /admin/customers/{id}/rules/save    — save reprint pricing rules
 */
class AdminController
{
    private function requireAdmin(): void
    {
        Auth::requireLogin();
        $u = Auth::user();
        if (empty($u['is_admin'])) { View::redirect('/dashboard'); exit; }
    }

    public function index(): void
    {
        $this->requireAdmin();
        $customers = Database::all("SELECT c.*,
            (SELECT COUNT(*) FROM orders WHERE customer_id = c.id) AS orders,
            (SELECT COUNT(*) FROM products WHERE customer_id = c.id AND active = 1) AS products,
            (SELECT COUNT(*) FROM users WHERE customer_id = c.id) AS users,
            (SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_id = c.id) AS total_revenue
            FROM customers c ORDER BY c.name");
        $stats = [
            'customers'  => count($customers),
            'orders'     => (int) Database::value("SELECT COUNT(*) FROM orders"),
            'products'   => (int) Database::value("SELECT COUNT(*) FROM products WHERE active = 1"),
            'revenue'    => (float) Database::value("SELECT COALESCE(SUM(total),0) FROM orders"),
        ];
        View::render('admin/index', [
            'user' => Auth::user(), 'customer' => null,
            'customers' => $customers, 'stats' => $stats,
            'csrf' => Auth::csrfToken(),
        ]);
    }

    public function newCustomerForm(): void
    {
        $this->requireAdmin();
        View::render('admin/customer_new', [
            'user' => Auth::user(), 'customer' => null, 'csrf' => Auth::csrfToken(),
        ]);
    }

    public function createCustomer(): void
    {
        $this->requireAdmin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/admin'); return; }
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { View::redirect('/admin/customers/new'); return; }
        $cid = Database::insert('customers', [
            'name' => $name,
            'free_delivery' => !empty($_POST['free_delivery']) ? 1 : 0,
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        // Default reprint pricing rules — can be overridden later.
        $defaults = [
            ['bw-letter-bond',    'B&W 8.5×11 (20# bond)',         0.10],
            ['color-letter-bond', 'Color 8.5×11 (20# bond)',       0.50],
            ['bw-tabloid',        'B&W 11×17',                     0.20],
            ['color-tabloid',     'Color 11×17',                   1.00],
            ['cardstock-add',     'Cardstock surcharge (any size)', 0.10],
            ['gloss-add',         'Gloss surcharge (any size)',     0.15],
        ];
        $rs = Database::pdo()->prepare("INSERT INTO pricing_rules (customer_id, rule_key, label, price) VALUES (?,?,?,?)");
        foreach ($defaults as $d) $rs->execute([$cid, $d[0], $d[1], $d[2]]);

        View::redirect("/admin/customers/$cid");
    }

    public function showCustomer(array $args): void
    {
        $this->requireAdmin();
        $cid = (int)$args['id'];
        $customer = Database::one("SELECT * FROM customers WHERE id = ?", [$cid]);
        if (!$customer) { View::redirect('/admin'); return; }
        View::render('admin/customer', [
            'user'      => Auth::user(),
            'customer'  => null,        // header customer (admin has none)
            'subject'   => $customer,   // the customer being managed
            'users'     => Database::all("SELECT * FROM users WHERE customer_id = ? ORDER BY id", [$cid]),
            'addresses' => Database::all("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default_ship DESC, id", [$cid]),
            'products'  => Database::all("SELECT * FROM products WHERE customer_id = ? ORDER BY active DESC, name", [$cid]),
            'rules'     => Database::all("SELECT * FROM pricing_rules WHERE customer_id = ? ORDER BY id", [$cid]),
            'orders'    => Database::all("SELECT id, type, name, status, total, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20", [$cid]),
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function saveCustomer(array $args): void
    {
        $this->requireAdmin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/admin'); return; }
        $cid = (int)$args['id'];
        Database::pdo()->prepare("UPDATE customers SET name = ?, free_delivery = ?, notes = ?,
            notify_received = ?, notify_in_production = ?, notify_shipped = ?, notify_delivered = ?, notify_invoiced = ?
            WHERE id = ?")
            ->execute([
                trim($_POST['name'] ?? ''),
                !empty($_POST['free_delivery']) ? 1 : 0,
                trim($_POST['notes'] ?? ''),
                !empty($_POST['notify_received'])      ? 1 : 0,
                !empty($_POST['notify_in_production']) ? 1 : 0,
                !empty($_POST['notify_shipped'])       ? 1 : 0,
                !empty($_POST['notify_delivered'])     ? 1 : 0,
                !empty($_POST['notify_invoiced'])      ? 1 : 0,
                $cid,
            ]);
        View::redirect("/admin/customers/$cid");
    }

    public function addUser(array $args): void
    {
        $this->requireAdmin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/admin'); return; }
        $cid = (int)$args['id'];
        $email = trim($_POST['email'] ?? '');
        $pw    = $_POST['password'] ?? '';
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name']  ?? '');
        if ($email === '' || $pw === '') { View::redirect("/admin/customers/$cid"); return; }
        try {
            Database::insert('users', [
                'customer_id' => $cid,
                'email' => $email, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
                'first_name' => $first, 'last_name' => $last,
            ]);
        } catch (\Throwable $e) { /* duplicate email; ignore for now */ }
        View::redirect("/admin/customers/$cid");
    }

    public function addAddress(array $args): void
    {
        $this->requireAdmin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/admin'); return; }
        $cid = (int)$args['id'];
        $label = trim($_POST['label'] ?? '');
        if ($label === '') { View::redirect("/admin/customers/$cid"); return; }
        $defaultShip = !empty($_POST['is_default_ship']) ? 1 : 0;
        $defaultBill = !empty($_POST['is_default_bill']) ? 1 : 0;
        if ($defaultShip) Database::exec("UPDATE addresses SET is_default_ship = 0 WHERE customer_id = ?", [$cid]);
        if ($defaultBill) Database::exec("UPDATE addresses SET is_default_bill = 0 WHERE customer_id = ?", [$cid]);
        Database::insert('addresses', [
            'customer_id' => $cid, 'label' => $label,
            'street1' => trim($_POST['street1'] ?? ''),
            'city'    => trim($_POST['city'] ?? ''),
            'state'   => trim($_POST['state'] ?? ''),
            'zip'     => trim($_POST['zip'] ?? ''),
            'is_default_ship' => $defaultShip,
            'is_default_bill' => $defaultBill,
        ]);
        View::redirect("/admin/customers/$cid");
    }

    public function addProduct(array $args): void
    {
        $this->requireAdmin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/admin'); return; }
        $cid = (int)$args['id'];
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { View::redirect("/admin/customers/$cid"); return; }
        Database::insert('products', [
            'customer_id'  => $cid,
            'name'         => $name,
            'spec'         => trim($_POST['spec'] ?? ''),
            'icon'         => trim($_POST['icon'] ?? '') ?: '📄',
            'unit_price'   => (float)($_POST['unit_price'] ?? 0),
            'unit_qty'     => max(1, (int)($_POST['unit_qty'] ?? 1)),
            'price_label'  => trim($_POST['price_label'] ?? ''),
            'multi_line'   => !empty($_POST['multi_line']) ? 1 : 0,
            'fulfillment'  => $_POST['fulfillment'] ?? 'inhouse',
            'active'       => 1,
        ]);
        View::redirect("/admin/customers/$cid");
    }

    public function saveProduct(array $args): void
    {
        $this->requireAdmin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/admin'); return; }
        $cid = (int)$args['id']; $pid = (int)$args['pid'];
        Database::pdo()->prepare(
            "UPDATE products SET name = ?, spec = ?, icon = ?, unit_price = ?, unit_qty = ?, price_label = ?,
             multi_line = ?, fulfillment = ?, active = ? WHERE id = ? AND customer_id = ?"
        )->execute([
            trim($_POST['name'] ?? ''),
            trim($_POST['spec'] ?? ''),
            trim($_POST['icon'] ?? '') ?: '📄',
            (float)($_POST['unit_price'] ?? 0),
            max(1, (int)($_POST['unit_qty'] ?? 1)),
            trim($_POST['price_label'] ?? ''),
            !empty($_POST['multi_line']) ? 1 : 0,
            $_POST['fulfillment'] ?? 'inhouse',
            !empty($_POST['active']) ? 1 : 0,
            $pid, $cid,
        ]);
        View::redirect("/admin/customers/$cid");
    }

    public function saveRules(array $args): void
    {
        $this->requireAdmin();
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) { View::redirect('/admin'); return; }
        $cid = (int)$args['id'];
        $rules = $_POST['rules'] ?? [];
        $stmt = Database::pdo()->prepare("UPDATE pricing_rules SET label = ?, price = ? WHERE id = ? AND customer_id = ?");
        foreach ($rules as $rid => $r) {
            $stmt->execute([
                trim($r['label'] ?? ''),
                (float)($r['price'] ?? 0),
                (int)$rid, $cid,
            ]);
        }
        // New rule
        if (!empty($_POST['new_rule_key']) && !empty($_POST['new_rule_label'])) {
            try {
                Database::insert('pricing_rules', [
                    'customer_id' => $cid,
                    'rule_key' => trim($_POST['new_rule_key']),
                    'label'    => trim($_POST['new_rule_label']),
                    'price'    => (float)($_POST['new_rule_price'] ?? 0),
                ]);
            } catch (\Throwable $e) { /* duplicate key; skip */ }
        }
        View::redirect("/admin/customers/$cid");
    }

    public function impersonate(array $args): void
    {
        $this->requireAdmin();
        if (!\Portal\Auth::checkCsrf($_POST['csrf'] ?? null)) { \Portal\View::redirect('/admin'); return; }
        $cid = (int)$args['id'];
        if (\Portal\Auth::startImpersonation($cid)) {
            \Portal\View::redirect('/dashboard');
        } else {
            \Portal\View::redirect('/admin');
        }
    }

    public function stopImpersonation(): void
    {
        if (!\Portal\Auth::checkCsrf($_POST['csrf'] ?? null)) { \Portal\View::redirect('/dashboard'); return; }
        \Portal\Auth::stopImpersonation();
        \Portal\View::redirect('/admin');
    }

    public function addAdminUser(): void
    {
        $this->requireAdmin();
        if (!\Portal\Auth::checkCsrf($_POST['csrf'] ?? null)) { \Portal\View::redirect('/admin'); return; }
        $email = trim($_POST['email'] ?? '');
        $pw    = $_POST['password'] ?? '';
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name']  ?? '');
        if ($email === '' || $pw === '') { \Portal\View::redirect('/admin'); return; }
        try {
            \Portal\Database::insert('users', [
                'customer_id' => null,
                'email' => $email,
                'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
                'first_name' => $first,
                'last_name' => $last,
                'is_admin' => 1,
            ]);
        } catch (\Throwable $e) { /* dup email; ignore */ }
        \Portal\View::redirect('/admin');
    }
}
