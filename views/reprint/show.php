<?php /** @var array $order */ /** @var array $lines */ /** @var array $files */ /** @var string $csrf */ ?>
<!-- Order-submit overlay -->
<div id="submitOverlay" class="hidden fixed inset-0 bg-gray-900/60 z-50 flex items-center justify-center" style="backdrop-filter: blur(2px);">
  <div class="bg-white rounded-lg shadow-2xl p-8 text-center max-w-sm">
    <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-gray-200" style="border-top-color: #f1551a;"></div>
    <div class="font-medium text-gray-900 mt-4">Placing your reprint…</div>
    <div class="text-xs text-gray-500 mt-2">Copying files and pushing to production. Please don't close or refresh this tab.</div>
  </div>
</div>
<script>function showSubmitOverlay() { document.getElementById('submitOverlay').classList.remove('hidden'); }</script>
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
    <form method="post" action="/reprint/<?= (int)$order['id'] ?>/place" class="bg-white rounded-lg border border-gray-200 p-5 sticky top-4" onsubmit="showSubmitOverlay()">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <h2 class="text-sm font-semibold text-gray-900 mb-3">Reprint quantity</h2>
      <label class="block text-xs text-gray-500 mb-1">Multiplier (1× = exact same as before)</label>
      <select name="multiplier" class="w-full border border-gray-300 roun