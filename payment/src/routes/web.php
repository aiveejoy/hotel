<?php

// Coupons
$route = env('PACKAGE_ROUTE', '').'/payments/';
$controller = 'Increment\Imarket\Payment\Http\PaymentController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'use', $controller."useCoupon");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");