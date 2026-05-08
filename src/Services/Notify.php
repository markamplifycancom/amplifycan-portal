<?php
namespace Portal\Services;

use Portal\Database;

/**
 * Email notifications. Fires when an order's status changes (e.g. via Monday status sync).
 *
 * Per-customer preferences (toggles) live on the customers table:
 *   notify_received, notify_in_production, notify_shipped, notify_delivered, notify_invoiced
 *
 * Defaults: shipped + delivered + invoiced ON. Other touchpoints OFF.
 *
 * Send mechanism: PHP's mail() by default; swap in PHPMailer + SMTP for production.
 * SMTP config via env vars: PORTAL_SMTP_HOST, PORTAL_SMTP_PORT, PORTAL_SMTP_USER, PORTAL_SMTP_PASS, PORTAL_FROM_EMAIL.
 */
class Notify
{
    public static function maybeNotifyStatusChange(int $orderId, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) return;
        $order = Database::one("SELECT * FROM orders WHERE id = ?", [$orderId]);
        if (!$order) return;
        $customer = Database::one("SELECT * FROM customers WHERE id = ?", [$order['customer_id']]);
        $user = Database::one("SELECT * FROM users WHERE id = ?", [$order['user_id']]);
        if (!$customer || !$user) return;

        $key = match ($newStatus) {
            'Submitted'     => 'notify_received',
            'In Production' => 'notify_in_production',
            'Shipped'       => 'notify_shipped',
            'Delivered'     => 'notify_delivered',
            'Invoiced'      => 'notify_invoiced',
            default         => null,
        };
        if (!$key || empty($customer[$key])) return;
        if (($order['last_notified_status'] ?? '') === $newStatus) return; // already notified

        $subject = "Order #$orderId — " . $newStatus;
        $body  = "Hi " . ($user['first_name'] ?: 'there') . ",\n\n";
        $body .= "Your order \"{$order['name']}\" is now: $newStatus.\n";
        if ($order['tracking']) $body .= "Tracking: {$order['tracking']}\n";
        $body .= "\nView your order: " . PORTAL_BASE_URL . "/orders/{$order['id']}\n\n";
        $body .= "— AmplifyCan\n";

        self::send($user['email'], $subject, $body);

        Database::pdo()->prepare("UPDATE orders SET last_notified_status = ? WHERE id = ?")
            ->execute([$newStatus, $orderId]);
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        $from = getenv('PORTAL_FROM_EMAIL') ?: 'noreply@amplifycan.com';
        // PHP mail() — works if MTA is configured. Production should use PHPMailer + SMTP.
        $headers = "From: AmplifyCan <$from>\r\n"
                 . "Reply-To: $from\r\n"
                 . "X-Mailer: AmplifyCan Portal\r\n"
                 . "Content-Type: text/plain; charset=utf-8\r\n";
        // For local dev / dry-run: log instead of sending
        if (getenv('PORTAL_EMAIL_DRYRUN') !== 'false') {
            $logPath = PORTAL_STORAGE . '/email_dryrun.log';
            $entry = "===== " . date('Y-m-d H:i:s') . " =====\n"
                   . "To: $to\nSubject: $subject\n\n$body\n\n";
            @file_put_contents($logPath, $entry, FILE_APPEND);
            return true;
        }
        return @mail($to, $subject, $body, $headers);
    }
}
