<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter;

use Farsidev\NovaCommandCenter\Actions\ExecuteCommand;
use Farsidev\NovaCommandCenter\Contracts\CommandExecutor;
use Farsidev\NovaCommandCenter\Http\Middleware\Authorize;
use Farsidev\NovaCommandCenter\Support\CommandBuilder;
use Farsidev\NovaCommandCenter\Support\CommandRepository;
use Farsidev\NovaCommandCenter\Support\ConcurrencyGuard;
use Farsidev\NovaCommandCenter\Support\ExecutionStore;
use Farsidev\NovaCommandCenter\Support\History;
use Farsidev\NovaCommandCenter\Support\ProcessExecutor;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;

final class ToolServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'nova-command-center');

        // Guard every Nova touch-point so the package can also boot in a plain
        // Laravel context (e.g. the test suite) where Nova is not installed.
        if (class_exists(Nova::class)) {
            Nova::serving(function (): void {
                //
            });

            $this->app->booted(function (): void {
                $this->routes();
            });
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nova-command-center.php' => config_path('nova-command-center.php'),
            ], 'nova-command-center-config');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/nova-command-center'),
            ], 'nova-command-center-lang');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nova-command-center.php', 'nova-command-center');

        $this->app->bind(CommandExecutor::class, ProcessExecutor::class);

        $this->app->singleton(CommandRepository::class, function (Application $app): CommandRepository {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('nova-command-center', []);

            return new CommandRepository($config);
        });

        $this->app->singleton(ExecutionStore::class, fn (Application $app): ExecutionStore => new ExecutionStore($this->cache($app)));

        $this->app->singleton(History::class, function (Application $app): History {
            return new History($this->cache($app), (int) $app['config']->get('nova-command-center.history', 0));
        });

        $this->app->bind(CommandBuilder::class, fn (Application $app): CommandBuilder => new CommandBuilder($app->basePath()));

        $this->app->bind(ConcurrencyGuard::class, function (Application $app): ConcurrencyGuard {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('nova-command-center.without_overlapping', []);

            return new ConcurrencyGuard($this->cache($app), $config);
        });

        $this->app->bind(ExecuteCommand::class, function (Application $app): ExecuteCommand {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('nova-command-center', []);

            return new ExecuteCommand(
                builder: $app->make(CommandBuilder::class),
                guard: $app->make(ConcurrencyGuard::class),
                history: $app->make(History::class),
                store: $app->make(ExecutionStore::class),
                executor: $app->make(CommandExecutor::class),
                events: $app->make('events'),
                basePath: $app->basePath(),
                config: $config,
            );
        });
    }

    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Nova::router(['nova', Authorize::class], 'nova-command-center')
            ->group(__DIR__.'/../routes/inertia.php');

        Route::middleware(['nova', Authorize::class])
            ->prefix('nova-vendor/farsidev/nova-command-center')
            ->group(__DIR__.'/../routes/api.php');
    }

    private function cache(Application $app): Cache
    {
        /** @var CacheFactory $factory */
        $factory = $app->make('cache');

        $store = $app['config']->get('nova-command-center.cache_store');

        return $factory->store(is_string($store) ? $store : null);
    }
}
