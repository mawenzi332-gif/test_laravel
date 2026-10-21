<?php

namespace App\Providers;

use App\Comments\MakeGifPreviewStatic;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'comment' => Comment::class,
            'user' => User::class,
        ]);

        Event::listen(ConversionHasBeenCompletedEvent::class, MakeGifPreviewStatic::class);
    }
}
