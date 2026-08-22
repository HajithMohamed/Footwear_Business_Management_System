<?php
use App\Services\StorageService;

$due  = $cheque['deposit_date'] ?: $cheque['cheque_date'];
$late = in_array($cheque['status'], ['pending', 'deposited'], true) && $due < date('Y-m-d');
$statusChip = [
    'deposited' => 'bg-blue-100 text-blue-700',
    'pending'   => 'bg-amber-100 text-amber-700',
    'cleared'   => 'bg-green-100 text-green-700',
    'bounced'   => 'bg-red-100 text-red-700',
    'cancelled' => 'bg-slate-100 text-slate-700',
][$cheque['status']] ?? 'bg-slate-100 text-slate-700';
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('cheques')) ?>" class="text-2xl">←</a>
  <div class="min-w-0">
    <h1 class="truncate text-lg font-bold text-slate-800">Cheque #<?= e($cheque['cheque_number']) ?></h1>
    <p class="text-xs text-slate-500"><?= e($cheque['bank_name'] ?: 'Bank not recorded') ?></p>
  </div>
</div>

<div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-start justify-between gap-3">
    <div class="min-w-0">
      <p class="text-[11px] font-medium text-slate-400">From</p>
      <a href="<?= e(url("customers/{$cheque['customer_id']}")) ?>"
         class="block truncate text-sm font-semibold text-slate-800"><?= e($cheque['customer_name']) ?></a>
    </div>
    <div class="shrink-0 text-right">
      <p class="text-xl font-bold text-slate-800"><?= money($cheque['amount']) ?></p>
      <span class="mt-1 inline-block rounded px-2 py-0.5 text-[10px] font-semibold <?= $statusChip ?>">
        <?= ucfirst($cheque['status']) ?>
      </span>
    </div>
  </div>

  <div class="mt-3 grid grid-cols-2 gap-3 border-t border-slate-100 pt-3">
    <div>
      <p class="text-[10px] text-slate-400">Written on</p>
      <p class="text-sm font-medium text-slate-700"><?= e(date('d M Y', strtotime($cheque['cheque_date']))) ?></p>
    </div>
    <div>
      <p class="text-[10px] text-slate-400">To bank on</p>
      <p class="text-sm font-medium <?= $late ? 'text-red-600' : 'text-slate-700' ?>">
        <?= $cheque['deposit_date'] ? e(date('d M Y', strtotime($cheque['deposit_date']))) : '— not set —' ?>
      </p>
    </div>
  </div>

  <?php if ($late): ?>
    <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-[11px] text-red-700">
      ⚠ This should have been banked <?= (int) ((strtotime('today') - strtotime($due)) / 86400) ?> day(s) ago.
    </p>
  <?php endif; ?>

  <?php if ($cheque['status'] === 'bounced' && $cheque['bounce_reason']): ?>
    <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-[11px] text-red-700">
      Bounced: <?= e($cheque['bounce_reason']) ?>
    </p>
  <?php endif; ?>

  <?php if ($cheque['deposited_at']): ?>
    <p class="mt-2 text-[11px] text-slate-400">
      Banked <?= e(date('d M Y H:i', strtotime($cheque['deposited_at']))) ?>
    </p>
  <?php endif; ?>
</div>

<!-- Deposit date -->
<form method="post" action="<?= e(url("cheques/{$cheque['id']}/deposit")) ?>"
      class="mt-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <?= csrf_field() ?>
  <label class="block text-xs font-semibold text-slate-600 mb-1">When will you bank it?</label>
  <div class="flex gap-2">
    <input type="date" name="deposit_date" value="<?= e($cheque['deposit_date'] ?? '') ?>"
           class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
    <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
  </div>
  <p class="mt-1 text-[11px] text-slate-400">
    The reminder on the cheques screen counts down to this date. Leave it blank to use the date on the cheque.
  </p>
</form>

