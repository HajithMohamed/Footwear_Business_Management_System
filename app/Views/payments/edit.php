<div class="mb-5 flex items-center gap-3">
  <a href="<?= e(url('customers/' . $payment['customer_id'] . '?tab=payments')) ?>" class="page-header-back" aria-label="Back">←</a>
  <div><h1 class="page-header-title">Edit Payment</h1><p class="text-xs text-slate-500"><?= e($payment['customer_name']) ?> · <?= e(ucfirst($payment['payment_method'])) ?></p></div>
</div>
<form method="post" action="<?= e(url('payments/' . $payment['id'])) ?>" class="space-y-4 pb-40 md:pb-24">
  <?= csrf_field() ?>
  <div class="card space-y-4">
    <div class="grid grid-cols-2 gap-3">
      <div><label class="mb-1 block text-xs font-bold text-slate-600">Payment Date *</label><input type="date" name="payment_date" required value="<?= e(old('payment_date', $payment['payment_date'])) ?>" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"></div>
      <div><label class="mb-1 block text-xs font-bold text-slate-600">Amount (Rs.) *</label><input type="number" name="amount" required min="0.01" step="0.01" value="<?= e(old('amount', $payment['amount'])) ?>" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"></div>
    </div>
    <?php if ($payment['payment_method'] === 'cheque'): ?>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="mb-1 block text-xs font-bold text-slate-600">Cheque Number *</label><input name="cheque_number" required value="<?= e(old('cheque_number', $payment['cheque_number'])) ?>" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"></div>
        <div><label class="mb-1 block text-xs font-bold text-slate-600">Bank</label><input name="bank_name" value="<?= e(old('bank_name', $payment['bank_name'])) ?>" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"></div>
        <div><label class="mb-1 block text-xs font-bold text-slate-600">Cheque Date *</label><input type="date" name="cheque_date" required value="<?= e(old('cheque_date', $payment['cheque_date'])) ?>" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"></div>
        <div><label class="mb-1 block text-xs font-bold text-slate-600">Deposit Date</label><input type="date" name="deposit_date" value="<?= e(old('deposit_date', $payment['deposit_date'])) ?>" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"></div>
      </div>
    <?php endif; ?>
    <div><label class="mb-1 block text-xs font-bold text-slate-600">Correction Note</label><textarea name="notes" rows="3" placeholder="Why was this corrected?" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"><?= e(old('notes', $payment['notes'])) ?></textarea></div>
  </div>
  <div class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">The corrected amount updates the payment, cheque (if any), customer outstanding and every later running balance.</div>
  <div class="fixed bottom-[69px] left-0 right-0 z-30 flex gap-3 border-t border-slate-200 bg-white/95 p-4 backdrop-blur md:bottom-0 md:left-64">
    <a href="<?= e(url('customers/' . $payment['customer_id'] . '?tab=payments')) ?>" class="btn btn-outline flex-1">Cancel</a>
    <button class="btn btn-primary flex-[2]">Save Correction</button>
  </div>
</form>
