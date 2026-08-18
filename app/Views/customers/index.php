<?php use App\Core\Auth; ?>

<div class="page-header justify-between">
  <div>
    <h1 class="page-header-title">Customers</h1>
    <p class="text-xs text-slate-500 mt-1">Directory & credit balances</p>
  </div>
  <a href="<?= e(url('customers/create')) ?>" class="btn btn-primary btn-sm">
    ➕ Add
  </a>
</div>

<!-- Search -->
<div class="search-bar">
  <form method="get" action="<?= e(url('customers')) ?>">
    <span class="search-bar-icon">🔍</span>
    <input type="text" name="search" placeholder="Search customer, phone, city..." value="<?= e($filters['search'] ?? '') ?>"
           class="search-bar-input">
  </form>
</div>

<!-- Quick Filters -->
<div class="mb-4 flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
  <?php
    $currentStatus = $filters['status'] ?? '';
    $pills = [
      '' => 'All',
      'credit' => 'Credit',
      'due' => 'Overdue',
      'risk' => 'High Risk',
      'good' => 'Good',
      'inactive' => 'Inactive',
      'deleted' => 'Deleted',
    ];
    foreach ($pills as $val => $label):
      $active = $currentStatus === $val;
  ?>
  <a href="<?= e(url('customers?status=' . $val)) ?>" 
     class="filter-chip <?= $active ? 'filter-chip-active' : '' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Summary Stats -->
<div class="card card-compact mb-5 flex justify-between items-end border-brand-100 bg-brand-50 shadow-none">
  <div>
    <p class="stat-card-label text-brand-700">Total Customers</p>
    <p class="text-xl font-bold text-brand-900"><?= count($customers) ?></p>
  </div>
  <div class="text-right">
    <p class="stat-card-label text-amber-700">Total Outstanding</p>
    <p class="text-xl font-bold text-amber-600">Rs. <?= number_format(array_sum(array_column($customers, 'outstanding_due')), 0) ?></p>
  </div>
</div>

<!-- Customer Cards -->
<?php if (!empty($customers)): ?>
  <div class="space-y-4">
    <?php foreach ($customers as $c): ?>
      <?php
        $daysOverdue = (int) ($c['days_overdue'] ?? 0);
        $outstanding = (float) $c['outstanding_due'];
        
        $statusClass = 'status-neutral';
        $statusLabel = 'Inactive';
        
        if (!empty($c['deleted_at'])) {
            $statusLabel = 'Deleted';
            $statusClass = 'status-neutral opacity-60';
        } elseif ($outstanding > 0) {
            if ($daysOverdue > 30) {
                $statusLabel = 'High Risk';
                $statusClass = 'status-danger';
            } elseif ($daysOverdue > 0) {
                $statusLabel = 'Due Soon';
                $statusClass = 'status-warning';
            } else {
                $statusLabel = 'Good Standing';
                $statusClass = 'status-good';
            }
        } else {
            if ($c['last_purchase_date'] && strtotime($c['last_purchase_date']) >= strtotime('-60 days')) {
                $statusLabel = 'Active';
                $statusClass = 'status-good';
            }
        }
        
        $creditUsed = $c['credit_limit'] > 0 ? min(100, round(($outstanding / $c['credit_limit']) * 100)) : 0;
      ?>
      <div class="card overflow-hidden relative <?= !empty($c['deleted_at']) ? 'opacity-60' : '' ?>">
        
        <!-- Top row: Name & City -->
        <div class="flex justify-between items-start mb-3">
          <div class="min-w-0 pr-4">
            <h3 class="text-base font-bold text-slate-800 truncate"><?= e($c['name']) ?></h3>
            <p class="text-xs font-medium text-slate-500 mt-0.5 truncate">
              📍 <?= e($c['city'] ?? $c['region'] ?? 'Unknown Location') ?>
            </p>
          </div>
          <span class="status-badge <?= $statusClass ?> shrink-0"><?= $statusLabel ?></span>
        </div>

        <!-- Middle row: Finances -->
        <div class="flex justify-between items-end mb-4">
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-0.5">Outstanding</p>
            <p class="text-lg font-bold <?= $outstanding > 0 ? 'text-amber-600' : 'text-slate-800' ?>">
              Rs. <?= number_format($outstanding, 0) ?>
            </p>
          </div>
          <div class="text-right">
            <?php if ($daysOverdue > 0 && $outstanding > 0): ?>
              <p class="text-[10px] font-bold text-red-500 uppercase tracking-wide mb-1">Overdue <?= $daysOverdue ?> Days</p>
            <?php endif; ?>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">
              Last Buy: <?= $c['last_purchase_date'] ? date('j M y', strtotime($c['last_purchase_date'])) : 'Never' ?>
            </p>
          </div>
        </div>

        <!-- Credit Bar -->
        <?php if ($c['credit_limit'] > 0): ?>
        <div class="mb-4">
          <div class="flex justify-between text-[10px] font-bold text-slate-400 uppercase mb-1">
            <span>Credit Used: <?= $creditUsed ?>%</span>
            <span>Limit: Rs. <?= number_format($c['credit_limit'], 0) ?></span>
          </div>
          <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full <?= $creditUsed > 80 ? 'bg-red-500' : 'bg-brand-500' ?> transition-all duration-500" style="width: <?= $creditUsed ?>%;"></div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions Bottom -->
        <div class="flex justify-between items-center gap-2 pt-4 border-t border-slate-100">
          <div class="flex gap-2">
            <a href="tel:<?= e($c['phone'] ?? '') ?>" class="btn btn-outline btn-icon">📞</a>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $c['phone'] ?? '') ?>" target="_blank" class="btn btn-outline btn-icon !border-green-200 !text-green-600 !bg-green-50">💬</a>
          </div>
          <div class="flex gap-2 flex-1 justify-end">
            <a href="<?= e(url("customers/{$c['id']}")) ?>" class="btn btn-outline py-2 px-3 text-sm">👁️ View</a>
            <a href="<?= e(url("customers/{$c['id']}/payment")) ?>" class="btn btn-success py-2 px-3 text-sm flex-1 max-w-[100px]">💵 Pay</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="empty-state mt-6">
    <div class="empty-state-icon">
      <?php if ($filters['status'] === 'due'): ?>
        🎉
      <?php else: ?>
        📭
      <?php endif; ?>
    </div>
    
    <?php if ($filters['status'] === 'due'): ?>
      <h3 class="empty-state-title">No Overdue Customers!</h3>
      <p class="empty-state-text">All customers are within their expected payment period.</p>
    <?php else: ?>
      <h3 class="empty-state-title">No Customers Found</h3>
      <p class="empty-state-text">Try adjusting your search or filters.</p>
    <?php endif; ?>

    <a href="<?= e(url('customers/create')) ?>" class="btn btn-primary">➕ Add Customer</a>
  </div>
<?php endif; ?>
