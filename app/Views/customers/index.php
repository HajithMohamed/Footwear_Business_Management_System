<div class="mb-4">
  <h1 class="text-2xl font-bold text-slate-800">Customers</h1>
  <p class="text-sm text-slate-500">CRM Dashboard — Find, Call, Pay, Invoice in seconds</p>
</div>

<!-- Search -->
<div class="mb-4 flex gap-2">
  <form method="get" action="<?= e(url('customers')) ?>" class="flex-1">
    <input type="text" name="search" placeholder="🔍 Search by name, phone, city..." value="<?= e($filters['search']) ?>"
           class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600">
  </form>
  <a href="<?= e(url('customers/create')) ?>" class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 whitespace-nowrap">
    ➕ Add
  </a>
</div>

<!-- Quick Filters -->
<div class="mb-4 flex gap-2 overflow-x-auto pb-2">
  <a href="<?= e(url('customers')) ?>" class="rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap <?= empty($filters['type']) && empty($filters['region']) ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">All</a>
  <a href="<?= e(url('customers?type=wholesale')) ?>" class="rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap <?= $filters['type'] === 'wholesale' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">Wholesale</a>
  <a href="<?= e(url('customers?type=retail')) ?>" class="rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap <?= $filters['type'] === 'retail' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">Retail</a>
</div>

<!-- Summary Stats -->
<div class="grid gap-2 sm:grid-cols-4 mb-6">
  <div class="rounded-lg bg-slate-50 p-3 ring-1 ring-slate-100">
    <p class="text-xs font-medium text-slate-500 uppercase">Total Customers</p>
    <p class="mt-1 text-2xl font-bold text-slate-800"><?= count($customers) ?></p>
  </div>
  <div class="rounded-lg bg-blue-50 p-3 ring-1 ring-blue-100">
    <p class="text-xs font-medium text-blue-600 uppercase">Credit</p>
    <p class="mt-1 text-2xl font-bold text-blue-700"><?= count(array_filter($customers, fn($c) => $c['customer_type'] === 'wholesale')) ?></p>
  </div>
  <div class="rounded-lg bg-red-50 p-3 ring-1 ring-red-100">
    <p class="text-xs font-medium text-red-600 uppercase">Outstanding</p>
    <p class="mt-1 text-lg font-bold text-red-700">Rs. <?= number_format(array_sum(array_column($customers, 'outstanding_due')), 0) ?></p>
  </div>
  <a href="<?= e(url('cheques')) ?>" class="rounded-lg bg-amber-50 p-3 ring-1 ring-amber-100">
    <p class="text-xs font-medium text-amber-600 uppercase">Pending Cheques</p>
    <p class="mt-1 text-2xl font-bold text-amber-700"><?= (int) ($chequeSummary['pending_count'] ?? 0) ?></p>
    <p class="text-[11px] text-amber-600"><?= money($chequeSummary['pending_value'] ?? 0) ?></p>
  </a>
</div>

<!-- Customer Cards -->
<?php if (!empty($customers)): ?>
  <div class="space-y-3">
    <?php foreach ($customers as $c): ?>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 hover:shadow-md transition">
        <!-- Header -->
        <div class="flex items-start justify-between mb-3">
          <div class="flex-1 min-w-0">
            <h3 class="text-sm font-bold text-slate-800 truncate"><?= e($c['name']) ?></h3>
            <p class="text-xs text-slate-500">
              <?php
                $class = strtolower($c['classification'] ?? 'regular');
                $badge = match($class) {
                  'vip' => '⭐ VIP',
                  'at_risk' => '⚠️ At Risk',
                  'dormant' => '😴 Inactive',
                  'prospect' => '🆕 New',
                  default => '✓ Regular'
                };
                echo $badge;
              ?>
            </p>
          </div>
          <span class="inline-block px-2 py-1 rounded text-xs font-semibold <?= $c['customer_type'] === 'wholesale' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' ?>">
            <?= ucfirst($c['customer_type']) ?>
          </span>
        </div>

        <!-- Key Info Row -->
        <div class="grid grid-cols-3 gap-2 mb-3 py-3 border-y border-slate-100">
          <div>
            <p class="text-xs text-slate-500">Outstanding</p>
            <p class="text-sm font-bold <?= $c['outstanding_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
              Rs. <?= number_format($c['outstanding_due'], 0) ?>
            </p>
          </div>
          <div>
            <p class="text-xs text-slate-500">Credit Limit</p>
            <p class="text-sm font-bold text-slate-700">Rs. <?= number_format($c['credit_limit'], 0) ?></p>
          </div>
          <div>
            <p class="text-xs text-slate-500">Available</p>
            <p class="text-sm font-bold <?= ($c['credit_limit'] - $c['outstanding_due']) >= 0 ? 'text-green-600' : 'text-red-600' ?>">
              Rs. <?= number_format(max(0, $c['credit_limit'] - $c['outstanding_due']), 0) ?>
            </p>
          </div>
        </div>

        <!-- Contact & Quick Info -->
        <div class="text-xs text-slate-600 space-y-1 mb-3">
          <p>📍 <?= e($c['region'] ?? $c['city'] ?? '—') ?></p>
          <p>📞 <?= e($c['phone'] ?? '—') ?></p>
        </div>

        <!-- Quick Actions -->
        <div class="flex gap-2 flex-wrap">
          <a href="tel:<?= e($c['phone'] ?? '') ?>" class="inline-flex items-center gap-1 rounded px-2.5 py-1.5 text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200">
            📞 Call
          </a>
          <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $c['phone'] ?? '') ?>" target="_blank" class="inline-flex items-center gap-1 rounded px-2.5 py-1.5 text-xs font-medium bg-green-100 text-green-700 hover:bg-green-200">
            💬 WhatsApp
          </a>
          <a href="<?= e(url("customers/{$c['id']}/payment")) ?>" class="inline-flex items-center gap-1 rounded px-2.5 py-1.5 text-xs font-medium bg-blue-100 text-blue-700 hover:bg-blue-200">
            💵 Payment
          </a>
          <a href="<?= e(url("customers/{$c['id']}")) ?>" class="inline-flex items-center gap-1 rounded px-2.5 py-1.5 text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 ml-auto">
            👁 View
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <p class="text-slate-500 mb-4">No customers found.</p>
    <a href="<?= e(url('customers/create')) ?>" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
      ➕ Add First Customer
    </a>
  </div>
<?php endif; ?>
