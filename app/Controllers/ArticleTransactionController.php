<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\ArticleTransaction;
use App\Models\Customer;
use App\Models\Product;
use App\Services\FlexibleArticleTransactionService;

class ArticleTransactionController extends Controller
{
    public function searchArticles(Request $request): void
    {
        $query = trim((string) $request->query('q', ''));
        $this->json(['success'=>true,'data'=>['products'=>(new Product())->searchByArticle($query)]]);
    }

    public function returnCreate(Request $request, array $params = []): void
    {
        $customerId = (int) ($params['customerId'] ?? $request->query('customer_id', 0));
        $customer = $customerId ? (new Customer())->getById($customerId) : null;
        $this->view('article-transactions/form', [
            'title'=>'Return Goods','mode'=>'return','customer'=>$customer,
            'customers'=>(new Customer())->search([]),
        ]);
    }

    public function returnStore(Request $request): void
    {
        $customerId = (int) $request->input('customer_id', 0);
        try {
            $id = (new FlexibleArticleTransactionService())->recordReturn($customerId, $request->all(), Auth::id());
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) { $this->json(['success'=>false,'message'=>$e->getMessage()],422); return; }
            Session::flash('error', $e->getMessage()); $this->back();
        } catch (\Throwable $e) {
            if ($request->wantsJson()) { $this->json(['success'=>false,'message'=>'Unable to record the return. Please try again.'],500); return; }
            Session::flash('error','Unable to record the return. Please try again.'); $this->back();
        }
        $transaction = (new ArticleTransaction())->findWithItems($id);
        $this->log('customer_return.created','article_transaction',$id,['customer_id'=>$customerId,'total'=>$transaction['grand_total']]);
        $url = url('article-transactions/' . $id);
        if ($request->wantsJson()) {
            $this->json(['success'=>true,'message'=>'Return recorded successfully.','data'=>[
                'id'=>$id,'url'=>$url,'ledger_url'=>url('customers/'.$customerId.'?tab=ledger'),
                'balance_after'=>$transaction['balance_after'],
            ]]); return;
        }
        Session::flash('success','Return recorded successfully.'); $this->redirect('article-transactions/'.$id);
    }

    public function stockCreate(Request $request): void
    {
        $this->view('article-transactions/form', ['title'=>'Stock Adjustment','mode'=>'stock','customer'=>null,'customers'=>[]]);
    }

    public function stockStore(Request $request): void
    {
        try {
            $id = (new FlexibleArticleTransactionService())->recordStock($request->all(), Auth::id());
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) { $this->json(['success'=>false,'message'=>$e->getMessage()],422); return; }
            Session::flash('error',$e->getMessage()); $this->back();
        } catch (\Throwable $e) {
            if ($request->wantsJson()) { $this->json(['success'=>false,'message'=>'Unable to record the stock adjustment. Please try again.'],500); return; }
            Session::flash('error','Unable to record the stock adjustment. Please try again.'); $this->back();
        }
        $url = url('article-transactions/'.$id);
        $this->log('stock_adjustment.created','article_transaction',$id,['type'=>$request->input('transaction_type')]);
        if ($request->wantsJson()) { $this->json(['success'=>true,'message'=>'Stock adjustment recorded successfully.','data'=>['id'=>$id,'url'=>$url]]); return; }
        Session::flash('success','Stock adjustment recorded successfully.'); $this->redirect('article-transactions/'.$id);
    }

    public function show(Request $request, array $params): void
    {
        $transaction = (new ArticleTransaction())->findWithItems((int)$params['id']);
        if (!$transaction) $this->abort(404,'Transaction not found.');
        $this->view('article-transactions/show',['title'=>$transaction['transaction_no'],'transaction'=>$transaction]);
    }
}
