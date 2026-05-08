<?php
/** @var array $user */
/** @var array $customer */
/** @var array $orders */
/** @var int $openCount */
/** @var float $ytdTotal */
/** @var int $orderCount */
/** @var array $products */
?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-gray-900">Welcome back, <?= e($user['first_name']) ?></h1>
  <p class="text-sm text-gray-500 mt-1">Here's what's happening with your orders.</p>
</div>

<!-- Primary CTAs -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
  <a href="/repro" class="bg-white border-2 border-gray-200 hover-border-brand rounded-lg p-5 group transition">
    <div class="flex items-start gap-4">
      <div class="w-12 h-12 rounded-md bg-brand-light brand flex items-center justify-center text-2xl flex-shrink-0">📐</div>
      <div class="flex-1">
        <div class="font-semibold text-gray-900">Repro order</div>
        <div class="text-xs text-gray-500 mt-1">Upload blueprints / construction docs. We auto-detect pages, size, and color, and apply your per-page rates.</div>
      </div>
      <div class="text-gray-300 group-hover:brand text-xl">→</div>
    </div>
  </a>
  <a href="/reprint" class="bg-white border-2 border-gray-200 hover-border-brand rounded-lg p-5 group transition">
    <div class="flex items-start gap-4">
      <div class="w-12 h-12 rounded-md bg-brand-light brand flex items-center justify-center text-2xl flex-shrink-0">↻</div>
      <div class="flex-1">
        <div class="font-semibold text-gray-900">Reprint a past order</div>
        <div class="text-xs text-gray-500 mt-1">Pick any previous order and re-run it with the same files and specs. Adjust quantity if needed.</div>
      </div>
      <div class="text-gray-300 group-hover:brand text-xl">→</div>
    </div>
  </a>
  <a href="/catalog" class="bg-white border-2 border-gray-200 hover-border-brand rounded-lg p-5 group transition">
    <div class="flex items-start gap-4">
      <div class="w-12 h-12 rounded-md bg-brand-light brand flex items-center justify-center text-2xl flex-shrink-0">+</div>
      <div class="flex-1">
        <div class="font-semibold text-gray-900">Order from catalog</div>
        <div class="text-xs text-gray-500 mt-1">Order business cards, banners, foam boards — anything we've set up for you with saved specs and pricing.</div>
      </div>
      <div class="text-gray-300 group-hover:brand text-xl">→</div>
    </div>
  </a>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
  <div class="bg-white rounded-lg border border-gray-200 p-5">
    <div class="text-xs text-gray-500 uppercase tracking-wide">Open orders</div>
    <div class="text-3xl font-semibold text-gray-900 mt-2"><?= (int)$openCount ?></div>
    <div class="text-xs text-gray-400 mt-1">In production or shipping</div>
  </div>
  <div class="bg-white rounded-lg border border-gray-200 p-5">
    <div class="text-xs text-gray-500 uppercase tracking-wide">Last order</div>
    <div class="text-lg font-medium text-gray-900 mt-2"><?= e($orders[0]['name'] ?? '—') ?></div>
    <div class="text-xs text-gray-400 mt-1"><?= e(isset($orders[0]) ? date('M j, Y', strtotime($orders[0]['created_at'])) : '') ?></div>
  </div>
  <div class="bg-white rounded-lg border border-gray-200 p-5">
    <div class="text-xs text-gray-500 uppercase tracking-wide">Year to date</div>
    <div class="text-3xl font-semibold text-gray-900 mt-2">$<?= number_format($ytdTotal, 2) ?></div>
    <div class="text-xs text-gray-400 mt-1"><?= (int)$orderCount ?> orders</div>
  </div>
</div>

<!-- Quick reorder -->
<?php if (!empty($products)): ?>
<h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Quick reorder</h2>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-8">
  <?php foreach ($products as $p): ?>
    <a href="/catalog" class="bg-white rounded-lg border border-gray-200 p-3 hover-border-brand transition">
      <div class="prod-icon mb-2"><?= e($p['icon']) ?></div>
      <div class="text-xs font-medium text-gray-900 leading-tight"><?= e($p['name']) ?></div>
      <div class="text-xs text-gray-500 mt-0.5"><?= e($p['price_label']) ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Recent orders -->
<h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Recent orders</h2>
<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
      <tr>
        <th class="text-left px-4 py-3">Order</th>
        <th class="text-left px-4 py-3">Type</th>
        <th class="text-left px-4 py-3 hidden sm:table-cell">Date</th>
        <th class="text-left px-4 py-3 hidden md:table-cell">Project</th>
        <th class="text-left px-4 py-3">Status</th>
        <th class="text-right px-4 py-3">Total</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">No orders yet. Try a <a href="/repro" class="brand underline">repro order</a> or <a href="/catalog" class="brand underline">ordering from your catalog</a>.</td></tr>
      <?php endif; ?>
      <?php foreach (array_slice($orders, 0, 6) as $o): ?>
        <tr class="border-t border-gray-100 hover:bg-gray-50">
          <td class="px-4 py-3">
            <a href="/orders/<?= (int)$o['id'] ?>" class="block">
              <div class="font-medium text-gray-900"><?= e($o['name']) ?></div>
              <div class="text-xs text-gray-500">#<?= (int)$o['id'] ?></div>
            </a>
          </td>
          <td class="px-4 py-3"><span class="pill <?= $o['type'] === 'Repro' ? 'pill-purple' : ($o['type'] === 'Reprint' ? 'pill-blue' : 'pill-gray') ?>"><?= e($o['type']) ?></span></td>
          <td class="px-4 py-3 text-gray-500 hidden sm:table-cell"><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
          <td class="px-4 py-3 text-gray-500 hidden md:table-cell"><?= e($o['project'] ?: '—') ?></td>
          <td class="px-4 py-3"><span class="pill <?= pill_class($o['status']) ?>"><?= e($o['status']) ?></span></td>
          <td class="px-4 py-3 text-right font-medium text-gray-900">$<?= number_format((float)$o['total'], 2) ?></td>
          <td class="px-4 py-3 text-right text-gray-300"><a href="/orders/<?= (int)$o['id'] ?>" class="text-gray-300 hover:text-gray-500">›</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
