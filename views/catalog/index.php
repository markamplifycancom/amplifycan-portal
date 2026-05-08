<?php /** @var array $products */ ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-gray-900">Order from catalog</h1>
  <p class="text-sm text-gray-500 mt-1">Your saved products. Click one to start an order.</p>
</div>

<?php if (empty($products)): ?>
  <div class="bg-white rounded-lg border border-gray-200 p-6 text-sm text-gray-500">No products saved for your account yet.</div>
<?php else: ?>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
  <?php foreach ($products as $p): ?>
    <a href="/catalog/<?= (int)$p['id'] ?>" class="bg-white rounded-lg border border-gray-200 p-4 hover-border-brand transition block">
      <div class="prod-icon mb-3"><?= e($p['icon']) ?></div>
      <div class="text-sm font-medium text-gray-900"><?= e($p['name']) ?></div>
      <div class="text-xs text-gray-500 mt-1 leading-snug"><?= e($p['spec']) ?></div>
      <div class="flex items-baseline justify-between mt-3">
        <div class="text-xs font-semibold text-gray-900"><?= e($p['price_label']) ?></div>
        <div class="text-xs text-gray-400"><?= $p['last_ordered_at'] ? 'Last: ' . e($p['last_ordered_at']) : 'New' ?></div>
      </div>
      <?php if (!empty($p['fulfillment']) && $p['fulfillment'] === '4over'): ?>
        <div class="text-xs text-gray-400 mt-2">via 4over</div>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
