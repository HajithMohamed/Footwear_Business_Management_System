<?php
$rows = $result['rows'];
$qs = function (array $overrides) use ($filters): string {
    return url('expenses?' . http_build_query(array_filter(array_merge($filters, $overrides), fn ($v) => $v !== '')));
};
$methodLabel = [
    'cash' => 'Cash', 'bank_transfer' => 'Bank', 'cheque' => 'Cheque',
    'card' => 'Card', 'other' => 'Other',
];
?>
<div class="mb-4 flex items-start justify-between gap-3">
  <div>
    <h1 class="text-lg font-bold text-slate-800">Expenses</h1>
    <p class="text-sm text-slate-500">Running costs — what turns gross profit into net profit</p>
  </div>
  <a href="<?= e(url('expenses/create')) ?>"
     class="shrink-0 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white">➕ New</a>
</div>

<div class="rounded-2xl bg-slate-800 p-4 text-white shadow-sm">
  <p class="text-xs font-medium text-white/60">
    Total spend<?= $filters['from'] || $filters['to'] ? ' in this period' : ' (all time)' ?>
  </p>
  <p class="mt-1 text-2xl font-bold"><?= money($total) ?></p>
  <p class="mt-1 text-[11px] text-white/60"><?= (int) $result['total'] ?> entr<?= $result['total'] === 1 ? 'y' : 'ies' ?></p>
</div>

<!-- Where the money went -->
<?php if ($byCategory): ?>
  <div class="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
    <div class="border-b border-slate-100 px-4 py-3">
      <h2 class="text-sm font-semibold text-slate-700">By category</h2>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach (array_slice($byCategory, 0, 8) as $c): ?>
        <?php $pct = $total > 0 ? (float) $c['total'] / $total * 100 : 0; ?>
        <li class="px-4 py-2.5">
          <div class="flex items-center justify-between">
            <span class="text-sm text-slate-700"><?= e($c['category_name']) ?></span>
            <span class="text-sm font-semibold text-slate-800"><?= money($c['total']) ?></span>
          </div>
          <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-brand-600" style="width: <?= number_format($pct, 1) ?>%"></div>
          </div>
          <p class="mt-1 text-[11px] text-slate-400">
            <?= number_format($pct, 1) ?>% · <?= (int) $c['entries'] ?> entr<?= (int) $c['entries'] === 1 ? 'y' : 'ies' ?>
          </p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Filters -->
<form method="get" action="<?= e(url('expenses')) ?>" class="mt-3 space-y-2">
  <input type="text" name="search" value="<?= e($filters['search']) ?>" placeholder="🔍 Payee, reference or note"
         class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600">
  <div class="flex gap-2">
    <select name="category_id" class="flex-1 rounded-lg border border-slate-200 px-2 py-2 text-xs">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (string) $filters['category_id'] === (string) $c['id'] ? 'selected' : '' ?>>
          <?= e($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= e($filters['from']) ?>" class="rounded-lg border border-slate-200 px-2 py-2 text-xs">
    <input type="date" name="to" value="<?= e($filters['to']) ?>" class="rounded-lg border border-slate-200 px-2 py-2 text-xs">
    <button class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-medium text-white">Go</button>
  </div>
</form>

<!-- List -->
<?php if ($rows): ?>
  <div class="mt-4 space-y-2">
    <?php foreach ($rows as $x): ?>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-800">
              <?= e($x['description'] ?: ($x['category_name'] ?: 'Expense')) ?>
            </p>
            <p class="text-[11px] text-slate-400">
              <?= e(date('d M Y', strtotime($x['expense_date']))) ?>
              <?= $x['payee'] ? ' · ' . e($x['payee']) : '' ?>
              <?= $x['reference'] ? ' · ' . e($x['reference']) : '' ?>
            </p>
          </div>
          <span class="shrink-0 text-sm font-bold text-slate-800"><?= money($x['amount']) ?></span>
        </div>
        <div class="mt-2 flex items-center gap-1.5">
          <?php if ($x['category_name']): ?>
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600"><?= e($x['category_name']) ?></span>
          <?php endif; ?>
          <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">
            <?= e($methodLabel[$x['payment_method']] ?? $x['payment_method']) ?>
          </span>
          <a href="<?= e(url("expenses/{$x['id']}/edit")) ?>" class="ml-auto text-[11px] font-semibold text-brand-600">Edit</a>
          <form method="post" action="<?= e(url("expenses/{$x['id']}/delete")) ?>"
                onsubmit="return confirm('Remove this expense?')">
            <?= csrf_field() ?>
            <button class="text-[11px] font-semibold text-red-600">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($result['pages'] > 1): ?>
    <div class="mt-4 flex items-center justify-between">
      <?php if ($result['page'] > 1): ?>
        <a href="<?= e($qs(['page' => $result['page'] - 1])) ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">← Prev</a>
      <?php else: ?><span></span><?php endif; ?>
      <span class="text-xs text-slate-400">Page <?= $result['page'] ?> of <?= $result['pages'] ?></span>
      <?php if ($result['page'] < $result['pages']): ?>
        <a href="<?= e($qs(['page' => $result['page'] + 1])) ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">Next →</a>
      <?php else: ?><span></span><?php endif; ?>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="mt-4 rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <p class="mb-4 text-slate-500">No expenses recorded for this view.</p>
    <a href="<?= e(url('expenses/create')) ?>" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white">
      ➕ Record an expense
    </a>
  </div>
<?php endif; ?>

<p class="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-500 ring-1 ring-slate-200">
  Only running costs belong here. What you paid for the shoes themselves is already counted against each sale —
  entering it again would show a loss that isn't real.
</p>
