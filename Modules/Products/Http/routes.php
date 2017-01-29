<?php

Route::group(['middleware' => 'api', 'prefix' => 'api/v1'], function()
{
    Route::resource('products', 'ProductApiController');
});

Route::group(['middleware' => 'auth:user', 'namespace' => 'Modules\Products\Http\Controllers'], function()
    Route::get('api/products', 'ProductController@getDatatable');
    Route::resource('products', 'ProductController');
    Route::post('products/bulk', 'ProductController@bulk');

{
    Route::get('/', 'ProductsController@index');
});
