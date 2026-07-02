<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Tests;

use Farsidev\NovaCommandCenter\Contracts\CommandExecutor;
use Farsidev\NovaCommandCenter\Http\Controllers\CommandController;
use Farsidev\NovaCommandCenter\Http\Controllers\ExecutionController;
use Farsidev\NovaCommandCenter\Http\Controllers\HistoryController;
use Farsidev\NovaCommandCenter\Support\CommandRepository;
use Farsidev\NovaCommandCenter\Tests\Fakes\FakeExecutor;
use Farsidev\NovaCommandCenter\ToolServiceProvider;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected FakeExecutor $executor;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ToolServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('cache.default', 'array');
        $config->set('queue.default', 'sync');

        // Pin an in-memory SQLite connection so the database command-source tests
        // behave the same across every Testbench/Laravel version in the matrix
        // (some default to MySQL, which is not available in CI).
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $config->set('nova-command-center.rate_limit', null);
        $config->set('nova-command-center.authorize', null);
        $config->set('nova-command-center.history', 10);
        $config->set('nova-command-center.bash', ['enabled' => false, 'working_directory' => null, 'env' => []]);
        $config->set('nova-command-center.custom_commands', []);
        $config->set('nova-command-center.searchable_models', []);
        $config->set('nova-command-center.without_overlapping', ['commands' => [], 'groups' => []]);
        $config->set('nova-command-center.commands', $this->defaultCommands());

        $this->executor = new FakeExecutor;
        $app->instance(CommandExecutor::class, $this->executor);
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('_ncr/commands', [CommandController::class, 'index']);
        $router->post('_ncr/commands/run', [CommandController::class, 'run']);
        $router->get('_ncr/commands/{command}/variables/{variable}/search', [CommandController::class, 'search']);
        $router->get('_ncr/executions/{execution}', [ExecutionController::class, 'show']);
        $router->get('_ncr/history', [HistoryController::class, 'index']);
        $router->delete('_ncr/history', [HistoryController::class, 'destroy']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultCommands(): array
    {
        return [
            'Clear Cache' => [
                'run' => 'cache:clear',
                'group' => 'Cache',
            ],
            'Forget Key' => [
                'run' => 'cache:forget {key}',
                'group' => 'Cache',
                'variables' => [
                    'key' => ['label' => 'Key', 'required' => true],
                ],
            ],
            'Optional Tag' => [
                'run' => 'inspire --tag={tag}',
                'variables' => [
                    'tag' => ['label' => 'Tag', 'required' => false],
                ],
            ],
            'Migrate' => [
                'run' => 'migrate',
                'group' => 'Database',
                'type' => 'danger',
                'flags' => [
                    ['label' => 'Force', 'flag' => '--force', 'default' => true],
                ],
            ],
            'Disk Usage' => [
                'run' => 'df -h',
                'command_type' => 'bash',
                'group' => 'System',
            ],
        ];
    }

    /**
     * Re-read config into a fresh repository binding (after mutating config).
     */
    protected function refreshCommands(): void
    {
        $this->app->forgetInstance(CommandRepository::class);
    }
}
