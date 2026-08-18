<?php
  use App\Services\StorageService;
  $outstanding = (float) $balance; // or $customer['outstanding_due']
  $creditLimit = (float) $customer['credit_limit'];
  $available = max(0, $creditLimit - $outstanding);
  $daysOverdue = (int) ($customer['days_overdue'] ?? 0);
  $waPhone = whatsapp_phone($customer['phone'] ?? null);
  $requestedTab = (string) request('tab', 'summary');
  $initialTab = in_array($requestedTab, ['summary', 'ledger', 'invoices', 'payments', 'cheques', 'analytics'], true)
      ? $requestedTab
      : 'summary';
  
  $statusLabel = 'Inactive';
  $statusClass = 'status-neutral';
  
  if (!empty($customer['deleted_at'])) {
      $statusLabel = 'Deleted';
      $statusClass = 'status-neutral opacity-60';
  } elseif ($outstanding > 0) {
      if (($customer['overdue_amount'] ?? 0) > 0 || $daysOverdue > 30) {
          $statusLabel = 'High Risk';
          $statusClass = 'status-danger';
      } elseif ($daysOverdue > 0) {
          $statusLabel = 'Due Soon';
          $statusClass = 'status-warning';
      } else {
          $statusLabel = 'Credit Active';
          $statusClass = 'status-good';
      }
  } else {
      $statusLabel = 'Good Standing';
      $statusClass = 'status-good';
  }
?>

<div class="page-header justify-between">
  <div class="flex items-center gap-3">
    <a href="<?= e(url('customers')) ?>" class="page-header-back">
      <span>←</span>
    </a>
    <div>
      <h1 class="page-header-title truncate"><?= e($customer['name']) ?></h1>
    </div>
  </div>
  <a href="<?= e(url("customers/{$customer['id']}/edit")) ?>" class="btn btn-outline btn-icon">
    <?= ui_icon('pencil') ?><span class="sr-only">Edit customer</span>
  </a>
</div>

<!-- Hero Card -->
<div class="hero-card hero-card-primary mb-5">
  <div class="flex justify-between items-start mb-6">
    <div>
      <p class="text-[10px] font-bold text-brand-200 uppercase tracking-wide">Outstanding Balance</p>
      <p class="text-3xl font-bold mt-1">Rs. <?= number_format($outstanding, 0) ?></p>
    </div>
    <div class="text-right">
      <span class="status-badge <?= $statusClass ?> !bg-white/20 !text-white !border-none">
        <?= $statusLabel ?>
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
    <a href="<?= e(url("customers/{$customer['id']}/bill")) ?>" class="flex-1 flex justify-center items-center h-12 rounded-xl bg-white/20 hover:bg-white/30 text-white font-bold active:scale-95 transition gap-2 shadow-sm text-sm">
      <?= ui_icon('bill', 'h-5 w-5') ?> Add Bill
    </a>
    <a href="<?= e(url("customers/{$customer['id']}/payment")) ?>" class="flex-1 flex justify-center items-center h-12 rounded-xl bg-white text-brand-700 font-bold active:scale-95 transition gap-2 shadow-sm text-sm">
      <?= ui_icon('wallet', 'h-5 w-5') ?> Payment
    </a>
    <a href="<?= e(url("customers/{$customer['id']}?tab=ledger")) ?>" class="flex-1 flex justify-center items-center h-12 rounded-xl bg-white/20 hover:bg-white/30 text-white font-bold active:scale-95 transition gap-2 shadow-sm text-sm">
      <?= ui_icon('note', 'h-5 w-5') ?> Ledger
    </a>
  </div>
  <?php if (!empty($customer['phone'])): ?>
    <div class="mt-3 grid grid-cols-2 gap-2 border-t border-white/20 pt-3">
      <a href="tel:<?= e($customer['phone']) ?>" class="flex h-10 items-center justify-center gap-2 rounded-xl bg-white/15 text-xs font-bold text-white hover:bg-white/25">
        <?= ui_icon('phone', 'h-4 w-4') ?> Call <?= e($customer['phone']) ?>
      </a>
      <?php if ($waPhone): ?>
        <a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" rel="noopener" class="flex h-10 items-center justify-center gap-2 rounded-xl bg-green-500/30 text-xs font-bold text-white hover:bg-green-500/40">
          <?= ui_icon('users', 'h-4 w-4') ?> WhatsApp
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Explicit carried-forward balance adjustment; every change remains in the ledger. -->
<div class="mb-5 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100" x-data="{ open: false }">
  <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-left">
    <span class="inline-flex items-center gap-2 text-sm font-bold text-slate-700"><?= ui_icon('plus', 'h-4 w-4') ?> Add outstanding balance</span>
    <span class="text-xs font-bold text-brand-600" x-text="open ? 'Cancel' : 'Open'"></span>
  </button>
  <form x-show="open" x-cloak method="post" action="<?= e(url("customers/{$customer['id']}/outstanding")) ?>" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
    <?= csrf_field() ?>
    <p class="text-xs text-slate-500">Use this for debt carried from outside the system. Normal new sales should use Add Bill.</p>
    <div class="grid grid-cols-2 gap-3">
      <label class="block"><span class="mb-1 block text-xs font-bold text-slate-600">Amount (Rs.)</span><input name="amount" type="number" min="0.01" step="0.01" required class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2.5 text-sm ring-1 ring-slate-200"></label>
      <label class="block"><span class="mb-1 block text-xs font-bold text-slate-600">Date</span><input name="transaction_date" type="date" value="<?= e(date('Y-m-d')) ?>" required class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2.5 text-sm ring-1 ring-slate-200"></label>
    </div>
    <input name="description" maxlength="255" placeholder="Reason or reference (optional)" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2.5 text-sm ring-1 ring-slate-200">
    <button class="btn btn-primary btn-full"><?= ui_icon('plus', 'h-4 w-4') ?> Add to Outstanding</button>
  </form>
