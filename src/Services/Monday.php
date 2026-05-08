<?php
namespace Portal\Services;

use Portal\Database;

/**
 * Monday.com integration — pushes portal orders into AmplifyCan's Estimates board (id 8483187264).
 *
 * Status lifecycle in that board:
 *   New Estimate → Estimate sent to cx → Temporary Approved / Estimate approved → Invoiced
 *
 * Portal orders land at "Estimate approved" since the customer already approved in the portal.
 *
 * The Estimates board has a subitems board (id 8483469691) that holds the line items;
 * each subitem has a quantity × price formula that rolls up to the parent's "Total $ before Tax" mirror.
 *
 * In dry-run mode (PORTAL_MONDAY_API_KEY unset), payloads are logged to monday_dryrun.log
 * so you can verify what will be sent before going live.
 */
class Monday
{
    private const ENDPOINT = 'https://api.monday.com/v2';

    /**
     * Board ids and column ids for AmplifyCan's actual Monday workspace.
     * Override via env vars if you ever migrate boards.
     */
    public static function estimatesBoardId(): int
    {
        return (int)(getenv('PORTAL_MONDAY_ESTIMATES_BOARD_ID') ?: 8483187264);
    }

    public static function subitemsBoardId(): int
    {
        return (int)(getenv('PORTAL_MONDAY_SUBITEMS_BOARD_ID') ?: 8483469691);
    }

    public const COL_ESTIMATE_NUM       = 'text_mknkpzvt';
    public const COL_STATUS             = 'status_mkn5fbp5';     // status — Estimate approved, etc.
    public const COL_ESTIMATE_TYPE      = 'color_mknpwzcp';      // Estimate Type — Others / Graphics / Equipment
    public const COL_LEAD_SOURCE        = 'color_mkxkavvs';      // Lead Source — we add "Portal" via createLabelsIfMissing
    public const COL_SHIP_STREET        = 'text_mkp1vxse';
    public const COL_SHIP_CITY          = 'text_mkp1kaac';
    public const COL_SHIP_STATE         = 'text_mkpnry5a';
    public const COL_SHIP_ZIP           = 'text_mkp1wc8c';
    public const COL_BILL_STREET        = 'text_mkwnesxx';
    public const COL_BILL_CITY          = 'text_mkwn4xax';
    public const COL_BILL_STATE         = 'text_mkwnbyp1';
    public const COL_BILL_ZIP           = 'text_mkwnafwq';
    public const COL_PO_NUMBER          = 'text_mkq0tp4e';
    public const COL_TAX_DROPDOWN       = 'dropdown_mkp1xss1';
    public const COL_TAX_AMOUNT         = 'numeric_mknxsj1n';
    public const COL_TOTAL_AFTER_TAX    = 'numeric_mknxq8b';
    public const COL_DATE_CREATED       = 'date4';
    public const COL_APPROVED_ON        = 'date_mkrjnb5x';
    public const COL_APPROVED_BOOL      = 'boolean_mm2n6ar9';
    public const COL_APPROVED_COLOR     = 'color_mm2nxjdf';     // single-label "v"

    // Subitem columns
    public const SUB_DESCRIPTION    = 'long_text_mkn5nvxv';
    public const SUB_CX_SALES_PRICE = 'numbers_mkncg0yy';
    public const SUB_QUANTITY       = 'quantity_mkn5c6xs';

