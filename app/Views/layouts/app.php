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
          brand: { 50:'#eff6ff', 100:'#dbeafe', 400:'#60a5fa', 500:'#3b82f6', 600:'#1E3A8A', 700:'#1e40af', 900:'#1e3a8a' }
        } } }
      };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased pb-20">

<!-- Top bar -->
<header class="fixed top-0 inset-x-0 z-30 bg-white text-slate-800 shadow-sm border-b border-slate-200">
  <div class="mx-auto max-w-3xl flex items-center justify-between h-14 px-4">
    <a href="<?= e(url('')) ?>" class="flex items-center gap-2 font-bold text-lg text-brand-600">
      <span class="text-2xl">👞</span>
      <span><?= e(config('app.name')) ?></span>
    </a>
    <div class="flex items-center gap-3">
      <!-- Notification Bell -->
      <button class="relative p-2 text-slate-400 hover:text-brand-600 transition">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500"></span>
      </button>
      <div x-data="{open:false}" class="relative">
        <button @click="open=!open" class="flex items-center justify-center h-8 w-8 rounded-full bg-brand-100 text-brand-600 font-bold text-sm ring-2 ring-white">
            <?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?>
        </button>
        <div x-show="open" @click.outside="open=false" x-transition
             class="absolute right-0 mt-2 w-48 rounded-xl bg-white text-slate-700 shadow-lg ring-1 ring-black/5 overflow-hidden" style="display:none">
          <div class="px-4 py-3 border-b border-slate-100">
            <p class="text-sm font-semibold"><?= e($user['name'] ?? '') ?></p>
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
  </div>
</header>

<!-- Content -->
<main class="mx-auto max-w-3xl px-4 pt-[4.5rem]">
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  <?= $content ?>
</main>

<!-- Floating Action Button (FAB) -->
<div class="fixed bottom-20 right-4 sm:right-auto sm:ml-[calc(768px-4.5rem)] z-40" x-data="{open:false}">
  <button @click="open=true" class="h-14 w-14 rounded-full bg-brand-600 text-white shadow-xl flex items-center justify-center active:scale-95 transition-transform hover:bg-brand-700">
    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
  </button>
  
  <!-- Action Sheet Overlay -->
  <div x-show="open" x-transition.opacity @click="open=false" style="display:none" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex flex-col justify-end">
    <div @click.stop class="bg-white rounded-t-3xl p-5 w-full max-w-3xl mx-auto shadow-2xl" x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
      <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-4"></div>
      <h3 class="text-lg font-bold text-slate-800 mb-4 px-2">Quick Actions</h3>
      
      <div class="grid grid-cols-1 gap-2">
        <a href="<?= e(url('sales/create')) ?>" class="flex items-center gap-4 rounded-2xl bg-brand-50 px-5 py-4 active:bg-brand-100 transition">
          <div class="h-10 w-10 rounded-full bg-brand-600 text-white flex items-center justify-center text-xl shadow-md">🧾</div>
          <span class="font-semibold text-brand-900 text-lg">New Invoice</span>
        </a>
        <a href="<?= e(url('customers/create')) ?>" class="flex items-center gap-4 rounded-2xl bg-slate-50 px-5 py-4 active:bg-slate-100 transition border border-slate-100">
          <div class="h-10 w-10 rounded-full bg-white text-slate-700 flex items-center justify-center text-xl shadow-sm ring-1 ring-slate-200">👤</div>
          <span class="font-medium text-slate-700 text-lg">Add Customer</span>
        </a>
        <a href="<?= e(url('products/create')) ?>" class="flex items-center gap-4 rounded-2xl bg-slate-50 px-5 py-4 active:bg-slate-100 transition border border-slate-100">
          <div class="h-10 w-10 rounded-full bg-white text-slate-700 flex items-center justify-center text-xl shadow-sm ring-1 ring-slate-200">➕</div>
          <span class="font-medium text-slate-700 text-lg">Add Product</span>
        </a>
        <a href="<?= e(url('purchases/import')) ?>" class="flex items-center gap-4 rounded-2xl bg-slate-50 px-5 py-4 active:bg-slate-100 transition border border-slate-100">
          <div class="h-10 w-10 rounded-full bg-white text-slate-700 flex items-center justify-center text-xl shadow-sm ring-1 ring-slate-200">📦</div>
          <span class="font-medium text-slate-700 text-lg">New Purchase</span>
        </a>
      </div>
      <button @click="open=false" class="w-full mt-4 rounded-2xl bg-slate-100 px-4 py-4 font-semibold text-slate-700 hover:bg-slate-200 transition">Cancel</button>
    </div>
  </div>
