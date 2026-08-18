<?php
/**
 * One customer row for the intelligence lists.
 * Expects $c (a customer_intelligence row joined to customers).
 */
$behaviour = $c['payment_behaviour'] ?? 'unknown';
$badge = match ($behaviour) {
    'reliable'  => ['✓ Pays on time', 'bg-emerald-100 text-emerald-700'],
    'slow'      => ['◷ Pays late',     'bg-amber-100 text-amber-700'],
    'defaulter' => ['⚠ Defaulting',    'bg-red-100 text-red-700'],
    default     => ['— No history',    'bg-slate-100 text-slate-500'],
};
$outstanding = (float) ($c['outstanding_due'] ?? 0);
?>
<div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-start justify-between gap-3">
    <div class="min-w-0">
      <a href="<?= e(url("customers/{$c['customer_id']}")) ?>"
         class="block truncate text-sm font-bold text-slate-800"><?= e($c['name']) ?></a>
      <p class="text-[11px] text-slate-400">
        <?= e(ucfirst($c['customer_type'] ?? '')) ?>
        <?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
      </p>
    </div>
    <span class="shrink-0 rounded px-2 py-0.5 text-[10px] font-semibold <?= $badge[1] ?>"><?= $badge[0] ?></span>
  </div>

  <div class="mt-3 grid grid-cols-3 gap-2 border-y border-slate-100 py-2.5">
    <div>
      <p class="text-[10px] text-slate-400">Lifetime</p>
      <p class="text-sm font-bold text-slate-800"><?= money($c['lifetime_value'] ?? 0) ?></p>
    </div>
    <div>
      <p class="text-[10px] text-slate-400">Outstanding</p>
      <p class="text-sm font-bold <?= $outstanding > 0 ? 'text-red-600' : 'text-emerald-600' ?>">
        <?= money($outstanding) ?>
      </p>
    </div>
    <div>
      <p class="text-[10px] text-slate-400">Orders</p>
      <p class="text-sm font-bold text-slate-800"><?= (int) ($c['total_purchases'] ?? 0) ?></p>
    </div>
  </div>

  <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500">
    <?php if ($c['avg_payment_days'] !== null): ?>
      <span>Pays in ~<?= number_format((float) $c['avg_payment_days'], 0) ?> days</span>
    <?php endif; ?>
    <?php if ($c['on_time_rate'] !== null): ?>
      <span><?= number_format((float) $c['on_time_rate'], 0) ?>% on time</span>
    <?php endif; ?>
    <?php if (!empty($c['purchase_frequency'])): ?>
      <span>Buys every ~<?= (int) $c['purchase_frequency'] ?> days</span>
    <?php endif; ?>
    <?php if ($c['days_since_purchase'] !== null): ?>
      <span>Last bought <?= (int) $c['days_since_purchase'] ?> days ago</span>
    <?php endif; ?>
    <?php if ((float) ($c['credit_utilization'] ?? 0) > 0): ?>
      <span class="<?= (float) $c['credit_utilization'] > 90 ? 'font-semibold text-red-600' : '' ?>">
        <?= number_format((float) $c['credit_utilization'], 0) ?>% of credit limit used
      </span>
    <?php endif; ?>
  </div>

  <?php if ((float) ($c['overdue_amount'] ?? 0) > 0): ?>
    <p class="mt-2 rounded-lg bg-red-50 px-2.5 py-1.5 text-[11px] text-red-700">
      <?= money($c['overdue_amount']) ?> overdue
      <?php if (!empty($c['overdue_days'])): ?>by <?= (int) $c['overdue_days'] ?> day(s)<?php endif; ?>
    </p>
  <?php endif; ?>

  <div class="mt-2 flex gap-2">
    <?php if (!empty($c['phone'])): ?>
      <a href="tel:<?= e($c['phone']) ?>" class="rounded bg-slate-100 px-2.5 py-1.5 text-[11px] font-medium text-slate-700">📞 Call</a>
      <a href="https://wa.me/<?= e(whatsapp_phone($c['phone'])) ?>" target="_blank" rel="noopener"
         class="rounded bg-green-100 px-2.5 py-1.5 text-[11px] font-medium text-green-700">💬 WhatsApp</a>
    <?php endif; ?>
    <a href="<?= e(url("customers/{$c['customer_id']}/ledger")) ?>"
       class="ml-auto rounded bg-slate-100 px-2.5 py-1.5 text-[11px] font-medium text-slate-700">📒 Ledger</a>
  </div>
</div>
