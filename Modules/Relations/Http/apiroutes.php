<?php

// Route groups for API
Route::group(['middleware' => 'api', 'prefix' => 'api/v1', 'namespace' => 'Modules\Relations\Http\Controllers'], function()
{

    Route::resource('relations', 'RelationApiController');

});



