<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url("customers/{$customer['id']}")) ?>" class="h-10 w-10 flex items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-slate-200 text-slate-600 active:scale-95 transition">
    <span class="text-xl">←</span>
  </a>
  <h1 class="text-xl font-bold text-slate-800">Record Payment</h1>
</div>

<form method="post" action="<?= e(url("customers/{$customer['id']}/payment")) ?>" class="pb-24" x-data="{ method: '<?= request('payment_method') ?? 'cash' ?>' }">
  <?= csrf_field() ?>

  <!-- Payment Details -->
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
    <div class="mb-5 pb-4 border-b border-slate-100 flex justify-between items-center">
      <div>
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Customer</p>
        <p class="text-sm font-bold text-slate-800 truncate">👤 <?= e($customer['name']) ?></p>
      </div>
      <div class="text-right">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Current Outstanding</p>
        <p class="text-lg font-bold text-red-600">Rs. <?= number_format($customer['outstanding_due'], 0) ?></p>
      </div>
    </div>
    
    <div class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Amount Received (Rs.) <span class="text-red-500">*</span></label>
        <input type="number" name="amount" step="10" required min="0" value="<?= e($customer['outstanding_due'] > 0 ? $customer['outstanding_due'] : '') ?>"
               class="w-full rounded-xl border-0 bg-green-50 px-4 py-4 text-2xl font-bold text-green-700 ring-1 ring-green-200 focus:bg-white focus:ring-2 focus:ring-green-600 transition text-right">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Date</label>
          <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required 
                 class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm font-bold ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Payment Method</label>
          <select name="payment_method" required x-model="method"
                  class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm font-bold ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
            <option value="cash">💵 Cash</option>
            <option value="bank_transfer">🏦 Bank Transfer</option>
            <option value="cheque">📋 Cheque</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Cheque Details (Only shows if cheque selected) -->
  <div x-show="method === 'cheque'" style="display: none;" x-transition class="rounded-2xl bg-amber-50 p-5 shadow-sm ring-1 ring-amber-200 mb-4">
    <h2 class="text-xs font-bold text-amber-700 uppercase tracking-wide mb-4 pb-2 border-b border-amber-200/50">Cheque Information</h2>
    
    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-amber-800 mb-1.5 uppercase tracking-wide">Cheque Number <span class="text-red-500">*</span></label>
          <input type="text" name="cheque_number" placeholder="000000" :required="method === 'cheque'"
                 class="w-full rounded-xl border-0 bg-white px-4 py-3 text-sm font-bold ring-1 ring-amber-200 focus:ring-2 focus:ring-amber-500 transition">
        </div>
        <div>
          <label class="block text-xs font-bold text-amber-800 mb-1.5 uppercase tracking-wide">Bank</label>
          <input type="text" name="bank_name" placeholder="Bank of Ceylon"
                 class="w-full rounded-xl border-0 bg-white px-4 py-3 text-sm ring-1 ring-amber-200 focus:ring-2 focus:ring-amber-500 transition">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-amber-800 mb-1.5 uppercase tracking-wide">Cheque Date <span class="text-red-500">*</span></label>
          <input type="date" name="cheque_date" value="<?= date('Y-m-d') ?>" :required="method === 'cheque'"
                 class="w-full rounded-xl border-0 bg-white px-4 py-3 text-sm font-bold ring-1 ring-amber-200 focus:ring-2 focus:ring-amber-500 transition">
        </div>
        <div>
          <label class="block text-xs font-bold text-amber-800 mb-1.5 uppercase tracking-wide">Deposit Date</label>
          <input type="date" name="deposit_date"
                 class="w-full rounded-xl border-0 bg-white px-4 py-3 text-sm font-bold ring-1 ring-amber-200 focus:ring-2 focus:ring-amber-500 transition">
        </div>
      </div>
    </div>
  </div>

  <!-- Additional -->
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
    <div>
      <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Reference / Notes</label>
      <input type="text" name="reference" placeholder="Invoice #, Receipt #, or Notes..."
             class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
    </div>
  </div>

  <!-- Sticky Action Bar -->
  <div class="fixed bottom-0 left-0 right-0 sm:left-64 z-40 bg-white border-t border-slate-200 p-4 shadow-[0_-10px_30px_rgba(0,0,0,0.05)]">
    <div class="max-w-3xl mx-auto flex gap-3">
      <a href="<?= e(url("customers/{$customer['id']}")) ?>" 
         class="flex-1 flex justify-center items-center h-12 rounded-xl bg-slate-100 text-slate-600 font-bold active:scale-95 transition">
        Cancel
      </a>
      <button type="submit" 
              class="flex-[2] flex justify-center items-center h-12 rounded-xl bg-green-600 text-white font-bold shadow-sm active:scale-95 transition text-lg">
        Save Payment
      </button>
    </div>
  </div>

</form>
