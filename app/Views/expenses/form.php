<?php
$isEdit = $expense !== null;
$action = $isEdit ? url("expenses/{$expense['id']}") : url('expenses');
$val = function (string $key, $default = '') use ($expense) {
    $old = old($key, null);
    if ($old !== null && $old !== '') {
        return $old;
    }
    return $expense[$key] ?? $default;
};
$methods = [
    'cash'          => 'Cash',
    'bank_transfer' => 'Bank transfer',
    'cheque'        => 'Cheque',
    'card'          => 'Card',
    'other'         => 'Other',
];
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('expenses')) ?>" class="text-2xl">←</a>
  <h1 class="text-lg font-bold text-slate-800"><?= e($title) ?></h1>
</div>

<form method="post" action="<?= e($action) ?>" class="space-y-4">
  <?= csrf_field() ?>

  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-4">
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Date *</label>
        <input type="date" name="expense_date" required
               value="<?= e($val('expense_date', $today)) ?>"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <?php if ($msg = error('expense_date')): ?>
          <p class="mt-1 text-xs text-red-600"><?= e($msg) ?></p>
        <?php endif; ?>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Amount (Rs.) *</label>
        <input type="number" name="amount" step="0.01" min="0.01" required
               value="<?= e($val('amount')) ?>"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <?php if ($msg = error('amount')): ?>
          <p class="mt-1 text-xs text-red-600"><?= e($msg) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
      <select name="category_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">— Uncategorised —</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (string) $val('category_id') === (string) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">Paid by *</label>
      <select name="payment_method" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <?php foreach ($methods as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $val('payment_method', 'cash') === $key ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">What was it for?</label>
      <input type="text" name="description" maxlength="255"
             value="<?= e($val('description')) ?>"
             placeholder="e.g. October shop rent"
             class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Paid to</label>
        <input type="text" name="payee" maxlength="120"
               value="<?= e($val('payee')) ?>"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Reference</label>
        <input type="text" name="reference" maxlength="100"
               value="<?= e($val('reference')) ?>"
               placeholder="Receipt / slip no."
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
      </div>
    </div>
  </div>

  <div class="flex gap-2 pb-4">
    <button type="submit" class="flex-1 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white">
      <?= $isEdit ? 'Save changes' : 'Record expense' ?>
    </button>
    <a href="<?= e(url('expenses')) ?>" class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-600">Cancel</a>
  </div>
</form>
