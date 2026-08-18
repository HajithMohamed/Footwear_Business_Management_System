<?php 
use App\Core\Auth;
$hour = date('H');
$greeting = 'Good Evening';
if ($hour < 12) $greeting = 'Good Morning';
elseif ($hour < 17) $greeting = 'Good Afternoon';
?>

<div class="mb-6">
  <h1 class="greeting-text"><?= $greeting ?>,</h1>
  <h2 class="text-xl font-bold text-slate-800"><?= e(Auth::user()['name'] ?? 'User') ?></h2>
  <p class="greeting-date"><?= e(date('l, j F')) ?></p>
</div>

<!-- Top Core Metrics: 2x2 Grid -->
<div class="grid grid-cols-2 gap-3 mb-6">
  
  <!-- Sales Today -->
  <a href="<?= e(url('sales')) ?>" class="stat-card flex flex-col justify-between">
    <div class="flex justify-between items-start mb-3">
      <div class="h-10 w-10 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center text-xl">📈</div>
    </div>
    <div>
      <p class="stat-card-label">Sales Today</p>
      <p class="stat-card-value text-brand-700"><?= money($periods['today'] ?? 0) ?></p>
    </div>
  </a>

  <!-- Cash Received -->
  <a href="<?= e(url('finance')) ?>" class="stat-card flex flex-col justify-between">
    <div class="flex justify-between items-start mb-3">
      <div class="h-10 w-10 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-xl">💵</div>
    </div>
    <div>
      <p class="stat-card-label">Collected Today</p>
      <p class="stat-card-value text-green-700"><?= money($cashToday['total'] ?? 0) ?></p>
    </div>
  </a>

  <!-- Outstanding Credit -->
  <a href="<?= e(url('reports/receivables')) ?>" class="stat-card flex flex-col justify-between">
    <div class="flex justify-between items-start mb-3">
      <div class="h-10 w-10 rounded-full <?= $receivables['outstanding'] > 0 ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600' ?> flex items-center justify-center text-xl">🤝</div>
    </div>
    <div>
      <p class="stat-card-label">Outstanding Credit</p>
      <p class="stat-card-value <?= $receivables['outstanding'] > 0 ? 'text-amber-600' : 'text-slate-800' ?>">
        <?= money($receivables['outstanding']) ?>
      </p>
    </div>
  </a>

  <!-- Approx. Profit (Month) -->
  <?php $net = (float) ($money['net_profit'] ?? 0); $isProfit = $net >= 0; ?>
  <a href="<?= e(url('finance/profit-loss')) ?>" class="stat-card flex flex-col justify-between <?= $isProfit ? 'bg-emerald-600 text-white border-transparent' : 'bg-red-600 text-white border-transparent' ?>">
    <div class="flex justify-between items-start mb-3">
      <div class="h-10 w-10 rounded-full bg-white/20 flex items-center justify-center text-xl">💎</div>
    </div>
    <div>
      <p class="text-[10px] font-bold text-white/80 uppercase tracking-wide mb-1">Approx. Profit (<?= e(date('M')) ?>)</p>
      <p class="stat-card-value text-white"><?= $isProfit ? '' : '− ' ?><?= money(abs($net)) ?></p>
    </div>
  </a>

</div>

<!-- Quick Action Buttons -->
<div class="grid grid-cols-2 gap-3 mb-8">
  <a href="<?= e(url('sales/create')) ?>" class="quick-action">
    <div class="quick-action-icon bg-brand-50 text-brand-600">🧾</div>
    <span>New Sale</span>
  </a>
  <a href="<?= e(url('customers')) ?>" class="quick-action">
    <div class="quick-action-icon bg-green-50 text-green-600">💵</div>
    <span>Payment</span>
  </a>
  <a href="<?= e(url('purchases/import')) ?>" class="quick-action">
    <div class="quick-action-icon bg-slate-100 text-slate-600">🚢</div>
    <span>Purchase</span>
  </a>
  <a href="<?= e(url('customers/create')) ?>" class="quick-action">
    <div class="quick-action-icon bg-slate-100 text-slate-600">👤</div>
    <span>Customer</span>
  </a>
</div>

