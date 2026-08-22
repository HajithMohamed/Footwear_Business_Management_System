<?php
  $outstanding = (float) $balance; // or $customer['outstanding_due']
  $creditLimit = (float) $customer['credit_limit'];
  $available = max(0, $creditLimit - $outstanding);
  $daysOverdue = (int) ($customer['days_overdue'] ?? 0); // we might not have this from withIntelligence directly, but we can guess it or calculate it if needed.
  
  $statusLabel = '⚫ Inactive';
  $statusIcon = '⚫';
  if ($outstanding > 0) {
      if (($customer['overdue_amount'] ?? 0) > 0) { // fallback heuristic if we don't have days_overdue explicitly passed
          $statusLabel = '🔴 High Risk / Overdue';
          $statusIcon = '🔴';
      } else {
          $statusLabel = '🟡 Credit Active';
          $statusIcon = '🟡';
      }
  } else {
      $statusLabel = '🟢 Good Customer';
      $statusIcon = '🟢';
  }
?>

<div class="mb-4 flex items-center justify-between">
  <div class="flex items-center gap-3">
    <a href="<?= e(url('customers')) ?>" class="h-10 w-10 flex items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-slate-200 text-slate-600 active:scale-95 transition">
      <span class="text-xl">←</span>
    </a>
    <div>
      <h1 class="text-xl font-bold text-slate-800 truncate"><?= e($customer['name']) ?></h1>
    </div>
  </div>
  <a href="<?= e(url("customers/{$customer['id']}/edit")) ?>" class="h-10 w-10 flex items-center justify-center rounded-full bg-slate-100 text-slate-600 ring-1 ring-slate-200 active:scale-95 transition">
    ✏️
  </a>
</div>

<!-- Hero Card -->
<div class="mb-4 rounded-3xl bg-brand-600 p-5 text-white shadow-md relative overflow-hidden">
  <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-white/10 blur-xl"></div>
  
  <div class="flex justify-between items-start mb-6">
    <div>
      <p class="text-[10px] font-bold text-brand-200 uppercase tracking-wide">Outstanding Balance</p>
      <p class="text-3xl font-bold mt-1">Rs. <?= number_format($outstanding, 0) ?></p>
    </div>
    <div class="text-right">
      <span class="inline-block rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-wider">
        <?= $statusIcon ?> <?= $statusLabel ?>
      </span>
    </div>
  </div>

  <div class="grid grid-cols-2 gap-4 border-t border-white/20 pt-4 mb-5">
    <div>
      <p class="text-[10px] font-bold text-brand-200 uppercase tracking-wide">Credit Limit</p>
      <p class="text-base font-bold">Rs. <?= number_format($creditLimit, 0) ?></p>
    </div>
    <div>
      <p class="text-[10px] font-bold text-brand-200 uppercase tracking-wide">Available Credit</p>
      <p class="text-base font-bold <?= $available == 0 ? 'text-red-200' : 'text-green-200' ?>">Rs. <?= number_format($available, 0) ?></p>
    </div>
  </div>

  <!-- Quick Action Row -->
  <div class="flex gap-2 justify-between">
    <a href="tel:<?= e($customer['phone'] ?? '') ?>" class="flex-1 flex justify-center items-center h-12 rounded-2xl bg-white/10 hover:bg-white/20 active:scale-95 transition text-lg">📞</a>
    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $customer['phone'] ?? '') ?>" target="_blank" class="flex-1 flex justify-center items-center h-12 rounded-2xl bg-white/10 hover:bg-white/20 active:scale-95 transition text-lg">💬</a>
    <a href="<?= e(url("customers/{$customer['id']}/payment")) ?>" class="flex-[2] flex justify-center items-center h-12 rounded-2xl bg-white text-brand-700 font-bold active:scale-95 transition gap-2 shadow-sm">
      <span>💵</span> Pay
    </a>
    <a href="<?= e(url("customers/{$customer['id']}/bill")) ?>" class="flex-[2] flex justify-center items-center h-12 rounded-2xl bg-white/20 hover:bg-white/30 text-white font-bold active:scale-95 transition gap-2">
      <span>🧾</span> Bill
    </a>
  </div>
</div>

