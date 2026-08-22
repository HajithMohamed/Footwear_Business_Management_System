<?php $customerId = (int) $bill['customer_id']; ?>
<div class="mb-5 flex items-center gap-3">
  <a href="<?= e(url('customers/' . $customerId . '?tab=ledger')) ?>" class="page-header-back" aria-label="Back">←</a>
  <div><h1 class="page-header-title">Edit Bill</h1><p class="text-xs text-slate-500"><?= e($bill['customer_name']) ?> · correction is audited</p></div>
</div>
<form method="post" action="<?= e(url('bills/' . $bill['id'])) ?>" class="space-y-4 pb-40 md:pb-24">
  <?= csrf_field() ?>
  <div class="card space-y-4">
    <div><label class="mb-1 block text-xs font-bold text-slate-600">Bill Number *</label><input name="bill_number" required maxlength="60" value="<?= e(old('bill_number', $bill['bill_number'])) ?>" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="mb-1 block text-xs font-bold text-slate-600">Bill Date *</label><input type="date" name="bill_date" required value="<?= e(old('bill_date', $bill['transaction_date'])) ?>" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"></div>
      <div><label class="mb-1 block text-xs font-bold text-slate-600">Total (Rs.) *</label><input type="number" name="amount" required min="0.01" step="0.01" value="<?= e(old('amount', $bill['amount'])) ?>" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"></div>
    </div>
    <div><label class="mb-1 block text-xs font-bold text-slate-600">Notes</label><textarea name="notes" rows="3" class="w-full rounded-xl border-0 px-3 py-3 ring-1 ring-slate-200"><?= e(old('notes', $bill['notes'])) ?></textarea></div>
  </div>
  <div class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">Saving recalculates this customer's full running ledger. The original correction remains traceable in the audit log.</div>
  <div class="fixed bottom-[69px] left-0 right-0 z-30 flex gap-3 border-t border-slate-200 bg-white/95 p-4 backdrop-blur md:bottom-0 md:left-64">
    <a href="<?= e(url('customers/' . $customerId . '?tab=ledger')) ?>" class="btn btn-outline flex-1">Cancel</a>
    <button class="btn btn-primary flex-[2]">Save Correction</button>
  </div>
</form>
