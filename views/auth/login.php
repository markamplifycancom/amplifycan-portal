<?php /** @var ?string $error */ /** @var string $email */ /** @var string $csrf */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in &middot; <?= e(PORTAL_NAME) ?></title>
<link rel="icon" href="/assets/favicon.png">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  :root {
    --brand-orange: #f1551a;
    --brand-orange-dark: #c43d10;
    --brand-orange-light: #fef0eb;
    --brand-navy: #212934;
  }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--brand-navy);
         background: linear-gradient(135deg, #f9fafb 0%, var(--brand-orange-light) 100%); }
  .brand { color: var(--brand-orange); }
  .bg-brand { background-color: var(--brand-orange); }
  .hover-bg-brand-dark:hover { background-color: var(--brand-orange-dark); }
  .ring-brand { --tw-ring-color: var(--brand-orange); }
</style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">
<div class="bg-white shadow-lg rounded-lg w-full max-w-md p-8">
  <div class="text-center mb-6">
    <img src="/assets/logo.png" alt="Amplify Graphics &amp; Branding" class="h-12 mx-auto mb-2">
    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Customer Portal</div>
  </div>
  <h1 class="text-xl font-semibold text-gray-900 mb-1">Sign in</h1>
  <p class="text-sm text-gray-500 mb-6">Welcome back. Sign in to place orders and check status.</p>

  <?php if ($error === 'invalid'): ?>
    <div class="mb-4 px-3 py-2 rounded bg-red-50 text-red-700 text-sm border border-red-100">Email or password is incorrect.</div>
  <?php elseif ($error === 'session'): ?>
    <div class="mb-4 px-3 py-2 rounded bg-red-50 text-red-700 text-sm border border-red-100">Your session expired. Please sign in again.</div>
  <?php elseif ($error === 'missing'): ?>
    <div class="mb-4 px-3 py-2 rounded bg-yellow-50 text-yellow-800 text-sm border border-yellow-100">Please enter both email and password.</div>
  <?php endif; ?>

  <form method="post" action="/login">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
    <input type="email" name="email" required autofocus value="<?= e($email) ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 mb-4 focus:outline-none focus:ring-2 ring-brand">
    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
    <input type="password" name="password" required class="w-full border border-gray-300 rounded-md px-3 py-2 mb-6 focus:outline-none focus:ring-2 ring-brand">
    <button type="submit" class="w-full bg-brand text-white font-medium py-2 rounded-md hover-bg-brand-dark">Sign in</button>
  </form>

  <div class="mt-4 text-xs text-gray-400 text-center">
    Demo: <span class="font-mono">lashworth@founders3.com</span> / <span class="font-mono">demo</span>
  </div>
</div>
</body>
</html>
