<?php
use App\Core\Auth;
use App\Services\StorageService;

$images = $product['images'] ?? [];
// Group images by colour (blank colour -> "Unspecified")
$groups = [];
foreach ($images as $img) {
    $groups[$img['colour'] ?: 'Unspecified'][] = $img;
}
$main = null;
foreach ($images as $img) { if ($img['is_main']) { $main = $img; break; } }
$main = $main ?: ($images[0] ?? null);

$typeBadge = [
    'imported' => 'bg-blue-100 text-blue-700',
    'local'    => 'bg-green-100 text-green-700',
    'custom'   => 'bg-purple-100 text-purple-700',
];
$fmt = fn ($v) => $v !== null && $v !== '' ? 'Rs. ' . number_format((float) $v, 2) : '—';
?>

<div class="flex items-center justify-between mb-4">
  <div class="flex items-center gap-2 min-w-0">
    <a href="<?= e(url('products')) ?>" class="text-slate-400">←</a>
    <h1 class="text-lg font-bold text-slate-800 truncate"><?= e($product['brand_name'] ?? 'Product') ?> <?= e($product['art_no'] ?? '') ?></h1>
  </div>
  <a href="<?= e(url('products/'.$product['id'].'/edit')) ?>" class="shrink-0 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Edit</a>
</div>

<!-- Gallery -->
<div x-data="{ big: '<?= $main ? e(StorageService::url($main['path'])) : '' ?>' }" class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
  <?php if ($images): ?>
    <div class="aspect-square w-full overflow-hidden rounded-xl bg-slate-100">
      <img :src="big" alt="" class="h-full w-full object-contain">
    </div>
    <?php foreach ($groups as $colour => $imgs): ?>
      <div class="mt-3">
        <p class="mb-1.5 flex items-center gap-2 text-xs font-semibold text-slate-500">
          <span class="inline-block h-2 w-2 rounded-full bg-brand-400"></span><?= e($colour) ?>
          <span class="text-slate-300">(<?= count($imgs) ?>)</span>
        </p>
        <div class="grid grid-cols-5 gap-2">
          <?php foreach ($imgs as $img): ?>
            <button type="button" @click="big='<?= e(StorageService::url($img['path'])) ?>'"
                    class="relative aspect-square overflow-hidden rounded-lg ring-1 ring-slate-100 active:scale-95">
              <img src="<?= e(StorageService::url($img['thumb_path'] ?: $img['path'])) ?>" alt="" loading="lazy" class="h-full w-full object-cover">
              <?php if ($img['is_main']): ?><span class="absolute bottom-0 inset-x-0 bg-brand-600/80 text-white text-[8px] text-center">main</span><?php endif; ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="aspect-square w-full rounded-xl bg-slate-100 flex items-center justify-center">
      <span class="text-5xl text-slate-300">👟</span>
    </div>
    <p class="mt-2 text-center text-xs text-slate-400">No images yet — <a href="<?= e(url('products/'.$product['id'].'/edit')) ?>" class="text-brand-600">add some</a>.</p>
  <?php endif; ?>
</div>

<!-- Summary -->
<div class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-center gap-2 mb-3">
    <span class="rounded-md px-2 py-0.5 text-[11px] font-semibold <?= $typeBadge[$product['type']] ?? 'bg-slate-100 text-slate-600' ?>"><?= ucfirst($product['type']) ?></span>
    <?php if ($product['stock_sets'] <= 0): ?>
      <span class="rounded-md bg-red-100 text-red-600 px-2 py-0.5 text-[11px] font-semibold">Out of stock</span>
    <?php elseif ($product['stock_sets'] <= $product['low_stock_threshold']): ?>
      <span class="rounded-md bg-amber-100 text-amber-700 px-2 py-0.5 text-[11px] font-semibold">Low stock</span>
    <?php endif; ?>
  </div>
  <dl class="grid grid-cols-2 gap-y-3 text-sm">
    <div><dt class="text-xs text-slate-400">Brand</dt><dd class="font-medium text-slate-700"><?= e($product['brand_name'] ?? '—') ?></dd></div>
    <div><dt class="text-xs text-slate-400">Art number</dt><dd class="font-medium text-slate-700"><?= e($product['art_no'] ?? '—') ?></dd></div>
    <div><dt class="text-xs text-slate-400">Category</dt><dd class="font-medium text-slate-700"><?= e($product['category_name'] ?? '—') ?></dd></div>
    <div><dt class="text-xs text-slate-400">Size set</dt><dd class="font-medium text-slate-700"><?= e($product['size_set_label'] ?? '—') ?> <?= $product['pairs_in_set'] ? '· '.(int)$product['pairs_in_set'].' pairs' : '' ?></dd></div>
    <div><dt class="text-xs text-slate-400">In stock</dt><dd class="font-medium text-slate-700"><?= (int) $product['stock_sets'] ?> sets</dd></div>
    <?php if ($product['set_weight_grams']): ?>
    <div><dt class="text-xs text-slate-400">Set weight</dt><dd class="font-medium text-slate-700"><?= (int) $product['set_weight_grams'] ?> g</dd></div>
    <?php endif; ?>
  </dl>
</div>

<!-- Pricing -->
<div class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <h2 class="text-sm font-semibold text-slate-700 mb-3">Pricing</h2>
  <div class="grid grid-cols-2 gap-3">
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Wholesale</p>
      <p class="text-lg font-bold text-slate-800"><?= $fmt($product['wholesale_price']) ?></p>
    </div>
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Retail</p>
      <p class="text-lg font-bold text-slate-800"><?= $fmt($product['retail_price']) ?></p>
    </div>
  </div>
  <?php if ($product['type'] === 'imported'): ?>
    <div class="mt-3 rounded-xl bg-brand-50 p-3 text-sm">
      <div class="flex justify-between"><span class="text-slate-500">Landed cost / pair</span><span class="font-bold text-brand-600"><?= $fmt($product['final_cost']) ?></span></div>
      <div class="mt-1 flex justify-between text-xs text-slate-400">
        <span>Indian ₹<?= e($product['indian_price']) ?> · <?= (float)$product['discount_percent'] ?>% off · @<?= e($product['lkr_rate_used']) ?></span>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($product['notes'])): ?>
<div class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <h2 class="text-sm font-semibold text-slate-700 mb-1">Notes</h2>
  <p class="text-sm text-slate-600 whitespace-pre-line"><?= e($product['notes']) ?></p>
</div>
<?php endif; ?>

<div class="mt-4 grid grid-cols-2 gap-3">
  <a href="<?= e(url('products/'.$product['id'].'/edit')) ?>" class="rounded-xl bg-brand-600 px-4 py-3 text-center text-sm font-semibold text-white">Edit product</a>
  <a href="<?= e(url('products')) ?>" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-center text-sm font-medium text-slate-600">Back to list</a>
</div>
