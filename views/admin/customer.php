<?php
/** @var array $subject */
/** @var array $users */
/** @var array $addresses */
/** @var array $products */
/** @var array $rules */
/** @var array $orders */
/** @var string $csrf */
?>
<a href="/admin" class="text-sm text-gray-500 hover:text-gray-700">← Back to admin</a>
<div class="mt-2 mb-6">
  <h1 class="text-2xl font-semibold text-gray-900"><?= e($subject['name']) ?></h1>
  <p class="text-sm text-gray-500 mt-1">Customer #<?= (int)$subject['id'] ?></p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

  <!-- Customer fields -->
  <form method="post" action="/admin/customers/<?= (int)$subject['id'] ?>/save" class="bg-white rounded-lg border border-gray-200 p-5">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <h2 class="text-sm font-semibold text-gray-900 mb-4">Customer info</h2>
    <div class="space-y-3">
      <div><label class="block text-xs text-gray-500 mb-1">Name</label>
        <input name="name" value="<?= e($subject['name']) ?>" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></div>
      <div><label class="text-sm text-gray-700 inline-flex items-center gap-2">
        <input type="checkbox" name="free_delivery" value="1" <?= $subject['free_delivery'] ? 'checked' : '' ?>>
        Free local delivery
      </label></div>
      <div><label class="block text-xs text-gray-500 mb-1">Notes</label>
        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"><?= e($subject['notes']) ?></textarea></div>
      <div class="border-t border-gray-100 mt-3 pt-3">
        <div class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">Email touchpoints</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 text-sm">
          <label class="inline-flex items-center gap-2"><input type="checkbox" name="notify_received"      value="1" <?= !empty($subject['notify_received'])      ? 'checked' : '' ?>> Order received</label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" name="notify_in_production" value="1" <?= !empty($subject['notify_in_production']) ? 'checked' : '' ?>> In production</label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" name="notify_shipped"       value="1" <?= !empty($subject['notify_shipped'])       ? 'checked' : '' ?>> Shipped</label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" name="notify_delivered"     value="1" <?= !empty($subject['notify_delivered'])     ? 'checked' : '' ?>> Delivered</label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" name="notify_invoiced"      value="1" <?= !empty($subject['notify_invoiced'])      ? 'checked' : '' ?>> Invoiced</label>
        </div>
      </div>
      <button class="text-sm text-brand font-medium hover:underline">Save →</button>
    </div>
  </form>

  <!-- Users -->
  <div class="bg-white rounded-lg border border-gray-200 p-5">
    <h2 class="text-sm font-semibold text-gray-900 mb-3">Login users</h2>
    <?php if (empty($users)): ?><div class="text-xs text-gray-400 mb-3">No users yet.</div><?php endif; ?>
    <ul class="text-sm space-y-1 mb-4">
      <?php foreach ($users as $u): ?>
        <li class="flex justify-between"><span><?= e($u['email']) ?></span><span class="text-xs text-gray-400"><?= e(trim($u['first_name'] . ' ' . $u['last_name'])) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <form method="post" action="/admin/customers/<?= (int)$subject['id'] ?>/users/new" class="space-y-2">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <div class="grid grid-cols-2 gap-2">
        <input name="first_name" placeholder="First" class="border border-gray-300 rounded px-2 py-1.5 text-sm">
        <input name="last_name"  placeholder="Last"  class="border border-gray-300 rounded px-2 py-1.5 text-sm">
      </div>
      <input name="email"    type="email" placeholder="email@customer.com" required class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
      <input name="password" type="text"  placeholder="Initial password (share securely)" required class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
      <button class="text-sm text-brand font-medium hover:underline">+ Add user</button>
    </form>
  </div>

  <!-- Addresses -->
  <div class="bg-white rounded-lg border border-gray-200 p-5 lg:col-span-2">
    <h2 class="text-sm font-semibold text-gray-900 mb-3">Addresses</h2>
    <?php if (empty($addresses)): ?><div class="text-xs text-gray-400 mb-3">No addresses yet.</div><?php endif; ?>
    <ul class="text-sm space-y-2 mb-4">
      <?php foreach ($addresses as $a): ?>
        <li class="flex justify-between gap-3 border-b border-gray-100 pb-2">
          <div>
            <div class="font-medium text-gray-900"><?= e($a['label']) ?></div>
            <div class="text-xs text-gray-500"><?= e(trim($a['street1'] . ', ' . $a['city'] . ', ' . $a['state'] . ' ' . $a['zip'], ' ,')) ?></div>
          </div>
          <div class="text-xs text-gray-400">
            <?php if ($a['is_default_ship']): ?><div>Default ship</div><?php endif; ?>
            <?php if ($a['is_default_bill']): ?><div>Default bill</div><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <form method="post" action="/admin/customers/<?= (int)$subject['id'] ?>/addresses/new" class="grid grid-cols-1 sm:grid-cols-12 gap-2">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input name="label"   placeholder="Label (e.g., HQ)"   class="sm:col-span-3 border border-gray-300 rounded px-2 py-1.5 text-sm" required>
      <input name="street1" placeholder="Street"             class="sm:col-span-3 border border-gray-300 rounded px-2 py-1.5 text-sm">
      <input name="city"    placeholder="City"               class="sm:col-span-2 border border-gray-300 rounded px-2 py-1.5 text-sm">
      <input name="state"   placeholder="St"                 class="sm:col-span-1 border border-gray-300 rounded px-2 py-1.5 text-sm">
      <input name="zip"     placeholder="ZIP"                class="sm:col-span-1 border border-gray-300 rounded px-2 py-1.5 text-sm">
      <label class="sm:col-span-1 text-xs inline-flex items-center gap-1"><input type="checkbox" name="is_default_ship" value="1"> Ship</label>
      <label class="sm:col-span-1 text-xs inline-flex items-center gap-1"><input type="checkbox" name="is_default_bill" value="1"> Bill</label>
      <div class="sm:col-span-12"><button class="text-sm text-brand font-medium hover:underline">+ Add address</button></div>
    </form>
  </div>

  <!-- Products -->
  <div class="bg-white rounded-lg border border-gray-200 p-5 lg:col-span-2">
    <h2 class="text-sm font-semibold text-gray-900 mb-3">Saved products</h2>
    <?php if (empty($products)): ?><div class="text-xs text-gray-400 mb-3">No products yet.</div><?php endif; ?>
    <div class="space-y-3 mb-4">
      <?php foreach ($products as $p): ?>
        <form method="post" action="/admin/customers/<?= (int)$subject['id'] ?>/products/<?= (int)$p['id'] ?>/save" class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center border border-gray-100 rounded p-2">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input name="icon" value="<?= e($p['icon']) ?>" class="sm:col-span-1 border border-gray-200 rounded px-2 py-1.5 text-sm text-center">
          <input name="name" value="<?= e($p['name']) ?>" class="sm:col-span-3 border border-gray-200 rounded px-2 py-1.5 text-sm">
          <input name="spec" value="<?= e($p['spec']) ?>" class="sm:col-span-3 border border-gray-200 rounded px-2 py-1.5 text-sm" placeholder="spec">
          <input name="unit_price" type="number" step="0.01" value="<?= (float)$p['unit_price'] ?>" class="sm:col-span-1 border border-gray-200 rounded px-2 py-1.5 text-sm">
          <input name="unit_qty"   type="number"             value="<?= (int)$p['unit_qty'] ?>"   class="sm:col-span-1 border border-gray-200 rounded px-2 py-1.5 text-sm">
          <input name="price_label" value="<?= e($p['price_label']) ?>" class="sm:col-span-2 border border-gray-200 rounded px-2 py-1.5 text-sm" placeholder="$X / N">
          <select name="fulfillment" class="sm:col-span-1 border border-gray-200 rounded px-1 py-1.5 text-xs">
            <option value="inhouse" <?= $p['fulfillment']==='inhouse' ? 'selected':'' ?>>In-house</option>
            <option value="4over"   <?= $p['fulfillment']==='4over'   ? 'selected':'' ?>>4over</option>
          </select>
          <label class="sm:col-span-12 text-xs text-gray-600 inline-flex items-center gap-3 mt-1">
            <span><input type="checkbox" name="multi_line" value="1" <?= $p['multi_line'] ? 'checked':'' ?>> Multi-line (one per recipient)</span>
            <span><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked':'' ?>> Active</span>
            <button class="ml-auto text-brand hover:underline">Save</button>
          </label>
        </form>
      <?php endforeach; ?>
    </div>
    <form method="post" action="/admin/customers/<?= (int)$subject['id'] ?>/products/new" class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center bg-gray-50 rounded p-2">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input name="icon" placeholder="📄" class="sm:col-span-1 border border-gray-200 rounded px-2 py-1.5 text-sm text-center">
      <input name="name" placeholder="Product name" required class="sm:col-span-3 border border-gray-200 rounded px-2 py-1.5 text-sm">
      <input name="spec" placeholder="Spec (e.g., 14pt matte 3.5×2)" class="sm:col-span-3 border border-gray-200 rounded px-2 py-1.5 text-sm">
      <input name="unit_price" type="number" step="0.01" placeholder="$" class="sm:col-span-1 border border-gray-200 rounded px-2 py-1.5 text-sm" required>
      <input name="unit_qty"   type="number"             placeholder="qty" value="1" class="sm:col-span-1 border border-gray-200 rounded px-2 py-1.5 text-sm">
      <input name="price_label" placeholder="$40 / 250" class="sm:col-span-2 border border-gray-200 rounded px-2 py-1.5 text-sm">
      <select name="fulfillment" class="sm:col-span-1 border border-gray-200 rounded px-1 py-1.5 text-xs">
        <option value="inhouse">In-house</option><option value="4over">4over</option>
      </select>
      <label class="sm:col-span-12 text-xs inline-flex items-center gap-3">
        <span><input type="checkbox" name="multi_line" value="1"> Multi-line</span>
        <button class="ml-auto text-brand hover:underline">+ Add product</button>
      </label>
    </form>
  </div>

  <!-- Reprint pricing rules -->
  <div class="bg-white rounded-lg border border-gray-200 p-5 lg:col-span-2">
    <h2 class="text-sm font-semibold text-gray-900 mb-3">Reprint pricing rules</h2>
    <form method="post" action="/admin/customers/<?= (int)$subject['id'] ?>/rules/save">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <table class="w-full text-sm">
        <thead class="text-xs text-gray-500 uppercase tracking-wide">
          <tr><th class="text-left">Key</th><th class="text-left">Label</th><th class="text-right">$ / page</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rules as $r): ?>
            <tr class="border-t border-gray-100">
              <td class="py-2"><span class="font-mono text-xs text-gray-500"><?= e($r['rule_key']) ?></span></td>
              <td class="py-2"><input name="rules[<?= (int)$r['id'] ?>][label]" value="<?= e($r['label']) ?>" class="w-full border border-gray-200 rounded px-2 py-1 text-sm"></td>
              <td class="py-2 w-24 text-right"><input type="number" step="0.01" name="rules[<?= (int)$r['id'] ?>][price]" value="<?= (float)$r['price'] ?>" class="w-24 border border-gray-200 rounded px-2 py-1 text-sm text-right"></td>
            </tr>
          <?php endforeach; ?>
          <tr class="border-t-2 border-gray-200 bg-gray-50">
            <td class="py-2"><input name="new_rule_key"   placeholder="new-rule-key" class="w-full font-mono text-xs border border-gray-200 rounded px-2 py-1"></td>
            <td class="py-2"><input name="new_rule_label" placeholder="New rule label" class="w-full border border-gray-200 rounded px-2 py-1 text-sm"></td>
            <td class="py-2"><input type="number" step="0.01" name="new_rule_price" placeholder="0.00" class="w-24 border border-gray-200 rounded px-2 py-1 text-sm text-right"></td>
          </tr>
        </tbody>
      </table>
      <div class="text-right mt-3"><button class="text-sm text-brand font-medium hover:underline">Save rates →</button></div>
    </form>
  </div>

  <!-- Recent orders -->
  <div class="bg-white rounded-lg border border-gray-200 p-5 lg:col-span-2">
    <h2 class="text-sm font-semibold text-gray-900 mb-3">Recent orders</h2>
    <?php if (empty($orders)): ?><div class="text-xs text-gray-400">No orders yet.</div><?php endif; ?>
    <table class="w-full text-sm">
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr class="border-t border-gray-100">
            <td class="py-2"><a class="font-medium text-gray-900" href="/orders/<?= (int)$o['id'] ?>"><?= e($o['name']) ?></a><span class="text-xs text-gray-400 ml-2">#<?= (int)$o['id'] ?></span></td>
            <td class="py-2"><span class="pill <?= $o['type']==='Reprint' ? 'pill-purple' : 'pill-gray' ?>"><?= e($o['type']) ?></span></td>
            <td class="py-2"><span class="pill <?= pill_class($o['status']) ?>"><?= e($o['status']) ?></span></td>
            <td class="py-2 text-gray-500 text-xs"><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
            <td class="py-2 text-right font-medium">$<?= number_format((float)$o['total'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