    public static function pushOrder(int $orderId): ?string
    {
        $order = Database::one("SELECT * FROM orders WHERE id = ?", [$orderId]);
        if (!$order) return null;
        $customer = Database::one("SELECT * FROM customers WHERE id = ?", [$order['customer_id']]);
        $shipTo = $order['ship_to_id'] ? Database::one("SELECT * FROM addresses WHERE id = ?", [$order['ship_to_id']]) : null;
        $billTo = $order['bill_to_id'] ? Database::one("SELECT * FROM addresses WHERE id = ?", [$order['bill_to_id']]) : $shipTo;
        $lines  = Database::all("SELECT * FROM order_lines WHERE order_id = ?", [$orderId]);
        $files  = Database::all("SELECT * FROM order_files WHERE order_id = ?", [$orderId]);

        $itemName = sprintf('#%d %s — %s', $orderId, $customer['name'], $order['name']);

        $columnValues = [
            self::COL_ESTIMATE_NUM      => 'P-' . $orderId,
            self::COL_STATUS            => ['label' => 'Estimate approved'],
            self::COL_ESTIMATE_TYPE     => ['label' => 'Others'],
            self::COL_LEAD_SOURCE       => ['label' => 'Portal'],          // createLabelsIfMissing: true creates this label on first use
            self::COL_TAX_DROPDOWN      => ['labels' => ['TAX']],
            self::COL_TAX_AMOUNT        => (float)$order['tax'],
            self::COL_TOTAL_AFTER_TAX   => (float)$order['total'],
            self::COL_DATE_CREATED      => ['date' => substr($order['created_at'], 0, 10)],
            self::COL_APPROVED_ON       => ['date' => substr($order['created_at'], 0, 10)],
            self::COL_APPROVED_BOOL     => ['checked' => 'true'],
            self::COL_APPROVED_COLOR    => ['label' => 'v'],
        ];
        if ($order['project']) $columnValues[self::COL_PO_NUMBER] = (string)$order['project'];
        if ($shipTo) {
            $columnValues[self::COL_SHIP_STREET] = (string)($shipTo['street1'] ?? '');
            $columnValues[self::COL_SHIP_CITY]   = (string)($shipTo['city']    ?? '');
            $columnValues[self::COL_SHIP_STATE]  = (string)($shipTo['state']   ?? '');
            $columnValues[self::COL_SHIP_ZIP]    = (string)($shipTo['zip']     ?? '');
        }
        if ($billTo) {
            $columnValues[self::COL_BILL_STREET] = (string)($billTo['street1'] ?? '');
            $columnValues[self::COL_BILL_CITY]   = (string)($billTo['city']    ?? '');
            $columnValues[self::COL_BILL_STATE]  = (string)($billTo['state']   ?? '');
            $columnValues[self::COL_BILL_ZIP]    = (string)($billTo['zip']     ?? '');
        }

        $update = "Order #{$orderId} from {$customer['name']}\n";
        $update .= "Type: {$order['type']}\n";
        if ($order['project']) $update .= "Project: {$order['project']}\n";
        $update .= "\nLine items:\n";
        foreach ($lines as $l) $update .= "  • {$l['description']} — \$" . number_format((float)$l['amount'], 2) . "\n";
        $update .= "\nSubtotal: \$" . number_format((float)$order['subtotal'], 2) . "\n";
        $update .= "Tax: \$" . number_format((float)$order['tax'], 2) . "\n";
        $update .= "Total: \$" . number_format((float)$order['total'], 2) . "\n";
        if ($shipTo) $update .= "Ship to: {$shipTo['label']}\n";
        if ($order['notes']) $update .= "Notes: {$order['notes']}\n";
        if (!empty($files)) {
            $update .= "\nFiles attached:\n";
            foreach ($files as $f) $update .= "  • {$f['filename']}\n";
        }

        if (!PORTAL_MONDAY_API_KEY) {
            return self::dryRun($order, $itemName, $columnValues, $lines, $update);
        }

        // 1. Create the parent estimate
        $createMut = 'mutation ($boardId: ID!, $name: String!, $cv: JSON!) {
            create_item(board_id: $boardId, item_name: $name, column_values: $cv, create_labels_if_missing: true) { id }
        }';
        $resp = self::graphql($createMut, [
            'boardId' => (string) self::estimatesBoardId(),
            'name'    => $itemName,
            'cv'      => json_encode($columnValues),
        ]);
        $itemId = $resp['data']['create_item']['id'] ?? null;
        if (!$itemId) {
            error_log("Monday create_item failed: " . json_encode($resp));
            return null;
        }

