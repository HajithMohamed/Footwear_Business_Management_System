<?php use App\Services\StorageService; ?>

<div class="mb-4">
  <h1 class="text-lg font-bold text-slate-800">Calculation Notes</h1>
  <p class="text-sm text-slate-500">Your own workings — kept for reference, never turned into a purchase</p>
</div>

<div class="mb-4 rounded-xl bg-blue-50 px-4 py-3 text-xs text-blue-800 ring-1 ring-blue-200">
  Notebook pages with totals, discounts and running balances are not invoices.
  Snap them here and attach them to a shipment, so months later you can see how a price was worked out.
</div>

<!-- Capture -->
<form method="post" action="<?= e(url('notes')) ?>" enctype="multipart/form-data"
      class="mb-6 space-y-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <?= csrf_field() ?>
  <p class="text-sm font-semibold text-slate-700">📷 Capture a note</p>
  <input type="file" name="document" required accept="application/pdf,image/jpeg,image/png,image/webp" capture="environment"
         class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
  <input name="caption" placeholder="What is this working out? (optional)"
         class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200">
  <select name="purchase_id" class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200">
    <option value="">Attach later</option>
    <?php foreach ($purchases as $p): ?>
      <option value="<?= (int) $p['id'] ?>"><?= e($p['purchase_number']) ?> — <?= e($p['supplier_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="w-full rounded-lg bg-brand-600 px-3 py-2.5 text-sm font-semibold text-white">Save note</button>
</form>

<h2 class="mb-2 text-sm font-semibold text-slate-500">Not yet attached</h2>
<div class="space-y-2">
  <?php foreach ($unfiled as $note): ?>
    <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-center gap-3">
        <a href="<?= e(StorageService::url($note['path'])) ?>" target="_blank" rel="noopener" class="shrink-0">
          <?php if ($note['thumb_path']): ?>
            <img src="<?= e(StorageService::url($note['thumb_path'])) ?>" alt="" class="h-12 w-12 rounded-lg object-cover">
          <?php else: ?>
            <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100 text-lg">🧮</span>
          <?php endif; ?>
        </a>
        <div class="min-w-0 flex-1">
          <p class="truncate text-xs font-medium text-slate-700"><?= e($note['caption'] ?: $note['original_name']) ?></p>
          <p class="text-[11px] text-slate-400"><?= e(date('j M Y', strtotime($note['created_at']))) ?></p>
        </div>
        <form method="post" action="<?= e(url('attachments/' . $note['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this note?')">
          <?= csrf_field() ?>
          <button class="text-xs text-red-600">Delete</button>
        </form>
      </div>

      <form method="post" action="<?= e(url('notes/' . $note['id'] . '/attach')) ?>" class="mt-2 flex gap-2">
        <?= csrf_field() ?>
        <select name="purchase_id" required class="flex-1 rounded-lg px-2.5 py-1.5 text-xs ring-1 ring-slate-200">
          <option value="">Attach to…</option>
          <?php foreach ($purchases as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= e($p['purchase_number']) ?> — <?= e($p['supplier_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white">Attach</button>
      </form>
    </div>
  <?php endforeach; ?>

  <?php if (!$unfiled): ?>
    <p class="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100">
      No unattached notes.
    </p>
  <?php endif; ?>
</div>
