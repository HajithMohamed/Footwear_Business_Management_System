<div class="mb-4">
  <a href="<?= e(url('purchases')) ?>" class="text-sm text-brand-600">&larr; Purchases</a>
  <h1 class="mt-1 text-lg font-bold text-slate-800">New Purchase</h1>
  <p class="text-sm text-slate-500">Upload the supplier's invoice, or type it in yourself.</p>
</div>

<?php if (!$extractionOnline): ?>
  <div class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
    Automatic reading is switched off — <code class="text-xs">ANTHROPIC_API_KEY</code> is not set.
    You can still upload the invoice for reference and enter the details by hand.
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('purchases/import')) ?>" enctype="multipart/form-data"
      x-data="{ kind: 'pdf', file: '' }" class="space-y-4">
  <?= csrf_field() ?>

  <!-- Method picker -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="mb-3 text-sm font-semibold text-slate-700">What are you uploading?</p>
    <div class="space-y-2">
      <label class="flex items-start gap-3 rounded-xl p-3 ring-1 transition"
             :class="kind === 'pdf' ? 'bg-brand-50 ring-brand-600' : 'bg-slate-50 ring-transparent'">
        <input type="radio" name="document_type" value="supplier_invoice_pdf" x-model="kind" class="mt-1">
        <span>
          <span class="block text-sm font-medium text-slate-800">📄 Printed PDF invoice</span>
          <span class="block text-xs text-slate-500">A computer-generated invoice file. Read most accurately.</span>
        </span>
      </label>

      <label class="flex items-start gap-3 rounded-xl p-3 ring-1 transition"
             :class="kind === 'image' ? 'bg-brand-50 ring-brand-600' : 'bg-slate-50 ring-transparent'">
        <input type="radio" name="document_type" value="invoice_image" x-model="kind" class="mt-1">
        <span>
          <span class="block text-sm font-medium text-slate-800">📷 Photo of a printed invoice</span>
          <span class="block text-xs text-slate-500">Snap the printed bill. Keep the whole page in frame.</span>
        </span>
      </label>

      <label class="flex items-start gap-3 rounded-xl p-3 ring-1 transition"
             :class="kind === 'handwritten' ? 'bg-brand-50 ring-brand-600' : 'bg-slate-50 ring-transparent'">
        <input type="radio" name="document_type" value="handwritten" x-model="kind" class="mt-1">
        <span>
          <span class="block text-sm font-medium text-slate-800">✍️ Handwritten supplier note</span>
          <span class="block text-xs text-slate-500">Every line will be checked by you before saving.</span>
        </span>
      </label>
    </div>
  </div>

  <!-- File -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <label class="block text-sm font-semibold text-slate-700 mb-2">File</label>
    <input type="file" name="document" required accept="application/pdf,image/jpeg,image/png,image/webp"
           :capture="kind === 'handwritten' || kind === 'image' ? 'environment' : false"
           @change="file = $event.target.files[0]?.name ?? ''"
           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
    <p class="mt-2 text-xs text-slate-400">PDF, JPG, PNG or WebP, up to 20 MB.</p>
  </div>

  <button class="w-full rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm active:scale-[.99]">
    Read invoice &rarr;
  </button>

  <p class="text-center text-xs text-slate-400">
    Nothing is saved yet — you will see everything that was read and can correct it first.
  </p>
</form>

<div class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <p class="text-sm font-semibold text-slate-700">No invoice to scan?</p>
  <p class="mt-1 text-xs text-slate-500">Enter the purchase details by hand instead.</p>
  <a href="<?= e(url('purchases/create')) ?>" class="mt-3 inline-block rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-white">✍️ Manual entry</a>
</div>

<div class="mt-4 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
  <p class="text-sm font-semibold text-slate-700">🧮 Your own calculation notes</p>
  <p class="mt-1 text-xs text-slate-500">
    Notebook workings and running totals are not invoices and never create a purchase.
    Save them separately and attach them to a shipment.
  </p>
  <a href="<?= e(url('notes')) ?>" class="mt-3 inline-block rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200">Calculation notes</a>
</div>
