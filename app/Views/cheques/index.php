<div class="mb-4">
  <h1 class="text-lg font-bold text-slate-800">Cheques</h1>
</div>

<!-- Status cards -->
<div class="grid gap-3 sm:grid-cols-4 mb-4">
  <?php foreach ([
    'pending' => ['icon' => '⏳', 'label' => 'Pending', 'color' => 'bg-amber-100 text-amber-700'],
    'cleared' => ['icon' => '✅', 'label' => 'Cleared', 'color' => 'bg-green-100 text-green-700'],
    'bounced' => ['icon' => '❌', 'label' => 'Bounced', 'color' => 'bg-red-100 text-red-700'],
    'cancelled' => ['icon' => '🚫', 'label' => 'Cancelled', 'color' => 'bg-slate-100 text-slate-700']
  ] as $status => $config): ?>
    <?php $count = 0; foreach ($stats ?? [] as $s) { if ($s['status'] === $status) { $count = (int)$s['count']; break; }} ?>
    <a href="<?= e(url("cheques?status={$status}")) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
      <div class="text-2xl mb-1"><?= $config['icon'] ?></div>
      <p class="text-xs font-medium text-slate-400"><?= $config['label'] ?></p>
      <p class="mt-1 text-2xl font-bold <?= $config['color'] ?>"><?= $count ?></p>
    </a>
  <?php endforeach; ?>
</div>

<!-- Cheques list -->
<?php if (!empty($cheques)): ?>
  <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="border-b border-slate-100 bg-slate-50">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Cheque No.</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Customer</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Bank</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Amount</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Cheque Date</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($cheques as $ch): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-700"><?= e($ch['cheque_number']) ?></td>
            <td class="px-4 py-3"><a href="<?= e(url("customers/{$ch['customer_id']}")) ?>" class="text-brand-600 hover:underline"><?= e($ch['customer_name']) ?></a></td>
            <td class="px-4 py-3 text-slate-600"><?= e($ch['bank_name'] ?? '—') ?></td>
            <td class="px-4 py-3 font-medium">Rs. <?= number_format($ch['amount'], 2) ?></td>
            <td class="px-4 py-3 text-slate-500 text-xs"><?= date('M d, Y', strtotime($ch['cheque_date'])) ?></td>
            <td class="px-4 py-3">
              <span class="inline-block px-2 py-1 rounded text-xs font-semibold
                <?= match($ch['status']) {
                  'pending' => 'bg-amber-100 text-amber-700',
                  'cleared' => 'bg-green-100 text-green-700',
                  'bounced' => 'bg-red-100 text-red-700',
                  'cancelled' => 'bg-slate-100 text-slate-700',
                  default => ''
                } ?>">
                <?= ucfirst($ch['status']) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button @click="showUpdate(<?= $ch['id'] ?>, '<?= e($ch['status']) ?>')" class="text-slate-400 hover:text-slate-600">⚙️</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <p class="text-slate-500">No cheques found.</p>
  </div>
<?php endif; ?>

<script>
function showUpdate(id, status) {
  const newStatus = prompt(`Current status: ${status}\n\nUpdate to:`, 'cleared');
  if (!newStatus) return;
  if (!['pending', 'cleared', 'bounced', 'cancelled'].includes(newStatus)) {
    alert('Invalid status. Use: pending, cleared, bounced, cancelled');
    return;
  }
  if (newStatus === 'bounced') {
    const reason = prompt('Bounce reason:');
    if (!reason) return;
    submitStatusUpdate(id, newStatus, reason);
  } else {
    submitStatusUpdate(id, newStatus);
  }
}

function submitStatusUpdate(id, status, reason = null) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = `/cheques/${id}/status`;
  form.innerHTML = `
    <input type="hidden" name="status" value="${status}">
    ${reason ? `<input type="hidden" name="bounce_reason" value="${reason}">` : ''}
    <input type="hidden" name="_token" value="${document.querySelector('input[name="_token"]')?.value || ''}">
  `;
  document.body.appendChild(form);
  form.submit();
}
</script>
