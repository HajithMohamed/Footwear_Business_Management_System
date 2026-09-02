<?php

namespace App\Services;

use App\Core\Database;
use App\Models\ArticleTransaction;
use App\Models\CustomerTransaction;
use App\Models\Product;

class FlexibleArticleTransactionService
{
    public const RETURN_REASONS = ['wrong_size','damaged','changed_order','wrong_item','defective','other'];
    public const CONDITIONS = ['resalable','damaged','returned_to_supplier','other'];
    public const TREATMENTS = ['customer_credit','outstanding_reduction','refund','replacement','stock_only'];
    public const STOCK_TYPES = ['purchase','purchase_return','stock_in','stock_out','return_in','damage','loss','adjustment'];

    public function normalizeRows(array $input): array
    {
        $articles = (array) ($input['article_no'] ?? []);
        $products = (array) ($input['product_id'] ?? []);
        $quantities = (array) ($input['quantity'] ?? []);
        $units = (array) ($input['unit_price'] ?? []);
        $totals = (array) ($input['line_total'] ?? []);
        $brands = (array) ($input['brand_name'] ?? []);
        $colours = (array) ($input['colour'] ?? []);
        $sizes = (array) ($input['size_set_label'] ?? []);
        $rows = []; $errors = [];
        foreach ($articles as $i => $rawArticle) {
            $article = trim((string) $rawArticle);
            $qty = (int) ($quantities[$i] ?? 0);
            $unit = round((float) ($units[$i] ?? 0), 2);
            $total = round((float) ($totals[$i] ?? 0), 2);
            if ($article === '' && $qty === 0 && $unit === 0.0 && $total === 0.0) continue;
            if ($article === '') $errors[] = 'Line ' . ($i + 1) . ': Article Number is required.';
            if ($qty <= 0) $errors[] = 'Line ' . ($i + 1) . ': Quantity must be greater than zero.';
            if ($unit < 0 || $total < 0) $errors[] = 'Line ' . ($i + 1) . ': Price and total must not be negative.';
            if ($unit <= 0 && $total > 0 && $qty > 0) $unit = round($total / $qty, 2);
            if ($total <= 0 && $unit >= 0 && $qty > 0) $total = round($unit * $qty, 2);
            if ($unit > 0 && $total > 0 && abs(($unit * $qty) - $total) > 0.05) {
                $errors[] = 'Line ' . ($i + 1) . ': Please verify the quantity, unit price and total.';
            }
            $productId = ctype_digit((string) ($products[$i] ?? '')) ? (int) $products[$i] : null;
            if ($productId) {
                $product = (new Product())->find($productId);
                if (!$product || strcasecmp(trim((string) $product['art_no']), $article) !== 0) {
                    $errors[] = 'Line ' . ($i + 1) . ': The selected product does not match the Article Number.';
                }
            }
            $rows[] = [
                'article_no'=>$article,'product_id'=>$productId,
                'brand_name'=>trim((string)($brands[$i]??''))?:null,
                'colour'=>trim((string)($colours[$i]??''))?:null,
                'size_set_label'=>trim((string)($sizes[$i]??''))?:null,
                'quantity'=>$qty,'unit_price'=>$unit,'line_total'=>$total,
            ];
        }
        if (!$rows) $errors[] = 'Add at least one article.';
        if ($errors) throw new \InvalidArgumentException(implode(' ', $errors));
        return $rows;
    }

