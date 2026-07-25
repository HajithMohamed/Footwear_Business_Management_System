<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Product;

class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $products = new Product();

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'stats' => [
                'total_products' => $products->totalActive(),
                'low_stock'      => $products->lowStockCount(),
                'out_of_stock'   => $products->outOfStockCount(),
            ],
            'lowStock' => $products->lowStockList(8),
        ]);
    }
}
