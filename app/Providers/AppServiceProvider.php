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

        // Every page's footer: the conformance status (E1.5) and the repository's issue forms (E3.9), also the statements' contact.
        Views::composer('components.site-footer', fn (View $view): View => $view->with('accessibilityStatus', app(Statements::class)->accessibility()['status']));
        Views::composer(['components.site-footer', 'statements.*'], fn (View $view): View => $view->with('reportUrl', Config::string('app.repository_url').'/issues/new/choose'));
    }
}
