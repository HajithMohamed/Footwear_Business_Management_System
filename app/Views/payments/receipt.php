<?php
use App\Services\StorageService;

$remaining = (float) ($payment['running_balance'] ?? 0);
$previous = $remaining + (float) $payment['amount'];
$formatDate = static fn ($value, string $fallback = '—'): string => !empty($value) ? date('j M Y', strtotime((string) $value)) : $fallback;
$paymentDate = $formatDate($payment['payment_date'] ?? $payment['created_at'] ?? null);
?>
<div class="mx-auto max-w-md">
  <div id="payment-receipt" class="rounded-3xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-100">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-green-700"><svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></div>
    <h1 class="mt-3 text-xl font-bold text-slate-900">Payment Received</h1><p class="text-sm text-slate-500"><?= e($payment['customer_name']) ?></p>
    <p class="mt-5 text-3xl font-bold text-green-600"><?= money($payment['amount']) ?></p><p class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= e(ucfirst($payment['payment_method'])) ?> payment</p>
    <dl class="mt-5 space-y-3 border-y border-slate-100 py-4 text-sm"><div class="flex justify-between"><dt class="text-slate-500">Payment date</dt><dd class="font-bold"><?= e($paymentDate) ?></dd></div><?php if ($payment['payment_method'] === 'cheque'): ?><div class="flex justify-between"><dt class="text-slate-500">Cheque number</dt><dd class="font-bold"><?= e($payment['cheque_number']) ?></dd></div><div class="flex justify-between"><dt class="text-slate-500">Cheque date</dt><dd class="font-bold"><?= e($formatDate($payment['cheque_date'] ?? null)) ?></dd></div><?php endif; ?><div class="flex justify-between"><dt class="text-slate-500">Previous balance</dt><dd><?= money($previous) ?></dd></div><div class="flex justify-between text-base"><dt class="font-bold">Remaining balance</dt><dd class="font-bold text-brand-700"><?= money($remaining) ?></dd></div></dl>
    <?php if (!empty($payment['cheque_image_path'])): ?><a href="<?= e(StorageService::url($payment['cheque_image_path'])) ?>" target="_blank" class="mt-4 inline-flex text-sm font-bold text-brand-600">View cheque image</a><?php endif; ?>
  </div>
  <div class="mt-4 grid gap-2"><button type="button" onclick="sharePayment()" class="btn btn-success btn-full">Share Payment</button><a href="<?= e(url('payments/' . $payment['id'] . '/edit')) ?>" class="btn btn-outline btn-full"><?= ui_icon('pencil', 'h-4 w-4') ?> Edit Payment</a><a href="<?= e(url('customers/' . $payment['customer_id'])) ?>" class="btn btn-primary btn-full">View Ledger</a><a href="<?= e(url('')) ?>" class="py-2 text-center text-sm font-semibold text-slate-500">Done</a></div>
</div>
<script>function sharePayment(){const text=<?= json_encode('Payment received from ' . $payment['customer_name'] . ': ' . money($payment['amount']) . ' on ' . $paymentDate . '. Remaining balance: ' . money($remaining)) ?>;if(navigator.share){navigator.share({title:'Payment Received',text});}else{navigator.clipboard.writeText(text).then(()=>alert('Payment summary copied.'));}}</script>