<!-- Alerts Section -->
<?php 
$hasAlerts = !empty($overdueSales) || !empty($chequesDue) || !empty($overdueCustomers) || !empty($pendingParcels) || !empty($pendingQuantity);
if ($hasAlerts): 
?>
  <div class="mb-8">
    <h2 class="section-title">Needs Attention</h2>
    <div class="flex flex-col gap-2">
      
      <?php if (!empty($chequesDue)): ?>
        <a href="<?= e(url('cheques')) ?>" class="alert-card alert-card-danger">
          <div class="flex items-center gap-3">
            <span class="text-xl">🏦</span>
            <div>
              <p class="font-bold text-red-900 text-sm"><?= count($chequesDue) ?> Cheque(s) Due</p>
              <p class="text-xs text-red-700">Needs deposit or follow-up</p>
            </div>
          </div>
          <span class="text-red-700 font-bold text-sm">View →</span>
        </a>
      <?php endif; ?>

      <?php if (!empty($overdueCustomers) || !empty($overdueSales)): ?>
        <a href="<?= e(url('reports/receivables')) ?>" class="alert-card alert-card-warning">
          <div class="flex items-center gap-3">
            <span class="text-xl">⏰</span>
            <div>
              <p class="font-bold text-amber-900 text-sm"><?= count($overdueCustomers) + count($overdueSales) ?> Overdue Account(s)</p>
              <p class="text-xs text-amber-700">Past expected payment period</p>
            </div>
          </div>
          <span class="text-amber-700 font-bold text-sm">View →</span>
        </a>
      <?php endif; ?>

      <?php if (!empty($pendingParcels) || !empty($pendingQuantity)): ?>
        <a href="<?= e(url('arrivals')) ?>" class="alert-card alert-card-info">
          <div class="flex items-center gap-3">
            <span class="text-xl">📦</span>
            <div>
              <p class="font-bold text-brand-900 text-sm">Arrivals Pending</p>
              <p class="text-xs text-brand-700">Verify parcels and product counts</p>
            </div>
          </div>
          <span class="text-brand-700 font-bold text-sm">View →</span>
        </a>
      <?php endif; ?>

    </div>
  </div>
<?php endif; ?>


<!-- Continue Working -->
<?php if (!empty($inProgressPurchases)): ?>
  <div class="mb-6">
    <div class="flex justify-between items-center mb-3 mt-8">
      <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Continue Working</h2>
      <a href="<?= e(url('purchases?status=in_progress')) ?>" class="text-[10px] font-bold text-brand-600 uppercase tracking-wide">View All</a>
    </div>
    
    <div class="card card-compact overflow-hidden p-0">
      <ul class="divide-y divide-slate-100">
        <?php foreach ($inProgressPurchases as $p): ?>
          <li>
            <a href="<?= e(url("purchases/{$p['id']}")) ?>" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 transition">
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-slate-800">
                  <?= e($p['purchase_number']) ?>
                  <span class="ml-2 status-badge status-neutral">
                    <?= e(\App\Models\Purchase::statusLabel($p['status'])) ?>
                  </span>
                </p>
                <p class="text-xs font-medium text-slate-500 mt-0.5 truncate"><?= e($p['supplier_name']) ?></p>
              </div>
              <div class="shrink-0 text-slate-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </div>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<!-- Recently Arrived -->
<?php if ($recentlyArrived): ?>
  <div class="mb-6">
    <h2 class="section-title">Recently Arrived</h2>
    <div class="card card-compact overflow-hidden p-0">
      <ul class="divide-y divide-slate-100">
        <?php foreach ($recentlyArrived as $p): ?>
          <li>
            <a href="<?= e(url('purchases/' . $p['id'])) ?>" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition">
              <div class="min-w-0">
                <p class="truncate text-sm font-bold text-slate-800"><?= e($p['purchase_number']) ?></p>
                <p class="text-xs font-medium text-slate-500 mt-0.5"><?= e($p['supplier_name']) ?></p>
              </div>
              <span class="status-badge <?= $p['arrival_status'] === 'confirmed' ? 'status-good' : 'status-warning' ?>">
                <?= e(str_replace('_', ' ', $p['arrival_status'])) ?>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>
