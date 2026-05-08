<?php
/** @var array $user */
/** @var array $customer */
/** @var array $draft */
/** @var array $addresses */
/** @var array $rules */
/** @var array $quote */
/** @var string $csrf */
$hasFiles = !empty($draft['files']);
?>
<!-- Upload-in-progress overlay -->
<div id="uploadOverlay" class="hidden fixed inset-0 bg-gray-900/60 z-50 flex items-center justify-center" style="backdrop-filter: blur(2px);">
  <div class="bg-white rounded-lg shadow-2xl p-8 text-center max-w-sm">
    <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-gray-200" style="border-top-color: #f1551a;"></div>
    <div class="font-medium text-gray-900 mt-4">Uploading & analyzing PDF…</div>
    <div class="text-xs text-gray-500 mt-2">Detecting page count, sizes, and color. This can take 10–30 seconds for large files. Please don't refresh.</div>
  </div>
</div>
<!-- Order-submit overlay -->
<div id="submitOverlay" class="hidden fixed inset-0 bg-gray-900/60 z-50 flex items-center justify-center" style="backdrop-filter: blur(2px);">
  <div class="bg-white rounded-lg shadow-2xl p-8 text-center max-w-sm">
    <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-gray-200" style="border-top-color: #f1551a;"></div>
    <div class="font-medium text-gray-900 mt-4">Submitting your order…</div>
    <div class="text-xs text-gray-500 mt-2">Saving files and pushing to production. Usually 5–15 seconds. Please don't close or refresh this tab.</div>
  </div>
</div>
<script>
function showUploadOverlay() { document.getElementById('uploadOverlay').classList.remove('hidden'); }
function showSubmitOverlay() { document.getElementById('submitOverlay').classList.remove('hidden'); }
</script>
<div class="mb-6 flex items-baseline justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900">Repro orders</h1>
    <p class="text-sm text-gray-500 mt-1">Upload one or more PDFs. We auto-detect page count, size, and color, and apply your saved pricing rules.</p>
  </div>
  <?php if ($hasFiles): ?>
  <form method="post" action="/repro/clear">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <button class="text-xs text-gray-400 hover:text-red-500">Clear all</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!$hasFiles): ?>
  <!-- Upload zone -->
  <form method="post" action="/repro/upload" enctype="multipart/form-data" id="reprintUpload" onsubmit="showUploadOverlay()">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label class="block bg-white rounded-lg border-2 border-dashed border-gray-300 hover-border-brand p-12 text-center cursor-pointer transition" id="dropzone">
      <input type="file" name="pdfs[]" accept=".pdf,application/pdf" multiple class="hidden" id="pdfInput" onchange="document.getElementById('reprintUpload').submit();">
      <div class="text-4xl text-gray-300 mb-3">📄</div>
      <div class="text-base font-medium text-gray-700">Click to upload PDFs</div>
      <div class="text-xs text-gray-400 mt-1">Multiple files OK · drag &amp; drop also works</div>
    </label>
  </form>
  <script>
    (function () {
      const dz = document.getElementById('dropzone');
      const inp = document.getElementById('pdfInput');
      const form = document.getElementById('reprintUpload');
      dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('border-brand'); });
      dz.addEventListener('dragleave', () => dz.classList.remove('border-brand'));
      dz.addEventListener('drop', e => {
        e.preventDefault(); dz.classList.remove('border-brand');
        if (e.dataTransfer.files.length === 0) return;
        inp.files = e.dataTransfer.files;
        form.submit();
      });
    })();
  </script>