</div>

<!-- Bottom Navigation -->
<nav class="fixed bottom-0 inset-x-0 z-30 bg-white border-t border-slate-200 pb-safe">
  <div class="mx-auto max-w-3xl grid grid-cols-5 h-16 relative">
    <a href="<?= e(url('')) ?>" class="flex flex-col items-center justify-center gap-1 <?= $navActive('/') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5M5 9.5V21h14V9.5"/></svg>
      <span class="text-[10px] font-semibold">Home</span>
    </a>
    <a href="<?= e(url('products')) ?>" class="flex flex-col items-center justify-center gap-1 <?= $navActive('/products') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
      <span class="text-[10px] font-semibold">Products</span>
    </a>
    <a href="<?= e(url('customers')) ?>" class="flex flex-col items-center justify-center gap-1 <?= $navActive('/customers') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.5a3 3 0 00-3-3H8.25a3 3 0 00-3 3M18.75 12.75h3m-9.75-6a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <span class="text-[10px] font-semibold">Customers</span>
    </a>
    <a href="<?= e(url('finance')) ?>" class="flex flex-col items-center justify-center gap-1 <?= $navActive('/finance') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span class="text-[10px] font-semibold">Finance</span>
    </a>
    <!-- More Menu Trigger -->
    <div x-data="{showMore:false}" class="flex flex-col items-center justify-center">
      <button @click="showMore=true" class="flex flex-col items-center justify-center gap-1 text-slate-400 hover:text-brand-600 transition w-full h-full">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        <span class="text-[10px] font-semibold">More</span>
      </button>

      <!-- More Menu Slide-over -->
      <div x-show="showMore" x-transition.opacity @click="showMore=false" style="display:none" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex justify-end">
        <div @click.stop class="bg-white w-4/5 max-w-sm h-full shadow-2xl flex flex-col" x-show="showMore" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
          <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h2 class="font-bold text-slate-800 text-lg">Menu</h2>
            <button @click="showMore=false" class="p-2 -mr-2 text-slate-400 hover:text-slate-600 rounded-full">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="flex-1 overflow-y-auto p-4 space-y-6">
            <div>
              <p class="text-xs font-bold text-brand-600 uppercase tracking-wider mb-3">Operations</p>
              <div class="space-y-1">
                <a href="<?= e(url('sales')) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 text-slate-700 font-medium"><span class="text-xl w-6">📃</span> All Sales</a>
                <a href="<?= e(url('purchases')) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 text-slate-700 font-medium"><span class="text-xl w-6">🚢</span> Purchases</a>
                <a href="<?= e(url('arrivals')) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 text-slate-700 font-medium"><span class="text-xl w-6">✅</span> Arrivals & Verify</a>
              </div>
            </div>
            <div>
              <p class="text-xs font-bold text-brand-600 uppercase tracking-wider mb-3">Money & Insights</p>
              <div class="space-y-1">
                <a href="<?= e(url('cheques')) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 text-slate-700 font-medium"><span class="text-xl w-6">🏦</span> Cheques</a>
                <a href="<?= e(url('expenses')) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 text-slate-700 font-medium"><span class="text-xl w-6">💸</span> Expenses</a>
                <a href="<?= e(url('reports')) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 text-slate-700 font-medium"><span class="text-xl w-6">📊</span> Reports</a>
              </div>
            </div>
            <div>
              <p class="text-xs font-bold text-brand-600 uppercase tracking-wider mb-3">System</p>
              <div class="space-y-1">
                <a href="<?= e(url('calculator')) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 text-slate-700 font-medium"><span class="text-xl w-6">🧮</span> Calculator</a>
                <?php if ($isAdmin): ?>
                <a href="<?= e(url('settings')) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 text-slate-700 font-medium"><span class="text-xl w-6">⚙️</span> Settings</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</nav>

<style>
  .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
</style>
</body>
</html>
