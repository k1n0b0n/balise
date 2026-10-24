<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\PositionsUpdated;
use App\Support\PositionChangeLog;
use App\Support\Statements;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View as Views;
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

        // E1.5: the conformance status in every page's footer, and the statements' contact (E3.9).
        Views::composer('components.site-footer', fn (View $view): View => $view->with('accessibilityStatus', app(Statements::class)->accessibility()['status']));
        Views::composer('statements.*', fn (View $view): View => $view->with('reportUrl', Config::string('app.repository_url').'/issues/new/choose'));
    }
}
