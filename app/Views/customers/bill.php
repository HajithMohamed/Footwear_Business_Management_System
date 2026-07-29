<?php
$today = $today ?? date('Y-m-d');
$creditDays = max(1, (int) ($creditDays ?? 30));
$oldBillDate = old('bill_date', $today);
$dueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $oldBillDate)
    ? (new DateTimeImmutable((string) $oldBillDate))->modify("+{$creditDays} days")->format('Y-m-d')
    : (new DateTimeImmutable($today))->modify("+{$creditDays} days")->format('Y-m-d');
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url("customers/{$customer['id']}")) ?>" class="text-2xl">&larr;</a>
  <div class="min-w-0">
    <h1 class="truncate text-lg font-bold text-slate-800">Attach Manual Bill</h1>
    <p class="text-xs text-slate-500"><?= e($customer['name']) ?></p>
  </div>
</div>

<div class="mb-3 rounded-xl bg-blue-50 px-4 py-3 text-xs text-blue-800 ring-1 ring-blue-200">
  Add the number and total from an already prepared bill. This updates credit tracking only; it will not create a
  product invoice or change stock.
</div>

<form method="post" action="<?= e(url("customers/{$customer['id']}/bill")) ?>"
      class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
  <?= csrf_field() ?>

  <div>
    <p class="text-sm font-semibold text-slate-700">Customer: <?= e($customer['name']) ?></p>
    <p class="text-xs text-slate-500">
      Current outstanding: <?= money($customer['outstanding_due']) ?>.
      Bills are treated as due after <?= (int) $creditDays ?> day(s).
    </p>
  </div>

  <div class="grid gap-4 sm:grid-cols-2">
    <label class="block">
      <span class="text-sm font-semibold text-slate-700">Bill number *</span>
      <input type="text" name="bill_number" value="<?= e(old('bill_number')) ?>" required
             placeholder="e.g. B-1025"
             class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </label>

    <label class="block">
      <span class="text-sm font-semibold text-slate-700">Bill date *</span>
      <input type="date" name="bill_date" value="<?= e($oldBillDate) ?>" required
             class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </label>

    <label class="block sm:col-span-2">
      <span class="text-sm font-semibold text-slate-700">Bill total (Rs.) *</span>
      <input type="number" name="amount" value="<?= e(old('amount')) ?>" step="0.01" min="0.01" required
             class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </label>

    <label class="block sm:col-span-2">
      <span class="text-sm font-semibold text-slate-700">Notes</span>
      <textarea name="notes" rows="2"
                class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= e(old('notes')) ?></textarea>
    </label>
  </div>

  <div class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500 ring-1 ring-slate-100">
    Due date for reminders: <?= e(date('d M Y', strtotime($dueDate))) ?>. The exact due date is calculated again from
    the saved bill date.
  </div>

  <div class="flex gap-2">
    <button class="flex-1 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white">
      Save bill to credit account
    </button>
    <a href="<?= e(url("customers/{$customer['id']}")) ?>"
       class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-600">Cancel</a>
  </div>
</form>
