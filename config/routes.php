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
    $r->post('/products/{id}',        'ProductController@update', ['auth']);
    $r->post('/products/{id}/stock',  'ProductController@stock',  ['auth']);
    $r->post('/products/{id}/delete', 'ProductController@destroy', ['auth', 'admin']);
    $r->post('/products/{id}/images/{imageId}/delete', 'ProductController@deleteImage', ['auth']);

    // --- Settings (admin only) ----------------------------------------------
    $r->get('/settings',  'SettingController@index',  ['auth', 'admin']);
    $r->post('/settings', 'SettingController@update',  ['auth', 'admin']);
};
