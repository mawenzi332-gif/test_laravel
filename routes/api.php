<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/comments', [CommentController::class, 'index'])->name('api.v1.comments.index');
    Route::post('/comments', [CommentController::class, 'store'])
        ->middleware('throttle:comment-publication')
        ->name('api.v1.comments.store');
    Route::post('/comments/preview', [CommentController::class, 'preview'])->name('api.v1.comments.preview');
    Route::patch('/comments/{comment}', [CommentController::class, 'update'])->middleware('auth:sanctum')->name('api.v1.comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->middleware('auth:sanctum')->name('api.v1.comments.destroy');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->whereNumber('attachment')->name('api.v1.attachments.show');
    Route::get('/attachments/{attachment}/preview', [AttachmentController::class, 'preview'])->whereNumber('attachment')->name('api.v1.attachments.preview');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [CurrentUserController::class, 'show'])->name('api.v1.me.show');
        Route::patch('/me', [CurrentUserController::class, 'update'])->name('api.v1.me.update');
    });
});
