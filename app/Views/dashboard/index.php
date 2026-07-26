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

<!-- Phase 2: Customers & Payments -->
<h2 class="mt-6 mb-2 text-sm font-semibold text-slate-500">Phase 2 — Customers & Payments</h2>
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
  <a href="<?= e(url('customers')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">👥</div><p class="mt-1 text-sm font-medium text-slate-700">Customers</p>
  </a>
  <a href="<?= e(url('cheques')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">📋</div><p class="mt-1 text-sm font-medium text-slate-700">Cheques</p>
  </a>
  <a href="<?= e(url('intelligence')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">📊</div><p class="mt-1 text-sm font-medium text-slate-700">Intelligence</p>
  </a>
</div>

<!-- Imports, clearance & arrivals -->
<h2 class="mt-6 mb-2 text-sm font-semibold text-slate-500">Imports &amp; Clearance</h2>

<div class="grid grid-cols-3 gap-3">
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">In transit</p>
    <p class="mt-1 text-lg font-bold text-slate-800"><?= number_format((float) ($importStats['weight_in_transit'] ?? 0), 1) ?><span class="text-xs font-medium text-slate-400"> kg</span></p>
  </div>
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Cleared</p>
    <p class="mt-1 text-lg font-bold text-slate-800"><?= number_format((float) ($importStats['weight_cleared'] ?? 0), 1) ?><span class="text-xs font-medium text-slate-400"> kg</span></p>
  </div>
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Received</p>
    <p class="mt-1 text-lg font-bold text-slate-800"><?= number_format((float) ($importStats['weight_received'] ?? 0), 1) ?><span class="text-xs font-medium text-slate-400"> kg</span></p>
  </div>
</div>

<div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
  <a href="<?= e(url('purchases')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">📦</div><p class="mt-1 text-sm font-medium text-slate-700">Purchases</p>
  </a>
  <a href="<?= e(url('clearance-persons')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">🚚</div><p class="mt-1 text-sm font-medium text-slate-700">Clearance</p>
  </a>
  <a href="<?= e(url('arrivals')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">✅</div><p class="mt-1 text-sm font-medium text-slate-700">Arrivals</p>
  </a>
  <a href="<?= e(url('notes')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">🧮</div><p class="mt-1 text-sm font-medium text-slate-700">Notes</p>
  </a>
</div>

<?php if ($awaitingClearance): ?>
  <div class="mt-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
      <h3 class="text-sm font-semibold text-slate-700">Awaiting clearance assignment</h3>
      <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700"><?= count($awaitingClearance) ?></span>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($awaitingClearance as $p): ?>
        <li>
          <a href="<?= e(url('purchases/' . $p['id'])) ?>" class="flex items-center justify-between px-4 py-2.5">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-700"><?= e($p['purchase_number']) ?></p>
              <p class="truncate text-xs text-slate-400"><?= e($p['supplier_name']) ?></p>
            </div>
            <span class="ml-3 shrink-0 text-xs font-semibold text-slate-600"><?= number_format((float) $p['total_weight_kg'], 1) ?> kg</span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($inTransit): ?>
  <div class="mt-3 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
      <h3 class="text-sm font-semibold text-slate-700">In transit</h3>
      <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700"><?= count($inTransit) ?></span>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($inTransit as $p): ?>
        <li>
          <a href="<?= e(url('purchases/' . $p['id'])) ?>" class="block px-4 py-2.5">
            <div class="flex items-center justify-between">
              <p class="truncate text-sm font-medium text-slate-700"><?= e($p['purchase_number']) ?></p>
              <span class="ml-3 shrink-0 text-xs text-slate-500">
                <?= $p['expected_arrival_date'] ? e(date('j M', strtotime($p['expected_arrival_date']))) : '—' ?>
              </span>
            </div>
            <p class="truncate text-xs text-slate-400"><?= e($p['clearance_names'] ?: 'Unassigned') ?></p>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($pendingParcels || $pendingQuantity): ?>
  <div class="mt-3 grid grid-cols-2 gap-3">
    <a href="<?= e(url('arrivals')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <p class="text-xs font-medium text-slate-400">Parcels to check</p>
      <p class="mt-1 text-2xl font-bold text-amber-600"><?= count($pendingParcels) ?></p>
    </a>
    <a href="<?= e(url('arrivals')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <p class="text-xs font-medium text-slate-400">Quantities to count</p>
      <p class="mt-1 text-2xl font-bold text-orange-600"><?= count($pendingQuantity) ?></p>
    </a>
  </div>
