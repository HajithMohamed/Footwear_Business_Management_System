<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SizeSet;
use App\Services\CostCalculator;
use App\Services\StorageService;

class ProductController extends Controller
{
    private Product $products;

    public function __construct()
    {
        $this->products = new Product();
    }

    public function index(Request $request): void
    {
        $filters = [
            'search'      => trim((string) $request->query('search', '')),
            'type'        => $request->query('type', ''),
            'brand_id'    => $request->query('brand_id', ''),
            'category_id' => $request->query('category_id', ''),
            'stock'       => $request->query('stock', ''),
        ];
        $page = (int) $request->query('page', 1);

        $result = $this->products->paginate($filters, $page, 20);

        $this->view('products/index', [
            'title'      => 'Products',
            'result'     => $result,
            'filters'    => $filters,
            'brands'     => (new Brand())->active(),
            'categories' => (new Category())->active(),
        ]);
    }

    /** Read-only detail page with the full image gallery. */
    public function show(Request $request, array $params): void
    {
        $product = $this->products->findWithRelations((int) $params['id']);
        if (!$product) {
            $this->abort(404, 'Product not found.');
        }
        $this->view('products/view', [
            'title'   => trim(($product['brand_name'] ?? 'Product') . ' ' . ($product['art_no'] ?? '')),
            'product' => $product,
        ]);
    }

    public function create(Request $request): void
    {
        $this->renderForm(null);
    }

    public function edit(Request $request, array $params): void
    {
        $product = $this->products->findWithRelations((int) $params['id']);
        if (!$product) {
            $this->abort(404, 'Product not found.');
        }
        $this->renderForm($product);
    }

