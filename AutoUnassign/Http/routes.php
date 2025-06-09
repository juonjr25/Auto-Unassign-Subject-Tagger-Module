<?php

Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\AutoUnassign\Http\Controllers'], function()
{
    Route::get('/', 'AutoUnassignController@index');
});
