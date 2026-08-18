<div class="mb-5 flex items-center gap-3">
  <a href="<?= e(url('')) ?>" class="page-header-back" aria-label="Back"><span>←</span></a>
  <div><h1 class="page-header-title">Add Bill</h1><p class="text-xs text-slate-500">Choose the customer whose paper bill you are recording</p></div>
</div>
<form method="get" action="<?= e(url('bills')) ?>" class="search-bar mb-4">
  <span class="search-bar-icon"><?= ui_icon('search', 'h-4 w-4') ?></span>
  <input type="search" name="search" value="<?= e($search) ?>" placeholder="Customer, phone or city…" class="search-bar-input !pr-24" autofocus>
  <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-xl bg-brand-600 px-3 py-2 text-xs font-bold text-white">Search</button>
</form>
<div class="space-y-3">
  <?php foreach ($customers as $customer): ?>
    <a href="<?= e(url('customers/' . $customer['id'] . '/bill')) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99]">
      <div class="flex items-center justify-between gap-3"><div class="min-w-0"><p class="truncate font-bold text-slate-800"><?= e($customer['name']) ?></p><p class="truncate text-xs text-slate-500"><?= e($customer['city'] ?: $customer['region'] ?: 'Location not set') ?></p></div><div class="text-right"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Outstanding</p><p class="font-bold text-amber-600"><?= money($customer['outstanding_due']) ?></p><span class="mt-1 inline-block text-xs font-bold text-brand-600">Select →</span></div></div>
    </a>
  <?php endforeach; ?>
  <?php if (!$customers): ?><div class="empty-state"><h2 class="empty-state-title">No customers found</h2><a href="<?= e(url('customers/create')) ?>" class="btn btn-primary">Add Customer</a></div><?php endif; ?>
</div>
