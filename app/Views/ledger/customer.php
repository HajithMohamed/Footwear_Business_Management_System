<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url("customers/{$customer['id']}")) ?>" class="text-2xl">←</a>
  <h1 class="text-lg font-bold text-slate-800"><?= e($title) ?></h1>
  <a href="<?= e(url("customers/{$customer['id']}/bill")) ?>"
     class="ml-auto rounded-lg bg-amber-100 px-3 py-2 text-xs font-semibold text-amber-700">Attach bill</a>
</div>

<!-- Summary cards -->
<div class="grid gap-3 sm:grid-cols-4 mb-4">
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-xs font-semibold text-slate-400 uppercase">Total Sales</p>
    <p class="mt-1 text-2xl font-bold text-blue-600">Rs. <?= number_format($total_sales, 2) ?></p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-xs font-semibold text-slate-400 uppercase">Total Paid</p>
    <p class="mt-1 text-2xl font-bold text-green-600">Rs. <?= number_format($total_payments, 2) ?></p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-xs font-semibold text-slate-400 uppercase">Credit Memo</p>
    <p class="mt-1 text-2xl font-bold text-amber-600">Rs. <?= number_format($total_credits, 2) ?></p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-xs font-semibold text-slate-400 uppercase">Outstanding</p>
    <p class="mt-1 text-2xl font-bold <?= $balance > 0 ? 'text-red-600' : 'text-green-600' ?>">Rs. <?= number_format($balance, 2) ?></p>
  </div>
</div>

<!-- Ledger table -->
<div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
  <div class="border-b border-slate-100 px-6 py-4">
    <h2 class="text-sm font-semibold text-slate-700">Transaction History</h2>
  </div>

  <?php if (!empty($transactions)): ?>
    <table class="w-full text-sm">
      <thead class="border-b border-slate-100 bg-slate-50">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Date</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Description</th>
          <th class="px-4 py-3 text-right font-semibold text-slate-700">Amount</th>
          <th class="px-4 py-3 text-right font-semibold text-slate-700">Balance</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($transactions as $txn): ?>
          <?php $txnDate = $txn['transaction_date'] ?? substr((string) $txn['created_at'], 0, 10); ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-500 text-xs"><?= date('M d, Y', strtotime($txnDate)) ?></td>
            <td class="px-4 py-3">
              <span class="inline-block px-2 py-1 rounded text-xs font-semibold
                <?= match($txn['transaction_type']) {
                  'sale' => 'bg-blue-100 text-blue-700',
                  'payment' => 'bg-green-100 text-green-700',
                  'credit_memo' => 'bg-amber-100 text-amber-700',
                  'opening_balance' => 'bg-slate-100 text-slate-700',
                  default => 'bg-slate-100 text-slate-700'
                } ?>">
                <?= ucwords(str_replace('_', ' ', $txn['transaction_type'])) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-slate-600"><?= e($txn['description'] ?? '—') ?></td>
            <td class="px-4 py-3 text-right font-medium <?= $txn['transaction_type'] === 'payment' || $txn['transaction_type'] === 'credit_memo' ? 'text-green-600' : 'text-slate-700' ?>">
              Rs. <?= number_format($txn['amount'], 2) ?>
            </td>
            <td class="px-4 py-3 text-right font-bold <?= $txn['running_balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
              Rs. <?= number_format($txn['running_balance'], 2) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="px-6 py-8 text-center text-slate-500">No transactions yet.</div>
  <?php endif; ?>
</div>
