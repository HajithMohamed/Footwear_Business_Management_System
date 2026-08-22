<div class="mb-4 flex items-center justify-between">
  <div class="flex items-center gap-3">
    <a href="<?= e(url('customers')) ?>" class="h-10 w-10 flex items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-slate-200 text-slate-600 active:scale-95 transition">
      <span class="text-xl">←</span>
    </a>
    <h1 class="text-xl font-bold text-slate-800"><?= e($title) ?></h1>
  </div>
</div>

<form method="post" action="<?= e(url($customer ? "customers/{$customer['id']}" : 'customers')) ?>" class="pb-40 md:pb-24">
  <?= csrf_field() ?>

  <!-- Basic Information -->
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-4 pb-2 border-b border-slate-100">Basic Information</h2>
    
    <div class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Shop / Customer Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" required value="<?= e(old('name', $customer['name'] ?? '')) ?>" placeholder="e.g. ABC Footwear"
               class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Mobile / WhatsApp</label>
          <input type="tel" name="phone" value="<?= e(old('phone', $customer['phone'] ?? '')) ?>" placeholder="+94 77 123 4567" inputmode="tel"
                 class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
          <p class="mt-1 text-[10px] text-slate-400">Local numbers are saved automatically with Sri Lankan country code +94.</p>
          <?php if ($msg = error('phone')): ?><p class="mt-1 text-xs text-red-600"><?= e($msg) ?></p><?php endif; ?>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Email</label>
          <input type="email" name="email" value="<?= e($customer['email'] ?? '') ?>" placeholder="mail@example.com"
                 class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
        </div>
      </div>
    </div>
  </div>

  <!-- Location -->
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-4 pb-2 border-b border-slate-100">Location</h2>
    
    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">City</label>
          <input type="text" name="city" value="<?= e($customer['city'] ?? '') ?>" placeholder="e.g. Colombo"
                 class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Region / Route</label>
          <input type="text" name="region" value="<?= e($customer['region'] ?? '') ?>" placeholder="e.g. Western"
                 class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
        </div>
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Full Address</label>
        <input type="text" name="address" value="<?= e($customer['address'] ?? '') ?>" placeholder="Street address..."
               class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
      </div>
    </div>
  </div>

  <!-- Credit Details -->
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-4 pb-2 border-b border-slate-100">Credit Details</h2>
    
    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Customer Type <span class="text-red-500">*</span></label>
          <select name="customer_type" required class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition">
            <option value="wholesale" <?= ($customer['customer_type'] ?? 'wholesale') === 'wholesale' ? 'selected' : '' ?>>Wholesale</option>
            <option value="retail" <?= ($customer['customer_type'] ?? '') === 'retail' ? 'selected' : '' ?>>Retail</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Credit Limit (Rs.)</label>
          <input type="number" name="credit_limit" step="1000" min="0" value="<?= e(isset($customer['credit_limit']) ? (int) $customer['credit_limit'] : 0) ?>" 
                 class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition text-right font-bold">
        </div>
      </div>
      
      <?php if (!$customer): ?>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Opening Balance (Rs.)</label>
        <input type="number" name="opening_balance" step="0.01" min="0" value="0" 
               class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition text-right font-bold">
        <p class="text-[10px] text-slate-400 mt-1">Leave as 0 if they don't owe anything currently.</p>
      </div>
      <?php else: ?>
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Current Outstanding</label>
        <p class="w-full rounded-xl border-0 bg-slate-100 px-4 py-3 text-sm font-bold text-slate-500 ring-1 ring-slate-200 text-right">Rs. <?= number_format($customer['outstanding_due'] ?? 0, 2) ?></p>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Additional -->
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 mb-4">
    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-4 pb-2 border-b border-slate-100">Additional</h2>
    
    <div>
      <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Notes</label>
      <textarea name="notes" rows="3" placeholder="Special requirements, delivery instructions..."
                class="w-full rounded-xl border-0 bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-600 transition"><?= e($customer['notes'] ?? '') ?></textarea>
    </div>
  </div>

  <?php if ($customer && !empty($customer['updated_at'])): ?>
    <div class="mb-4 text-center">
      <p class="text-xs text-slate-400">
        Last updated on <?= e(date('d M Y, h:i A', strtotime($customer['updated_at']))) ?>
      </p>
    </div>
  <?php endif; ?>

  <!-- Sticky Action Bar -->
  <div class="fixed bottom-[69px] left-0 right-0 md:bottom-0 md:left-64 z-40 bg-white border-t border-slate-200 p-4 shadow-[0_-10px_30px_rgba(0,0,0,0.05)]">
    <div class="max-w-3xl mx-auto flex gap-3">
      <a href="<?= e(url($customer ? "customers/{$customer['id']}" : 'customers')) ?>" 
         class="flex-1 flex justify-center items-center h-12 rounded-xl bg-slate-100 text-slate-600 font-bold active:scale-95 transition">
        Cancel
      </a>
      <button type="submit" 
              class="flex-[2] flex justify-center items-center h-12 rounded-xl bg-brand-600 text-white font-bold shadow-sm active:scale-95 transition">
        <?= $customer ? 'Save Changes' : 'Add Customer' ?>
      </button>
    </div>
  </div>

</form>
