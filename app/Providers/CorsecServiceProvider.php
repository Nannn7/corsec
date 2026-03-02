<?php

namespace Modules\Corsec\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spatie\Activitylog\Traits\LogsActivity;
use Throwable;

class CorsecServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Corsec';

    protected string $nameLower = 'corsec';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerCorsecAuditTrail();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        if (class_exists('Breadcrumbs')) {
            require __DIR__ . '/../../routes/breadcrumbs.php';
        }
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $config = str_replace($configPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $config_key = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $config);
                    $segments = explode('.', $this->nameLower . '.' . $config_key);

                    // Remove duplicated adjacent segments
                    $normalized = [];
                    foreach ($segments as $segment) {
                        if (end($normalized) !== $segment) {
                            $normalized[] = $segment;
                        }
                    }

                    $key = ($config === 'config.php') ? $this->nameLower : implode('.', $normalized);

                    $this->publishes([$file->getPathname() => config_path($config)], 'config');
                    $this->merge_config_from($file->getPathname(), $key);
                }
            }
        }
    }

    /**
     * Merge config from the given path recursively.
     */
    protected function merge_config_from(string $path, string $key): void
    {
        $existing = config($key, []);
        $module_config = require $path;

        config([$key => array_replace_recursive($existing, $module_config)]);
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        Blade::componentNamespace(config('modules.namespace') . '\\' . $this->name . '\\View\\Components', $this->nameLower);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function registerCorsecAuditTrail(): void
    {
        Event::listen('eloquent.created: *', fn(string $eventName, array $data) => $this->recordEloquentEvent('created', $data));
        Event::listen('eloquent.updated: *', fn(string $eventName, array $data) => $this->recordEloquentEvent('updated', $data));
        Event::listen('eloquent.deleted: *', fn(string $eventName, array $data) => $this->recordEloquentEvent('deleted', $data));
        Event::listen('eloquent.restored: *', fn(string $eventName, array $data) => $this->recordEloquentEvent('restored', $data));
    }

    private function recordEloquentEvent(string $event, array $data): void
    {
        $model = $data[0] ?? null;
        if (!$model instanceof Model) {
            return;
        }

        $this->recordCorsecActivity($event, $model);
    }

    private function recordCorsecActivity(string $event, Model $model): void
    {
        if (!function_exists('activity') || !$this->shouldRecordCorsecActivity($model)) {
            return;
        }

        try {
            $changes = Arr::except($model->getChanges(), ['updated_at']);
            if ($event === 'updated' && empty($changes)) {
                return;
            }

            $attributes = $event === 'deleted'
                ? $model->getOriginal()
                : $model->getAttributes();

            $properties = [
                'attributes' => Arr::except((array) $attributes, ['updated_at']),
                'changes' => $changes,
                'route' => request()?->route()?->getName(),
                'method' => request()?->method(),
                'path' => request()?->path(),
                'user_id' => Auth::id(),
            ];

            if (empty($properties['changes'])) {
                unset($properties['changes']);
            }

            $logger = activity('Corsec')
                ->performedOn($model)
                ->event($event)
                ->withProperties($properties);

            $user = Auth::user();
            if ($user) {
                $logger->causedBy($user);
            }

            $logger->log(class_basename($model) . ' ' . $event);
        } catch (Throwable $exception) {
            Log::warning('Failed recording Corsec audit trail', [
                'event' => $event,
                'model' => $model::class,
                'model_id' => $model->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldRecordCorsecActivity(Model $model): bool
    {
        if (!Str::startsWith($model::class, 'Modules\\Corsec\\Models\\')) {
            return false;
        }

        return !in_array(LogsActivity::class, class_uses_recursive($model), true);
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->nameLower)) {
                $paths[] = $path . '/modules/' . $this->nameLower;
            }
        }

        return $paths;
    }
}
