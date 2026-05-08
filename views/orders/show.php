<?php
/** @var array $order */
/** @var array $lines */
/** @var array $files */
$timelineMap = ['Submitted'=>1,'In Production'=>2,'Shipped'=>3,'Delivered'=>4,'Invoiced'=>5];
$cur = $timelineMap[$order['status']] ?? 1;
$steps = [
    ['Order submitted',          date('M j, Y', strtotime($order['created_at'])) . ' · pushed to production'],
    ['In production',            $cur >= 2 ? 'Job is on the press' : 'Pending'],
    ['Shipped / out for delivery', $order['tracking'] ?: 'Pending'],
    ['Delivered',                $cur >= 4 ? 'Confirmed delivered' : 'Pending'],
    ['Invoiced',                 $cur >= 5 ? 'Invoice sent' : 'Pending after delivery'],
];
?>
<a href="/dashboard" class="text-sm text-gray-500 hover:text-gray-700">← Back to dashboard</a>
<div class="flex items-baseline justify-between mb-6 mt-2">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900"><?= e($order['name']) ?></h1>
    <div class="text-sm text-gray-500 mt-1">
      Order <span class="font-mono">#<?= (int)$order['id'] ?></span>
      · <span class="pill <?= $order['type'] === 'Repro' ? 'pill-purple' : ($order['type'] === 'Reprint' ? 'pill-blue' : 'pill-gray') ?>"><?= e($order['type']) ?></span>
      · placed <?= e(date('M j, Y', strtotime($order['created_at']))) ?>
      <?php if ($order['project']): ?>· project <?= e($order['project']) ?><?php endif; ?>
    </div>
  </div>
  <span class="pill <?= pill_class($order['status']) ?>"><?= e($order['status']) ?></span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2">
    <div class="bg-white rounded-lg border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-900 mb-4">Status</h2>
      <div class="relative">
        <?php foreach ($steps as $idx => $step): $done = $idx < $cur; $current = $idx === $cur - 1; ?>
          <div class="flex gap-4 pb-6 relative">
            <?php if ($idx < count($steps) - 1): ?>
              <div style="position: absolute; left: 19px; top: 40px; bottom: -8px; width: 2px; background: <?= $done && !$current ? '#2E75B6' : '#E5E7EB' ?>"></div>
            <?php endif; ?>
            <div class="mt-1.5 flex-shrink-0 z-10 <?= $done && !$current ? 'bg-brand text-white' : ($current ? 'bg-yellow-400 text-white' : 'bg-gray-300 text-white') ?>" style="width: 40px; height: 40px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px;"><?= $idx + 1 ?></div>
            <div class="flex-1">
              <div class="font-medium <?= $done || $current ? 'text-gray-900' : 'text-gray-400' ?>"><?= e($step[0]) ?></div>
              <div class="text-xs text-gray-500 mt-0.5"><?= e($step[1]) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="lg:col-span-1 space-y-4">
    <div class="bg-white rounded-lg border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-900 mb-3">Order summary</h2>
      <div class="text-sm space-y-1 text-gray-700">
        <?php foreach ($lines as $l): ?>
          <div class="flex justify-between"><span><?= e($l['description']) ?></span><span>$<?= number_format((float)$l['amount'], 2) ?></span></div>
        <?php endforeach; ?>
      </div>
      <div class="border-t border-gray-100 mt-3 pt-3 space-y-1 text-sm">
        <div class="flex justify-between text-gray-500"><span>Subtotal</span><span>$<?= number_format((float)$order['subtotal'], 2) ?></span></div>
        <div class="flex justify-between text-gray-500"><span>Tax</span><span>$<?= number_format((float)$order['tax'], 2) ?></span></div>
        <div class="flex justify-between font-semibold text-gray-900 pt-2 border-t border-gray-200"><span>Total</span><span>$<?= number_format((float)$order['total'], 2) ?></span></div>
      </div>
    </div>
    <?php if (!empty($files)): ?>
    <div class="bg-white rounded-lg border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-900 mb-3">Files</h2>
      <?php foreach ($files as $f): ?>
        <div class="text-sm text-gray-700 py-1 flex items-center gap-2">
          <span class="text-gray-400">📄</span><span><?= e($f['filename']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($order['monday_item_id'])): ?>
    <div class="bg-white rounded-lg border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-900 mb-2">Production</h2>
      <div class="text-xs text-gray-500">Monday item: <span class="font-mono"><?= e((string)$order['monday_item_id']) ?></span></div>
      <?php if (str_starts_with((string)$order['monday_item_id'], 'dryrun-')): ?>
        <div class="text-xs text-yellow-700 mt-1">Dry-run mode (no Monday API key set; payload logged)</div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($order['tracking']): ?>
    <div class="bg-white rounded-lg border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-900 mb-2">Shipping</h2>
      <div class="text-sm text-gray-700"><?= e($order['tracking']) ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
