<div class="mb-4">
  <a href="<?= e(url('reports')) ?>" class="text-sm text-brand-600">&larr; Reports</a>
  <h1 class="mt-1 text-lg font-bold text-slate-800">Receivables</h1>
  <p class="text-sm text-slate-500">Outstanding customer balances from the ledger</p>
</div>

<?php $total = array_sum(array_map(fn ($r) => (float) $r['balance'], $rows)); ?>

<div class="mb-4 rounded-2xl bg-slate-800 p-4 text-white">
  <p class="text-xs text-white/70">Total outstanding</p>
  <p class="mt-1 text-2xl font-bold"><?= money($total) ?></p>
  <p class="mt-1 text-xs text-white/60"><?= count($rows) ?> customer(s) with a balance</p>
</div>

<div class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-800 ring-1 ring-amber-200">
  <p class="font-semibold">Read this figure carefully</p>
  <p class="mt-0.5">
    This reports what the customer ledger holds. Sales are not yet recorded through the system,
    so a low or zero total means <em>nothing has been billed</em> — not that customers have paid.
    Recorded payments and cheques do appear here.
  </p>
</div>

<div class="space-y-2">
  <?php foreach ($rows as $r): ?>
    <a href="<?= e(url('customers/' . $r['id'])) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-slate-800"><?= e($r['name']) ?></p>
          <p class="truncate text-[11px] text-slate-500">
            <?= e($r['city'] ?: 'No city') ?><?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?>
          </p>
          <?php if ($r['last_movement']): ?>
            <p class="text-[11px] text-slate-400">Last movement <?= e(date('j M Y', strtotime($r['last_movement']))) ?></p>
          <?php endif; ?>
        </div>
        <span class="shrink-0 text-sm font-bold <?= (float) $r['balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
          <?= money($r['balance']) ?>
        </span>
      </div>
      <?php if (abs((float) $r['balance'] - (float) $r['ledger_balance']) > 0.01): ?>
        <p class="mt-2 rounded-lg bg-amber-50 px-2.5 py-1.5 text-[11px] text-amber-800">
          ⚠ Ledger disagrees: customer record says <?= money($r['balance']) ?>,
          the transaction history ends at <?= money($r['ledger_balance']) ?>.
        </p>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>

  <?php if (!$rows): ?>
    <p class="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100">
      No customer currently carries a balance.
    </p>
  <?php endif; ?>
</div>
