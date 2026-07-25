<?php use App\Core\Auth; ?>

<div class="mb-4">
  <h1 class="text-lg font-bold text-slate-800">Welcome back 👋</h1>
  <p class="text-sm text-slate-500"><?= e(date('l, j M Y')) ?></p>
</div>

<!-- Primary stat cards -->
<div class="grid grid-cols-2 gap-3">
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-xs font-medium text-slate-400">Total Products</p>
    <p class="mt-1 text-2xl font-bold text-slate-800"><?= (int) $stats['total_products'] ?></p>
    <a href="<?= e(url('products')) ?>" class="mt-1 inline-block text-xs text-brand-600">View all →</a>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-xs font-medium text-slate-400">Low Stock</p>
    <p class="mt-1 text-2xl font-bold <?= $stats['low_stock'] > 0 ? 'text-amber-600' : 'text-slate-800' ?>"><?= (int) $stats['low_stock'] ?></p>
    <a href="<?= e(url('products?stock=low')) ?>" class="mt-1 inline-block text-xs text-brand-600">Review →</a>
  </div>
</div>

<!-- Alerts -->
<?php if (!empty($lowStock)): ?>
<div class="mt-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
  <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
    <h2 class="text-sm font-semibold text-slate-700">Stock alerts</h2>
    <span class="rounded-full bg-amber-100 text-amber-700 text-xs px-2 py-0.5"><?= count($lowStock) ?></span>
  </div>
  <ul class="divide-y divide-slate-50">
    <?php foreach ($lowStock as $p): ?>
      <li class="flex items-center justify-between px-4 py-2.5">
        <div class="min-w-0">
          <p class="text-sm font-medium text-slate-700 truncate">
            <?= e($p['brand_name'] ?? '—') ?> <?= e($p['art_no'] ?? $p['name'] ?? '') ?>
          </p>
          <p class="text-xs text-slate-400">Threshold: <?= (int) $p['low_stock_threshold'] ?> sets</p>
        </div>
        <span class="ml-3 shrink-0 rounded-lg px-2.5 py-1 text-xs font-semibold <?= $p['stock_sets'] <= 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' ?>">
          <?= (int) $p['stock_sets'] ?> left
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php else: ?>
<div class="mt-4 rounded-2xl bg-white p-5 text-center shadow-sm ring-1 ring-slate-100">
  <div class="text-3xl">✅</div>
  <p class="mt-1 text-sm text-slate-500">No stock alerts. You're all set.</p>
</div>
<?php endif; ?>

<!-- Quick actions -->
<h2 class="mt-6 mb-2 text-sm font-semibold text-slate-500">Quick actions</h2>
<div class="grid grid-cols-2 gap-3">
  <a href="<?= e(url('products/create')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
    <div class="text-2xl">➕</div><p class="mt-1 text-sm font-medium text-slate-700">Add Product</p>
  </a>
  <a href="<?= e(url('calculator')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
    <div class="text-2xl">🧮</div><p class="mt-1 text-sm font-medium text-slate-700">Cost Calculator</p>
  </a>
  <?php if (Auth::isAdmin()): ?>
  <a href="<?= e(url('settings')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
    <div class="text-2xl">⚙️</div><p class="mt-1 text-sm font-medium text-slate-700">Settings</p>
  </a>
  <?php endif; ?>
  <a href="<?= e(url('products')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
    <div class="text-2xl">📦</div><p class="mt-1 text-sm font-medium text-slate-700">Inventory</p>
  </a>
</div>

<!-- Coming-soon modules -->
<h2 class="mt-6 mb-2 text-sm font-semibold text-slate-500">Coming soon</h2>
<div class="grid grid-cols-3 gap-3 opacity-60">
  <div class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100">
    <div class="text-xl">👥</div><p class="mt-1 text-[11px] text-slate-500">Customers</p><p class="text-[10px] text-slate-300">Phase 2</p>
  </div>
  <div class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100">
    <div class="text-xl">🧾</div><p class="mt-1 text-[11px] text-slate-500">Invoices</p><p class="text-[10px] text-slate-300">Phase 3</p>
  </div>
  <div class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100">
    <div class="text-xl">🚢</div><p class="mt-1 text-[11px] text-slate-500">Imports</p><p class="text-[10px] text-slate-300">Phase 4</p>
  </div>
</div>