</div>

<!-- Tabs Dashboard -->
<div x-data="{ tab: '<?= e($initialTab) ?>', ledgerView: 'timeline' }" class="mb-10">
  <!-- Tab Navigation (Sticky) -->
  <div class="tab-nav mb-5">
    <template x-for="t in [
      {id:'summary', label:'Summary'},
      {id:'ledger', label:'Ledger'},
      {id:'invoices', label:'History'},
      {id:'payments', label:'Payments'},
      {id:'cheques', label:'Cheques'},
      {id:'analytics', label:'Activity'}
    ]">
      <button @click="tab = t.id" 
              class="tab-item"
              :class="tab === t.id ? 'tab-item-active' : ''">
        <span x-text="t.label"></span>
      </button>
    </template>
  </div>

  <!-- SUMMARY TAB -->
  <div x-show="tab === 'summary'" class="space-y-4" x-transition>
    <div class="card card-compact">
      <h3 class="section-title mt-0">Customer Information</h3>
      <div class="grid grid-cols-2 gap-y-3 gap-x-2 text-sm">
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Shop Name</span><span class="font-bold text-slate-800"><?= e($customer['name']) ?></span></div>
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Phone</span><span class="font-bold text-slate-800"><?= e($customer['phone'] ?? '—') ?></span></div>
        <div class="col-span-2"><span class="block text-[10px] font-bold text-slate-400 uppercase">Address</span><span class="font-bold text-slate-800"><?= e($customer['address'] ?? '—') ?>, <?= e($customer['city'] ?? '—') ?></span></div>
      </div>
    </div>

    <div class="card card-compact">
      <h3 class="section-title mt-0">Business Summary</h3>
      <div class="grid grid-cols-2 gap-y-4 gap-x-2 text-sm">
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Total Purchases</span><span class="text-base font-bold text-brand-600">Rs. <?= number_format($total_sales, 0) ?></span></div>
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Total Paid</span><span class="text-base font-bold text-green-600">Rs. <?= number_format($total_payments, 0) ?></span></div>
        
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Invoices</span><span class="font-bold text-slate-800"><?= count($invoices ?? []) ?></span></div>
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Pending Cheques</span><span class="font-bold text-slate-800"><?= count(array_filter($cheques ?? [], fn($c) => $c['status'] === 'pending')) ?></span></div>
        
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Last Purchase</span><span class="font-bold text-slate-800"><?= $customer['last_purchase_date'] ? date('j M Y', strtotime($customer['last_purchase_date'])) : '—' ?></span></div>
        <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Typical Payment Time</span><span class="font-bold text-slate-800">~15 days</span></div>
      </div>
    </div>
  </div>

  <!-- LEDGER TAB -->
  <div x-show="tab === 'ledger'" style="display: none;" x-transition class="space-y-4">
    <div class="flex justify-between items-center bg-white rounded-xl p-3 ring-1 ring-slate-200 shadow-sm">
      <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">Current Balance</span>
      <span class="text-sm font-bold <?= $balance > 0 ? 'text-amber-600' : 'text-green-600' ?>">Rs. <?= number_format($balance, 0) ?></span>
    </div>

    <div class="flex justify-end mb-2">
      <div class="bg-slate-100 rounded-lg p-1 inline-flex gap-1">
        <button @click="ledgerView = 'timeline'" :class="ledgerView === 'timeline' ? 'bg-white shadow-sm' : ''" class="px-3 py-1 rounded-md text-xs font-bold text-slate-700 transition">Timeline</button>
        <button @click="ledgerView = 'table'" :class="ledgerView === 'table' ? 'bg-white shadow-sm' : ''" class="px-3 py-1 rounded-md text-xs font-bold text-slate-700 transition">Table</button>
      </div>
    </div>
    
    <!-- Timeline View -->
    <div x-show="ledgerView === 'timeline'" class="pt-2 pl-2">
      <?php if (!empty($transactions)): ?>
        <?php foreach ($transactions as $txn): ?>
          <?php 
            $isDebit = $txn['transaction_type'] === 'sale';
            $isCredit = in_array($txn['transaction_type'], ['payment', 'credit_memo']);
            $dotClass = $isDebit ? 'timeline-dot-sale' : ($isCredit ? 'timeline-dot-payment' : 'timeline-dot-adjustment');
            $ref = $txn['bill_number'] ?? $txn['reference_id'] ?? ucwords(str_replace('_', ' ', $txn['transaction_type']));
          ?>
          <div class="timeline-entry">
            <div class="timeline-dot <?= $dotClass ?> flex items-center justify-center !w-6 !h-6 !-left-2 !top-0 bg-white"><?= ui_icon($isDebit ? 'bill' : ($isCredit ? 'wallet' : 'note'), 'h-3.5 w-3.5') ?></div>
            <div class="bg-white rounded-xl p-3 ring-1 ring-slate-200 shadow-sm">
              <div class="flex justify-between items-start mb-1">
                <div>
                  <p class="text-sm font-bold text-slate-800"><?= e($ref) ?></p>
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide"><?= date('d M Y, h:i A', strtotime($txn['transaction_date'] ?? $txn['created_at'])) ?></p>
                </div>
                <div class="text-right">
                  <p class="text-sm font-bold <?= $isDebit ? 'text-slate-800' : 'text-green-600' ?>">
                    <?= $isDebit ? '+' : '-' ?> Rs. <?= number_format($txn['amount'], 0) ?>
                  </p>
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mt-0.5">Bal: Rs. <?= number_format($txn['running_balance'], 0) ?></p>
                </div>
              </div>
              <?php if ($isDebit && !empty($txn['image_path'])): ?>
                <a href="<?= e(StorageService::url($txn['image_path'])) ?>" target="_blank" class="mt-2 inline-flex items-center gap-1.5 text-xs font-bold text-brand-600"><?= ui_icon('image', 'h-4 w-4') ?> Bill image available</a>
              <?php elseif ($isCredit && ($txn['payment_method'] ?? '') === 'cheque'): ?>
                <div class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                  <p class="font-bold">Cheque payment<?= !empty($txn['cheque_number']) ? ' · #' . e($txn['cheque_number']) : '' ?></p>
                  <?php if (!empty($txn['cheque_date'])): ?><p class="mt-0.5">Cheque date: <?= e(date('j M Y', strtotime($txn['cheque_date']))) ?></p><?php endif; ?>
                  <?php if (!empty($txn['cheque_image_path'])): ?><a href="<?= e(StorageService::url($txn['cheque_image_path'])) ?>" target="_blank" class="mt-1 inline-flex items-center gap-1.5 font-bold text-amber-900"><?= ui_icon('image', 'h-4 w-4') ?> Cheque image available</a><?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if (($txn['reference_type'] ?? '') === 'manual_bill'): ?>
                <a href="<?= e(url('bills/' . $txn['id'] . '/edit')) ?>" class="mt-2 inline-flex items-center gap-1.5 text-xs font-bold text-brand-600"><?= ui_icon('pencil', 'h-4 w-4') ?> Edit bill details</a>
              <?php elseif (($txn['reference_type'] ?? '') === 'payment' && !empty($txn['reference_id'])): ?>
                <a href="<?= e(url('payments/' . $txn['reference_id'] . '/edit')) ?>" class="mt-2 inline-flex items-center gap-1.5 text-xs font-bold text-brand-600"><?= ui_icon('pencil', 'h-4 w-4') ?> Edit payment details</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state"><p class="empty-state-text">No ledger entries found.</p></div>
      <?php endif; ?>
    </div>

    <!-- Compact Table View -->
    <div x-show="ledgerView === 'table'" style="display: none;" class="card p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs whitespace-nowrap">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-4 py-3 font-bold text-slate-500 uppercase tracking-wide">Date/Ref</th>
              <th class="px-4 py-3 font-bold text-slate-500 uppercase tracking-wide text-right">Debit</th>
              <th class="px-4 py-3 font-bold text-slate-500 uppercase tracking-wide text-right">Credit</th>
              <th class="px-4 py-3 font-bold text-slate-500 uppercase tracking-wide text-right">Balance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (!empty($transactions)): ?>
              <?php foreach ($transactions as $txn): ?>
                <tr class="hover:bg-slate-50 transition">
                  <td class="px-4 py-3">
                    <span class="block font-bold text-slate-800"><?= date('d M Y', strtotime($txn['transaction_date'] ?? $txn['created_at'])) ?></span>
                    <span class="block text-[10px] text-slate-400">
                      <?= e($txn['bill_number'] ?? $txn['reference_id'] ?? ucwords(str_replace('_', ' ', $txn['transaction_type']))) ?>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right font-bold text-slate-700">
                    <?= $txn['transaction_type'] === 'sale' ? number_format($txn['amount'], 0) : '—' ?>
                  </td>
                  <td class="px-4 py-3 text-right font-bold text-green-600">
                    <?= in_array($txn['transaction_type'], ['payment', 'credit_memo']) ? number_format($txn['amount'], 0) : '—' ?>
                  </td>
                  <td class="px-4 py-3 text-right font-bold <?= $txn['running_balance'] > 0 ? 'text-amber-600' : 'text-slate-800' ?>">
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
  </div>

  <!-- INVOICES TAB -->
  <div x-show="tab === 'invoices'" style="display: none;" class="space-y-4" x-transition>
    <?php if (!empty($invoices)): ?>
      <?php foreach ($invoices as $inv): ?>
        <?php 
          $unpaid = $inv['total_amount'] - $inv['amount_paid'];
          $status = $unpaid <= 0 ? 'Paid' : ($inv['amount_paid'] > 0 ? 'Partial' : 'Unpaid');
          $statusClass = $unpaid <= 0 ? 'status-good' : ($inv['amount_paid'] > 0 ? 'status-warning' : 'status-danger');
        ?>
        <div class="card card-compact">
          <div class="flex justify-between items-start mb-3">
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Invoice</p>
              <p class="text-base font-bold text-slate-800"><?= e($inv['invoice_number']) ?></p>
            </div>
            <div class="text-right">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1"><?= date('d M Y', strtotime($inv['sale_date'])) ?></p>
              <span class="status-badge <?= $statusClass ?>"><?= $status ?></span>
            </div>
          </div>
          
          <div class="flex justify-between items-end border-t border-slate-100 pt-3 mb-4">
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Total</p>
              <p class="text-sm font-bold text-slate-700">Rs. <?= number_format($inv['total_amount'], 0) ?></p>
            </div>
            <div class="text-right">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Outstanding</p>
              <p class="text-base font-bold text-amber-600">Rs. <?= number_format(max(0, $unpaid), 0) ?></p>
            </div>
          </div>

          <div class="flex gap-2">
            <a href="<?= e(url("sales/{$inv['id']}")) ?>" class="btn btn-outline flex-1">View</a>
            <?php if ($unpaid > 0): ?>
              <a href="<?= e(url("customers/{$customer['id']}/payment")) ?>" class="btn btn-primary flex-1">Pay</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state"><p class="empty-state-text">No invoices found.</p></div>
    <?php endif; ?>
  </div>

  <!-- PAYMENTS TAB -->
  <div x-show="tab === 'payments'" style="display: none;" class="space-y-4" x-transition>
    <a href="<?= e(url("customers/{$customer['id']}/payment")) ?>" class="btn btn-success btn-full">
      <?= ui_icon('wallet', 'h-5 w-5') ?> Record Payment
    </a>

    <?php if (!empty($payments)): ?>
      <?php foreach ($payments as $pay): ?>
        <div class="card card-compact">
          <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-lg">
              <?= ui_icon($pay['payment_method'] === 'cheque' ? 'cheque' : 'wallet') ?>
            </div>
            <div>
              <p class="text-sm font-bold text-slate-800"><?= ucfirst($pay['payment_method']) ?></p>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide"><?= date('d M Y', strtotime($pay['created_at'])) ?></p>
            </div>
            </div>
            <div class="text-right">
              <p class="text-lg font-bold text-green-600">Rs. <?= number_format($pay['amount'], 0) ?></p>
              <?php if (!empty($pay['recorded_by_name'])): ?><p class="text-[10px] text-slate-400">By <?= e($pay['recorded_by_name']) ?></p><?php endif; ?>
            </div>
          </div>
          <div class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3">
            <a href="<?= e(url('payments/' . $pay['id'] . '/receipt')) ?>" class="btn btn-outline justify-center">Receipt</a>
            <a href="<?= e(url('payments/' . $pay['id'] . '/edit')) ?>" class="btn btn-outline justify-center"><?= ui_icon('pencil', 'h-4 w-4') ?> Edit</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state"><p class="empty-state-text">No payments recorded.</p></div>
    <?php endif; ?>
  </div>

  <!-- CHEQUES TAB -->
  <div x-show="tab === 'cheques'" style="display: none;" class="space-y-4" x-transition>
    <?php if (!empty($cheques)): ?>
      <?php foreach ($cheques as $chq): ?>
        <div class="card card-compact">
          <div class="flex justify-between items-start mb-4">
            <div>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Cheque No</p>
              <p class="text-sm font-bold text-slate-800"><?= e($chq['cheque_number']) ?></p>
            </div>
            <span class="status-badge <?= $chq['status'] === 'cleared' ? 'status-good' : ($chq['status'] === 'bounced' ? 'status-danger' : 'status-warning') ?>">
              <?= ucfirst($chq['status']) ?>
            </span>
          </div>

          <div class="grid grid-cols-2 gap-3 mb-4 border-t border-slate-100 pt-3">
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
          
          <a href="<?= e(url("cheques/{$chq['id']}")) ?>" class="btn btn-outline btn-full">
            Manage Cheque
          </a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state"><p class="empty-state-text">No cheques recorded.</p></div>
    <?php endif; ?>
  </div>

  <!-- ANALYTICS TAB -->
  <div x-show="tab === 'analytics'" style="display: none;" class="space-y-4" x-transition>
    <div class="card card-compact">
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
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Classification</span>
          <span class="text-sm font-bold text-brand-600 capitalize">
            <?= $customer['classification'] ?? 'Regular' ?>
          </span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Risk Level</span>
          <span class="text-sm font-bold <?= $outstanding > 0 && $daysOverdue > 30 ? 'text-red-600' : 'text-green-600' ?>">
            <?= $outstanding > 0 && $daysOverdue > 30 ? 'High Risk' : 'Low Risk' ?>
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

