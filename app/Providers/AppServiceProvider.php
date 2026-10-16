<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\PositionsUpdated;
use App\Support\PositionChangeLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
        // Listeners of the single write path (ADR-0002).
        Event::listen(PositionsUpdated::class, [PositionChangeLog::class, 'record']);
    }
}
