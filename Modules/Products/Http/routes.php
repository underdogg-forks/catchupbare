<?php


Route::group(['middleware' => 'auth:user', 'namespace' => 'Modules\Products\Http\Controllers'], function()
{
    Route::resource('products', 'ProductController');
    Route::get('api/products', 'ProductController@getDatatable');
    Route::post('products/bulk', 'ProductController@bulk');
    Route::get('/', 'ProductsController@index');
});
