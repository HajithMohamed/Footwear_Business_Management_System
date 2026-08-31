<div x-data="costCalc()" x-init="compute()">
  <div class="mb-4">
    <h1 class="text-lg font-bold text-slate-800">Import Cost Calculator</h1>
    <p class="text-sm text-slate-500">Landed cost per pair — nothing is saved.</p>
  </div>

  <div class="grid gap-4 md:grid-cols-2">
    <!-- Inputs -->
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
      <div class="grid grid-cols-2 gap-3">
        <label class="block">
          <span class="text-xs font-medium text-slate-500">Indian price (₹/pair)</span>
          <input type="number" step="0.01" min="0" x-model.number="f.indian_price" @input="compute"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-[15px] focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
        </label>
        <label class="block">
          <span class="text-xs font-medium text-slate-500">Discount (%)</span>
          <input type="number" step="0.01" min="0" max="100" x-model.number="f.discount_percent" @input="compute"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-[15px] focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
        </label>
        <label class="block">
          <span class="text-xs font-medium text-slate-500">Exchange rate (LKR)</span>
          <input type="number" step="0.0001" min="0" x-model.number="f.lkr_rate" @input="compute"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-[15px] focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
        </label>
        <label class="block">
          <span class="text-xs font-medium text-slate-500">Clearance (Rs/kg)</span>
          <input type="number" step="0.01" min="0" x-model.number="f.per_kilo_clearance" @input="compute"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-[15px] focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
        </label>
        <label class="block">
          <span class="text-xs font-medium text-slate-500">Set weight (g)</span>
          <input type="number" step="1" min="0" x-model.number="f.set_weight_grams" @input="compute"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-[15px] focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
        </label>
        <label class="block">
          <span class="text-xs font-medium text-slate-500">Pairs / set</span>
          <input type="number" step="1" min="0" x-model.number="f.pairs_in_set" @input="compute"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-[15px] focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
        </label>
      </div>
      <p class="text-[11px] text-slate-400">Handling Rs. <?= e($defaults['handling_charge']) ?> · rounding to nearest Rs. <?= e($defaults['rounding_step']) ?> (from Settings)</p>
    </div>

    <!-- Result -->
    <div class="rounded-2xl bg-brand-600 text-white p-5 shadow-lg shadow-brand-600/20">
      <p class="text-xs font-medium text-white/60">Final landed cost / pair</p>
      <p class="mt-1 text-4xl font-extrabold tracking-[0.18em]" x-text="r.final_cost_code || '—'"></p>
      <p class="mt-1 text-sm font-semibold text-brand-100">Secret cost code</p>

      <div class="mt-4 space-y-1.5 text-sm">
        <div class="flex justify-between"><span class="text-white/70">Discounted price (₹)</span><span x-text="num(r.discounted_price)"></span></div>
        <div class="flex justify-between"><span class="text-white/70">Indian cost (LKR, ×rate)</span><span x-text="num(r.indian_cost_raw)"></span></div>
        <div class="flex justify-between font-semibold"><span class="text-white/80">→ rounded</span><span x-text="money(r.indian_cost_lkr)"></span></div>
        <div class="border-t border-white/10 my-2"></div>
        <div class="flex justify-between"><span class="text-white/70">Weight / pair (g)</span><span x-text="num(r.weight_per_pair)"></span></div>
        <div class="flex justify-between"><span class="text-white/70">Pairs / kg</span><span x-text="num(r.pairs_per_kilo)"></span></div>
        <div class="flex justify-between"><span class="text-white/70">Clearance / pair</span><span x-text="num(r.clearance_raw)"></span></div>
        <div class="flex justify-between font-semibold"><span class="text-white/80">→ rounded</span><span x-text="money(r.clearance_per_pair)"></span></div>
        <div class="flex justify-between"><span class="text-white/70">Handling</span><span x-text="money(r.handling_charge)"></span></div>
      </div>

      <div class="mt-4 rounded-xl bg-white/10 p-3">
        <div class="flex items-center justify-between text-sm">
          <span class="text-white/70">Suggested price @</span>
          <div class="flex items-center gap-2">
            <input type="number" min="0" x-model.number="margin" @input="compute" class="w-16 rounded-lg bg-white/15 px-2 py-1 text-right text-white outline-none">
            <span>%</span>
          </div>
        </div>
        <p class="mt-1 text-2xl font-bold" x-text="money(r.suggested_price)">—</p>
      </div>
    </div>
  </div>
  <p class="mt-4 text-xs text-slate-500">Cost code: F=1, I=2, S=3, H=4, G=5, O=6, L=7, D=8, E=9. Zero alternates as N/X for each zero: 1000 = FNXN; 1050 = FNGX.</p>
</div>

<script>
function costCalc() {
  return {
    f: {
      indian_price: 229, discount_percent: 35,
      lkr_rate: <?= (float) $defaults['lkr_rate'] ?>,
      per_kilo_clearance: <?= (float) $defaults['per_kilo_clearance'] ?>,
      set_weight_grams: 1100, pairs_in_set: 5,
      handling_charge: <?= (float) $defaults['handling_charge'] ?>,
      rounding_step: <?= (int) $defaults['rounding_step'] ?>,
    },
    r: {}, margin: 25, _t: null, _request: null,
    num(v){ return (v ?? 0).toLocaleString(undefined,{maximumFractionDigits:2}); },
    money(v){ return 'Rs. ' + (v ?? 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); },
    compute(){
      clearTimeout(this._t);
      this._t = setTimeout(async () => {
        this._request?.abort();
        this._request = new AbortController();
        const body = new FormData();
        Object.entries(this.f).forEach(([k,v]) => body.append(k, v ?? 0));
        body.append('margin_percent', this.margin ?? 0);
        body.append('_token', document.querySelector('meta[name=csrf-token]').content);
        try {
          const res = await fetch('<?= e(url('calculator')) ?>', {
            method:'POST', body, headers:{'X-Requested-With':'XMLHttpRequest'}, signal: this._request.signal
          });
          const data = await res.json();
          if (data.ok) this.r = data.result;
        } catch (error) {
          if (error.name !== 'AbortError') throw error;
        }
      }, 250);
    }
  };
}
</script>
