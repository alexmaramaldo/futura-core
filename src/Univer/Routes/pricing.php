<?php

Route::group(['namespace'=>'Univer\Controllers', 'prefix'=>'admin/pricing'], function() {
    // Criação de novo pricing  unitario
    Route::post('{type}/{id}/store','PricingController@storeUnitPricing');
    //
    Route::post('{type}/{id}/save','PricingController@updateUnitPricing');

    // Delete
    Route::post('{type}/{id}/delete','PricingController@deleteUnitPricing');
});