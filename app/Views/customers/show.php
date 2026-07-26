<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('customers')) ?>" class="text-2xl">←</a>
  <h1 class="text-lg font-bold text-slate-800"><?= e($customer['name']) ?></h1>
</div>

<!-- Header card -->
<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100 mb-4">
  <div class="grid gap-4 sm:grid-cols-2">
    <div>
      <p class="text-xs font-semibold text-slate-400 uppercase">Phone</p>
      <p class="text-slate-700 font-medium"><?= e($customer['phone'] ?? '—') ?></p>
    </div>
    <div>
      <p class="text-xs font-semibold text-slate-400 uppercase">Email</p>
      <p class="text-slate-700 font-medium"><?= e($customer['email'] ?? '—') ?></p>
    </div>
    <div>
      <p class="text-xs font-semibold text-slate-400 uppercase">Type</p>
      <p class="text-slate-700 font-medium"><?= ucfirst($customer['customer_type']) ?></p>
    </div>
    <div>
      <p class="text-xs font-semibold text-slate-400 uppercase">Region</p>
      <p class="text-slate-700 font-medium"><?= e($customer['region'] ?? '—') ?></p>
    </div>
  </div>
</div>

<!-- Financials -->
<div class="grid gap-4 sm:grid-cols-3 mb-4">
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-xs font-semibold text-slate-400 uppercase">Credit Limit</p>
    <p class="mt-1 text-2xl font-bold text-slate-800">Rs. <?= number_format($customer['credit_limit'], 2) ?></p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-xs font-semibold text-slate-400 uppercase">Outstanding Due</p>
    <p class="mt-1 text-2xl font-bold <?= $balance > 0 ? 'text-red-600' : 'text-green-600' ?>">Rs. <?= number_format($balance, 2) ?></p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-xs font-semibold text-slate-400 uppercase">Classification</p>
    <p class="mt-1 text-xl font-bold">
      <span class="inline-block px-2 py-1 rounded text-xs font-semibold
        <?php
          $class = strtolower($customer['classification'] ?? 'regular');
          echo match($class) {
            'vip' => 'bg-yellow-100 text-yellow-700',
            'at_risk' => 'bg-red-100 text-red-700',
            'dormant' => 'bg-slate-100 text-slate-700',
            'prospect' => 'bg-blue-100 text-blue-700',
            default => 'bg-slate-100 text-slate-700'
          };
        ?>">
        <?= ucfirst($class) ?>
      </span>
    </p>
  </div>
</div>

<!-- Actions -->
<div class="mb-4 flex gap-2">
  <a href="<?= e(url("customers/{$customer['id']}/payment")) ?>" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
    💰 Record Payment
  </a>
  <a href="<?= e(url("customers/{$customer['id']}/payments")) ?>" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
    📋 Payment History
  </a>
  <a href="<?= e(url("customers/{$customer['id']}/ledger")) ?>" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
    📊 Ledger
  </a>
  <a href="<?= e(url("customers/{$customer['id']}/edit")) ?>" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
    ✏️ Edit
  </a>
</div>

<!-- Recent transactions -->
<div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
  <div class="border-b border-slate-100 px-6 py-4">
    <h2 class="text-sm font-semibold text-slate-700">Recent Transactions</h2>
  </div>
  <?php if (!empty($transactions)): ?>
    <table class="w-full text-sm">
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($transactions as $txn): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-6 py-3">
              <span class="inline-block px-2 py-1 rounded text-xs font-semibold
                <?= match($txn['transaction_type']) {
                  'sale' => 'bg-blue-100 text-blue-700',
                  'payment' => 'bg-green-100 text-green-700',
                  'credit_memo' => 'bg-amber-100 text-amber-700',
                  default => 'bg-slate-100 text-slate-700'
                } ?>">
                <?= ucwords(str_replace('_', ' ', $txn['transaction_type'])) ?>
              </span>
            </td>
            <td class="px-6 py-3 text-slate-600"><?= e($txn['description'] ?? '—') ?></td>
            <td class="px-6 py-3 text-right font-medium text-slate-700">Rs. <?= number_format($txn['amount'], 2) ?></td>
            <td class="px-6 py-3 text-right text-slate-500 text-xs"><?= date('M d, Y', strtotime($txn['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="px-6 py-8 text-center text-slate-500">No transactions yet.</div>
  <?php endif; ?>
</div>
