<?php use App\Core\Auth; ?>

<div class="mb-4">
  <h1 class="text-lg font-bold text-slate-800">Welcome back 👋</h1>
  <p class="text-sm text-slate-500"><?= e(date('l, j M Y')) ?></p>
</div>

<!-- This month, in money -->
<?php $net = (float) $money['net_profit']; $isProfit = $net >= 0; ?>
<a href="<?= e(url('finance')) ?>"
   class="block rounded-2xl <?= $isProfit ? 'bg-emerald-600' : 'bg-red-600' ?> p-5 text-white shadow-sm active:scale-[.99] transition">
  <div class="flex items-start justify-between">
    <div>
      <p class="text-xs font-medium text-white/70">
        <?= $isProfit ? 'Net profit' : 'Net loss' ?> · <?= e(date('F')) ?>
      </p>
      <p class="mt-1 text-3xl font-bold"><?= $isProfit ? '' : '− ' ?><?= money(abs($net)) ?></p>
    </div>
    <span class="text-white/50">→</span>
  </div>
  <p class="mt-1 text-xs text-white/70">
    <?= money($money['revenue']) ?> sold · <?= money($money['cash_collected']) ?> collected
  </p>
</a>

<div class="mt-3 grid grid-cols-2 gap-3">
  <a href="<?= e(url('sales')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
    <p class="text-[11px] font-medium text-slate-400">Sales today</p>
    <p class="mt-1 text-xl font-bold text-slate-800"><?= money($periods['today'] ?? 0) ?></p>
    <p class="text-[11px] text-slate-400"><?= (int) ($periods['today_invoices'] ?? 0) ?> invoice(s)</p>
  </a>
  <a href="<?= e(url('reports/receivables')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
    <p class="text-[11px] font-medium text-slate-400">Owed to you</p>
    <p class="mt-1 text-xl font-bold <?= $receivables['outstanding'] > 0 ? 'text-red-600' : 'text-emerald-600' ?>">
      <?= money($receivables['outstanding']) ?>
    </p>
    <p class="text-[11px] text-slate-400"><?= (int) $receivables['customers'] ?> customer(s)</p>
  </a>
</div>

<!-- What needs chasing today -->
<?php if (!empty($overdueSales) || !empty($chequesDue)): ?>
  <div class="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
    <div class="border-b border-slate-100 bg-red-50 px-4 py-3">
      <h2 class="text-sm font-semibold text-red-800">Needs attention</h2>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($chequesDue as $c): ?>
        <li>
          <a href="<?= e(url("cheques/{$c['id']}")) ?>" class="flex items-center justify-between gap-3 px-4 py-2.5">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800">🏦 Bank cheque · <?= e($c['customer_name']) ?></p>
              <p class="text-[11px] text-slate-400">
                #<?= e($c['cheque_number']) ?> ·
                <?= (int) $c['days_until'] < 0
                      ? abs((int) $c['days_until']) . ' day(s) late'
                      : ((int) $c['days_until'] === 0 ? 'due today' : 'due in ' . (int) $c['days_until'] . ' day(s)') ?>
              </p>
            </div>
            <span class="shrink-0 text-sm font-semibold text-slate-800"><?= money($c['amount']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
      <?php foreach ($overdueSales as $o): ?>
        <li>
          <a href="<?= e(url("sales/{$o['id']}")) ?>" class="flex items-center justify-between gap-3 px-4 py-2.5">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800">⏰ Overdue · <?= e($o['customer_name'] ?: 'Walk-in') ?></p>
              <p class="text-[11px] text-slate-400">
                <?= e($o['invoice_number']) ?> · <?= (int) $o['days_overdue'] ?> day(s) late
              </p>
            </div>
            <span class="shrink-0 text-sm font-semibold text-red-600"><?= money($o['unpaid']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if (!empty($overdueCustomers)): ?>
  <div class="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
    <div class="border-b border-slate-100 bg-amber-50 px-4 py-3 flex justify-between items-center">
      <h2 class="text-sm font-semibold text-amber-800">Overdue Customer Accounts</h2>
      <span class="rounded-full bg-amber-200 text-amber-800 text-xs px-2 py-0.5 font-medium">> 30 days</span>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($overdueCustomers as $oc): ?>
        <li>
          <a href="<?= e(url("customers/{$oc['id']}")) ?>" class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-slate-50">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800">👤 <?= e($oc['name']) ?></p>
              <p class="text-[11px] text-slate-400">
                <?= e($oc['phone'] ?: 'No phone') ?> · Last paid <?= e(date('j M Y', strtotime($oc['oldest_unpaid_date']))) ?>
              </p>
            </div>
            <div class="shrink-0 text-right">
                <span class="block text-sm font-semibold text-amber-600"><?= money($oc['outstanding_due']) ?></span>
                <span class="block text-[11px] text-slate-500 font-medium mt-0.5"><?= (int) $oc['days_overdue'] ?> days overdue</span>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Primary stat cards -->
<div class="mt-3 grid grid-cols-2 gap-3">
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

<!-- Money out -->
<h2 class="mt-6 mb-2 text-sm font-semibold text-slate-500">Sales &amp; money</h2>
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
  <a href="<?= e(url('sales/create')) ?>" class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100 active:scale-95 transition">
    <div class="text-xl">🧾</div><p class="mt-1 text-[11px] text-slate-600">New invoice</p>
  </a>
  <a href="<?= e(url('sales')) ?>" class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100 active:scale-95 transition">
    <div class="text-xl">📃</div><p class="mt-1 text-[11px] text-slate-600">All sales</p>
  </a>
  <a href="<?= e(url('expenses')) ?>" class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100 active:scale-95 transition">
    <div class="text-xl">💸</div><p class="mt-1 text-[11px] text-slate-600">Expenses</p>
  </a>
  <a href="<?= e(url('finance/profit-loss')) ?>" class="rounded-2xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-100 active:scale-95 transition">
    <div class="text-xl">📈</div><p class="mt-1 text-[11px] text-slate-600">Profit &amp; loss</p>
  </a>
</div>
