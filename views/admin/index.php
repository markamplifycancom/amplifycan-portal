<?php /** @var array $customers */ /** @var array $stats */ /** @var string $csrf */ ?>
<div class="mb-6 flex items-baseline justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900">Admin</h1>
    <p class="text-sm text-gray-500 mt-1">Manage customers, products, and pricing.</p>
  </div>
  <a href="/admin/customers/new" class="bg-brand text-white px-4 py-2 rounded-md text-sm font-medium hover-bg-brand-dark">+ New customer</a>
</div>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
  <div class="bg-white rounded-lg border border-gray-200 p-4">
    <div class="text-xs text-gray-500 uppercase tracking-wide">Customers</div>
    <div class="text-2xl font-semibold mt-1"><?= (int)$stats['customers'] ?></div>
  </div>
  <div class="bg-white rounded-lg border border-gray-200 p-4">
    <div class="text-xs text-gray-500 uppercase tracking-wide">Products</div>
    <div class="text-2xl font-semibold mt-1"><?= (int)$stats['products'] ?></div>
  </div>
  <div class="bg-white rounded-lg border border-gray-200 p-4">
    <div class="text-xs text-gray-500 uppercase tracking-wide">Orders</div>
    <div class="text-2xl font-semibold mt-1"><?= (int)$stats['orders'] ?></div>
  </div>
  <div class="bg-white rounded-lg border border-gray-200 p-4">
    <div class="text-xs text-gray-500 uppercase tracking-wide">Revenue</div>
    <div class="text-2xl font-semibold mt-1">$<?= number_format((float)$stats['revenue'], 0) ?></div>
  </div>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
      <tr>
        <th class="text-left px-4 py-3">Customer</th>
        <th class="text-left px-4 py-3">Users</th>
        <th class="text-left px-4 py-3">Products</th>
        <th class="text-left px-4 py-3">Orders</th>
        <th class="text-right px-4 py-3">Revenue</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
        <tr class="border-t border-gray-100 hover:bg-gray-50">
          <td class="px-4 py-3">
            <a href="/admin/customers/<?= (int)$c['id'] ?>" class="font-medium text-gray-900"><?= e($c['name']) ?></a>
            <?php if ($c['free_delivery']): ?><div class="text-xs text-gray-400 mt-0.5">Free delivery</div><?php endif; ?>
          </td>
          <td class="px-4 py-3 text-gray-700"><?= (int)$c['users'] ?></td>
          <td class="px-4 py-3 text-gray-700"><?= (int)$c['products'] ?></td>
          <td class="px-4 py-3 text-gray-700"><?= (int)$c['orders'] ?></td>
          <td class="px-4 py-3 text-right font-medium text-gray-900">$<?= number_format((float)$c['total_revenue'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Admin team -->
<div class="mt-8 bg-white rounded-lg border border-gray-200 p-5">
  <h2 class="text-sm font-semibold text-gray-900 mb-3">Admin team</h2>
  <p class="text-xs text-gray-500 mb-4">AmplifyCan staff with admin access. Each can act as any customer to place orders on their behalf.</p>
  <ul class="text-sm space-y-1 mb-4">
    <?php foreach (\Portal\Database::all("SELECT email, first_name, last_name, last_login_at FROM users WHERE is_admin = 1 ORDER BY id") as $a): ?>
      <li class="flex justify-between border-b border-gray-100 py-1">
        <span><?= e(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: $a['email']) ?>
          <span class="text-xs text-gray-400 ml-2"><?= e($a['email']) ?></span>
        </span>
        <span class="text-xs text-gray-400"><?= $a['last_login_at'] ? 'last in ' . date('M j', strtotime($a['last_login_at'])) : 'never' ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
  <form method="post" action="/admin/admins/new" class="grid grid-cols-1 sm:grid-cols-12 gap-2">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input name="first_name" placeholder="First"  class="sm:col-span-2 border border-gray-300 rounded px-2 py-1.5 text-sm">
    <input name="last_name"  placeholder="Last"   class="sm:col-span-2 border border-gray-300 rounded px-2 py-1.5 text-sm">
    <input name="email" type="email" placeholder="email@amplifycan.com" required class="sm:col-span-4 border border-gray-300 rounded px-2 py-1.5 text-sm">
    <input name="password" placeholder="Initial password" required class="sm:col-span-3 border border-gray-300 rounded px-2 py-1.5 text-sm">
    <button class="sm:col-span-1 bg-brand text-white text-sm font-medium rounded hover-bg-brand-dark">+ Add</button>
  </form>
</div>
