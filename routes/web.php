<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::get('/', function () {
    return view('app');
});

Route::view('/login', 'app');
Route::view('/register', 'app');
Route::view('/forgot-password', 'app');
Route::get('/reset-password/{token}', fn () => view('app'));
Route::view('/profile', 'app');

Route::prefix('api/v1/auth')->group(function (): void {
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware(['guest:web', 'throttle:registration'])
        ->name('register.store');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware(['guest:web', 'throttle:login'])
        ->name('login.store');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:web')
        ->name('logout');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware(['guest:web', 'throttle:password-recovery'])
        ->name('password.email');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware(['guest:web', 'throttle:password-recovery'])
        ->name('password.update');
});
