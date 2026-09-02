<?php
/**
 * Route definitions. Returns a closure that receives the Router.
 * Middleware keys: 'auth', 'guest', 'admin'.
 */

use App\Core\Router;

return function (Router $r): void {

    // --- Authentication ------------------------------------------------------
    $r->get('/login',  'AuthController@showLogin', ['guest']);
    $r->post('/login', 'AuthController@login',     ['guest']);
    $r->post('/logout', 'AuthController@logout',   ['auth']);

    // --- Dashboard -----------------------------------------------------------
    $r->get('/', 'DashboardController@index', ['auth']);

    // --- Actionable notifications -------------------------------------------
    $r->get('/notifications',                 'NotificationController@index',   ['auth']);
    $r->post('/notifications/read',           'NotificationController@read',    ['auth']);
    $r->post('/notifications/read-all',       'NotificationController@readAll', ['auth']);

    // Local Tesseract OCR suggestions. These endpoints never save business data.
    $r->post('/ocr/bill',                     'OcrController@bill',              ['auth']);
    $r->post('/ocr/cheque',                   'OcrController@cheque',            ['auth']);

    // --- Cost Calculator -----------------------------------------------------
    $r->get('/calculator',  'CalculatorController@index',     ['auth']);
    $r->post('/calculator', 'CalculatorController@calculate', ['auth']);

    // --- Products ------------------------------------------------------------
    $r->get('/products',              'ProductController@index',  ['auth']);
    $r->get('/products/create',       'ProductController@create', ['auth']);
    $r->post('/products',             'ProductController@store',  ['auth']);
    $r->get('/products/{id}/edit',    'ProductController@edit',   ['auth']);
    $r->get('/products/{id}',         'ProductController@show',   ['auth']);   // must stay AFTER /create
    $r->post('/products/{id}',        'ProductController@update', ['auth']);
    $r->post('/products/{id}/stock',  'ProductController@stock',  ['auth']);
    $r->post('/products/{id}/delete', 'ProductController@destroy', ['auth', 'admin']);
    $r->post('/products/{id}/images/{imageId}',        'ProductController@updateImage', ['auth']);
    $r->post('/products/{id}/images/{imageId}/delete', 'ProductController@deleteImage', ['auth']);

    // --- Flexible article transactions, returns and audited stock changes ---
    $r->get('/articles/search',                 'ArticleTransactionController@searchArticles', ['auth']);
    $r->get('/returns/create',                  'ArticleTransactionController@returnCreate', ['auth']);
    $r->get('/customers/{customerId}/returns/create', 'ArticleTransactionController@returnCreate', ['auth']);
    $r->post('/returns',                        'ArticleTransactionController@returnStore', ['auth']);
    $r->get('/stock-adjustments/create',        'ArticleTransactionController@stockCreate', ['auth']);
    $r->post('/stock-adjustments',              'ArticleTransactionController@stockStore', ['auth']);
    $r->get('/article-transactions/{id}',       'ArticleTransactionController@show', ['auth']);

    // --- Customers -----------------------------------------------------------
    $r->get('/customers',             'CustomerController@index',  ['auth']);
    $r->get('/customers/create',      'CustomerController@create', ['auth']);
    $r->post('/customers',            'CustomerController@store',  ['auth']);
    $r->get('/customers/{id}/edit',   'CustomerController@edit',   ['auth']);
    $r->post('/customers/{id}/outstanding', 'CustomerController@addOutstanding', ['auth']);
    $r->get('/customers/{id}',        'CustomerController@show',   ['auth']);  // must stay AFTER /create
    $r->post('/customers/{id}',       'CustomerController@update', ['auth']);
    $r->post('/customers/{id}/delete','CustomerController@destroy',['auth', 'admin']);
    $r->post('/customers/{id}/restore','CustomerController@restore',['auth', 'admin']);

    // --- Manual customer bills (credit tracking only) -----------------------
    $r->get('/bills',                               'CustomerBillController@selectCustomer', ['auth']);
    $r->get('/bills/{id}/edit',                    'CustomerBillController@edit', ['auth']);
    $r->get('/bills/{id}/receipt',                  'CustomerBillController@receipt', ['auth']);
    $r->post('/bills/{id}',                        'CustomerBillController@update', ['auth']);
    $r->get('/customers/{customerId}/bill',       'CustomerBillController@create', ['auth']);
    $r->post('/customers/{customerId}/bill',      'CustomerBillController@store',  ['auth']);

    // --- Payments (within customer context) ----------------------------------
    $r->get('/payments',                              'PaymentController@selectCustomer', ['auth']);
    $r->get('/payments/{id}/edit',                    'PaymentController@edit', ['auth']);
    $r->get('/payments/{id}/receipt',                'PaymentController@receipt', ['auth']);
    $r->post('/payments/{id}',                        'PaymentController@update', ['auth']);
    $r->get('/customers/{customerId}/payment',       'PaymentController@create', ['auth']);
    $r->post('/customers/{customerId}/payment',      'PaymentController@store',  ['auth']);
    $r->get('/customers/{customerId}/payments',      'PaymentController@byCustomer', ['auth']);

    // --- Legacy sale history (new customer debt is recorded through Add Bill) --
    $r->get('/sales',              'SalesController@index',  ['auth']);
    $r->get('/sales/{id}',         'SalesController@show',   ['auth']);
    $r->post('/sales/{id}/cancel', 'SalesController@cancel', ['auth']);

    // --- Expenses ------------------------------------------------------------
    $r->get('/expenses',              'ExpenseController@index',   ['auth']);
    $r->get('/expenses/create',       'ExpenseController@create',  ['auth']);
    $r->post('/expenses',             'ExpenseController@store',   ['auth']);
    $r->get('/expenses/{id}/edit',    'ExpenseController@edit',    ['auth']);
    $r->post('/expenses/{id}',        'ExpenseController@update',  ['auth']);
    $r->post('/expenses/{id}/delete', 'ExpenseController@destroy', ['auth']);

    // --- Module 5: Import Purchase, Clearance & Goods Arrival ---------------
    // Purchases (static segments must stay ABOVE /purchases/{id})
    $r->get('/purchases',         'PurchaseController@index',      ['auth']);
    $r->get('/purchases/create',  'PurchaseController@create',     ['auth']);
    $r->get('/purchases/{id}/edit', 'PurchaseController@edit',     ['auth']);
    $r->get('/purchases/import',  'PurchaseController@importForm', ['auth']);
    $r->get('/purchases/import/review/{token}', 'PurchaseController@importReview', ['auth']);
    $r->post('/purchases/import', 'PurchaseController@import',     ['auth']);
    $r->get('/purchases/local',   'LocalPurchaseController@create', ['auth']);
    $r->post('/purchases/local',  'LocalPurchaseController@store',  ['auth']);
    $r->post('/purchases',        'PurchaseController@store',      ['auth']);
    $r->post('/purchases/{id}',   'PurchaseController@update',     ['auth']);
    $r->post('/purchases/{id}/delete', 'PurchaseController@destroy', ['auth', 'admin']);
    $r->get('/purchases/{id}',    'PurchaseController@show',       ['auth']);

    // Clearance assignment (many agents <-> many purchases)
    $r->get('/purchases/{id}/assign-clearance',  'ClearanceAssignmentController@create', ['auth']);
    $r->post('/purchases/{id}/assign-clearance', 'ClearanceAssignmentController@store',  ['auth']);
    $r->post('/purchases/{id}/in-transit',       'ClearanceAssignmentController@markInTransit', ['auth']);
    $r->post('/purchases/{id}/assignments/{assignmentId}/delete', 'ClearanceAssignmentController@destroy', ['auth']);
    $r->post('/purchases/{id}/link-received-parcels', 'ClearanceAssignmentController@linkReceivedParcels', ['auth']);
    $r->post('/purchases/{id}/assignments/{assignmentId}/payment-rate', 'ClearanceAssignmentController@updateReceivedPaymentRate', ['auth']);

    // Parcels
    $r->post('/purchases/{id}/parcels',            'ParcelController@store',  ['auth']);
    $r->post('/purchases/{id}/parcels/{parcelId}', 'ParcelController@update', ['auth']);

    // Arrival verification -> inventory
    $r->get('/arrivals',                        'ArrivalController@index',         ['auth']);
    $r->post('/purchases/{id}/arrival/open',    'ArrivalController@open',          ['auth']);
    $r->get('/purchases/{id}/arrival',          'ArrivalController@verify',        ['auth']);
    $r->post('/purchases/{id}/arrival/counts',  'ArrivalController@saveCounts',    ['auth']);
    $r->post('/purchases/{id}/arrival/count',   'ArrivalController@addCount',      ['auth']);
    $r->post('/purchases/{id}/arrival/weight',  'ArrivalController@saveWeight',    ['auth']);
    $r->post('/purchases/{id}/arrival/brand',   'ArrivalController@applyBrand',    ['auth']);
    $r->post('/purchases/{id}/arrival/items/{itemId}', 'ArrivalController@updateProductDetails', ['auth']);
    $r->post('/purchases/{id}/arrival/partial', 'ArrivalController@acceptPartial', ['auth']);
    $r->post('/purchases/{id}/arrival/confirm', 'ArrivalController@confirm',       ['auth']);

    // Phase 4: landed cost per pair (after arrival is confirmed)
    $r->get('/purchases/{id}/costing',  'CostingController@show',  ['auth']);
    $r->post('/purchases/{id}/costing', 'CostingController@store', ['auth']);

    // Attachments + quick calculation notes
    $r->post('/purchases/{id}/attachments', 'AttachmentController@store',     ['auth']);
    $r->post('/attachments/{id}/delete',    'AttachmentController@destroy',   ['auth']);
    $r->get('/notes',                       'AttachmentController@notes',     ['auth']);
    $r->post('/notes',                      'AttachmentController@storeNote', ['auth']);
    $r->post('/notes/{id}/attach',          'AttachmentController@attach',    ['auth']);

    // Clearance persons (static segments must stay ABOVE /clearance-persons/{id})
    $r->get('/clearance-persons',           'ClearancePersonController@index',  ['auth']);
    $r->get('/clearance-persons/create',    'ClearancePersonController@create', ['auth']);
    $r->post('/clearance-persons',          'ClearancePersonController@store',  ['auth']);
    $r->get('/clearance-persons/{id}/edit', 'ClearancePersonController@edit',   ['auth']);
    $r->get('/clearance-persons/{id}',      'ClearancePersonController@show',   ['auth']);
    $r->post('/clearance-persons/{id}',     'ClearancePersonController@update', ['auth']);
    $r->post('/clearance-persons/{id}/delete', 'ClearancePersonController@destroy', ['auth', 'admin']);
    $r->post('/clearance-persons/{id}/assignments/{assignmentId}/payment', 'ClearancePersonController@updatePayment', ['auth']);

    // --- Cheques (standalone dashboard) --------------------------------------
    $r->get('/cheques',                    'ChequeController@index',         ['auth']);
    $r->get('/cheques/pending',            'ChequeController@pending',       ['auth']);
    $r->get('/cheques/{id}',               'ChequeController@show',          ['auth']);
    $r->post('/cheques/{id}/status',       'ChequeController@updateStatus',  ['auth']);
    $r->post('/cheques/{id}/deposit',      'ChequeController@setDeposit',    ['auth']);
    $r->post('/cheques/{id}/image',        'ChequeController@uploadImage',   ['auth']);

    // --- Customer Ledger & Intelligence --------------------------------------
    // Static segments must stay ABOVE /intelligence/{classification}, or they
    // get swallowed as a classification name.
    $r->get('/customers/{customerId}/ledger',      'LedgerController@byCustomer', ['auth']);
    $r->get('/customers/{customerId}/statement',   'LedgerController@statement', ['auth']);
    $r->get('/customers/{customerId}/statement/pdf', 'LedgerController@statementPdf', ['auth']);
    $r->get('/intelligence',                       'LedgerController@intelligence', ['auth']);
    $r->get('/intelligence/top',                   'LedgerController@topCustomers', ['auth']);
    $r->get('/intelligence/overdue',               'LedgerController@overdue', ['auth']);
    $r->get('/intelligence/stale-debtors',         'LedgerController@staleDebtors', ['auth']);
    $r->post('/intelligence/recompute',            'LedgerController@recompute', ['auth']);
    $r->get('/intelligence/{classification}',      'LedgerController@byClassification', ['auth']);

    // --- Phase 5: Reports & accounting (read-only) ---------------------------
    $r->get('/reports',             'ReportController@index',       ['auth']);
    $r->get('/reports/stock',       'ReportController@stock',       ['auth']);
    $r->get('/reports/imports',     'ReportController@imports',     ['auth']);
    $r->get('/reports/clearance',   'ReportController@clearance',   ['auth']);
    $r->get('/reports/costs',       'ReportController@costs',       ['auth']);
    $r->get('/reports/receivables', 'ReportController@receivables', ['auth']);

    // --- Profit & loss -------------------------------------------------------
    $r->get('/finance',                  'FinanceController@index',      ['auth']);
    $r->get('/finance/profit-loss',      'FinanceController@profitLoss', ['auth']);
    $r->get('/finance/sales-summary',    'FinanceController@salesSummary', ['auth']);
    $r->get('/finance/brands',           'FinanceController@brands',     ['auth']);
    $r->get('/finance/products',         'FinanceController@products',   ['auth']);
    $r->get('/finance/customers',        'FinanceController@customers',  ['auth']);
    $r->get('/finance/expenses',         'FinanceController@expenses',   ['auth']);

    // --- Settings (admin only) ----------------------------------------------
    $r->get('/settings',  'SettingController@index',  ['auth', 'admin']);
    $r->post('/settings', 'SettingController@update',  ['auth', 'admin']);
};
