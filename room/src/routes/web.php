<?php

// Products
$route = env('PACKAGE_ROUTE', '').'/room/';
$controller = 'Increment\Hotel\Room\Http\RoomController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_by_type', $controller."retrieveByType");
Route::post($route.'retrieve_by_id', $controller."retrieveById");
Route::post($route.'retrieve_unique', $controller."retrieveUnique");
Route::post($route.'retrieve_basic', $controller."retrieveBasic");
Route::post($route.'retrieve_by_params', $controller."retrieveByParams");
Route::post($route.'update_with_images', $controller."updateWithImages");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");

// Pricings
$route = env('PACKAGE_ROUTE', '').'/pricings/';
$controller = 'Increment\\Hotel\Room\Http\PricingController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_pricings', $controller."retrievePricings");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");


// Product Images
$route = env('PACKAGE_ROUTE', '').'/room_images/';
$controller = 'Increment\Hotel\Room\Http\ProductImageController@';
Route::post($route.'create', $controller."create");
Route::post($route.'create_with_image', $controller."createWithImages");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");

//Availability
$route = env('PACKAGE_ROUTE', '').'/availabilities/';
$controller = 'Increment\Hotel\Room\Http\AvailabilityController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");


//Availability
$route = env('PACKAGE_ROUTE', '').'/carts/';
$controller = 'Increment\Hotel\Room\Http\CartController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_by_params', $controller."retrieveByParams");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller."test");