<!-- Tabs Dashboard -->
<div x-data="{ tab: 'summary' }" class="mb-10">
  <!-- Tab Navigation (Sticky) -->
  <div class="sticky top-0 z-10 bg-slate-50 border-b border-slate-200 mb-4 -mx-4 px-4 overflow-x-auto scrollbar-hide">
    <div class="flex gap-1 pb-1">
      <template x-for="t in [
        {id:'summary', icon:'📊', label:'Summary'},
        {id:'ledger', icon:'📓', label:'Ledger'},
        {id:'invoices', icon:'🧾', label:'Invoices'},
        {id:'payments', icon:'💵', label:'Payments'},
        {id:'cheques', icon:'📋', label:'Cheques'},
        {id:'analytics', icon:'📈', label:'Analytics'}
      ]">
        <button @click="tab = t.id" 
                :class="tab === t.id ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="px-3 py-3 border-b-2 font-bold text-xs whitespace-nowrap transition flex items-center gap-1.5">
          <span x-text="t.icon"></span> <span x-text="t.label"></span>
        </button>
      </template>
    </div>
  </div>

  <!-- SUMMARY TAB -->
  <div x-show="tab === 'summary'" class="space-y-4" x-transition>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
      <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-3 border-b border-slate-100 pb-2">Customer Information</h3>
      <div class="grid grid-cols-2 gap-y-3 gap-x-2 text-sm">
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Shop Name</span><span class="font-bold text-slate-800"><?= e($customer['name']) ?></span></div>
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Phone</span><span class="font-bold text-slate-800"><?= e($customer['phone'] ?? '—') ?></span></div>
        <div class="col-span-2"><span class="block text-[10px] font-bold text-slate-400 uppercase">Address</span><span class="font-bold text-slate-800"><?= e($customer['address'] ?? '—') ?>, <?= e($customer['city'] ?? '—') ?></span></div>
      </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
      <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-3 border-b border-slate-100 pb-2">Business Summary</h3>
      <div class="grid grid-cols-2 gap-y-4 gap-x-2 text-sm">
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Total Purchases</span><span class="text-base font-bold text-brand-600">Rs. <?= number_format($total_sales, 0) ?></span></div>
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Total Paid</span><span class="text-base font-bold text-green-600">Rs. <?= number_format($total_payments, 0) ?></span></div>
        
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Invoices</span><span class="font-bold text-slate-800"><?= count($invoices ?? []) ?></span></div>
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Pending Cheques</span><span class="font-bold text-slate-800"><?= count(array_filter($cheques ?? [], fn($c) => $c['status'] === 'pending')) ?></span></div>
        
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Last Purchase</span><span class="font-bold text-slate-800"><?= $customer['last_purchase_date'] ? date('j M Y', strtotime($customer['last_purchase_date'])) : '—' ?></span></div>
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Last Payment</span><span class="font-bold text-slate-800">—</span></div> <!-- Would need last payment date from DB -->
      </div>
    </div>
  </div>

  <!-- LEDGER TAB -->
  <div x-show="tab === 'ledger'" style="display: none;" x-transition>
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
      <div class="bg-slate-50 p-3 border-b border-slate-200 flex justify-between items-center">
        <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">Current Balance</span>
        <span class="text-sm font-bold <?= $balance > 0 ? 'text-red-600' : 'text-green-600' ?>">Rs. <?= number_format($balance, 0) ?></span>
      </div>
      
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="p-3 font-bold text-slate-500 uppercase">Date/Ref</th>
            <th class="p-3 font-bold text-slate-500 uppercase text-right">Debit</th>
            <th class="p-3 font-bold text-slate-500 uppercase text-right">Credit</th>
            <th class="p-3 font-bold text-slate-500 uppercase text-right">Balance</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php if (!empty($transactions)): ?>
            <?php foreach ($transactions as $txn): ?>
              <tr class="hover:bg-slate-50">
                <td class="p-3">
                  <span class="block font-bold text-slate-800 whitespace-nowrap"><?= date('d M', strtotime($txn['transaction_date'] ?? $txn['created_at'])) ?></span>
                  <span class="block text-[10px] text-slate-400 truncate max-w-[80px]">
                    <?= e($txn['bill_number'] ?? $txn['reference_id'] ?? ucwords(str_replace('_', ' ', $txn['transaction_type']))) ?>
                  </span>
                </td>
                <td class="p-3 text-right font-bold text-slate-700">
                  <?= $txn['transaction_type'] === 'sale' ? number_format($txn['amount'], 0) : '—' ?>
                </td>
                <td class="p-3 text-right font-bold text-green-600">
                  <?= in_array($txn['transaction_type'], ['payment', 'credit_memo']) ? number_format($txn['amount'], 0) : '—' ?>
                </td>
                <td class="p-3 text-right font-bold <?= $txn['running_balance'] > 0 ? 'text-red-600' : 'text-slate-800' ?>">
                  <?= number_format($txn['running_balance'], 0) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4" class="p-6 text-center text-slate-500">No ledger entries found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- INVOICES TAB -->
  <div x-show="tab === 'invoices'" style="display: none;" class="space-y-3" x-transition>
    <a href="<?= e(url("sales/create?customer_id={$customer['id']}")) ?>" class="block w-full rounded-xl bg-white border-2 border-dashed border-slate-300 p-4 text-center text-sm font-bold text-slate-500 hover:border-brand-500 hover:text-brand-600 active:scale-95 transition mb-4">
      + Create New Invoice
    </a>

    <?php if (!empty($invoices)): ?>
      <?php foreach ($invoices as $inv): ?>
        <?php 
          $unpaid = $inv['total_amount'] - $inv['amount_paid'];
          $status = $unpaid <= 0 ? '🟢 Paid' : ($inv['amount_paid'] > 0 ? '🟡 Partial' : '🔴 Unpaid');
        ?>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
          <div class="flex justify-between items-start mb-2">
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Invoice</p>
              <p class="text-base font-bold text-slate-800"><?= e($inv['invoice_number']) ?></p>
            </div>
            <div class="text-right">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide"><?= date('d M Y', strtotime($inv['sale_date'])) ?></p>
              <p class="text-sm font-bold"><?= $status ?></p>
            </div>
          </div>
          
          <div class="flex justify-between items-end border-t border-slate-100 pt-3 mb-3">
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Total</p>
              <p class="text-sm font-bold text-slate-700">Rs. <?= number_format($inv['total_amount'], 0) ?></p>
            </div>
            <div class="text-right">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Outstanding</p>
              <p class="text-base font-bold text-red-600">Rs. <?= number_format(max(0, $unpaid), 0) ?></p>
            </div>
          </div>

          <div class="flex gap-2">
            <a href="<?= e(url("sales/{$inv['id']}")) ?>" class="flex-1 rounded-xl bg-slate-50 px-3 py-2 text-center text-xs font-bold text-slate-600 ring-1 ring-slate-200 active:scale-95 transition">View</a>
            <?php if ($unpaid > 0): ?>
              <a href="<?= e(url("customers/{$customer['id']}/payment")) ?>" class="flex-1 rounded-xl bg-brand-50 px-3 py-2 text-center text-xs font-bold text-brand-700 ring-1 ring-brand-200 active:scale-95 transition">Pay</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-center text-slate-500 py-6">No invoices found.</p>
    <?php endif; ?>
  </div>

  <!-- PAYMENTS TAB -->
  <div x-show="tab === 'payments'" style="display: none;" class="space-y-3" x-transition>
    <a href="<?= e(url("customers/{$customer['id']}/payment")) ?>" class="block w-full rounded-xl bg-brand-600 p-4 text-center text-sm font-bold text-white shadow-sm active:scale-95 transition mb-4">
      + Record Payment
    </a>

    <?php if (!empty($payments)): ?>
      <?php foreach ($payments as $pay): ?>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 flex justify-between items-center">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-lg">
              <?= $pay['payment_method'] === 'cash' ? '💵' : ($pay['payment_method'] === 'cheque' ? '📋' : '🏦') ?>
            </div>
            <div>
              <p class="text-sm font-bold text-slate-800"><?= ucfirst($pay['payment_method']) ?></p>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide"><?= date('d M Y', strtotime($pay['created_at'])) ?></p>
            </div>
          </div>
          <div class="text-right">
            <p class="text-lg font-bold text-green-600">Rs. <?= number_format($pay['amount'], 0) ?></p>
            <?php if (!empty($pay['recorded_by_name'])): ?>
              <p class="text-[10px] text-slate-400">By <?= e($pay['recorded_by_name']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-center text-slate-500 py-6">No payments recorded.</p>
    <?php endif; ?>
  </div>

  <!-- CHEQUES TAB -->
  <div x-show="tab === 'cheques'" style="display: none;" class="space-y-3" x-transition>
    <?php if (!empty($cheques)): ?>
      <?php foreach ($cheques as $chq): ?>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
          <div class="flex justify-between items-start mb-3">
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Cheque No</p>
              <p class="text-sm font-bold text-slate-800"><?= e($chq['cheque_number']) ?></p>
            </div>
            <span class="rounded-lg px-2 py-1 text-[10px] font-bold uppercase tracking-wider
              <?= $chq['status'] === 'cleared' ? 'bg-green-100 text-green-700' : ($chq['status'] === 'bounced' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
              <?= ucfirst($chq['status']) ?>
            </span>
          </div>

          <div class="grid grid-cols-2 gap-2 mb-4 border-t border-slate-100 pt-3">
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Amount</p>
              <p class="text-base font-bold text-slate-800">Rs. <?= number_format($chq['amount'] ?? 0, 0) ?></p>
            </div>
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Bank</p>
              <p class="text-sm font-bold text-slate-600 truncate"><?= e($chq['bank_name'] ?? '—') ?></p>
            </div>
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Cheque Date</p>
              <p class="text-sm font-bold text-slate-600"><?= $chq['cheque_date'] ? date('d M Y', strtotime($chq['cheque_date'])) : '—' ?></p>
            </div>
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Deposit Date</p>
              <p class="text-sm font-bold text-slate-600"><?= $chq['deposit_date'] ? date('d M Y', strtotime($chq['deposit_date'])) : '—' ?></p>
            </div>
          </div>
          
          <a href="<?= e(url("cheques/{$chq['id']}")) ?>" class="block w-full rounded-xl bg-slate-50 py-2 text-center text-xs font-bold text-slate-600 ring-1 ring-slate-200 active:scale-95 transition">
            Manage Cheque
          </a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-center text-slate-500 py-6">No cheques recorded.</p>
    <?php endif; ?>
  </div>

  <!-- ANALYTICS TAB -->
  <div x-show="tab === 'analytics'" style="display: none;" class="space-y-4" x-transition>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
      <div class="mb-4">
        <div class="flex justify-between items-end mb-1">
          <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">Payment Score</span>
          <span class="text-lg font-bold text-brand-600">
            <?= $total_sales > 0 ? min(100, round(($total_payments / $total_sales) * 100)) : 0 ?>%
          </span>
        </div>
        <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
          <div class="h-full bg-brand-500" style="width: <?= $total_sales > 0 ? min(100, round(($total_payments / $total_sales) * 100)) : 0 ?>%;"></div>
        </div>
      </div>

      <div class="grid gap-3 pt-4 border-t border-slate-100">
        <div class="flex justify-between items-center">
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Risk Level</span>
          <span class="text-sm font-bold <?= $outstanding > 0 && $daysOverdue > 30 ? 'text-red-600' : 'text-green-600' ?>">
            <?= $outstanding > 0 && $daysOverdue > 30 ? '🔴 High Risk' : '🟢 Low Risk' ?>
          </span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Lifetime Value</span>
          <span class="text-sm font-bold text-slate-800">Rs. <?= number_format($customer['lifetime_value'] ?? 0, 0) ?></span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Total Invoices</span>
          <span class="text-sm font-bold text-slate-800"><?= $customer['total_purchases'] ?? count($invoices ?? []) ?></span>
        </div>
      </div>
    </div>
  </div>

</div>

  <?php if (empty($customer['deleted_at'])): ?>
    <div class="mt-8 rounded-2xl bg-red-50 p-4 border border-red-100">
      <h3 class="text-sm font-bold text-red-800 mb-2 flex items-center gap-2">⚠️ Danger Zone</h3>
      <p class="text-xs text-red-600 mb-4">Deleting this customer will hide them from the directory. You can restore them later.</p>
      <form method="post" action="<?= e(url("customers/{$customer['id']}/delete")) ?>" onsubmit="return confirm('Are you sure you want to delete this customer?');">
        <?= csrf_field() ?>
        <button class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-red-700 active:scale-95 transition">
          Delete Customer
        </button>
      </form>
    </div>
  <?php else: ?>
    <div class="mt-8 rounded-2xl bg-slate-100 p-4 border border-slate-200">
      <h3 class="text-sm font-bold text-slate-800 mb-2 flex items-center gap-2">🗑️ Deleted Customer</h3>
      <p class="text-xs text-slate-600 mb-4">This customer is currently deleted. Restore them to show them in the directory again.</p>
      <form method="post" action="<?= e(url("customers/{$customer['id']}/restore")) ?>">
        <?= csrf_field() ?>
        <button class="w-full rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-slate-900 active:scale-95 transition">
          Restore Customer
        </button>
      </form>
    </div>
  <?php endif; ?>

</div>
