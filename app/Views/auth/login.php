<div class="text-center mb-6">
  <div class="mx-auto mb-3 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15"><?= ui_icon('box', 'h-8 w-8') ?></div>
  <h1 class="text-xl font-bold text-white"><?= e(config('app.name')) ?></h1>
  <p class="text-sm text-white/70">Sign in to manage your shop</p>
</div>

<form method="post" action="<?= e(url('login')) ?>" class="bg-white rounded-2xl shadow-xl p-6 space-y-4">
  <?= csrf_field() ?>

  <div>
    <label class="block text-sm font-medium text-slate-600 mb-1">Username</label>
    <input name="username" value="<?= e(old('username')) ?>" autofocus autocomplete="username"
           class="w-full rounded-xl border <?= error('username') ? 'border-red-300' : 'border-slate-200' ?> px-4 py-3 text-[15px] focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none"
           placeholder="admin">
    <?php if ($msg = error('username')): ?>
      <p class="mt-1 text-xs text-red-600"><?= e($msg) ?></p>
    <?php endif; ?>
  </div>

  <div>
    <label class="block text-sm font-medium text-slate-600 mb-1">Password</label>
    <div class="relative">
      <input id="password" type="password" name="password" autocomplete="current-password"
             class="w-full rounded-xl border border-slate-200 px-4 py-3 pr-12 text-[15px] focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none"
             placeholder="••••••••">
      <button type="button" id="password-toggle" aria-label="Show password" aria-pressed="false"
              class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-slate-400 transition hover:text-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
        <svg id="password-eye-open" aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12S5.25 5.25 12 5.25 21.75 12 21.75 12 18.75 18.75 12 18.75 2.25 12 2.25 12Z" />
          <circle cx="12" cy="12" r="2.75" />
        </svg>
        <svg id="password-eye-closed" aria-hidden="true" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.6 10.6A2 2 0 0 0 13.4 13.4M9.9 5.45A10.7 10.7 0 0 1 12 5.25c6.75 0 9.75 6.75 9.75 6.75a18.7 18.7 0 0 1-3.4 4.22M6.1 6.1C3.68 7.72 2.25 12 2.25 12S5.25 18.75 12 18.75c1.03 0 1.95-.16 2.78-.43" />
        </svg>
      </button>
    </div>
  </div>

  <button class="w-full rounded-xl bg-brand-600 px-4 py-3 font-semibold text-white shadow-lg shadow-brand-600/25 active:scale-[.99] transition">
    Sign in
  </button>
</form>

<script>
  (() => {
    const password = document.getElementById('password');
    const toggle = document.getElementById('password-toggle');
    const openEye = document.getElementById('password-eye-open');
    const closedEye = document.getElementById('password-eye-closed');

    toggle.addEventListener('click', () => {
      const visible = password.type === 'password';
      password.type = visible ? 'text' : 'password';
      toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
      toggle.setAttribute('aria-pressed', String(visible));
      openEye.classList.toggle('hidden', visible);
      closedEye.classList.toggle('hidden', !visible);
    });
  })();
</script>

<p class="mt-4 text-center text-xs text-white/60">
  Default: <span class="font-mono">admin</span> / <span class="font-mono">admin123</span> — change after first login.
</p>
