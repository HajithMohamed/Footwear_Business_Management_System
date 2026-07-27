<?php
use App\Services\StorageService;

$statusMeta = [
    'pending'   => ['⏳', 'Pending',   'bg-amber-100 text-amber-700'],
    'cleared'   => ['✅', 'Cleared',   'bg-green-100 text-green-700'],
    'bounced'   => ['❌', 'Bounced',   'bg-red-100 text-red-700'],
    'cancelled' => ['🚫', 'Cancelled', 'bg-slate-100 text-slate-700'],
];
$countFor = function (string $status) use ($stats): int {
    foreach ($stats ?? [] as $s) {
        if ($s['status'] === $status) {
            return (int) $s['count'];
        }
    }
    return 0;
};
?>
<div class="mb-4">
  <h1 class="text-lg font-bold text-slate-800">Cheques</h1>
  <p class="text-sm text-slate-500">Nothing sits in the drawer past its date</p>
</div>

<!-- Money tied up -->
<div class="grid grid-cols-2 gap-3">
  <div class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-100">
    <p class="text-[11px] font-medium text-amber-600">Waiting to clear</p>
    <p class="mt-1 text-xl font-bold text-amber-800"><?= money($summary['pending_value'] ?? 0) ?></p>
    <p class="text-[11px] text-amber-600"><?= (int) ($summary['pending_count'] ?? 0) ?> cheque(s)</p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Bounced</p>
    <p class="mt-1 text-xl font-bold <?= (int) ($summary['bounced_count'] ?? 0) > 0 ? 'text-red-700' : 'text-slate-800' ?>">
      <?= money($summary['bounced_value'] ?? 0) ?>
    </p>
    <p class="text-[11px] text-slate-400"><?= (int) ($summary['bounced_count'] ?? 0) ?> cheque(s)</p>
  </div>
</div>

<!-- Reminder: what needs banking -->
<?php if (!empty($dueSoon)): ?>
  <div class="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
    <div class="border-b border-slate-100 bg-amber-50 px-4 py-3">
      <h2 class="text-sm font-semibold text-amber-800">🔔 Bank these now</h2>
      <p class="text-[11px] text-amber-600">Due within <?= (int) $reminderDays ?> days, or already past</p>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($dueSoon as $c): ?>
        <?php $late = (int) $c['days_until'] < 0; ?>
        <li>
          <a href="<?= e(url("cheques/{$c['id']}")) ?>" class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800"><?= e($c['customer_name']) ?></p>
              <p class="text-[11px] text-slate-400">
                #<?= e($c['cheque_number']) ?><?= $c['bank_name'] ? ' · ' . e($c['bank_name']) : '' ?>
                · <?= e(date('d M Y', strtotime($c['due_on']))) ?>
              </p>
            </div>
            <div class="shrink-0 text-right">
              <p class="text-sm font-bold text-slate-800"><?= money($c['amount']) ?></p>
              <p class="text-[10px] font-semibold <?= $late ? 'text-red-600' : 'text-amber-600' ?>">
                <?= $late
                      ? abs((int) $c['days_until']) . ' day(s) late'
                      : ((int) $c['days_until'] === 0 ? 'Today' : 'In ' . (int) $c['days_until'] . ' day(s)') ?>
              </p>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Status filter -->
<div class="mt-4 flex gap-2 overflow-x-auto pb-2">
  <?php foreach ($statusMeta as $status => [$icon, $label, ]): ?>
    <a href="<?= e(url("cheques?status={$status}")) ?>"
       class="whitespace-nowrap rounded-full px-3 py-1 text-sm font-medium <?= $filter_status === $status ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
      <?= $icon ?> <?= $label ?> (<?= $countFor($status) ?>)
    </a>
  <?php endforeach; ?>
</div>

<!-- List -->
<?php if (!empty($cheques)): ?>
  <div class="space-y-2 pb-4">
    <?php foreach ($cheques as $c): ?>
      <?php
        $due       = $c['deposit_date'] ?: $c['cheque_date'];
        $late      = $c['status'] === 'pending' && $due < date('Y-m-d');
        $chipClass = $statusMeta[$c['status']][2] ?? 'bg-slate-100 text-slate-700';
      ?>
      <a href="<?= e(url("cheques/{$c['id']}")) ?>"
         class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
        <div class="flex items-start justify-between gap-3">
          <div class="flex min-w-0 items-start gap-3">
            <?php if (!empty($c['thumb_path'])): ?>
              <img src="<?= e(StorageService::url($c['thumb_path'])) ?>" alt=""
                   class="h-11 w-11 shrink-0 rounded-lg object-cover ring-1 ring-slate-100">
            <?php endif; ?>
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-slate-800"><?= e($c['customer_name']) ?></p>
              <p class="text-[11px] text-slate-400">
                #<?= e($c['cheque_number']) ?><?= $c['bank_name'] ? ' · ' . e($c['bank_name']) : '' ?>
              </p>
              <p class="text-[11px] text-slate-400">
                Dated <?= e(date('d M Y', strtotime($c['cheque_date']))) ?>
                <?php if ($c['deposit_date']): ?>
                  · bank <?= e(date('d M Y', strtotime($c['deposit_date']))) ?>
                <?php endif; ?>
              </p>
            </div>
          </div>
          <div class="shrink-0 text-right">
            <p class="text-sm font-bold text-slate-800"><?= money($c['amount']) ?></p>
            <span class="mt-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold <?= $chipClass ?>">
              <?= ucfirst($c['status']) ?>
            </span>
          </div>
        </div>
        <?php if ($late): ?>
          <p class="mt-2 rounded-lg bg-red-50 px-2.5 py-1.5 text-[11px] text-red-700">
            ⚠ Should have been banked <?= (int) ((strtotime('today') - strtotime($due)) / 86400) ?> day(s) ago
          </p>
        <?php endif; ?>
        <?php if ($c['status'] === 'bounced' && $c['bounce_reason']): ?>
          <p class="mt-2 rounded-lg bg-red-50 px-2.5 py-1.5 text-[11px] text-red-700">
            Bounced: <?= e($c['bounce_reason']) ?>
          </p>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <p class="text-slate-500">No <?= e($filter_status) ?> cheques.</p>
    <p class="mt-2 text-xs text-slate-400">Cheques appear here when you record one as a customer payment.</p>
  </div>
<?php endif; ?>
