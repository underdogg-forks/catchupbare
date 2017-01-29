<?php

Route::group(['middleware' => 'auth:user', 'namespace' => 'Modules\Relations\Http\Controllers'], function()
{
    Route::resource('relations', 'RelationController');
    Route::get('api/relations', 'RelationController@getDatatable');
    Route::get('relations/{relation_id}', 'RelationController@show');
    Route::post('relations/bulk', 'RelationController@bulk');
    Route::get('relations/statement/{relation_id}', 'RelationController@statement');
});


Route::group(['middleware' => 'auth:user', 'namespace' => 'Modules\Relations\Http\Controllers'], function()
{
    Route::get('api/activities/{relation_id?}', 'App\Http\Controllers\ActivityController@getDatatable');
});