<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Jobs;

use Farsi\NovaCommandCenter\Actions\ExecuteCommand;
use Farsi\NovaCommandCenter\Data\ExecutionResult;
use Farsi\NovaCommandCenter\Support\CommandRepository;
use Farsi\NovaCommandCenter\Support\ExecutionStore;
use Farsi\NovaCommandCenter\Support\History;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

final class RunCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    /**
     * @param  array<string, string>  $values
     * @param  list<string>  $flags
     * @param  array{type: string, run: string}|null  $custom  Present when the
     *                                                         execution is an ad-hoc command; those are built per-request
     *                                                         and never exist in the repository, so the definition itself
     *                                                         must travel with the job.
     */
    public function __construct(
        public readonly string $commandId,
        public readonly array $values,
        public readonly array $flags,
        public readonly string $executionId,
        public readonly ?string $ranBy = null,
        int $timeout = 60,
        public readonly ?array $custom = null,
    ) {
        // Give the worker a little headroom over the process timeout.
        $this->timeout = $timeout + 15;
    }

    public function handle(CommandRepository $repository, ExecuteCommand $runner): void
    {
        $command = $this->custom !== null
            ? $repository->makeCustom($this->custom['type'], $this->custom['run'])
            : $repository->find($this->commandId);

        if ($command === null) {
            // The command was removed between dispatch and processing. Mark the
            // execution failed instead of leaving it pending forever.
            $this->markFailed('The command is no longer available.');

            return;
        }

        $runner->handle($command, $this->values, $this->flags, $this->executionId, $this->ranBy);
    }

    /**
     * Whatever kills the job (worker timeout, an unexpected exception, a lock
     * refusal), the execution must never stay "pending" — the UI polls it.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'The queued command failed unexpectedly.');
    }

    private function markFailed(string $reason): void
    {
        /** @var ExecutionStore $store */
        $store = app(ExecutionStore::class);

        $current = $store->get($this->executionId);

        if ($current === null) {
            // The live record expired from the cache. Synthesise a minimal base
            // so the UI (which polls this id) still receives a terminal result.
            $current = new ExecutionResult(
                id: $this->executionId,
                commandId: $this->commandId,
                name: $this->commandId,
                display: '',
                status: ExecutionResult::STATUS_PENDING,
                exitCode: null,
                output: '',
                startedAt: Carbon::now()->toIso8601String(),
                variables: $this->values,
                flags: $this->flags,
            );
        } elseif (!in_array($current->status, [ExecutionResult::STATUS_PENDING, ExecutionResult::STATUS_RUNNING], true)) {
            // A finished result must keep its outcome.
            return;
        }

        $failed = new ExecutionResult(
            id: $this->executionId,
            commandId: $current->commandId,
            name: $current->name,
            display: $current->display,
            status: ExecutionResult::STATUS_FAILED,
            exitCode: null,
            output: trim($current->output."\n".$reason),
            startedAt: $current->startedAt,
            finishedAt: Carbon::now()->toIso8601String(),
            ranBy: $this->ranBy,
            variables: $current->variables,
            flags: $current->flags !== [] ? $current->flags : $this->flags,
        );

        $store->put($failed);
        app(History::class)->push($failed);
    }

    /**
     * A stable identifier so Laravel's own WithoutOverlapping middleware (if the
     * host app adds it) and horizon tags can group executions.
     */
    public function displayName(): string
    {
        return 'nova-command-center:'.$this->commandId;
    }
}
