<?php

Route::group(['middleware' => 'api', 'prefix' => 'api/v1', 'namespace' => 'Modules\Products\Http\Controllers'], function()
{
    Route::resource('products', 'ProductApiController');
});
