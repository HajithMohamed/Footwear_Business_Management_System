<?php use App\Core\Auth; ?>

<div class="mb-6 flex justify-between items-end">
  <div>
    <h1 class="text-xl font-bold text-slate-800">Overview</h1>
    <p class="text-sm font-medium text-slate-500"><?= e(date('l, j M Y')) ?></p>
  </div>
</div>

<!-- Top Core Metrics: 2x2 Grid -->
<div class="grid grid-cols-2 gap-3 mb-6">
  
  <!-- Sales Today -->
  <a href="<?= e(url('sales')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 active:scale-95 transition flex flex-col justify-between">
    <div class="flex justify-between items-start mb-2">
      <div class="h-8 w-8 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">📈</div>
    </div>
    <div>
      <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Sales Today</p>
      <p class="text-lg font-bold text-slate-800 leading-tight"><?= money($periods['today'] ?? 0) ?></p>
    </div>
  </a>

  <!-- Cash Received -->
  <a href="<?= e(url('finance')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 active:scale-95 transition flex flex-col justify-between">
    <div class="flex justify-between items-start mb-2">
      <div class="h-8 w-8 rounded-full bg-green-50 text-green-600 flex items-center justify-center">💵</div>
    </div>
    <div>
      <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Cash Received</p>
      <p class="text-lg font-bold text-slate-800 leading-tight"><?= money($cashToday['total'] ?? 0) ?></p>
    </div>
  </a>

  <!-- Outstanding Credit -->
  <a href="<?= e(url('reports/receivables')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 active:scale-95 transition flex flex-col justify-between">
    <div class="flex justify-between items-start mb-2">
      <div class="h-8 w-8 rounded-full <?= $receivables['outstanding'] > 0 ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600' ?> flex items-center justify-center">🤝</div>
    </div>
    <div>
      <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Owed to you</p>
      <p class="text-lg font-bold <?= $receivables['outstanding'] > 0 ? 'text-amber-600' : 'text-slate-800' ?> leading-tight">
        <?= money($receivables['outstanding']) ?>
      </p>
    </div>
  </a>

  <!-- Net Profit (Month) -->
  <?php $net = (float) $money['net_profit']; $isProfit = $net >= 0; ?>
  <a href="<?= e(url('finance/profit-loss')) ?>" class="rounded-2xl <?= $isProfit ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' ?> p-4 shadow-sm active:scale-95 transition flex flex-col justify-between">
    <div class="flex justify-between items-start mb-2">
      <div class="h-8 w-8 rounded-full bg-white/20 flex items-center justify-center">💎</div>
    </div>
    <div>
      <p class="text-[11px] font-semibold text-white/80 uppercase tracking-wide">Net Profit (<?= e(date('M')) ?>)</p>
      <p class="text-lg font-bold leading-tight"><?= $isProfit ? '' : '− ' ?><?= money(abs($net)) ?></p>
    </div>
  </a>

</div>

