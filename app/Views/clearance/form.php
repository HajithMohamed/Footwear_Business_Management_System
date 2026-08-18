<?php
$action = $person
    ? url('clearance-persons/' . $person['id'])
    : url('clearance-persons');
?>

<div class="mb-4">
  <a href="<?= e(url('clearance-persons')) ?>" class="text-sm text-brand-600">&larr; Clearance persons</a>
  <h1 class="mt-1 text-lg font-bold text-slate-800"><?= e($title) ?></h1>
</div>

<form method="post" action="<?= e($action) ?>" class="space-y-4">
  <?= csrf_field() ?>

  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Name *</label>
      <input name="name" required value="<?= e(old('name', $person['name'] ?? '')) ?>"
             class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
      <?php if ($msg = error('name')): ?><p class="mt-1 text-xs text-red-600"><?= e($msg) ?></p><?php endif; ?>
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Mobile number</label>
      <input name="phone" type="tel" inputmode="tel" placeholder="+94 77 123 4567" value="<?= e(old('phone', $person['phone'] ?? '')) ?>"
             class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
      <p class="mt-1 text-xs text-slate-400">Local numbers are saved automatically with +94.</p>
      <?php if ($msg = error('phone')): ?><p class="mt-1 text-xs text-red-600"><?= e($msg) ?></p><?php endif; ?>
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Address</label>
      <textarea name="address" rows="2" class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200"><?= e(old('address', $person['address'] ?? '')) ?></textarea>
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Rate per kilo</label>
      <input name="wage_per_kilo" type="number" step="0.01" min="0"
             value="<?= e(old('wage_per_kilo', $person['wage_per_kilo'] ?? '0')) ?>"
             class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
      <p class="mt-1 text-xs text-slate-400">Used to work out the clearance cost of each shipment from its weight.</p>
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Notes</label>
      <textarea name="notes" rows="2" class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200"><?= e(old('notes', $person['notes'] ?? '')) ?></textarea>
    </div>

    <label class="flex items-center gap-2">
      <input type="checkbox" name="is_active" value="1" <?= (!$person || (int) $person['is_active']) ? 'checked' : '' ?>>
      <span class="text-sm text-slate-700">Active — can be assigned new shipments</span>
    </label>
  </div>

  <button class="w-full rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm">
    <?= $person ? 'Save changes' : 'Add clearance person' ?>
  </button>
</form>
