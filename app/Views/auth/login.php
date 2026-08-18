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

  <div x-data="{show:false}">
    <label class="block text-sm font-medium text-slate-600 mb-1">Password</label>
    <div class="relative">
      <input :type="show ? 'text' : 'password'" name="password" autocomplete="current-password"
             class="w-full rounded-xl border border-slate-200 px-4 py-3 pr-12 text-[15px] focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none"
             placeholder="••••••••">
      <button type="button" @click="show=!show" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">
        <span x-text="show ? 'Hide' : 'Show'"></span>
      </button>
    </div>
  </div>

  <button class="w-full rounded-xl bg-brand-600 px-4 py-3 font-semibold text-white shadow-lg shadow-brand-600/25 active:scale-[.99] transition">
    Sign in
  </button>
</form>

<p class="mt-4 text-center text-xs text-white/60">
  Default: <span class="font-mono">admin</span> / <span class="font-mono">admin123</span> — change after first login.
</p>