    public function store(Request $request): void
    {
        $data = $this->resolveReferences($request->all());
        $this->validate($data);

        $payload = $this->buildPayload($data, null);
        $payload['created_by'] = Auth::id();

        $id = $this->products->create($payload);

        // Initial price history snapshot
        foreach (['final_cost', 'wholesale_price', 'retail_price'] as $f) {
            if ($payload[$f] !== null) {
                $this->products->recordPriceChange($id, str_replace('_price', '', $f), null, $payload[$f], Auth::id());
            }
        }

        // Opening stock
        $opening = (int) ($data['stock_sets'] ?? 0);
        if ($opening !== 0) {
            $this->products->adjustStock($id, $opening, 'manual_in', Auth::id(), 'Opening stock');
        }

        $this->handleImageUploads($request, $id);

        $this->log('product_created', 'product', $id, ['type' => $payload['type']]);
        Session::flash('success', 'Product created.');
        $this->redirect('products/' . $id . '/edit');
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = $this->products->find($id);
        if (!$existing) {
            $this->abort(404, 'Product not found.');
        }

        $data = $this->resolveReferences($request->all());
        $this->validate($data);

        $payload = $this->buildPayload($data, $existing);

        // Price-change history
        foreach (['final_cost', 'wholesale_price', 'retail_price'] as $f) {
            $this->products->recordPriceChange(
                $id, str_replace('_price', '', $f), $existing[$f], $payload[$f], Auth::id()
            );
        }

        // Note: stock is adjusted via the stock endpoint, not here.
        unset($payload['stock_sets']);
        $this->products->update($id, $payload);
        $this->handleImageUploads($request, $id);

        $this->log('product_updated', 'product', $id);
        Session::flash('success', 'Product updated.');
        $this->redirect('products/' . $id . '/edit');
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!$this->products->find($id)) {
            $this->abort(404);
        }
        // Soft delete — files are purged later by the cleanup cron (Phase 5).
        $this->products->delete($id);
        $this->log('product_deleted', 'product', $id);
        Session::flash('success', 'Product deleted.');
        $this->redirect('products');
    }

    public function stock(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!$this->products->find($id)) {
            $this->abort(404);
        }
        $qty       = (int) $request->input('qty', 0);
        $direction = $request->input('direction', 'in') === 'out' ? -1 : 1;
        if ($qty <= 0) {
            Session::flash('error', 'Enter a quantity greater than zero.');
            $this->back();
        }
        $this->products->adjustStock(
            $id, $direction * $qty,
            $direction > 0 ? 'manual_in' : 'manual_out',
            Auth::id(), $request->input('note')
        );
        $this->log('stock_adjusted', 'product', $id, ['delta' => $direction * $qty]);
        Session::flash('success', 'Stock updated.');
        $this->back();
    }

    /** Update an image's colour name and/or mark it as the main image. */
    public function updateImage(Request $request, array $params): void
    {
        $image = $this->products->findImage((int) $params['imageId']);
        if (!$image || (int) $image['product_id'] !== (int) $params['id']) {
            $this->abort(404);
        }
        $colour = trim((string) $request->input('colour', ''));
        $this->products->updateImageMeta((int) $image['id'], $colour !== '' ? $colour : null);

        if ($request->input('is_main')) {
            $this->products->setMainImage((int) $params['id'], (int) $image['id']);
        }
        $this->log('product_image_updated', 'product', (int) $params['id']);
        Session::flash('success', 'Image updated.');
        $this->back();
    }

    public function deleteImage(Request $request, array $params): void
    {
        $image = $this->products->findImage((int) $params['imageId']);
        if (!$image || (int) $image['product_id'] !== (int) $params['id']) {
            $this->abort(404);
        }
        (new StorageService())->delete($image['path'], $image['thumb_path']);
        $this->products->deleteImage((int) $image['id']);
        $this->log('product_image_deleted', 'product', (int) $params['id']);
        Session::flash('success', 'Image removed.');
        $this->back();
    }

    // --- helpers -------------------------------------------------------------

    private function renderForm(?array $product): void
    {
        $this->view('products/form', [
            'title'      => $product ? 'Edit Product' : 'Add Product',
            'product'    => $product,
            'brands'     => (new Brand())->active(),      // includes `origin` for client-side filtering
            'categories' => (new Category())->active(),
            'sizeSets'   => (new SizeSet())->active(),
            'defaults'   => [
                'lkr_rate'           => setting('lkr_rate', 3.6),
                'per_kilo_clearance' => setting('per_kilo_clearance', 3000),
                'handling_charge'    => setting('handling_charge', 25),
                'low_stock'          => setting('low_stock_threshold', 5),
            ],
        ]);
    }

    /**
     * Turn "add new" selections into real brand/category/size-set ids, and
     * auto-derive pairs from the chosen size set. Reuses the model helpers.
     */
    private function resolveReferences(array $data): array
    {
        $type = $data['type'] ?? 'imported';

        // Brand — new brand inherits the product's origin (local hides Indian brands)
        if (($data['brand_id'] ?? '') === '__new__') {
            $name = trim((string) ($data['new_brand'] ?? ''));
            $data['brand_id'] = $name !== ''
                ? (new Brand())->findOrCreate($name, $type === 'imported' ? 'imported' : 'local')
                : '';
        }

        // Category
        if (($data['category_id'] ?? '') === '__new__') {
            $name = trim((string) ($data['new_category'] ?? ''));
            $data['category_id'] = $name !== '' ? (new Category())->findOrCreate($name) : '';
        }

        // Size set — pairs are auto-derived from the label (e.g. "5-9" → 5)
        if (($data['size_set_id'] ?? '') === '__new__') {
            $label = trim((string) ($data['new_size_set'] ?? ''));
            if ($label !== '') {
                $catId = ctype_digit((string) ($data['category_id'] ?? '')) ? (int) $data['category_id'] : null;
                $pairs = SizeSet::pairsFromLabel($label);
                $data['size_set_id'] = (new SizeSet())->findOrCreate($label, $catId, $pairs);
                if (empty($data['pairs_in_set']) && $pairs > 0) {
                    $data['pairs_in_set'] = $pairs;
                }
            } else {
                $data['size_set_id'] = '';
            }
        }

        // Existing size set is the source of truth for both category and pair count.
        // This keeps product and purchase entry consistent even if JavaScript is off.
        if (!empty($data['size_set_id']) && ctype_digit((string) $data['size_set_id'])) {
            $ss = (new SizeSet())->find((int) $data['size_set_id']);
            if ($ss) {
                if (!empty($ss['category_id'])) {
                    $data['category_id'] = (int) $ss['category_id'];
                }
                if (empty($data['pairs_in_set']) && !empty($ss['default_pairs'])) {
                    $data['pairs_in_set'] = (int) $ss['default_pairs'];
                }
            }
        }

        return $data;
    }

    private function validate(array $data): void
    {
        $type = $data['type'] ?? 'imported';
        $rules = [
            'type'                => 'required|in:imported,local,custom',
            'brand_id'            => 'nullable|integer',
            'art_no'              => 'nullable|string|max:60',
            'name'                => 'nullable|string|max:150',
            'category_id'         => 'nullable|integer',
            'size_set_id'         => 'nullable|integer',
            'pairs_in_set'        => 'nullable|integer|min:0',
            'set_weight_grams'    => 'nullable|integer|min:0',
            'wholesale_price'     => 'nullable|numeric|min:0',
            'retail_price'        => 'nullable|numeric|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'notes'               => 'nullable|string|max:1000',
        ];
        if ($type === 'imported') {
            $rules['indian_price']     = 'required|numeric|min:0';
            $rules['discount_percent'] = 'nullable|numeric|min:0|max:100';
            $rules['set_weight_grams'] = 'required|integer|min:1';
            $rules['pairs_in_set']     = 'required|integer|min:1';
        }

        $v = new Validator($data, $rules);
        if ($v->fails()) {
            $this->withErrors($v->errors(), $data);
        }
    }

    private function buildPayload(array $data, ?array $existing): array
    {
        $type = $data['type'];
        $n = fn ($k) => (isset($data[$k]) && $data[$k] !== '') ? $data[$k] : null;

        $payload = [
            'type'                => $type,
            'brand_id'            => $n('brand_id'),
            'art_no'              => $n('art_no'),
            'name'                => $n('name'),
            'category_id'         => $n('category_id'),
            'size_set_id'         => $n('size_set_id'),
            'pairs_in_set'        => $n('pairs_in_set'),
            'set_weight_grams'    => $n('set_weight_grams'),
            'wholesale_price'     => $n('wholesale_price'),
            'retail_price'        => $n('retail_price'),
            'low_stock_threshold' => $n('low_stock_threshold') ?? setting('low_stock_threshold', 5),
            'notes'               => $n('notes'),
            'indian_price'        => null,
            'discount_percent'    => null,
            'lkr_rate_used'       => null,
            'clearance_rate_used' => null,
            'final_cost'          => null,
        ];

        if ($type === 'imported') {
            $lkrRate   = (float) setting('lkr_rate', 3.6);
            $clearance = (float) setting('per_kilo_clearance', 3000);
            $cost = CostCalculator::calculate([
                'indian_price'       => $data['indian_price'] ?? 0,
                'discount_percent'   => $data['discount_percent'] ?? 0,
                'lkr_rate'           => $lkrRate,
                'per_kilo_clearance' => $clearance,
                'set_weight_grams'   => $data['set_weight_grams'] ?? 0,
                'pairs_in_set'       => $data['pairs_in_set'] ?? 0,
                'handling_charge'    => setting('handling_charge', 25),
                'rounding_step'      => setting('cost_rounding_step', 25),
            ]);
            $payload['indian_price']        = $n('indian_price');
            $payload['discount_percent']    = $n('discount_percent') ?? 0;
            $payload['lkr_rate_used']       = $lkrRate;
            $payload['clearance_rate_used'] = $clearance;
            $payload['final_cost']          = $cost['final_cost'];
        }

        if ($existing === null) {
            $payload['stock_sets'] = 0; // set via adjustStock in store()
        }

        return $payload;
    }

    /**
     * Store uploaded images. A batch colour can be applied to all files in this
     * upload; individual colours can be refined per-image afterwards.
     */
    private function handleImageUploads(Request $request, int $productId): void
    {
        $files = $request->files('images');
        if (!$files) {
            return;
        }
        $storage = new StorageService();
        $colour  = trim((string) $request->input('image_colour', ''));

        foreach ($files as $file) {
            $error = $storage->validateImage($file);
            if ($error) {
                Session::flash('error', $error);
                continue;
            }
            $stored = $storage->storeProductImage($file, $productId);
            $stored['colour'] = $colour !== '' ? $colour : null;
            $this->products->addImage($productId, $stored);
        }
    }
}
