<?php /** @var array $order */ /** @var array $lines */ /** @var array $files */ /** @var string $csrf */ ?>
<a href="/reprint" class="text-sm text-gray-500 hover:text-gray-700">← Back to past orders</a>
<div class="mt-2 mb-6">
  <h1 class="text-2xl font-semibold text-gray-900">Reprint: <?= e($order['name']) ?></h1>
  <p class="text-sm text-gray-500 mt-1">Originally placed <?= e(date('M j, Y', strtotime($order['created_at']))) ?> · order #<?= (int)$order['id'] ?></p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 bg-white rounded-lg border border-gray-200 p-5">
    <h2 class="text-sm font-semibold text-gray-900 mb-3">What we'll re-run</h2>
    <div class="text-sm space-y-1">
      <?php foreach ($lines as $l): ?>
        <div class="flex justify-between border-b border-gray-100 py-1.5"><span class="text-gray-700"><?= e($l['description']) ?></span><span class="text-gray-900 tabular-nums">$<?= number_format((float)$l['amount'], 2) ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($files)): ?>
      <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mt-4 mb-2">Files we'll reuse</h3>
      <ul class="text-sm space-y-1">
        <?php foreach ($files as $f): ?>
          <li class="text-gray-700">📄 <?= e($f['filename']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="lg:col-span-1">
    <form method="post" action="/reprint/<?= (int)$order['id'] ?>/place" class="bg-white rounded-lg border border-gray-200 p-5 sticky top-4">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <h2 class="text-sm font-semibold text-gray-900 mb-3">Reprint quantity</h2>
      <label class="block text-xs text-gray-500 mb-1">Multiplier (1× = exact same as before)</label>
      <select name="multiplier" class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-4">
        <option value="0.5">Half (0.5×)</option>
        <option value="1" selected>Same as before (1×)</option>
        <option value="2">Double (2×)</option>
        <option value="3">Triple (3×)</option>
      </select>
      <div class="flex justify-between text-base font-semibold text-gray-900 pt-3 mt-2 border-t border-gray-200">
        <span>Original total</span><span>$<?= number_format((float)$order['total'], 2) ?></span>
      </div>
      <button class="w-full bg-brand text-white font-medium py-2.5 rounded-md mt-4 hover-bg-brand-dark">Place reprint →</button>
      <p class="text-xs text-gray-400 mt-3">Quote scales linearly. We'll confirm specs match before printing.</p>
    </form>
  </div>
</div>
