<?php
$flashes = \App\Core\Session::getFlash();

// Standardized icons based on type
$icons = [
    'success' => '✅',
    'error'   => '❌',
    'warning' => '⚠️',
    'info'    => 'ℹ️',
];

if (!empty($flashes)): 
?>
  <div class="fixed top-20 left-1/2 -translate-x-1/2 z-50 w-full max-w-sm px-4 flex flex-col gap-2 pointer-events-none">
    <?php foreach ($flashes as $type => $message): ?>
      <?php 
        $icon = $icons[$type] ?? $icons['info'];
        $class = match($type) {
          'success' => 'flash-success',
          'error'   => 'flash-error',
          'warning' => 'flash-warning',
          default   => 'flash-info',
        };
      ?>
      <div x-data="{ show: true }" 
           x-show="show" 
           x-init="setTimeout(() => show = false, 4000)"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="opacity-0 -translate-y-4"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="opacity-100 scale-100"
           x-transition:leave-end="opacity-0 scale-95"
           class="flash-message <?= $class ?> shadow-lg pointer-events-auto cursor-pointer"
           @click="show = false">
        <span class="flash-icon"><?= $icon ?></span>
        <div class="flex-1 pt-0.5">
          <?= nl2br(e($message)) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
