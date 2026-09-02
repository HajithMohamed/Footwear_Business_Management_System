<div class="mb-5 flex items-center gap-3">
  <a href="<?= e(url('customers/' . $customer['id'] . '?tab=ledger')) ?>" class="page-header-back" aria-label="Back to customer">&larr;</a>
  <div>
    <h1 class="text-xl font-bold text-slate-900">Share Ledger</h1>
    <p class="text-sm text-slate-500"><?= e($customer['name']) ?><?= !empty($customer['phone']) ? ' · ' . e($customer['phone']) : '' ?></p>
  </div>
</div>

<div class="mx-auto max-w-xl" x-data="statementGenerator()">
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <h2 class="font-bold text-slate-800">Customer statement period</h2>
    <p class="mt-1 text-xs leading-5 text-slate-500">The opening balance includes all activity before the selected start date.</p>

    <div class="mt-4 grid grid-cols-2 gap-2" role="group" aria-label="Statement period">
      <template x-for="choice in periods" :key="choice.id">
        <button type="button" @click="period=choice.id; reset()" class="min-h-11 rounded-xl px-3 text-sm font-semibold ring-1 transition"
                :class="period===choice.id ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-slate-700 ring-slate-200'" x-text="choice.label"></button>
      </template>
    </div>

    <div x-show="period==='custom'" x-cloak class="mt-4 grid grid-cols-2 gap-3">
      <label class="text-xs font-bold text-slate-600">From<input x-model="from" type="date" class="mt-1 min-h-11 w-full rounded-xl border-0 px-3 ring-1 ring-slate-200"></label>
      <label class="text-xs font-bold text-slate-600">To<input x-model="to" type="date" class="mt-1 min-h-11 w-full rounded-xl border-0 px-3 ring-1 ring-slate-200"></label>
    </div>

    <div x-show="error" x-cloak class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200" role="alert">
      <p class="font-semibold">Unable to generate the ledger PDF.</p><p class="mt-1 text-xs" x-text="error"></p>
    </div>
    <div x-show="state==='success'" x-cloak class="mt-4 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-200" role="status">PDF generated successfully.</div>

    <button type="button" @click="generate()" :disabled="state==='loading'"
            class="mt-5 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 font-bold text-white disabled:opacity-60">
      <svg x-show="state==='loading'" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
      <span x-text="state==='loading' ? 'Generating Ledger...' : (error ? 'Try Again' : 'Generate Ledger PDF')"></span>
    </button>
  </div>

  <div x-show="state==='success'" x-cloak class="mt-4 grid grid-cols-3 gap-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <button type="button" @click="share()" class="min-h-12 rounded-xl bg-green-600 px-2 text-sm font-bold text-white">Share</button>
    <a :href="pdfUrl" :download="filename" class="flex min-h-12 items-center justify-center rounded-xl bg-slate-100 px-2 text-sm font-bold text-slate-700">Download</a>
    <button type="button" @click="printPdf()" class="min-h-12 rounded-xl bg-slate-100 px-2 text-sm font-bold text-slate-700">Print</button>
  </div>
</div>

<script>
function statementGenerator() {
  return {
    periods: [{id:'all',label:'All Time'},{id:'this_month',label:'This Month'},{id:'last_month',label:'Last Month'},{id:'custom',label:'Custom Range'}],
    period:'all', from:'', to:'', state:'idle', error:'', pdfUrl:'', blob:null,
    filename: <?= json_encode(preg_replace('/[^A-Za-z0-9]+/', '-', $customer['name']) . '-statement.pdf') ?>,
    reset() { this.error=''; if(this.pdfUrl) URL.revokeObjectURL(this.pdfUrl); this.pdfUrl=''; this.blob=null; this.state='idle'; },
    endpoint() { const q=new URLSearchParams({period:this.period}); if(this.period==='custom'){q.set('from',this.from);q.set('to',this.to);} return <?= json_encode(url('customers/' . $customer['id'] . '/statement/pdf')) ?>+'?'+q; },
    async generate() {
      if(this.period==='custom' && (!this.from || !this.to)){this.error='Select both start and end dates.';return;}
      this.state='loading'; this.error='';
      try {
        const response=await fetch(this.endpoint(),{credentials:'same-origin',headers:{Accept:'application/pdf'}});
        if(!response.ok){let message='Please try again.';try{message=(await response.json()).message||message;}catch(_){} throw new Error(message);}
        const blob=await response.blob(); if(blob.type!=='application/pdf' || blob.size<100){throw new Error('The server did not return a valid PDF.');}
        if(this.pdfUrl) URL.revokeObjectURL(this.pdfUrl); this.blob=blob; this.pdfUrl=URL.createObjectURL(blob); this.state='success';
      } catch(error) { this.state='error'; this.error=error.message||'Please try again.'; }
    },
    async share() {
      if(!this.blob) return;
      const file=new File([this.blob],this.filename,{type:'application/pdf'});
      if(navigator.canShare?.({files:[file]})){try{await navigator.share({title:'Customer Statement',text:<?= json_encode('Customer statement for ' . $customer['name']) ?>,files:[file]});return;}catch(error){if(error.name==='AbortError')return;}}
      const link=document.createElement('a');link.href=this.pdfUrl;link.download=this.filename;link.click();
    },
    printPdf() { if(!this.pdfUrl)return; const win=window.open(this.pdfUrl,'_blank','noopener'); if(!win){this.error='Allow pop-ups to print the statement.';return;} setTimeout(()=>win.print(),700); }
  };
}
</script>
