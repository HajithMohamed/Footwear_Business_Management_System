<?php
$oldNumbers = (array) old('cheque_number', []);
$oldBanks = (array) old('bank_name', []);
$oldDates = (array) old('cheque_date', []);
$oldDeposits = (array) old('deposit_date', []);
$oldAmounts = (array) old('cheque_amount', []);
$chequeRows = [];
foreach ($oldNumbers as $i => $number) {
    $chequeRows[] = [
        'number' => (string) $number,
        'bank' => (string) ($oldBanks[$i] ?? ''),
        'date' => (string) ($oldDates[$i] ?? date('Y-m-d')),
        'depositDate' => (string) ($oldDeposits[$i] ?? ''),
        'amount' => (string) ($oldAmounts[$i] ?? ''),
        'ocrMessage' => '',
        'reading' => false,
    ];
}
if (!$chequeRows) {
    $chequeRows[] = ['number' => '', 'bank' => '', 'date' => date('Y-m-d'), 'depositDate' => '', 'amount' => '', 'ocrMessage' => '', 'reading' => false];
}
$paymentInit = json_encode([
    'method' => (string) old('payment_method', 'cash'),
    'outstanding' => (float) $customer['outstanding_due'],
    'cashAmount' => (float) old('amount', 0),
    'cheques' => $chequeRows,
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url("customers/{$customer['id']}")) ?>" class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-slate-600 shadow-sm ring-1 ring-slate-200 active:scale-95"><span class="text-xl">←</span></a>
  <div><h1 class="text-xl font-bold text-slate-800">Record Payment</h1><p class="text-xs text-slate-500">Cash or one/more dated cheques</p></div>
</div>

<form method="post" action="<?= e(url("customers/{$customer['id']}/payment")) ?>" enctype="multipart/form-data" class="pb-40 md:pb-24" x-data='paymentForm(<?= $paymentInit ?>)'>
  <?= csrf_field() ?>

  <div class="mb-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <div class="mb-5 flex items-center justify-between border-b border-slate-100 pb-4">
      <div><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Customer</p><p class="flex items-center gap-1.5 text-sm font-bold text-slate-800"><?= ui_icon('users', 'h-4 w-4') ?> <?= e($customer['name']) ?></p></div>
      <div class="text-right"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400"><?= $customer['outstanding_due'] < 0 ? 'Customer credit' : 'Outstanding' ?></p><p class="text-lg font-bold <?= $customer['outstanding_due'] < 0 ? 'text-green-600' : 'text-red-600' ?>"><?= money(abs($customer['outstanding_due'])) ?></p></div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <label class="block"><span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-700">Received Date</span><input type="date" name="payment_date" value="<?= e(old('payment_date', date('Y-m-d'))) ?>" required class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm font-bold ring-1 ring-slate-200"></label>
      <label class="block"><span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-700">Method</span><select name="payment_method" x-model="method" required class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm font-bold ring-1 ring-slate-200"><option value="cash">Cash</option><option value="cheque">Cheque(s)</option></select></label>
    </div>

    <div x-show="method === 'cash'" class="mt-4">
      <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-700">Cash Received (Rs.)</label>
      <input type="number" name="amount" x-model.number="cashAmount" step="0.01" min="0.01" :required="method === 'cash'" :disabled="method !== 'cash'" class="w-full rounded-xl border-0 bg-green-50 px-4 py-4 text-right text-2xl font-bold text-green-700 ring-1 ring-green-200">
    </div>
    <div x-show="method === 'cheque'" style="display:none" class="mt-4 rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200">
      <p class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Total of all cheques</p>
      <p class="mt-1 text-2xl font-bold text-amber-800" x-text="money(chequeTotal)"></p>
      <input type="hidden" name="amount" :value="chequeTotal" :disabled="method !== 'cheque'">
    </div>
  </div>

  <div class="mb-4 grid grid-cols-3 gap-2 rounded-2xl bg-slate-900 p-4 text-white shadow-sm">
    <div><p class="text-[9px] font-bold uppercase tracking-wide text-slate-400" x-text="outstanding < 0 ? 'Customer credit' : 'Outstanding'"></p><p class="mt-1 text-xs font-bold" x-text="money(Math.abs(outstanding))"></p></div>
    <div class="text-center"><p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Payment</p><p class="mt-1 text-xs font-bold text-green-300" x-text="money(total)"></p></div>
    <div class="text-right"><p class="text-[9px] font-bold uppercase tracking-wide text-slate-400" x-text="outstanding - total < 0 ? 'Credit after payment' : 'Remaining due'"></p><p class="mt-1 text-sm font-bold" :class="total > outstanding ? 'text-green-300' : ''" x-text="money(Math.abs(outstanding - total))"></p></div>
  </div>
  <p class="mb-4 text-sm text-slate-500">Any extra payment is saved as customer credit and automatically deducted from upcoming bills.</p>

  <div x-show="method === 'cheque'" style="display:none" class="mb-4 space-y-3">
    <template x-for="(cheque, index) in cheques" :key="index">
      <div data-cheque-row class="rounded-2xl bg-amber-50 p-4 shadow-sm ring-1 ring-amber-200">
        <div class="mb-3 flex items-center justify-between border-b border-amber-200/60 pb-2">
          <h2 class="text-xs font-bold uppercase tracking-wide text-amber-800" x-text="'Cheque ' + (index + 1)"></h2>
          <button type="button" x-show="cheques.length > 1" @click="removeCheque(index)" class="inline-flex items-center gap-1 text-xs font-bold text-red-600"><?= ui_icon('trash', 'h-4 w-4') ?> Remove</button>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="block"><span class="mb-1 block text-xs font-bold text-amber-800">Cheque Number *</span><input name="cheque_number[]" x-model="cheque.number" :required="method === 'cheque'" class="w-full rounded-xl border-0 bg-white px-3 py-2.5 text-sm font-bold ring-1 ring-amber-200"></label>
          <label class="block"><span class="mb-1 block text-xs font-bold text-amber-800">Amount (Rs.) *</span><input name="cheque_amount[]" x-model.number="cheque.amount" type="number" min="0.01" step="0.01" :required="method === 'cheque'" class="w-full rounded-xl border-0 bg-white px-3 py-2.5 text-right text-sm font-bold ring-1 ring-amber-200"></label>
          <label class="block"><span class="mb-1 block text-xs font-bold text-amber-800">Cheque Date *</span><input name="cheque_date[]" x-model="cheque.date" type="date" :required="method === 'cheque'" class="w-full rounded-xl border-0 bg-white px-3 py-2.5 text-sm font-bold ring-1 ring-amber-200"></label>
          <label class="block"><span class="mb-1 block text-xs font-bold text-amber-800">Deposit Date</span><input name="deposit_date[]" x-model="cheque.depositDate" type="date" class="w-full rounded-xl border-0 bg-white px-3 py-2.5 text-sm font-bold ring-1 ring-amber-200"></label>
          <label class="col-span-2 block"><span class="mb-1 block text-xs font-bold text-amber-800">Bank</span><input name="bank_name[]" x-model="cheque.bank" placeholder="Bank of Ceylon" class="w-full rounded-xl border-0 bg-white px-3 py-2.5 text-sm ring-1 ring-amber-200"></label>
          <div class="col-span-2">
            <label class="mb-1 flex items-center gap-2 text-xs font-bold text-amber-800"><?= ui_icon('image', 'h-4 w-4') ?> Cheque image <span class="font-normal text-amber-600">(optional)</span></label>
            <input type="file" name="cheque_image[]" accept="image/jpeg,image/png,image/webp" capture="environment" class="block w-full text-xs text-amber-700 file:mr-2 file:rounded-xl file:border-0 file:bg-white file:px-3 file:py-2 file:font-bold file:text-amber-800">
            <button type="button" @click="readCheque(cheque, $event.currentTarget.parentElement.querySelector('input[type=file]'))" :disabled="cheque.reading" class="mt-2 inline-flex items-center gap-2 rounded-xl bg-amber-800 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"><?= ui_icon('search', 'h-4 w-4') ?> <span x-text="cheque.reading ? 'Reading…' : 'Read cheque with OCR'"></span></button>
            <p x-show="cheque.ocrMessage" x-text="cheque.ocrMessage" class="mt-1.5 text-xs font-medium text-amber-800"></p>
          </div>
        </div>
      </div>
    </template>

    <button type="button" @click="addCheque()" class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-amber-300 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800"><?= ui_icon('plus', 'h-4 w-4') ?> Add Another Cheque</button>
    <p class="text-center text-xs text-slate-500">Each cheque is stored separately with its own amount, cheque date, deposit date, status and image.</p>
  </div>

  <?php if (!empty($recentTransactions)): ?>
    <div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100"><p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Recent ledger activity</p><?php foreach ($recentTransactions as $txn): ?><div class="flex justify-between border-t border-slate-50 py-2 text-xs first:border-0"><span class="text-slate-600"><?= e(date('j M Y', strtotime($txn['transaction_date'] ?: $txn['created_at']))) ?> · <?= $txn['transaction_type'] === 'payment' ? 'Payment' : 'Bill' ?></span><span class="font-bold <?= $txn['transaction_type'] === 'payment' ? 'text-green-600' : 'text-amber-600' ?>"><?= $txn['transaction_type'] === 'payment' ? '−' : '+' ?><?= money($txn['amount']) ?></span></div><?php endforeach; ?></div>
  <?php endif; ?>

  <div class="fixed bottom-[69px] left-0 right-0 z-40 border-t border-slate-200 bg-white p-4 shadow-[0_-10px_30px_rgba(0,0,0,0.05)] md:bottom-0 md:left-64">
    <div class="mx-auto flex max-w-3xl gap-3">
      <a href="<?= e(url("customers/{$customer['id']}")) ?>" class="flex h-12 flex-1 items-center justify-center rounded-xl bg-slate-100 font-bold text-slate-600">Cancel</a>
      <button type="submit" :disabled="total <= 0" class="flex h-12 flex-[2] items-center justify-center rounded-xl bg-green-600 text-lg font-bold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-50" x-text="method === 'cheque' && cheques.length > 1 ? 'Save ' + cheques.length + ' Cheques' : 'Save Payment'"></button>
    </div>
  </div>
</form>

<script>
function paymentForm(init) {
  return {
    method: init.method,
    outstanding: Number(init.outstanding || 0),
    cashAmount: Number(init.cashAmount || 0),
    cheques: init.cheques,
    get chequeTotal() { return this.cheques.reduce((sum, cheque) => sum + (Number(cheque.amount) || 0), 0); },
    get total() { return this.method === 'cheque' ? this.chequeTotal : (Number(this.cashAmount) || 0); },
    money(value) { return 'Rs. ' + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
    addCheque() { this.cheques.push({number:'', bank:'', date:'<?= e(date('Y-m-d')) ?>', depositDate:'', amount:'', ocrMessage:'', reading:false}); },
    removeCheque(index) { this.cheques.splice(index, 1); if (!this.cheques.length) this.addCheque(); },
    async readCheque(cheque, input) {
      const file = input && input.files ? input.files[0] : null;
      if (!file) { cheque.ocrMessage = 'Choose this cheque image first.'; return; }
      cheque.reading = true;
      cheque.ocrMessage = '';
      const body = new FormData();
      body.append('document', file);
      body.append('_token', '<?= e(csrf_token()) ?>');
      try {
        const response = await fetch('<?= e(url('ocr/cheque')) ?>', {method:'POST', body, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
        const result = await response.json();
        if (!result.ok) throw new Error(result.reason || 'The cheque could not be read.');
        if (result.data.cheque_number) cheque.number = result.data.cheque_number;
        if (result.data.bank_name) cheque.bank = result.data.bank_name;
        if (result.data.cheque_date) cheque.date = result.data.cheque_date;
        if (result.data.amount) cheque.amount = result.data.amount;
        cheque.ocrMessage = result.message;
      } catch (error) {
        cheque.ocrMessage = error.message;
      } finally {
        cheque.reading = false;
      }
    }
  };
}
</script>
