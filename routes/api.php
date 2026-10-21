<?php

use App\Http\Controllers\Api\V1\CurrentUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [CurrentUserController::class, 'show'])->name('api.v1.me.show');
        Route::patch('/me', [CurrentUserController::class, 'update'])->name('api.v1.me.update');
    });
});
