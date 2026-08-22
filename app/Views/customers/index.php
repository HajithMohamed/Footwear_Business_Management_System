<?php use App\Core\Auth; ?>

<div class="page-header justify-between">
  <div>
    <h1 class="page-header-title">Customers</h1>
    <p class="text-xs text-slate-500 mt-1">Directory & credit balances</p>
  </div>
  <a href="<?= e(url('customers/create')) ?>" class="btn btn-primary btn-sm">
    <?= ui_icon('plus', 'h-4 w-4') ?> Add
  </a>
</div>

<!-- Search -->
<div class="search-bar">
  <form method="get" action="<?= e(url('customers')) ?>">
    <?php if (!empty($filters['status'])): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <span class="search-bar-icon"><?= ui_icon('search', 'h-4 w-4') ?></span>
    <input type="text" name="search" placeholder="Search customer, phone, city..." value="<?= e($filters['search'] ?? '') ?>"
           class="search-bar-input !pr-24">
    <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-xl bg-brand-600 px-3 py-2 text-xs font-bold text-white">Search</button>
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
      'inactive' => 'Dormant',
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
        $waPhone = whatsapp_phone($c['phone'] ?? null);
        
        $statusClass = 'status-neutral';
        $statusLabel = 'New';
        
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
        } elseif (empty($c['last_purchase_date'])) {
            $statusLabel = 'New';
            $statusClass = 'status-neutral';
        } elseif (strtotime($c['last_purchase_date']) >= strtotime('-60 days')) {
            $statusLabel = 'Active';
            $statusClass = 'status-good';
        } else {
            $statusLabel = 'Dormant';
            $statusClass = 'status-neutral';
        }
        
        $creditUsed = $c['credit_limit'] > 0 ? min(100, round(($outstanding / $c['credit_limit']) * 100)) : 0;
      ?>
      <div class="card overflow-hidden relative <?= !empty($c['deleted_at']) ? 'opacity-60' : '' ?> cursor-pointer hover:border-brand-300 transition-colors"
           onclick="window.location='<?= e(url("customers/{$c['id']}")) ?>'">
        
        <!-- Top row: Name & City -->
        <div class="flex justify-between items-start mb-3">
          <div class="min-w-0 pr-4">
            <h3 class="text-base font-bold text-slate-800 truncate"><?= e($c['name']) ?></h3>
            <p class="text-xs font-medium text-slate-500 mt-0.5 truncate">
              <span class="inline-flex items-center gap-1"><?= ui_icon('location', 'h-3.5 w-3.5') ?> <?= e($c['city'] ?? $c['region'] ?? 'Unknown Location') ?></span>
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
        <div class="space-y-2 pt-4 border-t border-slate-100" onclick="event.stopPropagation()">
          <div class="grid grid-cols-2 gap-2">
            <a href="<?= e(url("customers/{$c['id']}/bill")) ?>" class="btn btn-outline justify-center py-2.5 text-sm"><?= ui_icon('bill', 'h-4 w-4') ?> Add Bill</a>
            <a href="<?= e(url("customers/{$c['id']}/payment")) ?>" class="btn btn-success justify-center py-2.5 text-sm"><?= ui_icon('wallet', 'h-4 w-4') ?> Payment</a>
          </div>
          <div class="grid grid-cols-<?= Auth::isAdmin() ? '4' : '3' ?> gap-2">
            <?php if (!empty($c['phone'])): ?>
              <a href="tel:<?= e($c['phone']) ?>" class="btn btn-outline min-w-0 justify-center px-2 py-2 text-[11px]" aria-label="Call <?= e($c['name']) ?>"><?= ui_icon('phone', 'h-4 w-4') ?> <span>Call</span></a>
            <?php endif; ?>
            <?php if ($waPhone): ?>
              <a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" rel="noopener" class="btn btn-outline min-w-0 justify-center px-2 py-2 text-[11px] !border-green-200 !bg-green-50 !text-green-700" aria-label="WhatsApp <?= e($c['name']) ?>"><?= ui_icon('users', 'h-4 w-4') ?> <span>WhatsApp</span></a>
            <?php endif; ?>
            <a href="<?= e(url("customers/{$c['id']}/edit")) ?>" class="btn btn-outline min-w-0 justify-center px-2 py-2 text-[11px]"><?= ui_icon('pencil', 'h-4 w-4') ?> <span>Edit</span></a>
            <?php if (Auth::isAdmin()): ?>
              <form method="post" action="<?= e(url("customers/{$c['id']}/delete")) ?>" class="min-w-0" x-data @submit.prevent="$dispatch('confirm-action', {title:'Delete Customer', message:'Hide this customer from the active directory? Their ledger history will be preserved.', confirmText:'Delete', type:'danger', onConfirm:()=>$el.submit()})">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline h-full w-full min-w-0 justify-center px-2 py-2 text-[11px] !border-red-200 !bg-red-50 !text-red-600"><?= ui_icon('trash', 'h-4 w-4') ?> <span>Delete</span></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="empty-state mt-6">
    <div class="empty-state-icon">
      <?php if ($filters['status'] === 'due'): ?>
        <?= ui_icon('check', 'h-8 w-8') ?>
      <?php else: ?>
        <?= ui_icon('users', 'h-8 w-8') ?>
      <?php endif; ?>
    </div>
    
    <?php if ($filters['status'] === 'due'): ?>
      <h3 class="empty-state-title">No Overdue Customers!</h3>
      <p class="empty-state-text">All customers are within their expected payment period.</p>
    <?php else: ?>
      <h3 class="empty-state-title">No Customers Found</h3>
      <p class="empty-state-text">Try adjusting your search or filters.</p>
    <?php endif; ?>

    <a href="<?= e(url('customers/create')) ?>" class="btn btn-primary"><?= ui_icon('plus', 'h-4 w-4') ?> Add Customer</a>
  </div>
<?php endif; ?>
