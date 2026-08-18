<?php
use App\Core\Auth;
$user = Auth::user();
$isAdmin = Auth::isAdmin();
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
    <!-- Tailwind via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
            colors: {
              brand: { 50:'#eff6ff', 100:'#dbeafe', 400:'#60a5fa', 500:'#3b82f6', 600:'#1E3A8A', 700:'#1e40af', 900:'#0f2557' }
            }
          }
        }
      };
    </script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- App CSS (Design System) -->
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=<?= time() ?>">
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased content-with-bottom-nav md:pb-0" x-data>

<!-- Desktop Sidebar (Hidden on Mobile) -->
<aside class="desktop-sidebar hidden md:flex">
  <a href="<?= e(url('')) ?>" class="desktop-sidebar-logo">
    <span class="text-2xl">👞</span>
    <span><?= e(config('app.name')) ?></span>
  </a>

  <!-- Navigation Sections -->
  <div class="desktop-sidebar-section mt-4">
    <p class="desktop-sidebar-section-title">Operations</p>
    <a href="<?= e(url('')) ?>" class="desktop-sidebar-link <?= $navActive('/') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">🏠</span> Home
    </a>
    <a href="<?= e(url('sales')) ?>" class="desktop-sidebar-link <?= $navActive('/sales') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">🧾</span> Sales
    </a>
    <a href="<?= e(url('customers')) ?>" class="desktop-sidebar-link <?= $navActive('/customers') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">👥</span> Customers
    </a>
    <a href="<?= e(url('products')) ?>" class="desktop-sidebar-link <?= $navActive('/products') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">📦</span> Products
    </a>
  </div>

  <div class="desktop-sidebar-section">
    <p class="desktop-sidebar-section-title">Purchasing</p>
    <a href="<?= e(url('purchases')) ?>" class="desktop-sidebar-link <?= $navActive('/purchases') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">🚢</span> Purchases
    </a>
    <a href="<?= e(url('arrivals')) ?>" class="desktop-sidebar-link <?= $navActive('/arrivals') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">✅</span> Verify Arrivals
    </a>
    <a href="<?= e(url('clearance-persons')) ?>" class="desktop-sidebar-link <?= $navActive('/clearance') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">🚛</span> Clearance Persons
    </a>
  </div>

  <div class="desktop-sidebar-section">
    <p class="desktop-sidebar-section-title">Finance</p>
    <a href="<?= e(url('finance')) ?>" class="desktop-sidebar-link <?= $navActive('/finance') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">💰</span> Dashboard
    </a>
    <a href="<?= e(url('cheques')) ?>" class="desktop-sidebar-link <?= $navActive('/cheques') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">🏦</span> Cheques
    </a>
    <a href="<?= e(url('expenses')) ?>" class="desktop-sidebar-link <?= $navActive('/expenses') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">💸</span> Expenses
    </a>
    <a href="<?= e(url('reports')) ?>" class="desktop-sidebar-link <?= $navActive('/reports') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">📊</span> Reports
    </a>
  </div>

  <div class="desktop-sidebar-section mt-auto mb-4">
    <p class="desktop-sidebar-section-title">System</p>
    <a href="<?= e(url('calculator')) ?>" class="desktop-sidebar-link <?= $navActive('/calculator') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">🧮</span> Calculator
    </a>
    <a href="<?= e(url('notes')) ?>" class="desktop-sidebar-link <?= $navActive('/notes') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">📝</span> Notes
    </a>
    <?php if ($isAdmin): ?>
    <a href="<?= e(url('settings')) ?>" class="desktop-sidebar-link <?= $navActive('/settings') === 'text-brand-600' ? 'desktop-sidebar-link-active' : '' ?>">
      <span class="desktop-sidebar-link-icon">⚙️</span> Settings
    </a>
    <?php endif; ?>
  </div>
</aside>

<!-- Top Bar (Mobile + Desktop User Menu) -->
<header class="mobile-top-bar fixed top-0 inset-x-0 z-30 bg-white text-slate-800 border-b border-slate-200">
  <div class="mx-auto max-w-5xl flex items-center justify-between h-14 px-4">
    
    <!-- Mobile Brand (Hidden on Desktop) -->
    <a href="<?= e(url('')) ?>" class="md:hidden flex items-center gap-2 font-bold text-lg text-brand-600">
      <span class="text-2xl">👞</span>
      <span><?= e(config('app.name')) ?></span>
    </a>
    
    <!-- Empty div for desktop alignment since brand is in sidebar -->
    <div class="hidden md:block"></div>

    <div class="flex items-center gap-3">
      <!-- Notification Bell -->
      <button class="relative p-2 text-slate-400 hover:text-brand-600 transition">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
      </button>
      
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
            <a href="<?= e(url('settings')) ?>" class="block px-4 py-3 text-sm font-medium hover:bg-slate-50 border-b border-slate-50">⚙️ Settings</a>
          <?php endif; ?>
          <form method="post" action="<?= e(url('logout')) ?>">
            <?= csrf_field() ?>
            <button class="w-full text-left px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50 transition">🚪 Sign out</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Main Content Area -->
