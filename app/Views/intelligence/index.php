<div class="mb-4">
  <h1 class="text-lg font-bold text-slate-800">Customer Intelligence</h1>
  <p class="text-sm text-slate-500">VIP classification, lifetime value, purchase patterns, and overdue tracking</p>
</div>

<!-- Stats overview -->
<?php if ($stats): ?>
  <div class="grid gap-3 sm:grid-cols-4 mb-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <p class="text-xs font-semibold text-slate-400 uppercase">Total Customers</p>
      <p class="mt-1 text-2xl font-bold text-slate-800"><?= (int)($stats['total_customers'] ?? 0) ?></p>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <p class="text-xs font-semibold text-slate-400 uppercase">Total Lifetime Value</p>
      <p class="mt-1 text-2xl font-bold text-green-600">Rs. <?= number_format($stats['total_lifetime_value'] ?? 0, 0) ?></p>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <p class="text-xs font-semibold text-slate-400 uppercase">Average LTV</p>
      <p class="mt-1 text-2xl font-bold text-blue-600">Rs. <?= number_format($stats['avg_lifetime_value'] ?? 0, 0) ?></p>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <p class="text-xs font-semibold text-slate-400 uppercase">Total Overdue</p>
      <p class="mt-1 text-2xl font-bold text-red-600">Rs. <?= number_format($stats['total_overdue'] ?? 0, 0) ?></p>
    </div>
  </div>
<?php endif; ?>

<!-- Navigation -->
<div class="mb-4 flex gap-2 flex-wrap">
  <a href="<?= e(url('intelligence/vip')) ?>" class="rounded-lg bg-yellow-100 px-4 py-2 text-sm font-medium text-yellow-700 hover:bg-yellow-200">
    ⭐ VIP Customers (<?= count($vips) ?>)
  </a>
  <a href="<?= e(url('intelligence/at_risk')) ?>" class="rounded-lg bg-red-100 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-200">
    ⚠️ At-Risk (<?= count($at_risk) ?>)
  </a>
  <a href="<?= e(url('intelligence/dormant')) ?>" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
    😴 Dormant (<?= count($dormant) ?>)
  </a>
  <a href="<?= e(url('intelligence/overdue')) ?>" class="rounded-lg bg-orange-100 px-4 py-2 text-sm font-medium text-orange-700 hover:bg-orange-200">
    📅 Overdue (<?= count($overdue) ?>)
  </a>
  <a href="<?= e(url('intelligence/top')) ?>" class="rounded-lg bg-purple-100 px-4 py-2 text-sm font-medium text-purple-700 hover:bg-purple-200">
    🏆 Top Customers
  </a>
</div>

<!-- VIP Customers -->
<?php if (!empty($vips)): ?>
  <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden mb-4">
    <div class="border-b border-slate-100 px-6 py-4 bg-yellow-50">
      <h2 class="text-sm font-semibold text-yellow-900">⭐ VIP Customers</h2>
    </div>
    <table class="w-full text-sm">
      <tbody class="divide-y divide-slate-50">
        <?php foreach (array_slice($vips, 0, 5) as $c): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-6 py-3"><a href="<?= e(url("customers/{$c['customer_id']}")) ?>" class="text-brand-600 hover:underline font-medium"><?= e($c['name']) ?></a></td>
            <td class="px-6 py-3 text-right">
              <span class="text-slate-500 text-xs">LTV: </span>
              <span class="font-bold">Rs. <?= number_format($c['lifetime_value'], 0) ?></span>
            </td>
            <td class="px-6 py-3 text-right text-slate-500 text-xs"><?= (int)$c['total_purchases'] ?> purchases</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- At-Risk Customers -->
<?php if (!empty($at_risk)): ?>
  <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden mb-4">
    <div class="border-b border-slate-100 px-6 py-4 bg-red-50">
      <h2 class="text-sm font-semibold text-red-900">⚠️ At-Risk Customers</h2>
    </div>
    <table class="w-full text-sm">
      <tbody class="divide-y divide-slate-50">
        <?php foreach (array_slice($at_risk, 0, 5) as $c): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-6 py-3"><a href="<?= e(url("customers/{$c['customer_id']}")) ?>" class="text-brand-600 hover:underline font-medium"><?= e($c['name']) ?></a></td>
            <td class="px-6 py-3 text-right">
              <span class="text-slate-500 text-xs">LTV: </span>
              <span class="font-bold">Rs. <?= number_format($c['lifetime_value'], 0) ?></span>
            </td>
            <td class="px-6 py-3 text-right text-red-600 font-semibold"><?= isset($c['days_since_purchase']) ? $c['days_since_purchase'] . ' days' : 'never' ?> inactive</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- Overdue Customers -->
<?php if (!empty($overdue)): ?>
  <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden mb-4">
    <div class="border-b border-slate-100 px-6 py-4 bg-orange-50">
      <h2 class="text-sm font-semibold text-orange-900">📅 Overdue (30+ days)</h2>
    </div>
    <table class="w-full text-sm">
      <tbody class="divide-y divide-slate-50">
        <?php foreach (array_slice($overdue, 0, 5) as $c): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-6 py-3"><a href="<?= e(url("customers/{$c['customer_id']}")) ?>" class="text-brand-600 hover:underline font-medium"><?= e($c['name']) ?></a></td>
            <td class="px-6 py-3 text-right">
              <span class="text-slate-500 text-xs">Due: </span>
              <span class="font-bold text-red-600">Rs. <?= number_format($c['overdue_amount'], 0) ?></span>
            </td>
            <td class="px-6 py-3 text-right text-slate-500 text-xs"><?= (int)($c['overdue_days'] ?? 0) ?> days overdue</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
