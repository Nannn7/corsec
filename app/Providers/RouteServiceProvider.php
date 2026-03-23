<?php

namespace Modules\Corsec\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Corsec';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        RateLimiter::for('corsec-datatables', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?: 'guest');
            $route = (string) ($request->route()?->getName() ?: $request->path());

            return Limit::perMinute(60)->by($userId . '|' . $request->ip() . '|' . $route);
        });

        RateLimiter::for('corsec-preview', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?: 'guest');
            $route = (string) ($request->route()?->getName() ?: $request->path());

            return Limit::perMinute(30)->by($userId . '|' . $request->ip() . '|' . $route);
        });

        RateLimiter::for('corsec-write-heavy', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?: 'guest');
            $route = (string) ($request->route()?->getName() ?: $request->path());

            return Limit::perMinute(20)->by($userId . '|' . $request->ip() . '|' . $route);
        });

        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
