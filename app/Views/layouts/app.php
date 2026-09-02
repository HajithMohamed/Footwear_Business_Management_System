<?php
use App\Core\Auth;
$user = Auth::user();
$isAdmin = Auth::isAdmin();
$notificationPreview = [];
$notificationUnread = 0;
try {
    $notificationService = new \App\Services\NotificationService();
    $notificationPreview = $notificationService->all(5);
    $notificationUnread = $notificationService->unreadCount();
} catch (\Throwable $e) {
    // The main application remains usable while the database is being installed.
}
$currentPath = '/' . trim(str_replace(base_uri(), '', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
$navActive = function (string $prefix) use ($currentPath): string {
    $on = $prefix === '/' ? $currentPath === '/' : str_starts_with($currentPath, $prefix);
    return $on ? 'text-brand-600' : 'text-slate-400';
};
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f2557">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Shoe Bank') ?> · <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- App CSS (Design System) -->
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=<?= time() ?>">
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased content-with-bottom-nav md:pb-0" x-data>

<!-- Desktop Sidebar (Hidden on Mobile) -->
<aside class="desktop-sidebar hidden md:flex">
  <a href="<?= e(url('')) ?>" class="desktop-sidebar-logo">
    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white"><?= ui_icon('box', 'h-5 w-5') ?></span>
    <span><?= e(config('app.name')) ?></span>
  </a>

  <!-- Navigation Sections -->
  <div class="desktop-sidebar-section mt-4">
    <p class="desktop-sidebar-section-title">Operations</p>
    <a href="<?= e(url('')) ?>" class="desktop-sidebar-link <?= $navActive('/') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('home') ?></span> Dashboard
    </a>
    <a href="<?= e(url('customers')) ?>" class="desktop-sidebar-link <?= $navActive('/customers') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('users') ?></span> Customers
    </a>
    <a href="<?= e(url('products')) ?>" class="desktop-sidebar-link <?= $navActive('/products') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('box') ?></span> Products / Stock
    </a>
  </div>

  <div class="desktop-sidebar-section">
    <p class="desktop-sidebar-section-title">Purchasing</p>
    <a href="<?= e(url('purchases')) ?>" class="desktop-sidebar-link <?= $navActive('/purchases') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('purchase') ?></span> Purchases
    </a>
    <a href="<?= e(url('arrivals')) ?>" class="desktop-sidebar-link <?= $navActive('/arrivals') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('verify') ?></span> Verification
    </a>
    <a href="<?= e(url('clearance-persons')) ?>" class="desktop-sidebar-link <?= $navActive('/clearance') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('truck') ?></span> Clearance Persons
    </a>
  </div>

  <div class="desktop-sidebar-section">
    <p class="desktop-sidebar-section-title">Finance</p>
    <a href="<?= e(url('finance')) ?>" class="desktop-sidebar-link <?= $navActive('/finance') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('wallet') ?></span> Summary
    </a>
    <a href="<?= e(url('cheques')) ?>" class="desktop-sidebar-link <?= $navActive('/cheques') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('cheque') ?></span> Cheques
    </a>
    <a href="<?= e(url('expenses')) ?>" class="desktop-sidebar-link <?= $navActive('/expenses') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('expense') ?></span> Expenses
    </a>
    <a href="<?= e(url('reports')) ?>" class="desktop-sidebar-link <?= $navActive('/reports') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('chart') ?></span> Reports
    </a>
  </div>

  <div class="desktop-sidebar-section mt-auto mb-4">
    <p class="desktop-sidebar-section-title">System</p>
    <a href="<?= e(url('calculator')) ?>" class="desktop-sidebar-link <?= $navActive('/calculator') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('calculator') ?></span> Calculator
    </a>
    <a href="<?= e(url('notes')) ?>" class="desktop-sidebar-link <?= $navActive('/notes') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('note') ?></span> Notes
    </a>
    <?php if ($isAdmin): ?>
    <a href="<?= e(url('settings')) ?>" class="desktop-sidebar-link <?= $navActive('/settings') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon"><?= ui_icon('settings') ?></span> Settings
    </a>
    <?php endif; ?>
  </div>
</aside>

<!-- Top Bar (Mobile + Desktop User Menu) -->
<header class="mobile-top-bar fixed top-0 inset-x-0 z-30 bg-white text-slate-800 border-b border-slate-200">
  <div class="mx-auto max-w-5xl flex items-center justify-between h-14 px-4">
    
    <!-- Mobile Brand (Hidden on Desktop) -->
    <a href="<?= e(url('')) ?>" class="md:hidden flex items-center gap-2 font-bold text-lg text-brand-600">
      <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white"><?= ui_icon('box', 'h-5 w-5') ?></span>
      <span><?= e(config('app.name')) ?></span>
    </a>
    
    <!-- Empty div for desktop alignment since brand is in sidebar -->
    <div class="hidden md:block"></div>

    <div class="flex items-center gap-3">
      <!-- Notification center -->
      <div x-data="{open:false}" class="relative">
        <button type="button" @click="open=!open" @keydown.escape.window="open=false" aria-label="Open notifications" title="Notifications"
                :aria-expanded="open" class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.85 23.85 0 0 0 5.454-1.31A8.97 8.97 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.97 8.97 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.3 24.3 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
          <?php if ($notificationUnread > 0): ?><span class="absolute right-0 top-0 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white ring-2 ring-white"><?= min(99, $notificationUnread) ?></span><?php endif; ?>
        </button>
        <div x-show="open" x-cloak x-transition @click.outside="open=false" class="fixed left-3 right-3 top-14 z-50 mt-1 overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/10 sm:absolute sm:left-auto sm:right-0 sm:w-96">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3"><div><p class="font-bold text-slate-900">Notifications</p><p class="text-[11px] text-slate-500"><?= $notificationUnread ?> unread</p></div><a href="<?= e(url('notifications')) ?>" class="text-xs font-bold text-brand-600">View all</a></div>
          <?php if ($notificationPreview): ?><div class="max-h-[60vh] divide-y divide-slate-50 overflow-y-auto"><?php foreach ($notificationPreview as $notice): ?><form method="post" action="<?= e(url('notifications/read')) ?>"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($notice['id']) ?>"><input type="hidden" name="target" value="<?= e($notice['url']) ?>"><button class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-slate-50 <?= !$notice['read'] ? 'bg-brand-50/60' : '' ?>"><span class="mt-1 h-2 w-2 shrink-0 rounded-full <?= !$notice['read'] ? 'bg-brand-600' : 'bg-slate-200' ?>"></span><span class="min-w-0"><span class="block text-sm font-bold text-slate-800"><?= e($notice['title']) ?></span><span class="mt-0.5 block text-xs leading-5 text-slate-500"><?= e($notice['message']) ?></span><span class="mt-1 block text-[10px] font-medium text-slate-400"><?= e(date('j M Y · H:i', strtotime($notice['date']))) ?></span></span></button></form><?php endforeach; ?></div><?php else: ?><div class="px-5 py-8 text-center"><p class="text-sm font-bold text-slate-700">You're all caught up</p><p class="mt-1 text-xs text-slate-400">No reminders need attention.</p></div><?php endif; ?>
        </div>
      </div>
      
      <!-- User Menu -->
      <div x-data="{open:false}" class="relative">
        <button @click="open=!open" class="flex items-center justify-center h-8 w-8 rounded-full bg-brand-100 text-brand-600 font-bold text-sm ring-2 ring-white hover:bg-brand-200 transition">
            <?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?>
        </button>
        
        <div x-show="open" @click.outside="open=false" x-transition
             class="absolute right-0 mt-2 w-56 rounded-2xl bg-white text-slate-700 shadow-lg ring-1 ring-black/5 overflow-hidden" style="display:none">
          <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
            <p class="text-sm font-bold text-slate-800"><?= e($user['name'] ?? '') ?></p>
            <p class="text-[10px] uppercase tracking-wide font-bold text-slate-500 mt-0.5"><?= e($user['role_label'] ?? '') ?></p>
          </div>
          <?php if ($isAdmin): ?>
            <a href="<?= e(url('settings')) ?>" class="flex items-center gap-2 px-4 py-3 text-sm font-medium hover:bg-slate-50 border-b border-slate-50"><?= ui_icon('settings', 'h-4 w-4') ?> Settings</a>
          <?php endif; ?>
          <form method="post" action="<?= e(url('logout')) ?>">
            <?= csrf_field() ?>
            <button class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-medium text-red-600 hover:bg-red-50 transition"><?= ui_icon('logout', 'h-4 w-4') ?> Sign out</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Main Content Area -->
<main class="app-main mx-auto max-w-5xl px-4 pb-24 pt-20 md:pb-10">
  <!-- Flash Messages Partial -->
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  
  <!-- Page Content -->
  <?= $content ?>
</main>

<!-- Mobile Bottom Navigation (Hidden on Desktop) -->
<nav class="mobile-bottom-nav fixed bottom-0 inset-x-0 z-30 bg-white border-t border-slate-200 pb-safe md:hidden">
  <div class="grid grid-cols-5 h-16 relative">
    <a href="<?= e(url('')) ?>" class="flex flex-col items-center justify-center gap-1 <?= $navActive('/') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5M5 9.5V21h14V9.5"/></svg>
      <span class="text-[10px] font-bold">Home</span>
    </a>
    <a href="<?= e(url('products')) ?>" class="flex flex-col items-center justify-center gap-1 <?= $navActive('/products') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
      <span class="text-[10px] font-bold">Products</span>
    </a>
    <a href="<?= e(url('customers')) ?>" class="flex flex-col items-center justify-center gap-1 <?= $navActive('/customers') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.5a3 3 0 00-3-3H8.25a3 3 0 00-3 3M18.75 12.75h3m-9.75-6a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <span class="text-[10px] font-bold">Customers</span>
    </a>
    <a href="<?= e(url('finance')) ?>" class="flex flex-col items-center justify-center gap-1 <?= $navActive('/finance') ?>">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span class="text-[10px] font-bold">Finance</span>
    </a>
    
    <!-- More Menu Trigger -->
    <div x-data="{showMore:false}" class="flex flex-col items-center justify-center">
      <button @click="showMore=true" class="flex flex-col items-center justify-center gap-1 text-slate-400 hover:text-brand-600 transition w-full h-full">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        <span class="text-[10px] font-bold">More</span>
      </button>

      <!-- More Menu Slide-over -->
      <div x-show="showMore" style="display:none" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" x-show="showMore" x-transition.opacity @click="showMore=false"></div>
        <div @click.stop class="bg-slate-50 w-[85%] max-w-sm h-full shadow-2xl flex flex-col relative" x-show="showMore" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
          
          <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center bg-white sticky top-0 z-10 shadow-sm">
            <h2 class="font-bold text-slate-800 text-lg">Menu</h2>
            <button @click="showMore=false" class="h-8 w-8 flex justify-center items-center rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          
          <div class="flex-1 overflow-y-auto p-4 space-y-6 pb-24">
            
            <div>
              <p class="text-[10px] font-bold text-brand-600 uppercase tracking-wider mb-2 px-1">Purchases & Receiving</p>
              <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <a href="<?= e(url('purchases')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><?= ui_icon('purchase') ?> Purchases</a>
                <a href="<?= e(url('arrivals')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><?= ui_icon('verify') ?> Verification</a>
                <a href="<?= e(url('clearance-persons')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold transition"><?= ui_icon('truck') ?> Clearance Persons</a>
              </div>
            </div>

            <div>
              <p class="text-[10px] font-bold text-brand-600 uppercase tracking-wider mb-2 px-1">Money & Insights</p>
              <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <a href="<?= e(url('cheques')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><?= ui_icon('cheque') ?> Cheques</a>
                <a href="<?= e(url('expenses')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><?= ui_icon('expense') ?> Expenses</a>
                <a href="<?= e(url('reports')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold transition"><?= ui_icon('chart') ?> Reports</a>
              </div>
            </div>

            <div>
              <p class="text-[10px] font-bold text-brand-600 uppercase tracking-wider mb-2 px-1">System</p>
              <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <a href="<?= e(url('calculator')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><?= ui_icon('calculator') ?> Calculator</a>
                <a href="<?= e(url('notes')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><?= ui_icon('note') ?> Notes</a>
                <?php if ($isAdmin): ?>
                <a href="<?= e(url('settings')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold transition"><?= ui_icon('settings') ?> Settings</a>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Inject partials -->
<?php require BASE_PATH . '/app/Views/partials/confirm-dialog.php'; ?>

</body>
</html>
