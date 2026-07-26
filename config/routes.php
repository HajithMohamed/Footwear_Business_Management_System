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

    // --- Customers -----------------------------------------------------------
    $r->get('/customers',             'CustomerController@index',  ['auth']);
    $r->get('/customers/create',      'CustomerController@create', ['auth']);
    $r->post('/customers',            'CustomerController@store',  ['auth']);
    $r->get('/customers/{id}/edit',   'CustomerController@edit',   ['auth']);
    $r->get('/customers/{id}',        'CustomerController@show',   ['auth']);  // must stay AFTER /create
    $r->post('/customers/{id}',       'CustomerController@update', ['auth']);

    // --- Payments (within customer context) ----------------------------------
    $r->get('/customers/{customerId}/payment',       'PaymentController@create', ['auth']);
    $r->post('/customers/{customerId}/payment',      'PaymentController@store',  ['auth']);
    $r->get('/customers/{customerId}/payments',      'PaymentController@byCustomer', ['auth']);

    // --- Cheques (standalone dashboard) --------------------------------------
    $r->get('/cheques',                    'ChequeController@index',         ['auth']);
    $r->get('/cheques/pending',            'ChequeController@pending',       ['auth']);
    $r->post('/cheques/{id}/status',       'ChequeController@updateStatus',  ['auth']);

    // --- Customer Ledger & Intelligence (read-only) --------------------------
    $r->get('/customers/{customerId}/ledger',      'LedgerController@byCustomer', ['auth']);
    $r->get('/intelligence',                       'LedgerController@intelligence', ['auth']);
    $r->get('/intelligence/{classification}',      'LedgerController@byClassification', ['auth']);
    $r->get('/intelligence/top',                   'LedgerController@topCustomers', ['auth']);
    $r->get('/intelligence/overdue',               'LedgerController@overdue', ['auth']);

    // --- Settings (admin only) ----------------------------------------------
    $r->get('/settings',  'SettingController@index',  ['auth', 'admin']);
    $r->post('/settings', 'SettingController@update',  ['auth', 'admin']);
};
