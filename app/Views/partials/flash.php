<?php
use App\Core\Session;
$flash = Session::getFlash();
if (!$flash) {
    return;
}
$styles = [
    'success' => 'bg-green-50 text-green-800 ring-green-200',
    'error'   => 'bg-red-50 text-red-700 ring-red-200',
    'info'    => 'bg-blue-50 text-blue-700 ring-blue-200',
];
?>
<div class="space-y-2 mb-4" x-data="{show:true}" x-show="show">
  <?php foreach ($flash as $type => $message): ?>
    <div class="flex items-start justify-between gap-3 rounded-xl px-4 py-3 text-sm ring-1 <?= $styles[$type] ?? $styles['info'] ?>">
      <span><?= e($message) ?></span>
      <button @click="show=false" class="opacity-50 hover:opacity-100">&times;</button>
    </div>
  <?php endforeach; ?>
</div>
