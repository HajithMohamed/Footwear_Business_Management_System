<?php
$today = $today ?? date('Y-m-d');
$creditDays = max(1, (int) ($creditDays ?? 30));
$oldBillDate = old('bill_date', $today);
$dueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $oldBillDate)
    ? (new DateTimeImmutable((string) $oldBillDate))->modify("+{$creditDays} days")->format('Y-m-d')
    : (new DateTimeImmutable($today))->modify("+{$creditDays} days")->format('Y-m-d');
?>

<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url("customers/{$customer['id']}")) ?>" class="h-10 w-10 flex items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-slate-200 text-slate-600 active:scale-95 transition">
    <span class="text-xl">←</span>
  </a>
  <h1 class="text-xl font-bold text-slate-800">Add Bill</h1>
</div>

<div class="mb-4 rounded-xl bg-blue-50 p-4 text-xs font-medium text-blue-800 ring-1 ring-blue-200 shadow-sm flex items-start gap-3">
  <?= ui_icon('info', 'mt-0.5 h-5 w-5 shrink-0') ?>
  <div><p>Add the number and total from an already prepared customer bill. OCR updates the customer's credit ledger only; it will not create products or change stock.</p><a href="<?= e(url('purchases/import')) ?>" class="mt-1 inline-flex font-bold text-brand-700">Scan a supplier purchase invoice →</a></div>
</div>

<form method="post" action="<?= e(url("customers/{$customer['id']}/bill")) ?>" enctype="multipart/form-data" class="pb-40 md:pb-24"
      x-data="{ reading: false, ocrMessage: '', async readBill() { const file = this.$refs.billImage.files[0]; if (!file) { this.ocrMessage = 'Choose the bill image first.'; return; } this.reading = true; this.ocrMessage = ''; const body = new FormData(); body.append('document', file); body.append('_token', '<?= e(csrf_token()) ?>'); try { const response = await fetch('<?= e(url('ocr/bill')) ?>', {method:'POST', body, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}}); const result = await response.json(); if (!result.ok) throw new Error(result.reason || 'The bill could not be read.'); if (result.data.amount) this.$refs.billAmount.value = result.data.amount; if (result.data.bill_number) this.$refs.billNumber.value = result.data.bill_number; if (result.data.bill_date) this.$refs.billDate.value = result.data.bill_date; this.ocrMessage = result.message; } catch (error) { this.ocrMessage = error.message; } finally { this.reading = false; } } }">
  <?= csrf_field() ?>

  <!-- Details Card -->
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
    <div class="mb-5 pb-4 border-b border-slate-100 flex justify-between items-center">
      <div>
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Customer</p>
        <p class="flex items-center gap-1.5 truncate text-sm font-bold text-slate-800"><?= ui_icon('users', 'h-4 w-4') ?> <?= e($customer['name']) ?></p>
      </div>
      <div class="text-right">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide"><?= $customer['outstanding_due'] < 0 ? 'Available Customer Credit' : 'Current Outstanding' ?></p>
        <p class="text-lg font-bold <?= $customer['outstanding_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>">Rs. <?= number_format(abs($customer['outstanding_due']), 0) ?></p>
        <?php if ($customer['outstanding_due'] < 0): ?><p class="text-xs text-green-600">Applied automatically to this bill.</p><?php endif; ?>
      </div>
    </div>
    
    <div class="space-y-4">
      <!-- Bill Total -->
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Bill Total (Rs.) <span class="text-red-500">*</span></label>
        <input x-ref="billAmount" type="number" name="amount" value="<?= e(old('amount')) ?>" step="0.01" min="0.01" required placeholder="0.00"
               class="w-full rounded-xl border-0 bg-blue-50 px-4 py-4 text-2xl font-bold text-brand-700 ring-1 ring-blue-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition text-right">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Bill Number <span class="text-red-500">*</span></label>
          <input x-ref="billNumber" type="text" name="bill_number" value="<?= e(old('bill_number')) ?>" required placeholder="e.g. B-1025"
                 class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm font-bold ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Bill Date <span class="text-red-500">*</span></label>
          <input x-ref="billDate" type="date" name="bill_date" value="<?= e($oldBillDate) ?>" required
                 class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm font-bold ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($recentTransactions)): ?>
    <div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Recent ledger activity</p>
      <?php foreach ($recentTransactions as $txn): ?>
        <div class="flex justify-between border-t border-slate-50 py-2 text-xs first:border-0">
          <span class="text-slate-600"><?= e(date('j M Y', strtotime($txn['transaction_date'] ?: $txn['created_at']))) ?> · <?= $txn['transaction_type'] === 'payment' ? 'Payment' : 'Bill' ?></span>
          <span class="font-bold <?= $txn['transaction_type'] === 'payment' ? 'text-green-600' : 'text-amber-600' ?>"><?= $txn['transaction_type'] === 'payment' ? '−' : '+' ?><?= money($txn['amount']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Optional copy of the physical bill -->
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
    <label class="mb-1.5 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-700"><?= ui_icon('image', 'h-4 w-4') ?> Physical bill image <span class="font-normal normal-case text-slate-400">(optional)</span></label>
    <p class="mb-3 text-xs text-slate-500">Take a photo or choose an existing JPG, PNG or WebP image. Local OCR can suggest the bill number, date and total.</p>
    <input x-ref="billImage" type="file" name="bill_image" accept="image/jpeg,image/png,image/webp" capture="environment" class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:font-bold file:text-brand-700">
    <button type="button" @click="readBill()" :disabled="reading" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-50">
      <?= ui_icon('search', 'h-4 w-4') ?> <span x-text="reading ? 'Reading bill…' : 'Read bill with OCR'"></span>
    </button>
    <p x-show="ocrMessage" x-text="ocrMessage" class="mt-2 text-xs font-medium text-amber-700"></p>
  </div>
  
  <div class="text-center">
    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Reminders will trigger based on your <?= $creditDays ?>-day credit term.</p>
  </div>

  <!-- Sticky Action Bar -->
  <div class="fixed bottom-[69px] left-0 right-0 md:bottom-0 md:left-64 z-40 bg-white border-t border-slate-200 p-4 shadow-[0_-10px_30px_rgba(0,0,0,0.05)]">
    <div class="max-w-3xl mx-auto flex gap-3">
      <a href="<?= e(url("customers/{$customer['id']}")) ?>" 
         class="flex-1 flex justify-center items-center h-12 rounded-xl bg-slate-100 text-slate-600 font-bold active:scale-95 transition">
        Cancel
      </a>
      <button type="submit" 
              class="flex-[2] flex justify-center items-center h-12 rounded-xl bg-brand-600 text-white font-bold shadow-sm active:scale-95 transition text-lg">
        Save Bill
      </button>
    </div>
  </div>

</form>