<?php else: ?>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Files & form -->
    <div class="lg:col-span-2 space-y-4">
      <?php foreach ($draft['files'] as $idx => $file): ?>
        <form method="post" action="/repro/update" class="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="fileId" value="<?= e($file['id']) ?>">
          <input type="hidden" name="shipTo" value="<?= (int)$draft['shipTo'] ?>">
          <input type="hidden" name="project" value="<?= e($draft['project']) ?>">

          <div class="p-4 border-b border-gray-100 flex items-start justify-between">
            <div class="flex items-start gap-3">
              <div class="text-gray-400 text-xl">📄</div>
              <div>
                <div class="font-medium text-gray-900"><?= e($file['name']) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">
                  <?= (int)$file['totalPages'] ?> pages auto-detected · <?= e(implode(', ', $file['sizes'])) ?>
                </div>
              </div>
            </div>
            <button form="remove-<?= e($file['id']) ?>" class="text-gray-400 hover:text-red-500 text-lg" type="submit" formmethod="post" formaction="/repro/remove">×</button>
          </div>

          <div class="px-4 py-3">
            <div class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">Auto-detected breakdown</div>
            <div class="space-y-2" id="ranges-<?= e($file['id']) ?>">
              <?php foreach ($file['ranges'] as $rIdx => $r): ?>
                <div class="grid grid-cols-12 gap-2 items-center text-sm">
                  <input type="text" name="ranges[<?= $rIdx ?>][pages]" value="<?= e($r['pages']) ?>" placeholder="e.g., 1-5" class="col-span-3 border border-gray-200 rounded px-2 py-1 text-sm">
                  <select name="ranges[<?= $rIdx ?>][size]" class="col-span-3 border border-gray-200 rounded px-2 py-1 text-sm">
                    <?php foreach (['8.5×11','8.5×14 (legal)','11×17','12×18','17×22','18×24','22×34','24×36','30×42','34×44','36×48'] as $sz): ?>
                      <option value="<?= e($sz) ?>" <?= str_starts_with($r['size'], explode(' ', $sz)[0]) ? 'selected' : '' ?>><?= e($sz) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select name="ranges[<?= $rIdx ?>][color]" class="col-span-2 border border-gray-200 rounded px-2 py-1 text-sm">
                    <option value="bw"    <?= $r['color']==='bw'    ? 'selected' : '' ?>>B&amp;W</option>
                    <option value="color" <?= $r['color']==='color' ? 'selected' : '' ?>>Color</option>
                  </select>
                  <select name="ranges[<?= $rIdx ?>][stock]" class="col-span-3 border border-gray-200 rounded px-2 py-1 text-sm">
                    <option value="bond"      <?= $r['stock']==='bond'      ? 'selected' : '' ?>>20# Bond</option>
                    <option value="cardstock" <?= $r['stock']==='cardstock' ? 'selected' : '' ?>>100# Cover</option>
                    <option value="gloss"     <?= $r['stock']==='gloss'     ? 'selected' : '' ?>>100# Gloss</option>
                  </select>
                  <div class="col-span-1"></div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-100 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div>
                <label class="block text-gray-500 mb-1">Sides</label>
                <select name="sides" class="w-full border border-gray-200 rounded px-2 py-1 text-xs">
                  <option value="single" <?= $file['sides']==='single' ? 'selected':'' ?>>Single-sided</option>
                  <option value="double" <?= $file['sides']==='double' ? 'selected':'' ?>>Double-sided</option>
                </select>
              </div>
              <div>
                <label class="block text-gray-500 mb-1">Quantity (sets)</label>
                <input type="number" name="qty" min="1" value="<?= (int)$file['qty'] ?>" class="w-full border border-gray-200 rounded px-2 py-1 text-xs">
              </div>
              <div>
                <label class="block text-gray-500 mb-1">Finishing</label>
                <select name="finishing" class="w-full border border-gray-200 rounded px-2 py-1 text-xs">
                  <option value="none"   <?= $file['finishing']==='none'   ? 'selected':'' ?>>None</option>
                  <option value="staple" <?= $file['finishing']==='staple' ? 'selected':'' ?>>Stapled</option>
                  <option value="punch"  <?= $file['finishing']==='punch'  ? 'selected':'' ?>>3-hole punch</option>
                  <option value="bind"   <?= $file['finishing']==='bind'   ? 'selected':'' ?>>Bound</option>
                </select>
              </div>
              <div>
                <label class="block text-gray-500 mb-1">Notes</label>
                <input type="text" name="notes" value="<?= e($file['notes']) ?>" placeholder="(optional)" class="w-full border border-gray-200 rounded px-2 py-1 text-xs">
              </div>
            </div>

            <div class="mt-4 flex justify-end">
              <button type="submit" class="text-sm text-brand font-medium hover:underline">Update breakdown →</button>
            </div>
          </div>
        </form>

        <!-- Hidden remove form for the × button -->
        <form id="remove-<?= e($file['id']) ?>" method="post" action="/repro/remove" class="hidden">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="fileId" value="<?= e($file['id']) ?>">
        </form>
      <?php endforeach; ?>

      <!-- Add another file -->
      <form method="post" action="/repro/upload" enctype="multipart/form-data" id="addMore" onsubmit="showUploadOverlay()">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label class="block w-full border-2 border-dashed border-gray-200 rounded-lg py-3 text-sm text-center text-gray-500 hover-border-brand cursor-pointer">
          <input type="file" name="pdfs[]" accept=".pdf,application/pdf" multiple class="hidden" onchange="document.getElementById('addMore').submit();">
          + Add another file
        </label>
      </form>

      <!-- Ship-to / project shared across files -->
      <form method="post" action="/repro/update" class="bg-white rounded-lg border border-gray-200 p-4">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="fileId" value="">
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
            <label class="block text-xs text-gray-500 mb-1">Project / PO <span class="text-gray-300">(optional)</span></label>
            <input type="text" name="project" value="<?= e($draft['project']) ?>" onblur="this.form.submit()" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
          </div>
        </div>
      </form>
    </div>

    <!-- Live quote rail -->
    <div class="lg:col-span-1">
      <div class="bg-white rounded-lg border border-gray-200 p-5 sticky top-4">
        <div class="flex items-baseline justify-between mb-3">
          <h2 class="text-sm font-semibold text-gray-900">Live quote</h2>
          <details class="text-xs text-gray-500"><summary class="cursor-pointer hover:text-gray-700">Your rates</summary>
            <div class="bg-gray-50 rounded p-3 mt-2 text-xs space-y-1">
              <?php foreach ($rules as $rule): ?>
                <div class="flex justify-between gap-3">
                  <span class="text-gray-600"><?= e($rule['label']) ?></span>
                  <span class="font-mono text-gray-900">$<?= number_format((float)$rule['price'], 2) ?>/pg</span>
                </div>
              <?php endforeach; ?>
              <div class="text-gray-400 text-xs pt-2 border-t border-gray-200 mt-2">
                <?php if ($customer['free_delivery']): ?>Free local delivery · <?php endif; ?>5% off over 500 pages
              </div>
            </div>
          </details>
        </div>

        <div class="space-y-2 text-sm">
          <?php if (empty($quote['lines'])): ?>
            <div class="text-gray-400 text-xs italic">Add ranges to see your quote.</div>
          <?php endif; ?>
          <?php foreach ($quote['lines'] as $line): ?>
            <div class="flex justify-between">
              <span class="text-gray-700"><?= e($line['description']) ?></span>
              <span class="text-gray-900 tabular-nums">$<?= number_format((float)$line['amount'], 2) ?></span>
            </div>
          <?php endforeach; ?>
          <?php if ($quote['discount'] > 0): ?>
            <div class="flex justify-between text-green-700">
              <span>Volume discount (5%)</span>
              <span class="tabular-nums">-$<?= number_format((float)$quote['discount'], 2) ?></span>
            </div>
          <?php endif; ?>
          <div class="flex justify-between text-gray-500 text-xs pt-2 border-t border-gray-100">
            <span>Subtotal</span>
            <span class="tabular-nums">$<?= number_format((float)$quote['subtotal'], 2) ?></span>
          </div>
          <div class="flex justify-between text-gray-500 text-xs">
            <span>Delivery</span>
            <span><?= $customer['free_delivery'] ? 'Free' : '$22.00' ?></span>
          </div>
          <div class="flex justify-between text-gray-500 text-xs">
            <span>Tax (5.5%)</span>
            <span class="tabular-nums">$<?= number_format((float)$quote['tax'], 2) ?></spa