    public function recordReturn(int $customerId, array $input, ?int $userId): int
    {
        $rows = $this->normalizeRows($input);
        $reason = (string) ($input['return_reason'] ?? '');
        $condition = (string) ($input['item_condition'] ?? '');
        $treatment = (string) ($input['treatment'] ?? '');
        if (!in_array($reason, self::RETURN_REASONS, true) || !in_array($condition, self::CONDITIONS, true) || !in_array($treatment, self::TREATMENTS, true)) {
            throw new \InvalidArgumentException('Choose a valid return reason, condition and treatment.');
        }
        return Database::instance()->transaction(function () use ($customerId,$input,$userId,$rows,$reason,$condition,$treatment): int {
            $customer = Database::instance()->first('SELECT id, outstanding_due FROM customers WHERE id=? AND deleted_at IS NULL FOR UPDATE', [$customerId]);
            if (!$customer) throw new \InvalidArgumentException('Customer not found.');
            $subtotal = round(array_sum(array_column($rows, 'line_total')), 2);
            [$tax,$discount,$grandTotal] = $this->totals($subtotal, $input);
            $before = (float) $customer['outstanding_due'];
            $affectsBalance = in_array($treatment, ['customer_credit','outstanding_reduction'], true);
            $after = $affectsBalance ? round($before - $grandTotal, 2) : $before;
            $model = new ArticleTransaction();
            $id = $model->create([
                'transaction_no'=>$model->nextNumber('customer_return'),'transaction_type'=>'customer_return','customer_id'=>$customerId,
                'treatment'=>$treatment,'return_reason'=>$reason,'item_condition'=>$condition,'subtotal'=>$subtotal,'tax'=>$tax,'discount'=>$discount,'grand_total'=>$grandTotal,
                'balance_before'=>$before,'balance_after'=>$after,'reference'=>trim((string)($input['reference']??''))?:null,
                'notes'=>trim((string)($input['notes']??''))?:null,'created_by'=>$userId,
            ]);
            foreach ($rows as $row) {
                [$previous,$new,$delta] = $this->stockEffect($row, $condition === 'resalable' ? $row['quantity'] : 0, 'return_in', $id, $userId, $input);
                $model->addItem($row + ['transaction_id'=>$id,'stock_delta'=>$delta,'previous_stock'=>$previous,'new_stock'=>$new,'notes'=>null]);
            }
            if ($affectsBalance) {
                (new CustomerTransaction())->create([
                    'customer_id'=>$customerId,'transaction_type'=>'credit_memo','amount'=>$grandTotal,'running_balance'=>$after,
                    'transaction_date'=>date('Y-m-d'),'reference_type'=>'customer_return','reference_id'=>$id,
                    'description'=>'Customer return ' . $model->find($id)['transaction_no'],'created_by'=>$userId,
                ]);
                Database::instance()->update('customers', ['outstanding_due'=>$after], ['id'=>$customerId]);
            }
            return $id;
        });
    }

    public function recordStock(array $input, ?int $userId): int
    {
        $rows = $this->normalizeRows($input);
        $type = (string) ($input['transaction_type'] ?? '');
        if (!in_array($type, self::STOCK_TYPES, true)) throw new \InvalidArgumentException('Choose a valid stock adjustment type.');
        return Database::instance()->transaction(function () use ($rows,$type,$input,$userId): int {
            $model = new ArticleTransaction();
            $subtotal = round(array_sum(array_column($rows,'line_total')),2);
            [$tax,$discount,$grandTotal] = $this->totals($subtotal, $input);
            $id = $model->create([
                'transaction_no'=>$model->nextNumber($type),'transaction_type'=>$type,'subtotal'=>$subtotal,'tax'=>$tax,'discount'=>$discount,'grand_total'=>$grandTotal,
                'reference'=>trim((string)($input['reference']??''))?:null,'notes'=>trim((string)($input['notes']??''))?:null,'created_by'=>$userId,
            ]);
            $positive = in_array($type, ['purchase','stock_in','return_in','adjustment'], true);
            foreach ($rows as $row) {
                $signed = $positive ? $row['quantity'] : -$row['quantity'];
                [$previous,$new,$delta] = $this->stockEffect($row,$signed,$type,$id,$userId,$input);
                $model->addItem($row + ['transaction_id'=>$id,'stock_delta'=>$delta,'previous_stock'=>$previous,'new_stock'=>$new,'notes'=>null]);
            }
            return $id;
        });
    }

    private function stockEffect(array $row, int $delta, string $reason, int $transactionId, ?int $userId, array $input): array
    {
        if (!$row['product_id'] || $delta === 0) return [null,null,0];
        $previous = (int) Database::instance()->scalar('SELECT stock_sets FROM products WHERE id=? FOR UPDATE', [$row['product_id']]);
        if ($previous + $delta < 0) throw new \InvalidArgumentException('Insufficient stock for Article ' . $row['article_no'] . '.');
        (new Product())->adjustStock((int)$row['product_id'],$delta,$reason,$userId,trim((string)($input['notes']??''))?:null,'article_transaction',$transactionId);
        return [$previous,$previous+$delta,$delta];
    }

    private function totals(float $subtotal, array $input): array
    {
        $tax = round((float)($input['tax'] ?? 0), 2);
        $discount = round((float)($input['discount'] ?? 0), 2);
        if ($tax < 0 || $discount < 0 || $discount > $subtotal + $tax) {
            throw new \InvalidArgumentException('Tax and discount must be valid non-negative amounts.');
        }
        return [$tax, $discount, round($subtotal + $tax - $discount, 2)];
    }
}
