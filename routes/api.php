<?php

use App\Http\Controllers\Api\GameListImportController;
use App\Http\Middleware\EnsureImportToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/import')
    ->middleware(EnsureImportToken::class)
    ->group(function () {
        Route::post('check', [GameListImportController::class, 'check'])->name('api.import.check');
        Route::post('list-items', [GameListImportController::class, 'listItems'])->name('api.import.list-items');
    });
