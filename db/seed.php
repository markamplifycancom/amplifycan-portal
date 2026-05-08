<?php
/**
 * Seed data — runs once when the database is created.
 * Two customers (Founders 3, ICSI) with realistic products, addresses, pricing rules,
 * and a handful of historical orders.
 */

/** @var PDO $pdo */
$pdo->beginTransaction();

// ===== CUSTOMER 1: Founders 3 =====
$pdo->prepare("INSERT INTO customers (name, free_delivery, notes) VALUES (?, 1, ?)")
    ->execute(['Founders 3 Real Estate', 'Free local delivery. 5% volume discount over 500 reprint pages.']);
$f3Id = (int)$pdo->lastInsertId();

// F3 users
$pdo->prepare("INSERT INTO users (customer_id, email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?, ?)")
    ->execute([$f3Id, 'lashworth@founders3.com', password_hash('demo', PASSWORD_DEFAULT), 'Lori', 'Ashworth']);

// F3 addresses (3 office locations)
$addrStmt = $pdo->prepare("INSERT INTO addresses (customer_id, label, street1, city, state, zip, is_default_ship, is_default_bill) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$addrStmt->execute([$f3Id, 'F3 — SG (Brookfield, WI)', '13400 Bishops Lane Suite 190', 'Brookfield', 'WI', '53005', 1, 1]);
$f3SgAddrId = (int)$pdo->lastInsertId();
$addrStmt->execute([$f3Id, 'F3 — CPA Retail (Milwaukee, WI)', '1000 N Water St Suite 160', 'Milwaukee', 'WI', '53202', 0, 0]);
$f3CpaAddrId = (int)$pdo->lastInsertId();
$addrStmt->execute([$f3Id, 'F3 — RFP (Milwaukee, WI)', '330 E Kilbourn Ave Suite 800', 'Milwaukee', 'WI', '53202', 0, 0]);

// F3 saved products
$prodStmt = $pdo->prepare("INSERT INTO products (customer_id, name, spec, icon, unit_price, unit_qty, price_label, multi_line, fulfillment, last_ordered_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$prodStmt->execute([$f3Id, 'Business Card', '14pt matte, 3.5×2, 4/4 color', '🪪', 40.00, 250, '$40 / 250', 1, '4over', 'Apr 24, 2026']);
$prodStmt->execute([$f3Id, 'Letterhead', '8.5×11, 70# uncoated, 1-sided', '📄', 95.00, 500, '$95 / 500', 0, '4over', 'Mar 12, 2026']);
$prodStmt->execute([$f3Id, 'Envelope #10', '24# white wove, printed return address', '✉', 140.00, 500, '$140 / 500', 0, '4over', null]);
$prodStmt->execute([$f3Id, 'Rack Card', '4×9, 16pt gloss, 4/4', '🗒', 110.00, 500, '$110 / 500', 0, '4over', null]);

// F3 reprint pricing rules
$ruleStmt = $pdo->prepare("INSERT INTO pricing_rules (customer_id, rule_key, label, price) VALUES (?, ?, ?, ?)");
$ruleStmt->execute([$f3Id, 'bw-letter-bond',    'B&W 8.5×11 (20# bond)',         0.08]);
$ruleStmt->execute([$f3Id, 'color-letter-bond', 'Color 8.5×11 (20# bond)',       0.45]);
$ruleStmt->execute([$f3Id, 'bw-tabloid',        'B&W 11×17',                     0.18]);
$ruleStmt->execute([$f3Id, 'color-tabloid',     'Color 11×17',                   0.95]);
$ruleStmt->execute([$f3Id, 'cardstock-add',     'Cardstock surcharge (any size)', 0.10]);
$ruleStmt->execute([$f3Id, 'gloss-add',         'Gloss surcharge (any size)',     0.15]);

// F3 sample orders
$orderStmt = $pdo->prepare("INSERT INTO orders (customer_id, user_id, type, name, project, status, subtotal, tax, total, ship_to_id, bill_to_id, tracking, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$lineStmt  = $pdo->prepare("INSERT INTO order_lines (order_id, description, amount) VALUES (?, ?, ?)");
$fileStmt  = $pdo->prepare("INSERT INTO order_files (order_id, filename, path) VALUES (?, ?, ?)");

$f3UserId = $pdo->query("SELECT id FROM users WHERE email='lashworth@founders3.com'")->fetchColumn();

$seedOrder = function (PDO $pdo, $orderStmt, $lineStmt, $fileStmt, $custId, $userId, $type, $name, $project, $status, $subtotal, $tax, $total, $shipId, $billId, $tracking, $createdAt, $lines, $files) {
    $orderStmt->execute([$custId, $userId, $type, $name, $project, $status, $subtotal, $tax, $total, $shipId, $billId, $tracking, $createdAt]);
    $orderId = (int)$pdo->lastInsertId();
    foreach ($lines as $l) $lineStmt->execute([$orderId, $l[0], $l[1]]);
    foreach ($files as $f) $fileStmt->execute([$orderId, $f, "uploads/seed/$f"]);
};

$seedOrder($pdo, $orderStmt, $lineStmt, $fileStmt, $f3Id, $f3UserId, 'Repro',
    'Q2 Property Reports', '2026-Q2 reports', 'In Production',
    36.69, 2.02, 38.71, $f3SgAddrId, $f3SgAddrId, null, '2026-05-07 09:14:00',
    [['38pp B&W 8.5×11 × 5 sets', 15.20], ['12pp Color 8.5×11 × 5 sets', 27.00]],
    ['Q2_Property_Reports.pdf']);

$seedOrder($pdo, $orderStmt, $lineStmt, $fileStmt, $f3Id, $f3UserId, 'Catalog',
    'Business Cards — K Murphy', null, 'In Production',
    40.00, 2.20, 42.20, $f3SgAddrId, $f3SgAddrId, null, '2026-05-04 14:03:00',
    [['K Murphy — 250', 40.00]],
    ['K_Murphy.pdf']);

$seedOrder($pdo, $orderStmt, $lineStmt, $fileStmt, $f3Id, $f3UserId, 'Repro',
    'Lease packet — 1844 Wells', '1844 Wells', 'Delivered',
    17.49, 0.96, 18.45, $f3SgAddrId, $f3SgAddrId, 'Delivered Apr 30', '2026-04-30 10:42:00',
    [['24pp B&W 8.5×11 × 8 sets stapled', 17.49]],
    ['1844_Wells_lease_packet.pdf']);

$seedOrder($pdo, $orderStmt, $lineStmt, $fileStmt, $f3Id, $f3UserId, 'Catalog',
    'Business Cards — Kimpton, Dykas', null, 'Delivered',
    80.00, 4.40, 84.40, $f3SgAddrId, $f3SgAddrId, 'Delivered Apr 28', '2026-04-24 09:14:00',
    [['B. Kimpton — 250', 40.00], ['L. Dykas — 250', 40.00]],
    ['F3_Marina_Billy_Kimpton.pdf', 'Lisa_Dykas.pdf']);

$seedOrder($pdo, $orderStmt, $lineStmt, $fileStmt, $f3Id, $f3UserId, 'Repro',
    'Property package — Brookfield', 'Brookfield', 'Invoiced',
    26.38, 1.45, 27.83, $f3SgAddrId, $f3SgAddrId, 'Delivered Apr 19', '2026-04-18 13:28:00',
    [['6pp Color 11×17 × 3 sets gloss', 17.10], ['12pp B&W 8.5×11 × 3 sets', 2.88]],
    ['Brookfield_Property.pdf']);

$seedOrder($pdo, $orderStmt, $lineStmt, $fileStmt, $f3Id, $f3UserId, 'Catalog',
    'Business Cards — D. Halverson', null, 'Invoiced',
    40.00, 2.20, 42.20, $f3SgAddrId, $f3SgAddrId, 'Delivered Mar 18', '2026-03-12 11:00:00',
    [['D. Halverson — 250', 40.00]],
    ['D_Halverson.pdf']);

// ===== CUSTOMER 2: ICSI =====
$pdo->prepare("INSERT INTO customers (name, free_delivery, notes) VALUES (?, 0, ?)")
    ->execute(['Innovative Construction Solutions', 'Banner reorders. Approved artwork on file.']);
$icsiId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO users (customer_id, email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?, ?)")
    ->execute([$icsiId, 'rayek@buildics.com', password_hash('demo', PASSWORD_DEFAULT), 'Raye', 'Kassees']);

$addrStmt->execute([$icsiId, 'ICSI HQ — Waukesha', 'N19W24101 Riverwood Dr Suite 100', 'Waukesha', 'WI', '53188', 1, 1]);

$prodStmt->execute([$icsiId, 'ICSI 4×8 Hardhat Banner', '13oz vinyl, 1" hems, 8 grommets, on-file artwork', '🪧', 154.00, 1, '$154 each', 0, 'inhouse', 'Aug 12, 2025']);

// ICSI uses standard reprint rates too
$ruleStmt->execute([$icsiId, 'bw-letter-bond',    'B&W 8.5×11 (20# bond)',         0.10]);
$ruleStmt->execute([$icsiId, 'color-letter-bond', 'Color 8.5×11 (20# bond)',       0.50]);

// ===== ADMIN USER =====
$pdo->prepare("INSERT INTO users (customer_id, email, password_hash, first_name, last_name, is_admin) VALUES (NULL, ?, ?, ?, ?, 1)")
    ->execute(['admin@amplifycan.com', password_hash('demo', PASSWORD_DEFAULT), 'AmplifyCan', 'Admin']);

$pdo->commit();