<?php endif; ?>

<?php if ($byClearancePerson): ?>
  <div class="mt-3 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
      <h3 class="text-sm font-semibold text-slate-700">Shipments by clearance person</h3>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($byClearancePerson as $row): ?>
        <li>
          <a href="<?= e(url('clearance-persons/' . $row['id'])) ?>" class="flex items-center justify-between px-4 py-2.5">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-700"><?= e($row['name']) ?></p>
              <p class="text-xs text-slate-400"><?= (int) $row['shipments'] ?> open shipment(s)</p>
            </div>
            <span class="ml-3 shrink-0 text-xs font-semibold text-slate-600"><?= number_format((float) $row['weight'], 1) ?> kg</span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($clearancePerf): ?>
  <div class="mt-3 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
      <h3 class="text-sm font-semibold text-slate-700">Clearance person performance</h3>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($clearancePerf as $row): ?>
        <li class="flex items-center justify-between px-4 py-2.5">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-700"><?= e($row['name']) ?></p>
            <p class="text-xs text-slate-400">
              <?= (int) $row['shipments'] ?> shipments · <?= (int) $row['delivered'] ?> delivered
            </p>
          </div>
          <div class="ml-3 shrink-0 text-right">
            <p class="text-xs font-semibold text-slate-700"><?= number_format((float) $row['total_weight'], 1) ?> kg</p>
            <p class="text-[11px] text-slate-400"><?= money($row['total_cost']) ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($recentlyArrived): ?>
  <div class="mt-3 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
      <h3 class="text-sm font-semibold text-slate-700">Recently arrived</h3>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($recentlyArrived as $p): ?>
        <li>
          <a href="<?= e(url('purchases/' . $p['id'])) ?>" class="flex items-center justify-between px-4 py-2.5">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-700"><?= e($p['purchase_number']) ?></p>
              <p class="truncate text-xs text-slate-400"><?= e($p['supplier_name']) ?></p>
            </div>
            <span class="ml-3 shrink-0 rounded-md px-2 py-0.5 text-[10px] font-semibold <?= $p['arrival_status'] === 'confirmed' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' ?>">
              <?= e(ucfirst(str_replace('_', ' ', $p['arrival_status']))) ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Reports -->
<h2 class="mt-6 mb-2 text-sm font-semibold text-slate-500">Reports</h2>
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
  <a href="<?= e(url('reports/stock')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">📦</div><p class="mt-1 text-sm font-medium text-slate-700">Stock value</p>
  </a>
  <a href="<?= e(url('reports/imports')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">🚢</div><p class="mt-1 text-sm font-medium text-slate-700">Import spend</p>
  </a>
  <a href="<?= e(url('reports/costs')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">📈</div><p class="mt-1 text-sm font-medium text-slate-700">Cost changes</p>
  </a>
  <a href="<?= e(url('reports')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">📊</div><p class="mt-1 text-sm font-medium text-slate-700">All reports</p>
  </a>
</div>

<!-- Coming soon -->
<h2 class="mt-6 mb-2 text-sm font-semibold text-slate-500">Coming soon</h2>
<div x-data="{ soon: '' }" class="relative">
  <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
    <button type="button" @click="soon='Recording sales to customers — not built yet, so stock only ever goes in'"
            class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100 active:scale-95 transition">
      <div class="text-xl">🧾</div><p class="mt-1 text-[11px] text-slate-500">Sales</p><p class="text-[10px] text-slate-300">Not built</p>
    </button>
  </div>
  <div x-show="soon" x-transition @click="soon=''" style="display:none"
       class="mt-3 rounded-xl bg-slate-800 px-4 py-2.5 text-center text-sm text-white" x-text="soon"></div>
</div>
