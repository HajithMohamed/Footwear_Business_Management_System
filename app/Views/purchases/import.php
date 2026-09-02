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
      x-data="invoiceUploader()" @submit.prevent="submit($el)" class="space-y-4" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="supplier_hint" value="<?= e($supplierName ?? '') ?>">

  <!-- File -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700"><?= ui_icon('purchase', 'h-5 w-5') ?> Supplier invoice</label>
    <p class="mb-3 text-xs leading-5 text-slate-500">Choose the Indian supplier invoice as a PDF or a clear photo. You will review every detail before it is saved.</p>
    <input type="file" name="document" required accept="application/pdf,image/jpeg,image/png,image/webp"
           @change="choose($event.target.files[0])" :disabled="busy"
           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
    <div x-show="file" class="mt-3 rounded-xl bg-slate-50 p-3 text-sm ring-1 ring-slate-200">
      <p class="font-semibold text-slate-700" x-text="file"></p>
      <p class="mt-0.5 text-xs text-slate-500" x-text="statusText"></p>
      <div x-show="state === 'uploading'" class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200" aria-label="Upload progress">
        <div class="h-full rounded-full bg-brand-600 transition-all" :style="`width:${progress}%`"></div>
      </div>
    </div>
    <p class="mt-2 text-xs text-slate-400">PDF, JPG, PNG or WebP, up to 20 MB.</p>
  </div>

  <div x-show="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200" role="alert">
    <p class="font-semibold">Unable to read this invoice</p>
    <p class="mt-1 text-xs" x-text="error"></p>
    <a href="<?= e(url('purchases/create' . (!empty($supplierName) ? '?supplier=' . rawurlencode($supplierName) : ''))) ?>" class="mt-2 inline-flex min-h-11 items-center font-semibold text-red-800">Enter manually</a>
  </div>

  <div x-show="duplicate" class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200" role="alert">
    <p class="font-semibold">This supplier invoice has already been recorded.</p>
    <p class="mt-1 text-xs" x-text="duplicate ? `${duplicate.purchase_number} · ${duplicate.supplier_name} · ${duplicate.supplier_invoice_no}` : ''"></p>
    <a :href="duplicate ? '<?= e(url('purchases')) ?>/' + duplicate.id : '#'" class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-amber-700 px-4 py-2 font-semibold text-white">Open existing purchase</a>
  </div>

  <button type="submit" :disabled="busy || !file || duplicate"
          class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-60 active:scale-[.99]">
    <svg x-show="busy" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
    <span x-text="buttonText">Upload &amp; Read Invoice</span>
  </button>

  <p class="text-center text-xs text-slate-400">
    OCR creates an editable draft only. Nothing is saved until you review and confirm the purchase.
  </p>
</form>

<script>
function invoiceUploader() {
  return {
    state: 'upload', file: '', progress: 0, error: '', duplicate: null,
    get busy() { return ['uploading', 'processing'].includes(this.state); },
    get buttonText() {
      return this.state === 'uploading' ? `Uploading... ${this.progress}%`
        : this.state === 'processing' ? 'Reading invoice...'
        : 'Upload & Read Invoice';
    },
    get statusText() {
      return this.state === 'uploading' ? `Uploading invoice... ${this.progress}%`
        : this.state === 'processing' ? 'Upload successful. Reading and extracting information...'
        : this.state === 'error' ? 'Upload or processing failed.'
        : 'Ready to upload.';
    },
    choose(selected) {
      this.file = selected?.name || '';
      this.state = 'upload'; this.progress = 0; this.error = ''; this.duplicate = null;
    },
    submit(form) {
      if (!this.file || this.busy) return;
      this.state = 'uploading'; this.progress = 0; this.error = ''; this.duplicate = null;
      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.timeout = 120000;
      xhr.upload.onprogress = event => {
        if (event.lengthComputable) this.progress = Math.min(99, Math.round(event.loaded / event.total * 100));
      };
      xhr.upload.onload = () => { this.progress = 100; this.state = 'processing'; };
      xhr.onload = () => {
        let response;
        try { response = JSON.parse(xhr.responseText); }
        catch (_) { this.fail('The server returned an invalid response. Please try again.'); return; }
        if (!response.success) { this.fail(response.message || 'Invoice could not be processed.'); return; }
        if (response.data?.duplicate) {
          this.state = 'duplicate'; this.duplicate = response.data.duplicate; return;
        }
        if (!response.data?.review_url) { this.fail('The review screen could not be opened. Please try again.'); return; }
        this.state = 'review';
        window.location.assign(response.data.review_url);
      };
      xhr.onerror = () => this.fail('Network error. Check your connection and try again.');
      xhr.ontimeout = () => this.fail('Reading the invoice took too long. Please try again or enter it manually.');
      xhr.onloadend = () => { if (this.busy && xhr.status === 0) this.state = 'error'; };
      xhr.send(new FormData(form));
    },
    fail(message) { this.state = 'error'; this.error = message; }
  };
}
</script>

<div class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <p class="text-sm font-semibold text-slate-700">No invoice to scan?</p>
  <p class="mt-1 text-xs text-slate-500">Enter the purchase details by hand instead.</p>
  <a href="<?= e(url('purchases/create' . (!empty($supplierName) ? '?supplier=' . rawurlencode($supplierName) : ''))) ?>" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-white"><?= ui_icon('pencil', 'h-4 w-4') ?> Enter purchase manually</a>
</div>
