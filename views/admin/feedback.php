<?php /** @var array $rows */ /** @var string $csrf */ ?>
<a href="/admin" class="text-sm text-gray-500 hover:text-gray-700">← Back to admin</a>
<div class="mb-6 mt-2 flex items-baseline justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900">Feedback queue</h1>
    <p class="text-sm text-gray-500 mt-1">Inline feedback from admins testing the portal. Claude reads these from chat sessions.</p>
  </div>
  <div class="text-xs text-gray-500">Total: <?= count($rows) ?></div>
</div>

<?php
$open = array_values(array_filter($rows, fn($r) => $r['status'] === 'open'));
$done = array_values(array_filter($rows, fn($r) => $r['status'] !== 'open'));
?>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
  <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-700">Open (<?= count($open) ?>)</div>
  <?php if (empty($open)): ?>
    <div class="p-6 text-sm text-gray-400 text-center italic">No open feedback. Use the orange chat bubble bottom-right of any page to file feedback.</div>
  <?php endif; ?>
  <?php foreach ($open as $r):
      $ctx = $r['context_json'] ? json_decode($r['context_json'], true) : null;
  ?>
    <div class="p-4 border-t border-gray-100">
      <div class="flex items-start justify-between gap-4 mb-2">
        <div class="text-xs text-gray-500">
          <?= e(date('M j, g:i a', strtotime($r['created_at']))) ?>
          · <?= e(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: e($r['admin_email'] ?? '?') ?>
          <?php if ($r['customer_name']): ?>· acting as <strong><?= e($r['customer_name']) ?></strong><?php endif; ?>
          <?php if ($r['page_url']): ?>· <span class="font-mono text-xs"><?= e($r['page_url']) ?></span><?php endif; ?>
        </div>
        <form method="post" action="/admin/feedback/<?= (int)$r['id'] ?>/resolve" class="flex items-center gap-2">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="text" name="claude_note" placeholder="resolution note (optional)" class="border border-gray-200 rounded px-2 py-1 text-xs w-48">
          <button class="text-xs bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">Mark resolved</button>
        </form>
      </div>
      <div class="text-sm text-gray-900 whitespace-pre-wrap mb-2"><?= e($r['message']) ?></div>
      <?php if ($ctx && !empty($ctx['order'])): ?>
        <details class="mt-2">
          <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">Order context (#<?= (int)$ctx['order']['id'] ?>, total $<?= number_format((float)$ctx['order']['total'], 2) ?>)</summary>
          <pre class="mt-2 text-xs bg-gray-50 border border-gray-200 rounded p-3 overflow-x-auto"><?= e(json_encode($ctx['order'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
        </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if (!empty($done)): ?>
<details class="mt-6">
  <summary class="text-sm text-gray-500 cursor-pointer hover:text-gray-700">Resolved (<?= count($done) ?>)</summary>
  <div class="bg-white rounded-lg border border-gray-200 overflow-hidden mt-3">
    <?php foreach ($done as $r): ?>
      <div class="p-4 border-t border-gray-100 first:border-t-0 opacity-60">
        <div class="text-xs text-gray-500 mb-1">
          <?= e(date('M j', strtotime($r['created_at']))) ?>
          · <?= e($r['admin_email'] ?? '?') ?>
          <?php if ($r['customer_name']): ?>· <?= e($r['customer_name']) ?><?php endif; ?>
          <?php if ($r['resolved_at']): ?>· resolved <?= e(date('M j', strtotime($r['resolved_at']))) ?><?php endif; ?>
        </div>
        <div class="text-sm text-gray-700"><?= e($r['message']) ?></div>
        <?php if ($r['claude_note']): ?>
          <div class="text-xs text-green-700 mt-1">↳ <?= e($r['claude_note']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</details>
<?php endif; ?>
