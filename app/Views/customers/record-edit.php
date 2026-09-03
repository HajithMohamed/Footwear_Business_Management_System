<div class="mb-5">
  <h1 class="page-header-title">Edit Balance Record</h1>
  <p class="mt-1 text-sm text-slate-500">Saving updates this customer's running balances.</p>
</div>
<form method="post" action="<?= e(url('customer-records/' . $record['id'])) ?>" class="card space-y-4">
  <?= csrf_field() ?>
  <div><label for="record-date" class="mb-1 block text-sm font-bold">Date</label><input id="record-date" name="transaction_date" type="date" required value="<?= e(old('transaction_date', $record['transaction_date'] ?? substr($record['created_at'], 0, 10))) ?>" class="w-full rounded-xl border border-slate-200 p-3"></div>
  <div><label for="record-amount" class="mb-1 block text-sm font-bold">Amount (Rs.)</label><input id="record-amount" name="amount" type="number" step="0.01" min="-9999999999.99" max="9999999999.99" required value="<?= e(old('amount', $record['amount'])) ?>" class="w-full rounded-xl border border-slate-200 p-3"><p class="mt-1 text-xs text-slate-500">Positive amounts increase the amount owed; negative amounts give the customer credit.</p></div>
  <div><label for="record-description" class="mb-1 block text-sm font-bold">Description</label><input id="record-description" name="description" maxlength="255" value="<?= e(old('description', $record['description'])) ?>" class="w-full rounded-xl border border-slate-200 p-3"></div>
  <div class="flex gap-3"><a href="<?= e(url('customers/' . $record['customer_id'] . '?tab=ledger')) ?>" class="btn btn-outline">Cancel</a><button class="btn btn-primary">Save Changes</button></div>
</form>
