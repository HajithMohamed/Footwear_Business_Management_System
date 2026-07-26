<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-lg font-bold text-slate-800">Customers</h1>
  </div>
  <a href="<?= e(url('customers/create')) ?>" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
    ➕ Add Customer
  </a>
</div>

<!-- Filters -->
<form method="get" action="<?= e(url('customers')) ?>" class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="grid gap-3 sm:grid-cols-4">
    <input type="text" name="search" placeholder="Name, phone, email…" value="<?= e($filters['search']) ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
    <select name="type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
      <option value="">All types</option>
      <option value="retail" <?= $filters['type'] === 'retail' ? 'selected' : '' ?>>Retail</option>
      <option value="wholesale" <?= $filters['type'] === 'wholesale' ? 'selected' : '' ?>>Wholesale</option>
    </select>
    <input type="text" name="region" placeholder="Region…" value="<?= e($filters['region']) ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
    <button type="submit" class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Search</button>
  </div>
</form>

<!-- List -->
<?php if (!empty($customers)): ?>
  <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="border-b border-slate-100 bg-slate-50">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Customer</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Contact</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Credit Limit</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Due</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($customers as $c): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3"><a href="<?= e(url("customers/{$c['id']}")) ?>" class="text-brand-600 hover:underline"><?= e($c['name']) ?></a></td>
            <td class="px-4 py-3 text-slate-500"><?= e($c['phone'] ?? '—') ?></td>
            <td class="px-4 py-3">
              <span class="inline-block rounded-full px-2 py-1 text-xs font-semibold <?= $c['customer_type'] === 'wholesale' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700' ?>">
                <?= ucfirst($c['customer_type']) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-slate-600">Rs. <?= number_format($c['credit_limit'], 2) ?></td>
            <td class="px-4 py-3 <?= $c['outstanding_due'] > 0 ? 'text-red-600 font-semibold' : 'text-slate-500' ?>">
              <?= $c['outstanding_due'] > 0 ? 'Rs. ' . number_format($c['outstanding_due'], 2) : '—' ?>
            </td>
            <td class="px-4 py-3 text-right">
              <a href="<?= e(url("customers/{$c['id']}/edit")) ?>" class="text-slate-400 hover:text-slate-600">✏️</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <p class="text-slate-500">No customers found.</p>
  </div>
<?php endif; ?>
