<?php /** @var string $csrf */ ?>
<a href="/admin" class="text-sm text-gray-500 hover:text-gray-700">← Back to admin</a>
<div class="mt-2 mb-6"><h1 class="text-2xl font-semibold text-gray-900">New customer</h1></div>
<form method="post" action="/admin/customers/new" class="bg-white rounded-lg border border-gray-200 p-6 max-w-xl space-y-4">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
    <input type="text" name="name" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="e.g., Smith Industries">
  </div>
  <div>
    <label class="text-sm text-gray-700 inline-flex items-center gap-2">
      <input type="checkbox" name="free_delivery" value="1"> Free local delivery
    </label>
  </div>
  <div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="Internal notes (visible to admin only)"></textarea>
  </div>
  <p class="text-xs text-gray-500">Default reprint pricing rules will be added — you can edit them on the next screen.</p>
  <div><button class="bg-brand text-white px-4 py-2 rounded-md text-sm font-medium hover-bg-brand-dark">Create customer →</button></div>
</form>