<!-- Photo -->
<div class="mt-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <h2 class="mb-2 text-sm font-semibold text-slate-700">Cheque photo</h2>
  <?php if (!empty($cheque['image_path'])): ?>
    <a href="<?= e(StorageService::url($cheque['image_path'])) ?>" target="_blank" rel="noopener">
      <img src="<?= e(StorageService::url($cheque['thumb_path'] ?: $cheque['image_path'])) ?>" alt="Cheque"
           class="w-full rounded-xl object-cover ring-1 ring-slate-100">
    </a>
    <p class="mt-1 text-[11px] text-slate-400">Tap to open full size.</p>
  <?php else: ?>
    <p class="mb-2 text-xs text-slate-400">No photo yet.</p>
  <?php endif; ?>

  <form method="post" action="<?= e(url("cheques/{$cheque['id']}/image")) ?>"
        enctype="multipart/form-data" class="mt-3 flex gap-2">
    <?= csrf_field() ?>
    <input type="file" name="image" accept="image/*" capture="environment" required
           class="flex-1 text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-medium">
    <button class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Upload</button>
  </form>
</div>

<!-- Status -->
<?php if ($cheque['status'] === 'pending'): ?>
  <div class="mt-3 space-y-2 pb-4" x-data="{bounce:false}">
    <form method="post" action="<?= e(url("cheques/{$cheque['id']}/status")) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="status" value="deposited">
      <p class="mb-2 text-center text-[11px] text-blue-700">First mark the cheque as deposited; it remains a receivable until it clears.</p>
      <button class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white">
        ✅ Cleared — money is in the bank
      </button>
    </form>

    <button @click="bounce = !bounce"
            class="w-full rounded-xl border border-red-200 px-4 py-3 text-sm font-medium text-red-600">
      ❌ It bounced
    </button>

    <form x-show="bounce" x-transition style="display:none" method="post"
          action="<?= e(url("cheques/{$cheque['id']}/status")) ?>"
          class="space-y-2 rounded-xl bg-red-50 p-3 ring-1 ring-red-100">
      <?= csrf_field() ?>
      <input type="hidden" name="status" value="bounced">
      <p class="text-[11px] text-red-700">
        <?= money($cheque['amount']) ?> goes back onto <?= e($cheque['customer_name']) ?>'s account.
      </p>
      <input type="text" name="bounce_reason" placeholder="Reason (insufficient funds, signature…)"
             class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm">
      <button class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white">
        Confirm bounce
      </button>
    </form>

    <form method="post" action="<?= e(url("cheques/{$cheque['id']}/status")) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="status" value="cancelled">
      <button class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-500">
        Cancel this cheque
      </button>
    </form>
  </div>
<?php elseif ($cheque['status'] === 'deposited'): ?>
  <div class="mt-3 space-y-2 pb-4" x-data="{bounce:false}">
    <form method="post" action="<?= e(url("cheques/{$cheque['id']}/status")) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="status" value="cleared">
      <button class="w-full rounded-xl bg-green-600 px-4 py-3 text-sm font-semibold text-white">Cleared — money is in the bank</button>
    </form>
    <button @click="bounce = !bounce" class="w-full rounded-xl border border-red-200 px-4 py-3 text-sm font-medium text-red-600">It bounced</button>
    <form x-show="bounce" x-transition style="display:none" method="post" action="<?= e(url("cheques/{$cheque['id']}/status")) ?>" class="space-y-2 rounded-xl bg-red-50 p-3 ring-1 ring-red-100">
      <?= csrf_field() ?>
      <input type="hidden" name="status" value="bounced">
      <input type="text" name="bounce_reason" placeholder="Reason" class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm">
      <button class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white">Confirm bounce</button>
    </form>
  </div>
<?php else: ?>
  <p class="mt-3 pb-4 text-center text-[11px] text-slate-400">
    Marked <?= e($cheque['status']) ?>
    <?php if ($cheque['status_updated_at']): ?>
      on <?= e(date('d M Y H:i', strtotime($cheque['status_updated_at']))) ?>
    <?php endif; ?>
  </p>
<?php endif; ?>