<main class="app-main mx-auto max-w-5xl px-4 pt-20">
  <!-- Flash Messages Partial -->
  <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
  
  <!-- Page Content -->
  <?= $content ?>
</main>

<!-- Floating Action Button (FAB) -->
<div class="fab-container fixed bottom-20 right-4 sm:right-auto sm:ml-[calc(1024px-4.5rem)] z-40 md:bottom-8 md:right-8 md:ml-0" x-data="{open:false}">
  <button @click="open=true" class="h-14 w-14 rounded-full bg-brand-600 text-white shadow-lg flex items-center justify-center active:scale-95 transition hover:bg-brand-700 hover:shadow-xl">
    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
  </button>
  
  <!-- Action Sheet Overlay -->
  <div class="bottom-sheet" x-show="open" style="display:none">
    <div class="bottom-sheet-backdrop" x-show="open" x-transition.opacity @click="open=false"></div>
    <div class="bottom-sheet-content" @click.stop x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
      <div class="bottom-sheet-handle"></div>
      <h3 class="text-lg font-bold text-slate-800 mb-4 px-2">Quick Actions</h3>
      
      <div class="grid grid-cols-1 gap-2 mb-4">
        <a href="<?= e(url('sales/create')) ?>" class="flex items-center gap-4 rounded-xl bg-brand-50 px-4 py-3.5 active:bg-brand-100 transition">
          <div class="h-10 w-10 rounded-full bg-brand-600 text-white flex items-center justify-center text-lg shadow-sm">🧾</div>
          <span class="font-bold text-brand-900 text-base">New Sale Invoice</span>
        </a>
        <a href="<?= e(url('customers')) ?>" class="flex items-center gap-4 rounded-xl bg-green-50 px-4 py-3.5 active:bg-green-100 transition border border-green-100">
          <div class="h-10 w-10 rounded-full bg-green-600 text-white flex items-center justify-center text-lg shadow-sm">💵</div>
          <span class="font-bold text-green-900 text-base">Record Payment</span>
        </a>
        <a href="<?= e(url('purchases/import')) ?>" class="flex items-center gap-4 rounded-xl bg-slate-50 px-4 py-3.5 active:bg-slate-100 transition border border-slate-200">
          <div class="h-10 w-10 rounded-full bg-white text-slate-700 flex items-center justify-center text-lg shadow-sm ring-1 ring-slate-200">🚢</div>
          <span class="font-semibold text-slate-700 text-base">New Import Purchase</span>
        </a>
        <a href="<?= e(url('customers/create')) ?>" class="flex items-center gap-4 rounded-xl bg-slate-50 px-4 py-3.5 active:bg-slate-100 transition border border-slate-200">
          <div class="h-10 w-10 rounded-full bg-white text-slate-700 flex items-center justify-center text-lg shadow-sm ring-1 ring-slate-200">👤</div>
          <span class="font-semibold text-slate-700 text-base">Add Customer</span>
        </a>
        <a href="<?= e(url('products/create')) ?>" class="flex items-center gap-4 rounded-xl bg-slate-50 px-4 py-3.5 active:bg-slate-100 transition border border-slate-200">
          <div class="h-10 w-10 rounded-full bg-white text-slate-700 flex items-center justify-center text-lg shadow-sm ring-1 ring-slate-200">📦</div>
          <span class="font-semibold text-slate-700 text-base">Add Product</span>
        </a>
      </div>
      <button @click="open=false" class="w-full rounded-xl bg-slate-100 px-4 py-3.5 font-bold text-slate-600 hover:bg-slate-200 transition">Cancel</button>
    </div>
  </div>
</div>

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
                <a href="<?= e(url('purchases')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><span class="text-xl">🚢</span> Purchases</a>
                <a href="<?= e(url('arrivals')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><span class="text-xl">🚚</span> Verify Arrivals</a>
                <a href="<?= e(url('clearance-persons')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold transition"><span class="text-xl">👷</span> Clearance Persons</a>
              </div>
            </div>

            <div>
              <p class="text-[10px] font-bold text-brand-600 uppercase tracking-wider mb-2 px-1">Money & Insights</p>
              <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <a href="<?= e(url('cheques')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><span class="text-xl">🧾</span> Cheques</a>
                <a href="<?= e(url('sales')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><span class="text-xl">📃</span> Sales</a>
                <a href="<?= e(url('expenses')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><span class="text-xl">💸</span> Expenses</a>
                <a href="<?= e(url('reports')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold transition"><span class="text-xl">📊</span> Reports</a>
              </div>
            </div>

            <div>
              <p class="text-[10px] font-bold text-brand-600 uppercase tracking-wider mb-2 px-1">System</p>
              <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <a href="<?= e(url('calculator')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><span class="text-xl">🧮</span> Calculator</a>
                <a href="<?= e(url('notes')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold border-b border-slate-50 transition"><span class="text-xl">📝</span> Notes</a>
                <?php if ($isAdmin): ?>
                <a href="<?= e(url('settings')) ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 text-slate-700 font-semibold transition"><span class="text-xl">⚙️</span> Settings</a>
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
