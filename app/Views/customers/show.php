<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('customers')) ?>" class="text-2xl">←</a>
  <div class="flex-1">
    <h1 class="text-lg font-bold text-slate-800"><?= e($customer['name']) ?></h1>
    <p class="text-xs text-slate-500"><?= e($customer['customer_type'] === 'wholesale' ? '👥 Wholesale' : '🏪 Retail') ?> • <?= e($customer['region'] ?? '—') ?></p>
  </div>
  <a href="<?= e(url("customers/{$customer['id']}/edit")) ?>" class="text-xl hover:text-slate-600">✏️</a>
</div>

<!-- Quick Contact Bar -->
<div class="mb-4 flex gap-2">
  <a href="tel:<?= e($customer['phone'] ?? '') ?>" class="flex-1 rounded-lg bg-slate-100 px-3 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-200">📞 Call</a>
  <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $customer['phone'] ?? '') ?>" target="_blank" class="flex-1 rounded-lg bg-green-100 px-3 py-2 text-center text-sm font-medium text-green-700 hover:bg-green-200">💬 Chat</a>
  <a href="<?= e(url("customers/{$customer['id']}/payment")) ?>" class="flex-1 rounded-lg bg-blue-600 px-3 py-2 text-center text-sm font-medium text-white hover:bg-blue-700">💵 Payment</a>
</div>

<div class="mb-4 grid grid-cols-2 gap-2">
  <a href="<?= e(url("customers/{$customer['id']}/bill")) ?>"
     class="rounded-lg bg-amber-100 px-3 py-2 text-center text-sm font-medium text-amber-700 hover:bg-amber-200">
    Attach bill
  </a>
  <a href="<?= e(url("customers/{$customer['id']}/ledger")) ?>"
     class="rounded-lg bg-slate-100 px-3 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-200">
    Full ledger
  </a>
</div>

<!-- Financial Summary -->
<div class="grid gap-3 sm:grid-cols-3 mb-4">
  <div class="rounded-lg bg-red-50 p-4 ring-1 ring-red-100">
    <p class="text-xs font-medium text-red-600 uppercase">Outstanding</p>
    <p class="mt-1 text-2xl font-bold text-red-700">Rs. <?= number_format($balance, 0) ?></p>
  </div>
  <div class="rounded-lg bg-blue-50 p-4 ring-1 ring-blue-100">
    <p class="text-xs font-medium text-blue-600 uppercase">Credit Limit</p>
    <p class="mt-1 text-2xl font-bold text-blue-700">Rs. <?= number_format($customer['credit_limit'], 0) ?></p>
  </div>
  <div class="rounded-lg <?= ($customer['credit_limit'] - $balance) >= 0 ? 'bg-green-50' : 'bg-red-50' ?> p-4 ring-1 <?= ($customer['credit_limit'] - $balance) >= 0 ? 'ring-green-100' : 'ring-red-100' ?>">
    <p class="text-xs font-medium <?= ($customer['credit_limit'] - $balance) >= 0 ? 'text-green-600' : 'text-red-600' ?> uppercase">Available</p>
    <p class="mt-1 text-2xl font-bold <?= ($customer['credit_limit'] - $balance) >= 0 ? 'text-green-700' : 'text-red-700' ?>">Rs. <?= number_format(max(0, $customer['credit_limit'] - $balance), 0) ?></p>
  </div>
</div>

