<div id="uploadOverlay" class="hidden fixed inset-0 bg-gray-900/60 z-50 flex items-center justify-center" style="backdrop-filter: blur(2px);">
  <div class="bg-white rounded-lg shadow-2xl p-8 text-center max-w-sm">
    <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-gray-200" style="border-top-color: #f1551a;"></div>
    <div class="font-medium text-gray-900 mt-4">Uploading & checking your file…</div>
    <div class="text-xs text-gray-500 mt-2">Running preflight against the product spec. Just a moment — please don't refresh.</div>
  </div>
</div>
<script>function showUploadOverlay() { document.getElementById('uploadOverlay').classList.remove('hidden'); }</script>
<?php
/** @var array $user */
/** @var array $customer */
/** @var array $product */
/** @var array $draft */
/** @var array $addresses */
/** @var array $quote */
/** @var ?array $preflight */
/** @var string $csrf */
$multi = !empty($product['multi_line']);
$savedArt = !empty($draft['file']['id']) && $draft['file']['id'] === 'saved';
?>
<a href="/catalog" class="text-sm text-gray-500 hover:text-gray-700">← Back to products</a>
<div class="mb-6 mt-2 flex items-baseline justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900"><?= e($product['name']) ?></h1>
    <p class="text-sm text-gray-500 mt-1"><?= e($product['spec']) ?> · <?= e($product['price_label']) ?></p>
  </div>
  <form method="post" action="/catalog/<?= (int)$product['id'] ?>/clear">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <button class="text-xs text-gray-400 hover:text-red-500">Reset</button>
  </form>
</div>