<!-- Needs Attention Alerts -->
<?php if (!empty($overdueSales) || !empty($chequesDue)): ?>
  <div class="mb-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="border-b border-slate-100 bg-red-50 px-4 py-3">
      <h2 class="text-sm font-bold text-red-800 flex items-center gap-2">
        <span class="text-lg">🔴</span> Action Required
      </h2>
    </div>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($chequesDue as $c): ?>
        <li>
          <a href="<?= e(url("cheques/{$c['id']}")) ?>" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 transition">
            <div class="min-w-0">
              <p class="truncate text-sm font-bold text-slate-800">🏦 Cheque Due · <?= e($c['customer_name']) ?></p>
              <p class="text-xs font-medium text-slate-500">
                <?= (int) $c['days_until'] < 0
                      ? abs((int) $c['days_until']) . ' day(s) late'
                      : ((int) $c['days_until'] === 0 ? 'due today' : 'due in ' . (int) $c['days_until'] . ' day(s)') ?>
              </p>
            </div>
            <span class="shrink-0 text-sm font-bold text-slate-800"><?= money($c['amount']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
      <?php foreach ($overdueSales as $o): ?>
        <li>
          <a href="<?= e(url("sales/{$o['id']}")) ?>" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 transition">
            <div class="min-w-0">
              <p class="truncate text-sm font-bold text-slate-800">⏰ Overdue Invoice · <?= e($o['customer_name'] ?: 'Walk-in') ?></p>
              <p class="text-xs font-medium text-slate-500">
                <?= (int) $o['days_overdue'] ?> day(s) late
              </p>
            </div>
            <span class="shrink-0 text-sm font-bold text-red-600"><?= money($o['unpaid']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if (!empty($overdueCustomers)): ?>
  <div class="mb-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="border-b border-slate-100 bg-amber-50 px-4 py-3 flex justify-between items-center">
      <h2 class="text-sm font-bold text-amber-800 flex items-center gap-2">
        <span class="text-lg">🟡</span> Overdue Accounts
      </h2>
      <span class="rounded-full bg-amber-200 text-amber-800 text-[10px] uppercase tracking-wide px-2 py-0.5 font-bold">> 30 days</span>
    </div>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($overdueCustomers as $oc): ?>
        <li>
          <a href="<?= e(url("customers/{$oc['id']}")) ?>" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 transition">
            <div class="min-w-0">
              <p class="truncate text-sm font-bold text-slate-800">👤 <?= e($oc['name']) ?></p>
              <p class="text-xs font-medium text-slate-500">
                Last paid <?= e(date('j M Y', strtotime($oc['oldest_unpaid_date']))) ?>
              </p>
            </div>
            <div class="shrink-0 text-right">
                <span class="block text-sm font-bold text-amber-600"><?= money($oc['outstanding_due']) ?></span>
                <span class="block text-[10px] text-slate-400 font-semibold uppercase mt-0.5"><?= (int) $oc['days_overdue'] ?> days</span>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Imports & Shipments Summary -->
<h2 class="mt-8 mb-3 text-sm font-bold text-slate-500 uppercase tracking-wider">Shipments Summary</h2>
<div class="grid grid-cols-3 gap-3 mb-4">
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 text-center">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">In transit</p>
    <p class="mt-1 text-lg font-bold text-brand-600"><?= number_format((float) ($importStats['weight_in_transit'] ?? 0), 1) ?><span class="text-[10px] text-brand-400">kg</span></p>
  </div>
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 text-center">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Cleared</p>
    <p class="mt-1 text-lg font-bold text-slate-800"><?= number_format((float) ($importStats['weight_cleared'] ?? 0), 1) ?><span class="text-[10px] text-slate-400">kg</span></p>
  </div>
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 text-center">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Received</p>
    <p class="mt-1 text-lg font-bold text-slate-800"><?= number_format((float) ($importStats['weight_received'] ?? 0), 1) ?><span class="text-[10px] text-slate-400">kg</span></p>
  </div>
</div>

<?php if ($pendingParcels || $pendingQuantity): ?>
  <div class="mb-4 grid grid-cols-2 gap-3">
    <a href="<?= e(url('arrivals')) ?>" class="rounded-2xl bg-amber-50 p-3 shadow-sm ring-1 ring-amber-200 text-center active:scale-95 transition">
      <p class="text-[10px] font-bold text-amber-700 uppercase tracking-wide">Parcels to check</p>
      <p class="mt-1 text-xl font-bold text-amber-700"><?= count($pendingParcels) ?></p>
    </a>
    <a href="<?= e(url('arrivals')) ?>" class="rounded-2xl bg-orange-50 p-3 shadow-sm ring-1 ring-orange-200 text-center active:scale-95 transition">
      <p class="text-[10px] font-bold text-orange-700 uppercase tracking-wide">Count verify</p>
      <p class="mt-1 text-xl font-bold text-orange-700"><?= count($pendingQuantity) ?></p>
    </a>
  </div>
<?php endif; ?>

<?php if ($recentlyArrived): ?>
  <div class="mb-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="px-4 py-3 border-b border-slate-100">
      <h3 class="text-sm font-bold text-slate-700">Recently arrived</h3>
    </div>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($recentlyArrived as $p): ?>
        <li>
          <a href="<?= e(url('purchases/' . $p['id'])) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition">
            <div class="min-w-0">
              <p class="truncate text-sm font-bold text-slate-800"><?= e($p['purchase_number']) ?></p>
              <p class="text-xs font-medium text-slate-500"><?= e($p['supplier_name']) ?></p>
            </div>
            <span class="ml-3 shrink-0 rounded-lg px-2 py-1 text-[10px] font-bold uppercase tracking-wider <?= $p['arrival_status'] === 'confirmed' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' ?>">
              <?= e(str_replace('_', ' ', $p['arrival_status'])) ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
