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
  <h1 class="text-xl font-bold text-slate-800">Add Manual Bill</h1>
</div>

<div class="mb-4 rounded-xl bg-blue-50 p-4 text-xs font-medium text-blue-800 ring-1 ring-blue-200 shadow-sm flex items-start gap-3">
  <span class="text-lg mt-0.5">ℹ️</span>
  <p>Add the number and total from an already prepared paper bill. This will instantly update the customer's outstanding balance, but will not create a product invoice or change your stock.</p>
</div>

<form method="post" action="<?= e(url("customers/{$customer['id']}/bill")) ?>" class="pb-24">
  <?= csrf_field() ?>

  <!-- Details Card -->
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
    <div class="mb-5 pb-4 border-b border-slate-100 flex justify-between items-center">
      <div>
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Customer</p>
        <p class="text-sm font-bold text-slate-800 truncate">👤 <?= e($customer['name']) ?></p>
      </div>
      <div class="text-right">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Current Outstanding</p>
        <p class="text-lg font-bold <?= $customer['outstanding_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>">Rs. <?= number_format($customer['outstanding_due'], 0) ?></p>
      </div>
    </div>
    
    <div class="space-y-4">
      <!-- Bill Total -->
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Bill Total (Rs.) <span class="text-red-500">*</span></label>
        <input type="number" name="amount" value="<?= e(old('amount')) ?>" step="0.01" min="0.01" required placeholder="0.00"
               class="w-full rounded-xl border-0 bg-blue-50 px-4 py-4 text-2xl font-bold text-brand-700 ring-1 ring-blue-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition text-right">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Bill Number <span class="text-red-500">*</span></label>
          <input type="text" name="bill_number" value="<?= e(old('bill_number')) ?>" required placeholder="e.g. B-1025"
                 class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm font-bold ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Bill Date <span class="text-red-500">*</span></label>
          <input type="date" name="bill_date" value="<?= e($oldBillDate) ?>" required
                 class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm font-bold ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
        </div>
      </div>
    </div>
  </div>

  <!-- Additional -->
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
    <div>
      <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Notes</label>
      <input type="text" name="notes" value="<?= e(old('notes')) ?>" placeholder="Special terms or reference..."
             class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
    </div>
  </div>
  
  <div class="text-center">
    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Reminders will trigger based on your <?= $creditDays ?>-day credit term.</p>
  </div>

  <!-- Sticky Action Bar -->
  <div class="fixed bottom-0 left-0 right-0 sm:left-64 z-40 bg-white border-t border-slate-200 p-4 shadow-[0_-10px_30px_rgba(0,0,0,0.05)]">
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
