<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Cheque;
use App\Models\ClearancePerson;
use App\Models\GoodsArrival;
use App\Models\Parcel;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\ProfitService;

class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $products  = new Product();
        $purchases = new Purchase();
        $people    = new ClearancePerson();
        $profit    = new ProfitService();

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'stats' => [
                'total_products' => $products->totalActive(),
                'low_stock'      => $products->lowStockCount(),
                'out_of_stock'   => $products->outOfStockCount(),
            ],
            'lowStock' => $products->lowStockList(8),

            // Money: this month's trading, plus what is owed and what is due
            'money'        => $profit->summary(date('Y-m-01'), date('Y-m-d')),
            'periods'      => $profit->periodRevenue(),
            'receivables'  => $profit->receivables(),
            'recentSales'  => (new Sale())->recent(5),
            'overdueSales' => (new Sale())->overdueCredit(5),
            'chequesDue'   => (new Cheque())->dueSoon((int) setting('cheque_reminder_days', 7), 5),
            'overdueCustomers' => (new \App\Models\Customer())->getOverdueCustomers(30, 5),

            // Module 5 — import purchase / clearance / arrival
            'importStats'        => $purchases->stats(),
            'awaitingClearance'  => $purchases->awaitingClearance(5),
            'inTransit'          => $purchases->inTransit(5),
            'recentlyArrived'    => $purchases->recentlyArrived(5),
            'recentPurchases'    => $purchases->recent(5),
            'pendingParcels'     => (new Parcel())->pendingVerification(5),
            'pendingQuantity'    => (new GoodsArrival())->pendingQuantityVerification(5),
            'byClearancePerson'  => $people->openShipments(),
            'clearancePerf'      => $people->performance(5),
        ]);
    }
}
