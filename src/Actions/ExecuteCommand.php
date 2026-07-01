<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Actions;

use Farsidev\NovaCommandCenter\Contracts\CommandExecutor;
use Farsidev\NovaCommandCenter\Data\CommandDefinition;
use Farsidev\NovaCommandCenter\Data\ExecutionResult;
use Farsidev\NovaCommandCenter\Events\CommandFinished;
use Farsidev\NovaCommandCenter\Events\CommandStarted;
use Farsidev\NovaCommandCenter\Exceptions\CommandNotAllowedException;
use Farsidev\NovaCommandCenter\Support\CommandBuilder;
use Farsidev\NovaCommandCenter\Support\ConcurrencyGuard;
use Farsidev\NovaCommandCenter\Support\ExecutionStore;
use Farsidev\NovaCommandCenter\Support\History;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Executes a resolved command, streaming live output and recording history.
 */
final class ExecuteCommand
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly CommandBuilder $builder,
        private readonly ConcurrencyGuard $guard,
        private readonly History $history,
        private readonly ExecutionStore $store,
        private readonly CommandExecutor $executor,
        private readonly Dispatcher $events,
        private readonly string $basePath,
        private readonly array $config,
    ) {}

    /**
     * @param  array<string, string>  $values  Resolved variable values keyed by name.
     * @param  list<string>  $flags  Enabled flag strings.
     */
    public function handle(
        CommandDefinition $command,
        array $values,
        array $flags,
        ?string $executionId = null,
        ?string $ranBy = null,
    ): ExecutionResult {
        if ($command->isBash() && !$this->bashEnabled()) {
            throw CommandNotAllowedException::bashDisabled();
        }

        $built = $this->builder->build($command, $values, $flags);
        $id = $executionId ?? (string) Str::uuid();
        $startedAt = Carbon::now();
        $start = microtime(true);

        $running = new ExecutionResult(
            id: $id,
            commandId: $command->id,
            name: $command->name,
            display: $built->display,
            status: ExecutionResult::STATUS_RUNNING,
            exitCode: null,
            output: '',
            startedAt: $startedAt->toIso8601String(),
            ranBy: $ranBy,
        );

        $this->store->put($running);
        $this->events->dispatch(new CommandStarted($command, $running, $ranBy));

        $buffer = '';

        $outcome = $this->guard->run($command, $command->timeout, fn (): array => $this->executor->execute(
            $built->arguments,
            $command->timeout,
            $this->workingDirectory($command),
            $this->environment($command, $id),
            function (string $chunk) use (&$buffer, $command, $running): void {
                $buffer .= $chunk;
                $this->store->put($running->withOutput($this->trim($buffer, $command->outputSize)));
            },
        ));

        $result = new ExecutionResult(
            id: $id,
            commandId: $command->id,
            name: $command->name,
            display: $built->display,
            status: $this->statusFor($outcome),
            exitCode: $outcome['timed_out'] ? null : $outcome['exit_code'],
            output: $this->trim($outcome['output'] !== '' ? $outcome['output'] : $buffer, $command->outputSize),
            startedAt: $startedAt->toIso8601String(),
            finishedAt: Carbon::now()->toIso8601String(),
            duration: round(microtime(true) - $start, 3),
            ranBy: $ranBy,
        );

        $this->store->put($result);
        $this->history->push($result);
        $this->events->dispatch(new CommandFinished($command, $result, $ranBy));

        return $result;
    }

    /**
     * @param  array{exit_code: int, output: string, timed_out: bool}  $outcome
     */
    private function statusFor(array $outcome): string
    {
        if ($outcome['timed_out']) {
            return ExecutionResult::STATUS_TIMED_OUT;
        }

        return $outcome['exit_code'] === 0
            ? ExecutionResult::STATUS_SUCCESS
            : ExecutionResult::STATUS_FAILED;
    }

    private function trim(string $output, int $lines): string
    {
        $output = rtrim($output, "\n");

        if ($lines <= 0 || $output === '') {
            return $output;
        }

        $exploded = explode("\n", $output);

        if (count($exploded) <= $lines) {
            return $output;
        }

        return implode("\n", array_slice($exploded, -$lines));
    }

    private function workingDirectory(CommandDefinition $command): string
    {
        if ($command->isBash()) {
            $configured = $this->bashConfig()['working_directory'] ?? null;

            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }

        return $this->basePath;
    }

    /**
     * @return array<string, string>
     */
    private function environment(CommandDefinition $command, string $executionId): array
    {
        // Expose the execution id so child Artisan commands using the
        // InteractsWithProgress trait can report progress to this execution.
        $normalized = ['NOVA_COMMAND_CENTER_EXECUTION' => $executionId];

        if (!$command->isBash()) {
            return $normalized;
        }

        $env = $this->bashConfig()['env'] ?? [];

        if (!is_array($env)) {
            return $normalized;
        }

        foreach ($env as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
    }

    private function bashEnabled(): bool
    {
        return (bool) ($this->bashConfig()['enabled'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    private function bashConfig(): array
    {
        $bash = $this->config['bash'] ?? [];

        return is_array($bash) ? $bash : [];
    }
}
