<?php use App\Core\Auth; ?>

<div class="mb-4 flex items-center justify-between">
  <div>
    <h1 class="text-xl font-bold text-slate-800">Customers</h1>
    <p class="text-xs text-slate-500">Customer directory & credit</p>
  </div>
  <a href="<?= e(url('customers/create')) ?>" class="rounded-full bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-sm active:scale-95 transition">
    ➕ Add
  </a>
</div>

<!-- Search -->
<div class="mb-4">
  <form method="get" action="<?= e(url('customers')) ?>">
    <div class="relative">
      <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">🔍</span>
      <input type="text" name="search" placeholder="Search customer, phone, city..." value="<?= e($filters['search'] ?? '') ?>"
             class="w-full rounded-2xl border-0 bg-white py-3 pl-10 pr-4 text-sm shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-600">
    </div>
  </form>
</div>

<!-- Quick Filters -->
<div class="mb-4 flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
  <?php
    $currentStatus = $filters['status'] ?? '';
    $pills = [
      '' => 'All',
      'credit' => 'Credit',
      'due' => 'Due',
      'good' => 'Good',
      'risk' => 'Risk',
      'inactive' => 'Inactive',
    ];
    foreach ($pills as $val => $label):
      $active = $currentStatus === $val;
  ?>
  <a href="<?= e(url('customers?status=' . $val)) ?>" 
     class="rounded-full px-4 py-1.5 text-xs font-bold whitespace-nowrap transition <?= $active ? 'bg-brand-600 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Summary Stats -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
  <div class="flex justify-between items-end">
    <div>
      <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Total Customers</p>
      <p class="text-lg font-bold text-slate-800"><?= count($customers) ?></p>
    </div>
    <div class="text-right">
      <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Total Outstanding</p>
      <p class="text-lg font-bold text-red-600">Rs. <?= number_format(array_sum(array_column($customers, 'outstanding_due')), 0) ?></p>
    </div>
  </div>
</div>

<!-- Customer Cards -->
<?php if (!empty($customers)): ?>
  <div class="space-y-4">
    <?php foreach ($customers as $c): ?>
      <?php
        // Determine dynamic status
        $statusLabel = '⚫ Inactive';
        $statusClass = 'text-slate-600';
        $daysOverdue = (int) ($c['days_overdue'] ?? 0);
        $outstanding = (float) $c['outstanding_due'];
        
        if ($outstanding > 0) {
            if ($daysOverdue > 30) {
                $statusLabel = '🔴 High Risk';
                $statusClass = 'text-red-600';
            } elseif ($daysOverdue > 0) {
                $statusLabel = '🟡 Due Soon';
                $statusClass = 'text-amber-600';
            } else {
                $statusLabel = '🟢 Good Customer';
                $statusClass = 'text-green-600';
            }
        } else {
            if ($c['last_purchase_date'] && strtotime($c['last_purchase_date']) >= strtotime('-60 days')) {
                $statusLabel = '🟢 Good Customer';
                $statusClass = 'text-green-600';
            }
        }
        
        $creditUsed = $c['credit_limit'] > 0 ? min(100, round(($outstanding / $c['credit_limit']) * 100)) : 0;
      ?>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 overflow-hidden relative">
        <!-- Top row: Name & City -->
        <div class="flex justify-between items-start mb-2">
          <div class="min-w-0 pr-4">
            <h3 class="text-base font-bold text-slate-800 truncate">👤 <?= e($c['name']) ?></h3>
            <p class="text-xs font-medium text-slate-500">📍 <?= e($c['city'] ?? $c['region'] ?? 'Unknown Location') ?></p>
          </div>
          <a href="<?= e(url("customers/{$c['id']}")) ?>" class="shrink-0 h-8 w-8 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center text-lg active:scale-95">
            👁
          </a>
        </div>

        <!-- Middle row: Finances -->
        <div class="flex justify-between items-end mb-3">
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Outstanding</p>
            <p class="text-lg font-bold <?= $outstanding > 0 ? 'text-red-600' : 'text-slate-800' ?>">Rs. <?= number_format($outstanding, 0) ?></p>
          </div>
          <div class="text-right">
            <p class="text-sm font-bold <?= $statusClass ?>"><?= $statusLabel ?></p>
            <?php if ($daysOverdue > 0 && $outstanding > 0): ?>
              <p class="text-[10px] font-bold text-red-500 uppercase tracking-wide mt-0.5">Overdue <?= $daysOverdue ?> Days</p>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($c['credit_limit'] > 0): ?>
        <div class="mb-4">
          <div class="flex justify-between text-[10px] font-bold text-slate-400 uppercase mb-1">
            <span>Credit Used: <?= $creditUsed ?>%</span>
            <span>Limit: Rs. <?= number_format($c['credit_limit'], 0) ?></span>
          </div>
          <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full <?= $creditUsed > 80 ? 'bg-red-500' : 'bg-brand-500' ?>" style="width: <?= $creditUsed ?>%;"></div>
          </div>
        </div>
        <?php endif; ?>

        <div class="flex justify-between text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-4 pb-4 border-b border-slate-100">
          <span>Last Buy: <?= $c['last_purchase_date'] ? date('j M y', strtotime($c['last_purchase_date'])) : 'Never' ?></span>
        </div>

        <!-- Quick Actions Bottom -->
        <div class="flex justify-between items-center gap-2">
          <div class="flex gap-2">
            <a href="tel:<?= e($c['phone'] ?? '') ?>" class="h-10 w-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-600 ring-1 ring-slate-200 active:scale-95 transition text-lg">
              📞
            </a>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $c['phone'] ?? '') ?>" target="_blank" class="h-10 w-10 flex items-center justify-center rounded-xl bg-green-50 text-green-600 ring-1 ring-green-200 active:scale-95 transition text-lg">
              💬
            </a>
          </div>
          <div class="flex gap-2">
            <a href="<?= e(url("customers/{$c['id']}/payment")) ?>" class="h-10 px-4 flex items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 font-bold text-sm ring-1 ring-emerald-200 active:scale-95 transition gap-1">
              💵 Pay
            </a>
            <a href="<?= e(url("customers/{$c['id']}/bill")) ?>" class="h-10 px-4 flex items-center justify-center rounded-xl bg-blue-50 text-blue-700 font-bold text-sm ring-1 ring-blue-200 active:scale-95 transition gap-1">
              🧾 Bill
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 mt-6">
    <div class="text-4xl mb-3">📭</div>
    <p class="text-sm font-bold text-slate-700 mb-1">No customers found</p>
    <p class="text-xs text-slate-500 mb-4">Try adjusting your search or filters.</p>
    <a href="<?= e(url('customers/create')) ?>" class="inline-block rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm active:scale-95 transition">
      Add Customer
    </a>
  </div>
<?php endif; ?>
