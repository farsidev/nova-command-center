<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter;

use Farsi\NovaCommandCenter\Actions\ExecuteCommand;
use Farsi\NovaCommandCenter\Contracts\CommandExecutor;
use Farsi\NovaCommandCenter\Contracts\CommandSource;
use Farsi\NovaCommandCenter\Http\Middleware\Authorize;
use Farsi\NovaCommandCenter\Models\Command;
use Farsi\NovaCommandCenter\Support\Cast;
use Farsi\NovaCommandCenter\Support\CommandBuilder;
use Farsi\NovaCommandCenter\Support\CommandRepository;
use Farsi\NovaCommandCenter\Support\ConcurrencyGuard;
use Farsi\NovaCommandCenter\Support\ExecutionStore;
use Farsi\NovaCommandCenter\Support\History;
use Farsi\NovaCommandCenter\Support\ProcessExecutor;
use Farsi\NovaCommandCenter\Support\Sources\ConfigCommandSource;
use Farsi\NovaCommandCenter\Support\Sources\DatabaseCommandSource;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;

final class ToolServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'nova-command-center');

        // Guard the Nova touch-points so the package can also boot in a plain
        // Laravel context (e.g. the test suite) where Nova is not installed.
        if (class_exists(Nova::class)) {
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

            // Only needed when opting into the "database" command source.
            $this->publishes([
                __DIR__.'/../database/migrations/create_nova_command_center_commands_table.php.stub' => $this->migrationPath('create_nova_command_center_commands_table.php'),
            ], 'nova-command-center-migrations');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nova-command-center.php', 'nova-command-center');

        $this->app->bind(CommandExecutor::class, ProcessExecutor::class);

        $this->app->singleton(CommandSource::class, fn (Application $app): CommandSource => $this->makeSource($app));

        $this->app->singleton(CommandRepository::class, function (Application $app): CommandRepository {
            /** @var array<string, mixed> $config */
            $config = $this->configRepository($app)->get('nova-command-center', []);

            return new CommandRepository($config, $app->make(CommandSource::class));
        });

        $this->app->singleton(ExecutionStore::class, fn (Application $app): ExecutionStore => new ExecutionStore($this->cache($app)));

        $this->app->singleton(History::class, function (Application $app): History {
            return new History($this->cache($app), Cast::int($this->configRepository($app)->get('nova-command-center.history', 0)));
        });

        $this->app->bind(CommandBuilder::class, fn (Application $app): CommandBuilder => new CommandBuilder($app->basePath()));

        $this->app->bind(ConcurrencyGuard::class, function (Application $app): ConcurrencyGuard {
            /** @var array<string, mixed> $config */
            $config = $this->configRepository($app)->get('nova-command-center.without_overlapping', []);

            return new ConcurrencyGuard($this->cache($app), $config);
        });

        $this->app->bind(ExecuteCommand::class, function (Application $app): ExecuteCommand {
            /** @var array<string, mixed> $config */
            $config = $this->configRepository($app)->get('nova-command-center', []);

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

    /**
     * Resolve the configured command source. Defaults to the config file — the
     * safest, version-controlled posture. Set "source.driver" to "database" to
     * manage commands through the optional Eloquent model / Nova resource, or to
     * a custom class-string implementing {@see CommandSource} for any other store.
     */
    private function makeSource(Application $app): CommandSource
    {
        /** @var array<string, mixed> $config */
        $config = $this->configRepository($app)->get('nova-command-center', []);

        $source = $this->configRepository($app)->get('nova-command-center.source', 'config');
        $driver = is_array($source) ? ($source['driver'] ?? 'config') : $source;

        if ($driver === 'database') {
            $model = is_array($source) && is_string($source['model'] ?? null)
                ? $source['model']
                : Command::class;

            return new DatabaseCommandSource(
                is_subclass_of($model, Model::class) ? $model : Command::class,
            );
        }

        // Allow a custom class-string implementing the contract.
        if (is_string($driver) && $driver !== 'config'
            && is_subclass_of($driver, CommandSource::class)) {
            return $app->make($driver);
        }

        return new ConfigCommandSource($config);
    }

    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Nova::router(['nova', Authorize::class], 'nova-command-center')
            ->group(__DIR__.'/../routes/inertia.php');

        Route::middleware(['nova', Authorize::class])
            ->prefix('nova-vendor/farsi/nova-command-center')
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Build a timestamped destination path for a published migration, reusing an
     * already-published copy so re-running the publish command is idempotent.
     */
    private function migrationPath(string $filename): string
    {
        $directory = $this->app->databasePath('migrations');

        foreach ((array) glob($directory.'/*_'.$filename) as $existing) {
            if (is_string($existing)) {
                return $existing;
            }
        }

        return $directory.'/'.date('Y_m_d_His').'_'.$filename;
    }

    private function configRepository(Application $app): ConfigRepository
    {
        return $app->make(ConfigRepository::class);
    }

    private function cache(Application $app): Cache
    {
        /** @var CacheFactory $factory */
        $factory = $app->make('cache');

        $store = $this->configRepository($app)->get('nova-command-center.cache_store');

        return $factory->store(is_string($store) ? $store : null);
    }
}
