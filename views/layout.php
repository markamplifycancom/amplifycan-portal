<?php
use Portal\Auth;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navItems = [
    ['/dashboard', 'Dashboard'],
    ['/repro',     'Repro orders'],
    ['/reprint',   'Reprint past order'],
    ['/catalog',   'Order from catalog'],
    ['/orders',    'Order history'],
];
$user     = Auth::user();
$customer = Auth::customer();
$initials = $user ? strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1)) : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(PORTAL_NAME) ?></title>
<link rel="icon" href="/assets/favicon.png">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
  /* AmplifyCan brand colors (pulled from amplifycan.com) */
  :root {
    --brand-orange: #f1551a;
    --brand-orange-dark: #c43d10;
    --brand-orange-light: #fef0eb;
    --brand-navy: #212934;
    --brand-navy-soft: #4a4e57;
    --brand-blue: #0070b9;
  }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--brand-navy); }
  .brand { color: var(--brand-orange); }
  .brand-navy { color: var(--brand-navy); }
  .bg-brand { background-color: var(--brand-orange); }
  .bg-brand-light { background-color: var(--brand-orange-light); }
  .bg-brand-navy { background-color: var(--brand-navy); }
  .border-brand { border-color: var(--brand-orange); }
  .ring-brand { --tw-ring-color: var(--brand-orange); }
  .hover-bg-brand-dark:hover { background-color: var(--brand-orange-dark); }
  .hover-border-brand:hover { border-color: var(--brand-orange); }
  .hover-brand:hover { color: var(--brand-orange); }
  .pill { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 9999px; font-size: 12px; font-weight: 500; }
  .pill-blue { background: #DBEAFE; color: #0070b9; }
  .pill-green { background: #D1FAE5; color: #065F46; }
  .pill-yellow { background: #FEF3C7; color: #92400E; }
  .pill-gray { background: #F3F4F6; color: #4a4e57; }
  .pill-orange { background: var(--brand-orange-light); color: var(--brand-orange-dark); }
  .pill-purple { background: var(--brand-orange-light); color: var(--brand-orange-dark); }   /* alias — Repro pills now orange */
  .prod-icon { height: 56px; display: flex; align-items: center; justify-content: center; font-size: 28px; color: #94A3B8; background: #F8FAFC; border-radius: 6px; }
  [x-cloak] { display: none !important; }
</style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php $_admin = \Portal\Auth::adminUser(); $_imperson = \Portal\Auth::isImpersonating(); ?>
<?php if ($_imperson): ?>
<div class="bg-brand-navy text-white text-sm py-2 px-4">
  <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
    <div class="flex items-center gap-2">
      <span class="text-xs uppercase tracking-wider opacity-75">Acting as</span>
      <span class="font-semibold"><?= e($customer['name'] ?? '?') ?></span>
      <span class="text-xs opacity-60">(signed in as <?= e($_admin['first_name'] . ' ' . $_admin['last_name']) ?>, admin)</span>
    </div>
    <form method="post" action="/admin/impersonate/stop" class="inline">
      <input type="hidden" name="csrf" value="<?= e(\Portal\Auth::csrfToken()) ?>">
      <button class="text-xs bg-white/10 hover:bg-white/20 px-3 py-1 rounded border border-white/20">← Stop acting as customer</button>
    </form>
  </div>
</div>
<?php endif; ?>


<header class="bg-white border-b border-gray-200">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
    <div class="flex items-center gap-8">
      <a href="/dashboard" class="flex items-center gap-2">
        <img src="/assets/logo.png" alt="Amplify Graphics &amp; Branding" class="h-8 w-auto">
        <span class="hidden sm:inline text-xs font-semibold uppercase tracking-wider brand-navy">Customer Portal</span>
      </a>
      <nav class="hidden md:flex items-center gap-6 text-sm">
        <?php
        $navItems = $user && !empty($user['is_admin'])
          ? [['/admin', 'Admin'], ['/orders', 'Orders']]
          : $navItems;
        ?>
        <?php foreach ($navItems as [$path, $label]): ?>
          <a href="<?= e($path) ?>" class="text-gray-700 hover-brand <?= $currentPath === $path ? 'brand font-semibold' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="flex items-center gap-3 text-sm">
      <?php if ($customer): ?><span class="text-gray-500 hidden sm:inline"><?= e($customer['name']) ?></span><?php endif; ?>
      <div class="w-8 h-8 rounded-full bg-brand text-white flex items-center justify-center font-semibold text-xs"><?= e($initials) ?></div>
      <form method="post" action="/logout" class="inline">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <button class="text-gray-400 hover:text-gray-600 text-xs">Sign out</button>
      </form>
    </div>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
<?= $content ?? '' ?>
</main>

<footer class="max-w-7xl mx-auto px-4 sm:px-6 py-8 text-center text-xs text-gray-400">
  Amplify Graphics &amp; Branding &middot; Customer Portal
</footer>

</body>
</html>
