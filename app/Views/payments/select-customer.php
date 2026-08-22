<div class="mb-5 flex items-center gap-3">
  <a href="<?= e(url('')) ?>" class="page-header-back"><span>←</span></a>
  <div>
    <h1 class="page-header-title">Make Payment</h1>
    <p class="text-xs text-slate-500">Choose the customer first</p>
  </div>
</div>

<form method="get" action="<?= e(url('payments')) ?>" class="search-bar mb-4">
  <span class="search-bar-icon"><?= ui_icon('search', 'h-4 w-4') ?></span>
  <input type="search" name="search" value="<?= e($search) ?>" placeholder="Customer, phone, city…"
         class="search-bar-input !pr-24" autofocus>
  <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-xl bg-brand-600 px-3 py-2 text-xs font-bold text-white">Search</button>
</form>

<?php if ($customers): ?>
  <div class="space-y-3">
    <?php foreach ($customers as $customer): ?>
      <a href="<?= e(url('customers/' . $customer['id'] . '/payment')) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99]">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-base font-bold text-slate-800"><?= e($customer['name']) ?></p>
            <p class="mt-0.5 flex items-center gap-1 truncate text-xs text-slate-500"><?= ui_icon('location', 'h-3.5 w-3.5') ?> <?= e($customer['city'] ?: $customer['region'] ?: 'Location not set') ?></p>
            <p class="mt-1 text-xs text-slate-500">Recent bill: <?= !empty($customer['last_purchase_date']) ? e(date('j M Y', strtotime($customer['last_purchase_date']))) : 'None' ?></p>
          </div>
          <div class="shrink-0 text-right">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Outstanding</p>
            <p class="mt-1 text-lg font-bold <?= (float) $customer['outstanding_due'] > 0 ? 'text-amber-600' : 'text-green-600' ?>"><?= money($customer['outstanding_due']) ?></p>
            <span class="mt-2 inline-block rounded-lg bg-green-600 px-3 py-1.5 text-xs font-bold text-white">Select</span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="empty-state"><div class="empty-state-icon"><?= ui_icon('users', 'h-8 w-8') ?></div><h2 class="empty-state-title">No customers found</h2><p class="empty-state-text">Add a customer before recording a payment.</p><a href="<?= e(url('customers/create')) ?>" class="btn btn-primary">Add Customer</a></div>
<?php endif; ?>
