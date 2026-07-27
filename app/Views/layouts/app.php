<?php
use App\Core\Auth;
$user = Auth::user();
$isAdmin = Auth::isAdmin();
$currentPath = '/' . trim(str_replace(base_uri(), '', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
$navActive = function (string $prefix) use ($currentPath): string {
    $on = $prefix === '/' ? $currentPath === '/' : str_starts_with($currentPath, $prefix);
    return $on ? 'text-brand-600' : 'text-slate-400';
};
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f2557">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Shoe Bank') ?> · <?= e(config('app.name')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: { extend: { colors: {
          brand: { 50:'#eef2fb',100:'#d5def5',400:'#3b5ba9',500:'#24417f',600:'#0f2557',700:'#0b1c44',900:'#060f26' }
        } } }
      };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">

<!-- Top bar -->
<header class="fixed top-0 inset-x-0 z-30 bg-brand-600 text-white shadow-md">
  <div class="mx-auto max-w-3xl flex items-center justify-between h-14 px-4">
    <a href="<?= e(url('')) ?>" class="flex items-center gap-2 font-semibold">
      <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-lg">👞</span>
      <span class="text-[15px] leading-tight"><?= e(config('app.name')) ?></span>
    </a>
    <div x-data="{open:false}" class="relative">
      <button @click="open=!open" class="flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-sm">
        <span class="hidden sm:inline"><?= e($user['name'] ?? '') ?></span>
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-xs font-bold">
          <?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?>
        </span>
      </button>
      <div x-show="open" @click.outside="open=false" x-transition
           class="absolute right-0 mt-2 w-48 rounded-xl bg-white text-slate-700 shadow-lg ring-1 ring-black/5 overflow-hidden" style="display:none">
        <div class="px-4 py-3 border-b">
          <p class="text-sm font-medium"><?= e($user['name'] ?? '') ?></p>
          <p class="text-xs text-slate-400"><?= e($user['role_label'] ?? '') ?></p>
        </div>
        <?php if ($isAdmin): ?>
          <a href="<?= e(url('settings')) ?>" class="block px-4 py-2.5 text-sm hover:bg-slate-50">Settings</a>
        <?php endif; ?>
        <form method="post" action="<?= e(url('logout')) ?>">
          <?= csrf_field() ?>
          <button class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">Sign out</button>
        </form>
      </div>
    </div>
  </div>
</header>

<!-- Content -->
<main class="mx-auto max-w-3xl px-4 pt-[4.5rem] pb-28">
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <?= $content ?>
</main>

