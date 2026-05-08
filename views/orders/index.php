<?php /** @var array $orders */ /** @var array $customer */ ?>
<h1 class="text-2xl font-semibold text-gray-900 mb-1">Order history</h1>
<p class="text-sm text-gray-500 mb-6">Every order you've placed. Click any order to see the file, status, and tracking.</p>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
      <tr>
        <th class="text-left px-4 py-3">Order</th>
        <th class="text-left px-4 py-3">Type</th>
        <th class="text-left px-4 py-3">Date</th>
        <th class="text-left px-4 py-3">Project</th>
        <th class="text-left px-4 py-3">Status</th>
        <th class="text-right px-4 py-3">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">No orders yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o): ?>
        <tr class="border-t border-gray-100 hover:bg-gray-50">
          <td class="px-4 py-3"><a href="/orders/<?= (int)$o['id'] ?>" class="font-medium text-gray-900"><?= e($o['name']) ?></a><div class="text-xs text-gray-500">#<?= (int)$o['id'] ?></div></td>
          <td class="px-4 py-3"><span class="pill <?= $o['type'] === 'Repro' ? 'pill-purple' : ($o['type'] === 'Reprint' ? 'pill-blue' : 'pill-gray') ?>"><?= e($o['type']) ?></span></td>
          <td class="px-4 py-3 text-gray-500"><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
          <td class="px-4 py-3 text-gray-500"><?= e($o['project'] ?: '—') ?></td>
          <td class="px-4 py-3"><span class="pill <?= pill_class($o['status']) ?>"><?= e($o['status']) ?></span></td>
          <td class="px-4 py-3 text-right font-medium text-gray-900">$<?= number_format((float)$o['total'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
