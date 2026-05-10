<?php
/**
 * Floating "talk to Claude" feedback widget. Visible only to admins.
 * Captures the current URL + any order/customer context so feedback can be
 * acted on without follow-up round trips.
 *
 * Included from views/layout.php at the bottom of <body>.
 */
use Portal\Auth;

$_fbAdmin = Auth::adminUser();
if (!$_fbAdmin || empty($_fbAdmin['is_admin'])) return;   // admin-only

$_fbCustomer    = Auth::customer();
$_fbImpersonating = Auth::isImpersonating();
$_fbCsrf        = Auth::csrfToken();

// Best-effort: if URL is /orders/{id}, grab the order id for context.
$_fbPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$_fbOrderId = null;
if (preg_match('#^/orders/(\d+)$#', $_fbPath, $m)) $_fbOrderId = (int)$m[1];
?>

<!-- Feedback widget styles -->
<style>
  #fbBubble { position: fixed; bottom: 20px; right: 20px; z-index: 9000; background: var(--brand-orange); color: white; width: 56px; height: 56px; border-radius: 28px; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 20px rgba(0,0,0,0.2); cursor: pointer; transition: transform .15s; border: none; }
  #fbBubble:hover { transform: scale(1.05); background: var(--brand-orange-dark); }
  #fbBubble svg { width: 26px; height: 26px; }
  #fbPanel { position: fixed; bottom: 90px; right: 20px; z-index: 9001; width: 360px; max-width: calc(100vw - 40px); background: white; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.25); display: none; overflow: hidden; }
  #fbPanel.show { display: block; }
  #fbPanel header { background: var(--brand-navy); color: white; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; }
  #fbPanel header h3 { font-size: 14px; font-weight: 600; margin: 0; }
  #fbPanel header .sub { font-size: 11px; opacity: 0.7; margin-top: 2px; }
  #fbPanel header .x { background: transparent; border: none; color: white; font-size: 20px; cursor: pointer; opacity: 0.7; padding: 0 4px; }
  #fbPanel header .x:hover { opacity: 1; }
  #fbPanel .body { padding: 14px 16px; }
  #fbPanel .ctx { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; font-size: 11px; color: #4a4e57; margin-bottom: 12px; line-height: 1.5; }
  #fbPanel .ctx .label { color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: 0.05em; margin-bottom: 4px; }
  #fbPanel textarea { width: 100%; min-height: 90px; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px; font-size: 13px; font-family: inherit; resize: vertical; outline: none; }
  #fbPanel textarea:focus { border-color: var(--brand-orange); box-shadow: 0 0 0 3px rgba(241, 85, 26, 0.1); }
  #fbPanel button.send { background: var(--brand-orange); color: white; font-size: 13px; font-weight: 600; padding: 9px 16px; border: none; border-radius: 8px; cursor: pointer; width: 100%; margin-top: 10px; }
  #fbPanel button.send:hover { background: var(--brand-orange-dark); }
  #fbPanel button.send:disabled { background: #9ca3af; cursor: not-allowed; }
  #fbPanel .toast { font-size: 12px; padding: 8px 10px; border-radius: 6px; margin-top: 10px; }
  #fbPanel .toast.ok { background: #d1fae5; color: #065f46; }
  #fbPanel .toast.err { background: #fee2e2; color: #991b1b; }
  #fbPanel .recent { border-top: 1px solid #e5e7eb; margin-top: 12px; padding-top: 10px; }
  #fbPanel .recent .label { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
  #fbPanel .recent .item { font-size: 12px; padding: 6px 8px; background: #f8fafc; border-left: 2px solid #e5e7eb; margin-bottom: 4px; }
  #fbPanel .recent .item.resolved { border-left-color: #10b981; opacity: 0.7; }
</style>

<button id="fbBubble" title="Send feedback to Claude" onclick="fbToggle()">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
  </svg>
</button>

<div id="fbPanel">
  <header>
    <div>
      <h3>Send feedback to Claude</h3>
      <div class="sub">Goes to Mark's chat session. Claude reads &amp; pushes code.</div>
    </div>
    <button class="x" onclick="fbToggle()" aria-label="Close">×</button>
  </header>
  <div class="body">
    <div class="ctx">
      <div class="label">Context (auto-captured)</div>
      <div><strong>Page:</strong> <?= e($_fbPath) ?></div>
      <?php if ($_fbCustomer): ?>
        <div><strong>Customer:</strong> <?= e($_fbCustomer['name']) ?><?= $_fbImpersonating ? ' (acting as)' : '' ?></div>
      <?php endif; ?>
      <?php if ($_fbOrderId): ?>
        <div><strong>Order:</strong> #<?= (int)$_fbOrderId ?></div>
      <?php endif; ?>
      <div><strong>Admin:</strong> <?= e(trim(($_fbAdmin['first_name'] ?? '') . ' ' . ($_fbAdmin['last_name'] ?? ''))) ?></div>
    </div>
    <form id="fbForm" onsubmit="fbSend(event)">
      <input type="hidden" name="csrf" value="<?= e($_fbCsrf) ?>">
      <input type="hidden" name="page_url" value="<?= e($_fbPath) ?>">
      <input type="hidden" name="order_id" value="<?= (int)$_fbOrderId ?>">
      <textarea name="message" placeholder="What's wrong, what do you want changed, or what feedback do you have on pricing / Monday data / UI? Be specific — include numbers if you can." required></textarea>
      <button type="submit" class="send" id="fbSendBtn">Send to Claude</button>
    </form>
    <div id="fbToast" style="display: none;"></div>
  </div>
</div>

<script>
function fbToggle() {
  var p = document.getElementById('fbPanel');
  p.classList.toggle('show');
  if (p.classList.contains('show')) {
    setTimeout(function () { p.querySelector('textarea').focus(); }, 50);
  }
}
function fbSend(e) {
  e.preventDefault();
  var form = document.getElementById('fbForm');
  var btn = document.getElementById('fbSendBtn');
  var toast = document.getElementById('fbToast');
  var data = new FormData(form);
  btn.disabled = true; btn.textContent = 'Sending…';
  toast.style.display = 'none';
  fetch('/admin/feedback/new', { method: 'POST', body: data, credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d && d.ok) {
        toast.className = 'toast ok';
        toast.textContent = 'Sent! Tell Mark to "check feedback" in chat — Claude will fetch & act.';
        toast.style.display = 'block';
        form.querySelector('textarea').value = '';
      } else {
        throw new Error((d && d.error) || 'Save failed');
      }
    })
    .catch(function (err) {
      toast.className = 'toast err';
      toast.textContent = 'Failed to send: ' + err.message;
      toast.style.display = 'block';
    })
    .finally(function () {
      btn.disabled = false; btn.textContent = 'Send to Claude';
    });
}
</script>