<!-- Bottom navigation + FAB -->
<nav class="fixed bottom-0 inset-x-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur">
  <div class="mx-auto max-w-3xl grid grid-cols-5 h-16 relative">
    <a href="<?= e(url('')) ?>" class="flex flex-col items-center justify-center gap-0.5 <?= $navActive('/') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5M5 9.5V21h14V9.5"/></svg>
      <span class="text-[11px]">Home</span>
    </a>
    <a href="<?= e(url('sales')) ?>" class="flex flex-col items-center justify-center gap-0.5 <?= $navActive('/sales') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m-7.5 5h9a1.5 1.5 0 0 0 1.5-1.5V7.5L14.25 3h-6A1.5 1.5 0 0 0 6.75 4.5v15A1.5 1.5 0 0 0 8.25 21Z"/></svg>
      <span class="text-[11px]">Sales</span>
    </a>
    <!-- Center FAB (3rd of 5 cells, so it sits dead centre) -->
    <div class="flex items-start justify-center" x-data="{open:false, soon:''}">
      <button @click="open=true" class="-mt-6 h-14 w-14 rounded-full bg-brand-600 text-white shadow-lg shadow-brand-600/30 flex items-center justify-center active:scale-95 transition">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
      </button>
      <!-- Action sheet -->
      <div x-show="open" x-transition.opacity @click="open=false; soon=''" style="display:none" class="fixed inset-0 z-40 bg-black/40 flex items-end sm:items-center justify-center">
        <div @click.stop class="w-full sm:max-w-sm bg-white rounded-t-2xl sm:rounded-2xl p-4 space-y-2">
          <p class="text-center text-sm font-semibold text-slate-500 pb-1">Quick actions</p>
          <a href="<?= e(url('sales/create')) ?>" class="flex items-center gap-3 rounded-xl bg-brand-600 px-4 py-3 text-white">
            <span class="text-xl">🧾</span><span class="font-medium">New Invoice</span>
          </a>
          <a href="<?= e(url('expenses/create')) ?>" class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 hover:bg-slate-100">
            <span class="text-xl">💸</span><span class="font-medium">Record Expense</span>
          </a>
          <a href="<?= e(url('products/create')) ?>" class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 hover:bg-slate-100">
            <span class="text-xl">➕</span><span class="font-medium">Add Product</span>
          </a>
          <a href="<?= e(url('calculator')) ?>" class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 hover:bg-slate-100">
            <span class="text-xl">🧮</span><span class="font-medium">Cost Calculator</span>
          </a>
          <a href="<?= e(url('purchases/local')) ?>" class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 hover:bg-slate-100">
            <span class="text-xl">🏪</span><span class="font-medium">Local Purchase</span>
          </a>
          <a href="<?= e(url('purchases/import')) ?>" class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 hover:bg-slate-100">
            <span class="text-xl">📦</span><span class="font-medium">Import Invoice</span>
          </a>
          <a href="<?= e(url('arrivals')) ?>" class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 hover:bg-slate-100">
            <span class="text-xl">✅</span><span class="font-medium">Verify Arrival</span>
          </a>
          <a href="<?= e(url('notes')) ?>" class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 hover:bg-slate-100">
            <span class="text-xl">📝</span><span class="font-medium">Calculation Note</span>
          </a>

          <div class="grid grid-cols-2 gap-2 pt-1">
            <a href="<?= e(url('products')) ?>" class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-slate-100">
              <span class="text-lg">👟</span><span class="text-sm font-medium">Products</span>
            </a>
            <a href="<?= e(url('cheques')) ?>" class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-slate-100">
              <span class="text-lg">🏦</span><span class="text-sm font-medium">Cheques</span>
            </a>
            <a href="<?= e(url('expenses')) ?>" class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-slate-100">
              <span class="text-lg">💸</span><span class="text-sm font-medium">Expenses</span>
            </a>
            <a href="<?= e(url('intelligence')) ?>" class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-slate-100">
              <span class="text-lg">🧠</span><span class="text-sm font-medium">Insights</span>
            </a>
            <a href="<?= e(url('purchases')) ?>" class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-slate-100">
              <span class="text-lg">🚢</span><span class="text-sm font-medium">Purchases</span>
            </a>
            <a href="<?= e(url('reports')) ?>" class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5 hover:bg-slate-100">
              <span class="text-lg">📊</span><span class="text-sm font-medium">Reports</span>
            </a>
          </div>

          <p x-show="soon" x-transition style="display:none" class="rounded-lg bg-slate-800 px-3 py-2 text-center text-xs text-white" x-text="soon"></p>
          <button @click="open=false; soon=''" class="w-full mt-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-500">Close</button>
        </div>
      </div>
    </div>

    <a href="<?= e(url('customers')) ?>" class="flex flex-col items-center justify-center gap-0.5 <?= $navActive('/customers') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.5a3 3 0 0 0-3-3H8.25a3 3 0 0 0-3 3M18.75 12.75h3m-9.75-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
      <span class="text-[11px]">Customers</span>
    </a>
    <a href="<?= e(url('finance')) ?>" class="flex flex-col items-center justify-center gap-0.5 <?= $navActive('/finance') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17.25 9 11.25l3.75 3.75L21 6.75M21 6.75h-5.25M21 6.75V12"/></svg>
      <span class="text-[11px]">Finance</span>
    </a>
  </div>
</nav>

</body>
</html>
