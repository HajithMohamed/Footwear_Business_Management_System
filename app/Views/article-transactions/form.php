<?php $isReturn = $mode === 'return'; ?>
<div class="mb-5 flex items-center gap-3">
  <a href="<?= e($isReturn && $customer ? url('customers/'.$customer['id']) : url('')) ?>" class="page-header-back">&larr;</a>
  <div><h1 class="text-xl font-bold text-slate-900"><?= e($title) ?></h1><p class="text-sm text-slate-500"><?= $isReturn ? 'Record existing or manual articles without blocking the return.' : 'Every registered-product change is written to stock history.' ?></p></div>
</div>

<form x-data="flexibleArticleForm(<?= $isReturn ? 'true' : 'false' ?>)" @submit.prevent="submit($el)" method="post" action="<?= e(url($isReturn ? 'returns' : 'stock-adjustments')) ?>" class="mx-auto max-w-2xl space-y-4">
  <?= csrf_field() ?>
  <?php if ($isReturn): ?>
  <div class="card card-compact space-y-3">
    <label class="block text-xs font-bold text-slate-600">Customer
      <select name="customer_id" required x-model="customerId" @change="customerBalance=Number($event.target.selectedOptions[0]?.dataset.balance||0)" class="mt-1 min-h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200">
        <option value="">Select customer</option>
        <?php foreach ($customers as $row): ?><option value="<?= (int)$row['id'] ?>" data-balance="<?= e($row['outstanding_due']) ?>" <?= $customer && (int)$customer['id']===(int)$row['id']?'selected':'' ?>><?= e($row['name']) ?> · Rs. <?= number_format((float)$row['outstanding_due'],0) ?></option><?php endforeach; ?>
      </select>
    </label>
    <div class="grid grid-cols-2 gap-3">
      <label class="text-xs font-bold text-slate-600">Reason<select name="return_reason" required class="mt-1 min-h-11 w-full rounded-xl border-0 px-3 ring-1 ring-slate-200"><option value="wrong_size">Wrong size</option><option value="damaged">Damaged</option><option value="changed_order">Customer changed order</option><option value="wrong_item">Wrong item</option><option value="defective">Defective</option><option value="other">Other</option></select></label>
      <label class="text-xs font-bold text-slate-600">Condition<select name="item_condition" required class="mt-1 min-h-11 w-full rounded-xl border-0 px-3 ring-1 ring-slate-200"><option value="resalable">Resalable</option><option value="damaged">Damaged</option><option value="returned_to_supplier">Returned to supplier</option><option value="other">Other</option></select></label>
    </div>
    <label class="block text-xs font-bold text-slate-600">Treatment<select name="treatment" x-model="treatment" required class="mt-1 min-h-11 w-full rounded-xl border-0 px-3 ring-1 ring-slate-200"><option value="customer_credit">Customer credit adjustment</option><option value="outstanding_reduction">Outstanding reduction</option><option value="refund">Refund</option><option value="replacement">Replacement</option><option value="stock_only">Stock-only return</option></select></label>
  </div>
  <?php else: ?>
  <div class="card card-compact">
    <label class="block text-xs font-bold text-slate-600">Adjustment Type<select name="transaction_type" x-model="stockType" required class="mt-1 min-h-11 w-full rounded-xl border-0 px-3 ring-1 ring-slate-200"><option value="purchase">Purchase / Goods Receiving</option><option value="purchase_return">Purchase Return</option><option value="stock_in">Stock In</option><option value="stock_out">Stock Out</option><option value="return_in">Return In</option><option value="damage">Damage</option><option value="loss">Loss</option><option value="adjustment">Correction / Adjustment In</option></select></label>
  </div>
  <?php endif; ?>

  <div class="space-y-3">
    <template x-for="(row,index) in rows" :key="row.key">
      <div class="card card-compact relative">
        <div class="mb-3 flex items-center justify-between"><span class="text-xs font-bold uppercase text-slate-400" x-text="'Article '+(index+1)"></span><button x-show="rows.length>1" type="button" @click="rows.splice(index,1)" class="min-h-11 px-2 text-xs font-bold text-red-600">Remove</button></div>
        <label class="block text-xs font-bold text-slate-600">Article Number
          <input name="article_no[]" x-model="row.article" @input.debounce.300ms="search(row)" required autocomplete="off" class="mt-1 min-h-11 w-full rounded-xl border-0 px-3 ring-1 ring-slate-200" placeholder="e.g. FLR41701">
        </label>
        <input type="hidden" name="product_id[]" :value="row.productId">
        <div x-show="row.results.length" class="mt-2 overflow-hidden rounded-xl ring-1 ring-slate-200">
          <template x-for="product in row.results" :key="product.id"><button type="button" @click="select(row,product)" class="flex min-h-12 w-full items-center justify-between border-b border-slate-100 bg-white px-3 text-left"><span><span class="block text-sm font-bold" x-text="product.art_no+' · '+(product.brand_name||'No brand')"></span><span class="text-xs text-slate-500" x-text="product.name||'Registered product'"></span></span><span class="text-xs font-bold" x-text="'Stock '+product.stock_sets"></span></button></template>
        </div>
        <div x-show="row.searched && !row.results.length && !row.productId" class="mt-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800"><p class="font-bold">Article not found in Products.</p><p class="mt-1">You can continue as a manual article. It will not create an incomplete Product record.</p><a :href="'<?= e(url('products/create')) ?>?art_no='+encodeURIComponent(row.article)" class="mt-2 inline-flex min-h-11 items-center font-bold">Create Product</a></div>
        <div x-show="row.productId" class="mt-2 rounded-xl bg-green-50 p-3 text-xs text-green-800"><p class="font-bold" x-text="row.productLabel"></p><p x-text="'Current stock: '+row.currentStock"></p></div>
        <div class="mt-3 grid grid-cols-3 gap-2">
          <input name="brand_name[]" x-model="row.brand" placeholder="Brand (optional)" class="min-h-11 rounded-xl border-0 px-2 text-xs ring-1 ring-slate-200">
          <input name="colour[]" x-model="row.colour" placeholder="Color (optional)" class="min-h-11 rounded-xl border-0 px-2 text-xs ring-1 ring-slate-200">
          <input name="size_set_label[]" x-model="row.size" placeholder="Size set" class="min-h-11 rounded-xl border-0 px-2 text-xs ring-1 ring-slate-200">
        </div>
        <div class="mt-3 grid grid-cols-3 gap-2">
          <label class="text-[10px] font-bold text-slate-500">Quantity<input name="quantity[]" x-model.number="row.qty" @input="calculateFromUnit(row)" type="number" min="1" required class="mt-1 min-h-11 w-full rounded-xl border-0 px-2 ring-1 ring-slate-200"></label>
          <label class="text-[10px] font-bold text-slate-500">Unit Price<input name="unit_price[]" x-model.number="row.unit" @input="calculateFromUnit(row)" type="number" min="0" step="0.01" class="mt-1 min-h-11 w-full rounded-xl border-0 px-2 ring-1 ring-slate-200"></label>
          <label class="text-[10px] font-bold text-slate-500">Total<input name="line_total[]" x-model.number="row.total" @input="calculateFromTotal(row)" type="number" min="0" step="0.01" class="mt-1 min-h-11 w-full rounded-xl border-0 px-2 ring-1 ring-slate-200"></label>
        </div>
        <p class="mt-2 text-xs text-slate-500" x-text="calculation(row)"></p>
      </div>
    </template>
    <button type="button" @click="addRow()" class="min-h-12 w-full rounded-xl border border-dashed border-brand-300 bg-brand-50 font-bold text-brand-700">+ Add Article</button>
  </div>

  <div class="card card-compact space-y-2">
    <div class="flex justify-between text-sm"><span>Subtotal</span><strong x-text="money(subtotal)"></strong></div>
    <div class="grid grid-cols-2 gap-2"><label class="text-[10px] font-bold text-slate-500">Tax<input name="tax" x-model.number="tax" type="number" min="0" step="0.01" class="mt-1 min-h-11 w-full rounded-xl border-0 px-3 ring-1 ring-slate-200"></label><label class="text-[10px] font-bold text-slate-500">Discount<input name="discount" x-model.number="discount" type="number" min="0" step="0.01" class="mt-1 min-h-11 w-full rounded-xl border-0 px-3 ring-1 ring-slate-200"></label></div>
    <div class="flex justify-between border-t border-slate-100 pt-2 text-base"><span>Grand Total</span><strong x-text="money(grandTotal)"></strong></div>
    <?php if ($isReturn): ?><div class="border-t border-slate-100 pt-2 text-sm"><div class="flex justify-between"><span>Outstanding Before</span><strong x-text="money(customerBalance)"></strong></div><div class="mt-1 flex justify-between"><span>Return</span><strong class="text-green-600" x-text="affectsBalance ? '- '+money(grandTotal) : money(0)"></strong></div><div class="mt-2 flex justify-between text-base"><span>Outstanding After</span><strong x-text="money(balanceAfter)"></strong></div></div><?php endif; ?>
    <input name="reference" maxlength="100" placeholder="Reference (optional)" class="min-h-11 w-full rounded-xl border-0 px-3 ring-1 ring-slate-200">
    <textarea name="notes" rows="2" placeholder="Notes (optional)" class="w-full rounded-xl border-0 px-3 py-2 ring-1 ring-slate-200"></textarea>
  </div>
  <div x-show="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-200" role="alert" x-text="error"></div>
  <button :disabled="loading" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-brand-600 px-4 font-bold text-white disabled:opacity-60" x-text="loading?'Processing...':'<?= $isReturn ? 'Confirm Return' : 'Confirm Stock Adjustment' ?>'"></button>
  <div x-show="success" class="rounded-xl bg-green-50 p-4 text-green-800 ring-1 ring-green-200"><p class="font-bold" x-text="success"></p><a :href="resultUrl" class="mt-2 inline-flex min-h-11 items-center font-bold">View Transaction</a></div>
