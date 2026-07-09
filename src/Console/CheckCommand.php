<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Console;

use Farsi\NovaCommandCenter\Contracts\CommandSource;
use Farsi\NovaCommandCenter\Data\CommandDefinition;
use Farsi\NovaCommandCenter\Data\CommandVariable;
use Farsi\NovaCommandCenter\Models\Command as CommandModel;
use Farsi\NovaCommandCenter\Support\Cast;
use Farsi\NovaCommandCenter\Support\CommandBuilder;
use Farsi\NovaCommandCenter\Support\CommandRepository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Statically validates the allow-list and surrounding configuration, and
 * reports everything that would otherwise fail silently at runtime: commands
 * dropped for having no run string, placeholders with no matching variable
 * (passed literally to the process), model variables whose class is missing
 * or not allow-listed, bash commands while bash is disabled, overlap locks on
 * a store that cannot lock, and more.
 *
 * Exits non-zero when it finds errors so it can gate CI — the allow-list is
 * code; lint it like code.
 */
final class CheckCommand extends Command
{
    protected $signature = 'nova-command-center:check {--strict : Treat warnings as errors}';

    protected $description = 'Validate the Command Center allow-list and configuration.';

    private int $errors = 0;

    private int $warnings = 0;

    public function handle(CommandSource $source, CommandRepository $repository, ConfigRepository $config): int
    {
        $this->errors = 0;
        $this->warnings = 0;

        $this->newLine();
        $this->components->info('Checking the Command Center configuration…');

        $checked = $this->checkCommands($source, $repository);
        $this->checkGlobals($repository, $config);

        $this->newLine();

        $summary = sprintf(
            '%d command%s checked · %d error%s · %d warning%s',
            $checked,
            $checked === 1 ? '' : 's',
            $this->errors,
            $this->errors === 1 ? '' : 's',
            $this->warnings,
            $this->warnings === 1 ? '' : 's',
        );

        if ($this->errors > 0 || ((bool) $this->option('strict') && $this->warnings > 0)) {
            $this->components->error($summary);

            return self::FAILURE;
        }

        $this->components->info($summary);

        return self::SUCCESS;
    }

