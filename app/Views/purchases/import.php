<div class="mb-4">
  <a href="<?= e(url('purchases')) ?>" class="text-sm text-brand-600">&larr; Purchases</a>
  <h1 class="mt-1 text-lg font-bold text-slate-800">New Purchase</h1>
  <p class="text-sm text-slate-500"><?= !empty($supplierName) ? 'Add another bill for ' . e($supplierName) . '.' : 'Upload the supplier\'s invoice, or type it in yourself.' ?></p>
  <p class="mt-2 rounded-xl bg-blue-50 px-3 py-2 text-xs leading-5 text-blue-800 ring-1 ring-blue-200">Upload one supplier invoice at a time. The same clearance person can later receive multiple invoices as separate assignments.</p>
</div>

<?php if (!$extractionOnline): ?>
  <div class="mb-4 flex items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
    <?= ui_icon('info', 'mt-0.5 h-5 w-5 shrink-0') ?>
    <p>Automatic reading is unavailable right now. Your invoice can still be attached while you enter and review the purchase details.</p>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('purchases/import')) ?>" enctype="multipart/form-data"
      x-data="{ file: '' }" class="space-y-4">
  <?= csrf_field() ?>
  <input type="hidden" name="supplier_hint" value="<?= e($supplierName ?? '') ?>">

  <!-- File -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700"><?= ui_icon('purchase', 'h-5 w-5') ?> Supplier invoice</label>
    <p class="mb-3 text-xs leading-5 text-slate-500">Choose the Indian supplier invoice as a PDF or a clear photo. You will review every detail before it is saved.</p>
    <input type="file" name="document" required accept="application/pdf,image/jpeg,image/png,image/webp"
           @change="file = $event.target.files[0]?.name ?? ''"
           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
    <p class="mt-2 text-xs text-slate-400">PDF, JPG, PNG or WebP, up to 20 MB.</p>
  </div>

  <button class="w-full rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm active:scale-[.99]">
    Review purchase &rarr;
  </button>

  <p class="text-center text-xs text-slate-400">
    OCR creates an editable draft only. Nothing is saved until you review and confirm the purchase.
  </p>
</form>

<div class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <p class="text-sm font-semibold text-slate-700">No invoice to scan?</p>
  <p class="mt-1 text-xs text-slate-500">Enter the purchase details by hand instead.</p>
  <a href="<?= e(url('purchases/create' . (!empty($supplierName) ? '?supplier=' . rawurlencode($supplierName) : ''))) ?>" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-white"><?= ui_icon('pencil', 'h-4 w-4') ?> Enter purchase manually</a>
</div>