</form>

<script>
function flexibleArticleForm(isReturn){return{isReturn,rows:[],customerId:<?= json_encode((string)($customer['id']??'')) ?>,customerBalance:<?= json_encode((float)($customer['outstanding_due']??0)) ?>,treatment:'customer_credit',stockType:'stock_in',tax:0,discount:0,loading:false,error:'',success:'',resultUrl:'',
init(){this.addRow()},blank(){return{key:Date.now()+Math.random(),article:'',productId:'',productLabel:'',currentStock:0,brand:'',colour:'',size:'',qty:1,unit:0,total:0,results:[],searched:false}},addRow(){this.rows.push(this.blank())},
get subtotal(){return this.rows.reduce((s,r)=>s+(Number(r.total)||0),0)},get grandTotal(){return this.subtotal+(Number(this.tax)||0)-(Number(this.discount)||0)},get affectsBalance(){return ['customer_credit','outstanding_reduction'].includes(this.treatment)},get balanceAfter(){return this.affectsBalance?this.customerBalance-this.grandTotal:this.customerBalance},money(v){return 'Rs. '+Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})},
calculateFromUnit(r){if(r.qty&&r.unit>=0)r.total=Number((r.qty*r.unit).toFixed(2))},calculateFromTotal(r){if(r.qty&&r.total>=0)r.unit=Number((r.total/r.qty).toFixed(2))},calculation(r){let text=`${r.qty||0} × ${this.money(r.unit)} = ${this.money(r.total)}`;if(r.productId&&!this.isReturn){const sign=['purchase','stock_in','return_in','adjustment'].includes(this.stockType)?1:-1;text+=` · Stock ${r.currentStock} → ${r.currentStock+(sign*(Number(r.qty)||0))}`}return text},
async search(r){r.productId='';r.productLabel='';if(r.article.trim().length<2){r.results=[];r.searched=false;return}try{const x=await fetch('<?= e(url('articles/search')) ?>?q='+encodeURIComponent(r.article));const j=await x.json();r.results=j.data?.products||[];r.searched=true}catch(e){r.results=[];r.searched=true}},
select(r,p){r.article=p.art_no;r.productId=String(p.id);r.productLabel=[p.name,p.brand_name,p.art_no].filter(Boolean).join(' · ');r.currentStock=Number(p.stock_sets);r.brand=p.brand_name||'';r.size=p.size_set_label||'';r.results=[];r.searched=true;if(!r.unit)r.unit=Number(p.wholesale_price||p.retail_price||0);this.calculateFromUnit(r)},
async submit(form){if(this.loading)return;this.loading=true;this.error='';this.success='';try{const x=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const j=await x.json();if(!x.ok||!j.success)throw new Error(j.message||'Unable to save.');this.success=j.message;this.resultUrl=j.data.url;setTimeout(()=>location.assign(j.data.url),700)}catch(e){this.error=e.message||'Unable to save. Please try again.'}finally{this.loading=false}}
}}
</script>
