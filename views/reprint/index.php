<?php /** @var array $orders */ /** @var array $customer */ ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-gray-900">Reprint a past order</h1>
  <p class="text-sm text-gray-500 mt-1">Pick an order — we'll re-run it with the same files, specs, and ship-to. Adjust quantity if you want more or fewer this time.</p>
</div>

<?php if (empty($orders)): ?>
  <div class="bg-white rounded-lg border border-gray-200 p-6 text-sm text-gray-500">No past orders yet.</div>
<?php else: ?>
<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
      <tr>
        <th class="text-left px-4 py-3">Order</th>
        <th class="text-left px-4 py-3">Type</th>
        <th class="text-left px-4 py-3">Date</th>
        <th class="text-left px-4 py-3">Project</th>
        <th class="text-right px-4 py-3">Total</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr class="border-t border-gray-100 hover:bg-gray-50">
          <td class="px-4 py-3"><a href="/reprint/<?= (int)$o['id'] ?>" class="font-medium text-gray-900"><?= e($o['name']) ?></a><div class="text-xs text-gray-500">#<?= (int)$o['id'] ?></div></td>
          <td class="px-4 py-3"><span class="pill <?= $o['type']==='Repro' ? 'pill-purple' : ($o['type']==='Reprint' ? 'pill-blue' : 'pill-gray') ?>"><?= e($o['type']) ?></span></td>
          <td class="px-4 py-3 text-gray-500"><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
          <td class="px-4 py-3 text-gray-500"><?= e($o['project'] ?: '—') ?></td>
          <td class="px-4 py-3 text-right font-medium text-gray-900">$<?= number_format((float)$o['total'], 2) ?></td>
          <td class="px-4 py-3 text-right">
            <a href="/reprint/<?= (int)$o['id'] ?>" class="text-sm brand font-medium hover:underline">Reprint →</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
