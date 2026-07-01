<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Jobs;

use Farsidev\NovaCommandCenter\Actions\ExecuteCommand;
use Farsidev\NovaCommandCenter\Support\CommandRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
     */
    public function __construct(
        public readonly string $commandId,
        public readonly array $values,
        public readonly array $flags,
        public readonly string $executionId,
        public readonly ?string $ranBy = null,
        int $timeout = 60,
    ) {
        // Give the worker a little headroom over the process timeout.
        $this->timeout = $timeout + 15;
    }

    public function handle(CommandRepository $repository, ExecuteCommand $runner): void
    {
        $command = $repository->find($this->commandId);

        if ($command === null) {
            return;
        }

        $runner->handle($command, $this->values, $this->flags, $this->executionId, $this->ranBy);
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
