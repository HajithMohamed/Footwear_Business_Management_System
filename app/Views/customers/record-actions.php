<?php if (\App\Services\CustomerRecordService::kind($txn)): ?>
  <div class="mt-2 flex flex-wrap items-center gap-3">
    <a href="<?= e(url('customer-records/' . $txn['id'] . '/edit')) ?>" class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-600"><?= ui_icon('pencil', 'h-4 w-4') ?> Edit</a>
    <?php if (\App\Core\Auth::isAdmin()): ?>
      <form method="post" action="<?= e(url('customer-records/' . $txn['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this record? Linked payment and cheque records will also be removed, and the customer balance will be recalculated.');">
        <?= csrf_field() ?>
        <button type="submit" class="text-xs font-bold text-red-600">Delete</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