    private function checkCommands(CommandSource $source, CommandRepository $repository): int
    {
        $checked = 0;

        try {
            foreach ($source->definitions() as $name => $definition) {
                $checked++;

                if (!is_string($name) || $name === '') {
                    $this->report((string) $name, [
                        ['error', 'The definition key (display name) is not a string — this entry is ignored entirely.'],
                    ]);

                    continue;
                }

                $command = CommandDefinition::fromConfig($name, $definition);

                $this->report($name, $this->commandIssues($command, $repository));
            }
        } catch (Throwable $exception) {
            // A broken source (most commonly the database driver without its
            // migration) is itself a finding, not a crash — the global checks
            // that follow name the fix.
            $this->report('Command source', [
                ['error', 'Reading the command source failed: '.$exception->getMessage()],
            ]);

            return $checked;
        }

        if ($checked === 0) {
            $this->components->warn('No commands are defined — the tool page will be empty.');
            $this->warnings++;
        }

        return $checked;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function commandIssues(CommandDefinition $command, CommandRepository $repository): array
    {
        $issues = [];

        if ($command->run === '') {
            $issues[] = ['error', 'No "run" string — this command is dropped and will not appear in the UI.'];

            return $issues;
        }

        $placeholders = CommandBuilder::placeholders($command->run);

        foreach ($placeholders as $placeholder) {
            if (!array_key_exists($placeholder, $command->variables)) {
                $issues[] = ['error', "The {{$placeholder}} placeholder has no matching variable — it will be passed to the process literally, braces and all."];
            }
        }

        foreach ($command->variables as $variable) {
            if (!in_array($variable->name, $placeholders, true)) {
                $issues[] = ['note', "Variable [{$variable->name}] is not referenced by a {placeholder}; when filled in, its value is appended as its own trailing argument."];
            }

            array_push($issues, ...$this->variableIssues($variable, $repository));
        }

        if ($command->isBash() && !$repository->bashEnabled()) {
            $issues[] = ['warning', 'This is a bash command but bash execution is disabled (bash.enabled) — running it will be refused.'];
        }

        if ($command->can !== null && !Gate::has($command->can)) {
            $issues[] = ['warning', "The [{$command->can}] gate ability is not defined — unless a policy covers it, this command will always be denied."];
        }

        return $issues;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function variableIssues(CommandVariable $variable, CommandRepository $repository): array
    {
        $issues = [];

        if ($variable->type === 'select' && $variable->options === []) {
            $issues[] = $variable->required && $variable->default === null
                ? ['error', "Select variable [{$variable->name}] is required but has no options and no default — the Run button can never be enabled."]
                : ['warning', "Select variable [{$variable->name}] has no options — the dropdown will be empty."];
        }

        if ($variable->type === 'model') {
            if ($variable->model === null) {
                $issues[] = ['error', "Model variable [{$variable->name}] does not name a model class — its search box can never return results."];
            } elseif (!class_exists($variable->model)) {
                $issues[] = ['error', "Model variable [{$variable->name}] points at [{$variable->model}], which does not exist."];
            } elseif (!is_subclass_of($variable->model, Model::class)) {
                $issues[] = ['error', "Model variable [{$variable->name}] points at [{$variable->model}], which is not an Eloquent model."];
            } elseif (!in_array($variable->model, $repository->searchableModels(), true)) {
                $issues[] = ['error', "Model variable [{$variable->name}] uses [{$variable->model}], which is not allow-listed in searchable_models — the search endpoint will refuse it."];
            }
        }

        return $issues;
    }

    private function checkGlobals(CommandRepository $repository, ConfigRepository $config): void
    {
        $this->newLine();

        $issues = [];

        $customTypes = $repository->allowedCustomTypes();

        if (in_array('bash', $customTypes, true) && !$repository->bashEnabled()) {
            $issues[] = ['warning', 'custom_commands allows ad-hoc bash, but bash execution is disabled — those submissions will be refused.'];
        }

        $issues = [...$issues, ...$this->searchableModelIssues($repository, $config)];

        if ($this->overlapConfigured($config) && !$this->cacheSupportsLocks($config)) {
            $issues[] = ['warning', 'without_overlapping is configured, but the cache store cannot provide atomic locks — overlap protection is silently disabled. Use redis, memcached, database or another lock-capable store.'];
        }

        if ($repository->historySize() <= 0) {
            $issues[] = ['note', 'History is disabled (history <= 0) — executions will leave no trace after the page reloads.'];
        }

        $issues = [...$issues, ...$this->databaseSourceIssues($config)];

        $this->report('Global configuration', $issues);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function searchableModelIssues(CommandRepository $repository, ConfigRepository $config): array
    {
        $raw = $config->get('nova-command-center.searchable_models', []);

        if (!is_array($raw)) {
            return [['warning', 'searchable_models is not an array — every model search will be refused.']];
        }

        $valid = $repository->searchableModels();
        $issues = [];

        foreach ($raw as $entry) {
            $name = is_string($entry) ? $entry : get_debug_type($entry);

            if (!is_string($entry) || !in_array($entry, $valid, true)) {
                $issues[] = ['warning', "searchable_models entry [{$name}] is not an Eloquent model class — it is silently dropped from the allow-list."];
            }
        }

        return $issues;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function databaseSourceIssues(ConfigRepository $config): array
    {
        $source = $config->get('nova-command-center.source');
        $driver = is_array($source) ? ($source['driver'] ?? 'config') : $source;

        if ($driver !== 'database') {
            return [];
        }

        $model = is_array($source) && is_string($source['model'] ?? null) && is_subclass_of($source['model'], Model::class)
            ? $source['model']
            : CommandModel::class;

        try {
            $instance = new $model;
            $table = $instance->getTable();

            if (!$instance->getConnection()->getSchemaBuilder()->hasTable($table)) {
                return [['error', "The database source is enabled but the [{$table}] table does not exist — publish and run the migration: php artisan vendor:publish --tag=nova-command-center-migrations && php artisan migrate"]];
            }
        } catch (Throwable $exception) {
            return [['error', 'The database source is enabled but the database cannot be queried: '.$exception->getMessage()]];
        }

        return [];
    }

    private function overlapConfigured(ConfigRepository $config): bool
    {
        $overlap = $config->get('nova-command-center.without_overlapping', []);

        if (!is_array($overlap)) {
            return false;
        }

        $commands = $overlap['commands'] ?? [];
        $groups = $overlap['groups'] ?? [];

        return (is_array($commands) && $commands !== []) || (is_array($groups) && $groups !== []);
    }

    private function cacheSupportsLocks(ConfigRepository $config): bool
    {
        try {
            /** @var CacheFactory $factory */
            $factory = $this->laravel->make('cache');
            $store = $config->get('nova-command-center.cache_store');

            return $factory->store(is_string($store) ? $store : null)->getStore() instanceof LockProvider;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>  $issues
     */
    private function report(string $subject, array $issues): void
    {
        if ($issues === []) {
            $this->line("  <fg=green>✓</> {$subject}");

            return;
        }

        $worst = 'note';

        foreach ($issues as [$level]) {
            if ($level === 'error') {
                $worst = 'error';

                break;
            }

            if ($level === 'warning') {
                $worst = 'warning';
            }
        }

        $mark = match ($worst) {
            'error' => '<fg=red>✗</>',
            'warning' => '<fg=yellow>⚠</>',
            default => '<fg=blue>ℹ</>',
        };

        $this->line("  {$mark} ".Cast::string($subject, '(unnamed)'));

        foreach ($issues as [$level, $message]) {
            $colour = match ($level) {
                'error' => 'red',
                'warning' => 'yellow',
                default => 'blue',
            };

            $this->line("      <fg={$colour}>{$level}</>  {$message}");

            if ($level === 'error') {
                $this->errors++;
            } elseif ($level === 'warning') {
                $this->warnings++;
            }
        }
    }
}