<!-- Tabs -->
<div x-data="{ tab: 'ledger' }" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
  <!-- Tab Buttons -->
  <div class="border-b border-slate-100 flex overflow-x-auto">
    <button @click="tab='ledger'" :class="tab==='ledger'?'border-b-2 border-brand-600 text-brand-600':'text-slate-600 hover:text-slate-800'" class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 border-transparent">
      📊 Ledger
    </button>
    <button @click="tab='payments'" :class="tab==='payments'?'border-b-2 border-brand-600 text-brand-600':'text-slate-600 hover:text-slate-800'" class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 border-transparent">
      💰 Payments
    </button>
    <button @click="tab='cheques'" :class="tab==='cheques'?'border-b-2 border-brand-600 text-brand-600':'text-slate-600 hover:text-slate-800'" class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 border-transparent">
      📋 Cheques
    </button>
    <button @click="tab='analytics'" :class="tab==='analytics'?'border-b-2 border-brand-600 text-brand-600':'text-slate-600 hover:text-slate-800'" class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 border-transparent">
      📈 Analytics
    </button>
  </div>

  <!-- Tab Content -->
  <div class="p-4">
    <!-- Ledger Tab -->
    <div x-show="tab==='ledger'" class="space-y-2">
      <?php if (!empty($transactions)): ?>
        <div class="space-y-2">
          <?php foreach (array_slice($transactions, 0, 20) as $txn): ?>
            <?php $txnDate = $txn['transaction_date'] ?? substr((string) $txn['created_at'], 0, 10); ?>
            <div class="flex items-center justify-between p-3 hover:bg-slate-50 rounded">
              <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-slate-500"><?= date('M d', strtotime($txnDate)) ?></p>
                <p class="text-sm font-medium text-slate-800"><?= e($txn['description'] ?? ucwords(str_replace('_', ' ', $txn['transaction_type']))) ?></p>
                <?php if (!empty($txn['bill_number']) || !empty($txn['due_date'])): ?>
                  <p class="text-[11px] text-slate-400">
                    <?= !empty($txn['bill_number']) ? 'Bill #' . e($txn['bill_number']) : '' ?>
                    <?= !empty($txn['due_date']) ? ' Due ' . e(date('d M Y', strtotime($txn['due_date']))) : '' ?>
                  </p>
                <?php endif; ?>
              </div>
              <div class="text-right">
                <p class="text-sm font-bold <?= $txn['transaction_type'] === 'payment' || $txn['transaction_type'] === 'credit_memo' ? 'text-green-600' : 'text-red-600' ?>">
                  <?= $txn['transaction_type'] === 'payment' || $txn['transaction_type'] === 'credit_memo' ? '-' : '+' ?>Rs. <?= number_format($txn['amount'], 0) ?>
                </p>
                <p class="text-xs text-slate-500">Bal: Rs. <?= number_format($txn['running_balance'], 0) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-center text-slate-500 py-6">No transactions yet.</p>
      <?php endif; ?>
    </div>

    <!-- Payments Tab -->
    <div x-show="tab==='payments'" class="space-y-2">
      <?php
        $paymentsByMethod = [];
        foreach ($transactions as $txn) {
          if ($txn['transaction_type'] === 'payment') {
            $method = $txn['reference_type'] ?? 'unknown';
            if (!isset($paymentsByMethod[$method])) {
              $paymentsByMethod[$method] = [];
            }
            $paymentsByMethod[$method][] = $txn;
          }
        }
      ?>
      <?php if (!empty($paymentsByMethod)): ?>
        <?php foreach ($paymentsByMethod as $method => $payments): ?>
          <div class="mb-4">
            <h3 class="text-xs font-bold text-slate-600 uppercase mb-2">
              <?php
                echo match($method) {
                  'payment' => '💵 Cash & Transfers',
                  'cheque_bounce' => '❌ Cheque Bounce',
                  default => '💰 ' . ucfirst($method)
                };
              ?>
            </h3>
            <div class="space-y-2">
              <?php foreach ($payments as $p): ?>
                <div class="flex justify-between p-2 bg-slate-50 rounded">
                  <p class="text-sm text-slate-700"><?= date('M d, Y', strtotime($p['created_at'])) ?></p>
                  <p class="text-sm font-bold text-green-600">Rs. <?= number_format($p['amount'], 0) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="border-t pt-3 mt-3">
          <div class="flex justify-between font-bold">
            <p>Total Paid</p>
            <p class="text-green-600">Rs. <?= number_format($total_payments, 0) ?></p>
          </div>
        </div>
      <?php else: ?>
        <p class="text-center text-slate-500 py-6">No payments yet.</p>
      <?php endif; ?>
    </div>

    <!-- Cheques Tab -->
    <div x-show="tab==='cheques'" class="space-y-2">
      <p class="text-xs text-slate-500 text-center py-6">Cheques will appear here when recorded.</p>
    </div>

    <!-- Analytics Tab -->
    <div x-show="tab==='analytics'" class="grid gap-4 sm:grid-cols-2">
      <div class="p-3 bg-blue-50 rounded-lg ring-1 ring-blue-100">
        <p class="text-xs text-blue-600 uppercase font-medium">Total Sales</p>
        <p class="text-xl font-bold text-blue-700 mt-1">Rs. <?= number_format($total_sales, 0) ?></p>
      </div>
      <div class="p-3 bg-green-50 rounded-lg ring-1 ring-green-100">
        <p class="text-xs text-green-600 uppercase font-medium">Total Paid</p>
        <p class="text-xl font-bold text-green-700 mt-1">Rs. <?= number_format($total_payments, 0) ?></p>
      </div>
      <div class="p-3 bg-amber-50 rounded-lg ring-1 ring-amber-100">
        <p class="text-xs text-amber-600 uppercase font-medium">Avg Payment Delay</p>
        <p class="text-xl font-bold text-amber-700 mt-1">—</p>
      </div>
      <div class="p-3 bg-slate-50 rounded-lg ring-1 ring-slate-100">
        <p class="text-xs text-slate-600 uppercase font-medium">Invoice Count</p>
        <p class="text-xl font-bold text-slate-700 mt-1">—</p>
      </div>
    </div>
  </div>
</div>
