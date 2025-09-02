<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\BlueprintFramework\Extensions\{identifier};

Route::group(['prefix' => '/servers/{server}/mcplugins'], function () {
        Route::get('/', [{identifier}\PluginsManagerController::class, 'index']);
        Route::get('/version', [{identifier}\PluginVersionsController::class, 'index']);
        Route::post('/install', [{identifier}\InstallPluginsController::class, 'index']);
});
