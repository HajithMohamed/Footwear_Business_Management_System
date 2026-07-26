<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('customers')) ?>" class="text-2xl">←</a>
  <h1 class="text-lg font-bold text-slate-800"><?= e($title) ?></h1>
</div>

<form method="post" action="<?= e(url($customer ? "customers/{$customer['id']}" : 'customers')) ?>" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
  <?= csrf_field() ?>

  <div class="grid gap-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
      <label class="block text-sm font-semibold text-slate-700 mb-1">Customer Name *</label>
      <input type="text" name="name" required value="<?= e($customer['name'] ?? '') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600">
    </div>

    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1">Phone</label>
      <input type="tel" name="phone" value="<?= e($customer['phone'] ?? '') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </div>

    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
      <input type="email" name="email" value="<?= e($customer['email'] ?? '') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </div>

    <div class="sm:col-span-2">
      <label class="block text-sm font-semibold text-slate-700 mb-1">Address</label>
      <input type="text" name="address" value="<?= e($customer['address'] ?? '') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </div>

    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1">City</label>
      <input type="text" name="city" value="<?= e($customer['city'] ?? '') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </div>

    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1">Region</label>
      <input type="text" name="region" value="<?= e($customer['region'] ?? '') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </div>

    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1">Customer Type *</label>
      <select name="customer_type" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">Select type…</option>
        <option value="retail" <?= ($customer['customer_type'] ?? '') === 'retail' ? 'selected' : '' ?>>Retail</option>
        <option value="wholesale" <?= ($customer['customer_type'] ?? '') === 'wholesale' ? 'selected' : '' ?>>Wholesale</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1">Credit Limit (Rs.)</label>
      <input type="number" name="credit_limit" step="0.01" value="<?= e($customer['credit_limit'] ?? '0') ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
    </div>

    <div class="sm:col-span-2">
      <label class="block text-sm font-semibold text-slate-700 mb-1">Notes</label>
      <textarea name="notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= e($customer['notes'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="mt-6 flex gap-2">
    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
      <?= $customer ? 'Save Changes' : 'Add Customer' ?>
    </button>
    <a href="<?= e(url('customers')) ?>" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
  </div>
</form>
