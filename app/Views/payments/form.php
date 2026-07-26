<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url("customers/{$customer['id']}")) ?>" class="text-2xl">←</a>
  <h1 class="text-lg font-bold text-slate-800"><?= e($title) ?></h1>
</div>

<form method="post" action="<?= e(url("customers/{$customer['id']}/payment")) ?>" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100" x-data="{ method: '<?= request('payment_method') ?? 'cash' ?>' }">
  <?= csrf_field() ?>

  <div class="grid gap-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
      <p class="text-sm font-semibold text-slate-700 mb-2">Customer: <?= e($customer['name']) ?></p>
      <p class="text-xs text-slate-500">Credit Limit: Rs. <?= number_format($customer['credit_limit'], 2) ?> | Due: Rs. <?= number_format($customer['outstanding_due'], 2) ?></p>
    </div>

    <div class="sm:col-span-2">
      <label class="block text-sm font-semibold text-slate-700 mb-1">Amount (Rs.) *</label>
      <input type="number" name="amount" step="0.01" required min="0" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-600">
    </div>

    <div class="sm:col-span-2">
      <label class="block text-sm font-semibold text-slate-700 mb-1">Payment Method *</label>
      <select name="payment_method" required @change="method = $event.target.value" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="cash">Cash</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="cheque">Cheque</option>
        <option value="card">Card</option>
      </select>
    </div>

    <div class="sm:col-span-2">
      <label class="block text-sm font-semibold text-slate-700 mb-1">Reference</label>
      <input type="text" name="reference" placeholder="Invoice, receipt, deposit slip…" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </div>

    <!-- Cheque fields (only show if method = cheque) -->
    <div x-show="method === 'cheque'" class="sm:col-span-2 p-4 bg-slate-50 rounded-lg border border-slate-200">
      <h3 class="text-sm font-semibold text-slate-700 mb-3">Cheque Details</h3>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1">Cheque Number *</label>
          <input type="text" name="cheque_number" placeholder="e.g. 123456" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1">Bank Name</label>
          <input type="text" name="bank_name" placeholder="e.g. Bank of Ceylon" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>

        <div class="sm:col-span-2">
          <label class="block text-sm font-semibold text-slate-700 mb-1">Cheque Date *</label>
          <input type="date" name="cheque_date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
      </div>
    </div>

    <div class="sm:col-span-2">
      <label class="block text-sm font-semibold text-slate-700 mb-1">Notes</label>
      <textarea name="notes" rows="2" placeholder="Additional notes…" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
    </div>
  </div>

  <div class="mt-6 flex gap-2">
    <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
      Record Payment
    </button>
    <a href="<?= e(url("customers/{$customer['id']}")) ?>" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
  </div>
</form>