<!-- Danger Zone / Restore -->
<?php if (empty($customer['deleted_at'])): ?>
  <div class="mt-8 rounded-2xl bg-red-50 p-4 border border-red-100 mb-8">
    <h3 class="text-sm font-bold text-red-800 mb-2 flex items-center gap-2"><?= ui_icon('warning', 'h-5 w-5') ?> Danger Zone</h3>
    <p class="text-xs text-red-600 mb-4">Deleting this customer will hide them from the directory. You can restore them later.</p>
    <form method="post" action="<?= e(url("customers/{$customer['id']}/delete")) ?>" 
          x-data="{}" 
          @submit.prevent="$dispatch('confirm-action', { 
              title: 'Delete Customer', 
              message: 'Are you sure you want to hide this customer from the directory?', 
              confirmText: 'Delete', 
              type: 'danger', 
              onConfirm: () => $el.submit() 
          })">
      <?= csrf_field() ?>
      <button class="btn btn-danger btn-full">
        Delete Customer
      </button>
    </form>
  </div>
<?php else: ?>
  <div class="mt-8 rounded-2xl bg-slate-100 p-4 border border-slate-200 mb-8">
    <h3 class="text-sm font-bold text-slate-800 mb-2 flex items-center gap-2"><?= ui_icon('trash', 'h-5 w-5') ?> Deleted Customer</h3>
    <p class="text-xs text-slate-600 mb-4">This customer is currently deleted. Restore them to show them in the directory again.</p>
    <form method="post" action="<?= e(url("customers/{$customer['id']}/restore")) ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary btn-full">
        Restore Customer
      </button>
    </form>
  </div>
<?php endif; ?>