        // 2. Create subitems for each line
        foreach ($lines as $line) {
            $subCols = [
                self::SUB_DESCRIPTION    => ['text' => $line['description']],
                self::SUB_CX_SALES_PRICE => (float)$line['amount'],
                self::SUB_QUANTITY       => '1',
            ];
            $subMut = 'mutation ($parentId: ID!, $name: String!, $cv: JSON!) {
                create_subitem(parent_item_id: $parentId, item_name: $name, column_values: $cv) { id }
            }';
            self::graphql($subMut, [
                'parentId' => $itemId,
                'name'     => substr($line['description'], 0, 100),
                'cv'       => json_encode($subCols),
            ]);
        }

        // 3. Add the long-form update note
        self::addUpdateNote((int)$itemId, $update);

        Database::pdo()->prepare("UPDATE orders SET monday_item_id = ?, updated_at = datetime('now') WHERE id = ?")
            ->execute([$itemId, $orderId]);

        return (string)$itemId;
    }

    public static function pullStatus(int $orderId): ?string
    {
        $order = Database::one("SELECT monday_item_id FROM orders WHERE id = ?", [$orderId]);
        if (empty($order['monday_item_id'])) return null;
        if (!PORTAL_MONDAY_API_KEY) return null;

        $query = 'query ($itemId: [ID!]) { items(ids: $itemId) { column_values { id text } } }';
        $resp = self::graphql($query, ['itemId' => [$order['monday_item_id']]]);
        $cols = $resp['data']['items'][0]['column_values'] ?? [];
        foreach ($cols as $c) {
            if ($c['id'] === self::COL_STATUS) {
                $status = self::mapMondayStatus($c['text']);
                if ($status) {
                    Database::pdo()->prepare("UPDATE orders SET status = ?, updated_at = datetime('now') WHERE id = ?")
                        ->execute([$status, $orderId]);
                    return $status;
                }
            }
        }
        return null;
    }

    private static function graphql(string $query, array $variables = []): array
    {
        $payload = json_encode(['query' => $query, 'variables' => $variables]);
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: ' . PORTAL_MONDAY_API_KEY,
                'API-Version: 2024-01',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            error_log("Monday GraphQL error: $err");
            return ['errors' => [['message' => $err]]];
        }
        return json_decode($body, true) ?: ['errors' => [['message' => 'invalid response']]];
    }

    private static function addUpdateNote(int $itemId, string $body): void
    {
        $mutation = 'mutation ($itemId: ID!, $body: String!) {
            create_update(item_id: $itemId, body: $body) { id }
        }';
        self::graphql($mutation, ['itemId' => $itemId, 'body' => $body]);
    }

    public static function mapMondayStatus(?string $text): ?string
    {
        if (!$text) return null;
        $map = [
            'New Estimate'                     => 'Submitted',
            'Estimate sent to cx'              => 'Submitted',
            'Estimate approved'                => 'In Production',
            'Temporary Approved'               => 'In Production',
            'Changes needed or awaiting approval' => 'In Production',
            'Invoiced'                         => 'Invoiced',
            'OtherComplete - need invoice'     => 'Delivered',
        ];
        return $map[trim($text)] ?? null;
    }

    private static function dryRun(array $order, string $itemName, array $columnValues, array $lines, string $note): string
    {
        $logPath = PORTAL_STORAGE . '/monday_dryrun.log';
        $entry  = "===== " . date('Y-m-d H:i:s') . " =====\n";
        $entry .= "Target: Estimates board (id " . self::estimatesBoardId() . ")\n";
        $entry .= "Item name: $itemName\n";
        $entry .= "Column values:\n";
        foreach ($columnValues as $col => $val) $entry .= "  $col = " . json_encode($val) . "\n";
        $entry .= "Subitems (" . count($lines) . "):\n";
        foreach ($lines as $l) $entry .= "  - {$l['description']} — \$" . number_format((float)$l['amount'], 2) . "\n";
        $entry .= "\nUpdate note:\n$note\n";
        @file_put_contents($logPath, $entry, FILE_APPEND);
        $fakeId = 'dryrun-' . $order['id'] . '-' . substr(md5((string)microtime(true)), 0, 6);
        Database::pdo()->prepare("UPDATE orders SET monday_item_id = ?, updated_at = datetime('now') WHERE id = ?")
            ->execute([$fakeId, $order['id']]);
        return $fakeId;
    }
}