<?php if (!empty($preflight)): ?>
  <div class="mb-6 space-y-3">
    <?php foreach ($preflight as $r): ?>
      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-start justify-between mb-2">
          <div>
            <div class="font-medium text-gray-900"><?= e($r['file']) ?></div>
            <div class="text-xs text-gray-500"><?= e($r['recipient']) ?></div>
          </div>
          <span class="pill <?= $r['status']==='pass' ? 'pill-green' : ($r['status']==='warn' ? 'pill-yellow' : 'pill-red') ?>">
            <?= $r['status']==='pass' ? '✓ Passed' : ($r['status']==='warn' ? '⚠ Warnings' : '✗ Errors') ?>
          </span>
        </div>
        <div class="space-y-1 text-sm">
          <?php foreach ($r['checks'] as $c): ?>
            <div class="flex items-start gap-2">
              <span class="<?= $c['ok']===true ? 'text-green-600' : ($c['ok']==='warn' ? 'text-yellow-600' : 'text-red-600') ?>">
                <?= $c['ok']===true ? '✓' : ($c['ok']==='warn' ? '⚠' : '✗') ?>
              </span>
              <span class="text-gray-700"><?= e($c['label']) ?></span>
              <?php if (!empty($c['value'])): ?><span class="text-gray-400">— <?= e($c['value']) ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($r['action'])): ?>
          <div class="mt-2 pt-2 border-t border-gray-100 text-sm text-gray-600"><?= e($r['action']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php
      $hasFail = false;
      foreach ($preflight as $r) if ($r['status']==='fail') { $hasFail = true; break; }
    ?>
    <?php if (!$hasFail): ?>
      <form method="post" action="/catalog/<?= (int)$product['id'] ?>/place" class="bg-blue-50 rounded-lg border border-blue-200 p-4 flex items-center justify-between">
        <div class="text-sm text-blue-900">All files passed (or warnings only). Confirm to place this order.</div>
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="confirm" value="1">
        <button class="bg-brand text-white text-sm font-medium px-4 py-2 rounded-md hover-bg-brand-dark">Confirm &amp; place →</button>
      </form>
    <?php else: ?>
      <div class="bg-red-50 rounded-lg border border-red-200 p-4 text-sm text-red-800">Fix the errors above (re-upload corrected files), then click "Run preflight" again.</div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-4">

    <?php if ($multi): ?>
      <!-- Multi-line: cards for N recipients -->
      <form method="post" action="/catalog/<?= (int)$product['id'] ?>/update" class="bg-white rounded-lg border border-gray-200 p-5">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="shipTo" value="<?= (int)$draft['shipTo'] ?>">
        <input type="hidden" name="billTo" value="<?= (int)($draft['billTo'] ?? $draft['shipTo']) ?>">
        <input type="hidden" name="project" value="<?= e($draft['project']) ?>">
        <input type="hidden" name="notes" value="<?= e($draft['notes']) ?>">

        <h2 class="text-sm font-semibold text-gray-900 mb-1">Recipients</h2>
        <p class="text-xs text-gray-500 mb-4">One per person. Upload artwork per recipient.</p>

        <?php foreach ($draft['lines'] as $idx => $line): ?>
          <div class="grid grid-cols-12 gap-2 mb-3 items-start">
            <div class="col-span-4">
              <label class="block text-xs text-gray-500 mb-1">Name</label>
              <input type="text" name="lines[<?= $idx ?>][name]" value="<?= e($line['name']) ?>" placeholder="e.g., Billy Kimpton" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div class="col-span-3">
              <label class="block text-xs text-gray-500 mb-1">Quantity</label>
              <select name="lines[<?= $idx ?>][qty]" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                <?php foreach ([(int)$product['unit_qty'], (int)$product['unit_qty']*2, (int)$product['unit_qty']*4] as $q): ?>
                  <option value="<?= $q ?>" <?= (int)$line['qty'] === $q ? 'selected' : '' ?>><?= $q ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-span-4">
              <label class="block text-xs text-gray-500 mb-1">Artwork</label>
              <?php if (!empty($line['file'])): ?>
                <div class="text-xs text-gray-700 truncate border border-gray-200 rounded px-3 py-2">📄 <?= e($line['file']['name']) ?></div>
              <?php else: ?>
                <button type="button" onclick="document.getElementById('upload-line-<?= $idx ?>').click()" class="w-full border border-gray-300 rounded px-3 py-2 text-sm text-left text-gray-500 hover:border-brand">Upload PDF…</button>
              <?php endif; ?>
            </div>
            <div class="col-span-1 flex items-end h-full pb-1">
              <button type="submit" name="removeLine" value="<?= $idx ?>" class="text-gray-400 hover:text-red-500 text-lg" <?= count($draft['lines']) <= 1 ? 'disabled' : '' ?>>×</button>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="flex justify-between items-center mt-3">
          <button type="submit" name="addLine" value="1" class="text-sm text-brand font-medium hover:underline">+ Add another recipient</button>
          <button type="submit" class="text-sm text-brand font-medium hover:underline">Update →</button>
        </div>
      </form>

      <!-- Hidden file-upload forms per line -->
      <?php foreach ($draft['lines'] as $idx => $line): ?>
        <?php if (empty($line['file'])): ?>
          <form id="upload-form-<?= $idx ?>" method="post" action="/catalog/<?= (int)$product['id'] ?>/upload" enctype="multipart/form-data" class="hidden" onsubmit="showUploadOverlay()">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="lineIdx" value="<?= $idx ?>">
            <input type="file" id="upload-line-<?= $idx ?>" name="pdf" accept=".pdf,application/pdf" onchange="document.getElementById('upload-form-<?= $idx ?>').submit();">
          </form>
        <?php endif; ?>
      <?php endforeach; ?>

    <?php else: ?>
      <!-- Single-line product -->
      <form method="post" action="/catalog/<?= (int)$product['id'] ?>/update" class="bg-white rounded-lg border border-gray-200 p-5">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="shipTo" value="<?= (int)$draft['shipTo'] ?>">
        <input type="hidden" name="billTo" value="<?= (int)($draft['billTo'] ?? $draft['shipTo']) ?>">
        <input type="hidden" name="project" value="<?= e($draft['project']) ?>">
        <input type="hidden" name="notes" value="<?= e($draft['notes']) ?>">

        <h2 class="text-sm font-semibold text-gray-900 mb-1">Quantity &amp; artwork</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4 mt-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Quantity</label>
            <input type="number" name="qty" min="1" value="<?= (int)($draft['qty'] ?? 1) ?>" onchange="this.form.submit()" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
          </div>
          <div class="flex items-end gap-2">
            <?php if (!empty($product['icon']) && (int)$product['unit_price'] >= 100): ?>
            <label class="text-xs text-gray-700 inline-flex items-center gap-2">
              <input type="checkbox" name="useSavedArt" value="1" <?= $savedArt ? 'checked' : '' ?> onchange="this.form.submit()">
              Use saved on-file artwork
            </label>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($draft['file'])): ?>
          <div class="border border-gray-200 rounded px-3 py-2 text-sm text-gray-700">
            📄 <?= e($draft['file']['name']) ?>
            <button type="button" onclick="document.getElementById('reupload').click()" class="ml-3 text-xs text-gray-500 hover:brand">Replace</button>
          </div>
        <?php else: ?>
          <button type="button" onclick="document.getElementById('reupload').click()" class="w-full border-2 border-dashed border-gray-300 rounded px-3 py-8 text-sm text-gray-500 hover:border-brand">📄 Click to upload PDF</button>
        <?php endif; ?>
      </form>

      <form id="upload-single" method="post" action="/catalog/<?= (int)$product['id'] ?>/upload" enctype="multipart/form-data" class="hidden" onsubmit="showUploadOverlay()">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="file" id="reupload" name="pdf" accept=".pdf,application/pdf" onchange="document.getElementById('upload-single').submit();">
      </form>
    <?php endif; ?>

    <!-- Ship-to / project shared -->
    <form method="post" action="/catalog/<?= (int)$product['id'] ?>/update" class="bg-white rounded-lg border border-gray-200 p-4">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <h3 class="text-sm font-semibold text-gray-900 mb-3">Shipping &amp; project</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Ship to</label>
          <select name="shipTo" onchange="this.form.submit()" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            <?php foreach ($addresses as $a): ?>
              <option value="<?= (int)$a['id'] ?>" <?= (int)$draft['shipTo'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Bill to</label>
          <select name="billTo" onchange="this.form.submit()" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            <?php foreach ($addresses as $a): ?>
              <option value="<?= (int)$a['id'] ?>" <?= (int)($draft['billTo'] ?? $draft['shipTo']) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs text-gray-500 mb-1">Project / PO <span class="text-gray-300">(optional)</span></label>
          <input type="text" name="project" value="<?= e($draft['project']) ?>" onblur="this.form.submit()" placeholder="e.g., 24-108 St. Marcus Center" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs text-gray-500 mb-1">Notes <span class="text-gray-300">(optional)</span></label>
          <input type="text" name="notes" value="<?= e($draft['notes']) ?>" onblur="this.form.submit()" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
        </div>
      </div>
    </form>
  </div>

  <!-- Quote rail -->
  <div class="lg:col-span-1">
    <div class="bg-white rounded-lg border border-gray-200 p-5 sticky top-4">
      <h2 class="text-sm font-semibold text-gray-900 mb-4">Live quote</h2>
      <div class="space-y-2 text-sm">
        <?php if (empty($quote['lines'])): ?>
          <div class="text-gray-400 text-xs italic">Add details to see your quote.</div>
        <?php endif; ?>
        <?php foreach ($quote['lines'] as $line): ?>
          <div class="flex justify-between"><span class="text-gray-700"><?= e($line['description']) ?></span><span class="text-gray-900 tabular-nums">$<?= number_format((float)$line['amount'], 2) ?></span></div>
        <?php endforeach; ?>
        <div class="flex justify-between text-gray-500 text-xs pt-2 border-t border-gray-100">
          <span>Subtotal</span><span class="tabular-nums">$<?= number_format((float)$quote['subtotal'], 2) ?></span>
        </div>
        <div class="flex justify-between text-gray-500 text-xs">
          <span>Tax (5.5%)</span><span class="tabular-nums">$<?= number_format((float)$quote['tax'], 2) ?></span>
        </div>
        <div class="flex justify-between text-base font-semibold text-gray-900 pt-3 mt-2 border-t border-gray-200">
          <span>Total</span><span class="tabular-nums">$<?= number_format((float)$quote['total'], 2) ?></span>
        </div>
      </div>

      <form method="post" action="/catalog/<?= (int)$product['id'] ?>/place" class="mt-5">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button class="w-full bg-brand text-white font-medium py-2.5 rounded-md hover-bg-brand-dark" <?= empty($quote['lines']) ? 'disabled' : '' ?>>Run preflight &amp; continue →</button>
      </form>
      <p class="text-xs text-gray-400 mt-3">No commitment yet. You'll review preflight before placing.</p>

      <?php if (!empty($product['fulfillment']) && $product['fulfillment'] === '4over'): ?>
        <div class="mt-4 text-xs text-gray-400 border-t border-gray-100 pt-3">Produced through 4over.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
