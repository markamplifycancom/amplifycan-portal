<?php /** @var array $user */ ?>
<div class="bg-white rounded-lg border border-gray-200 p-6 max-w-2xl">
  <h1 class="text-2xl font-semibold text-gray-900">Admin home</h1>
  <p class="text-sm text-gray-500 mt-2">Hi <?= e($user['first_name']) ?>. You're signed in as an admin.</p>
  <a href="/admin" class="inline-block mt-4 bg-brand text-white px-4 py-2 rounded-md text-sm font-medium hover-bg-brand-dark">Open admin →</a>
</div>
