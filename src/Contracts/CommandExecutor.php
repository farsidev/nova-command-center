<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Contracts;

interface CommandExecutor
{
    /**
     * Execute an argument vector and return the outcome.
     *
     * Implementations MUST pass each argument to the OS as a discrete argument
     * (no shell interpolation).
     *
     * @param  list<string>  $arguments
     * @param  array<string, string>  $env
     * @param  (callable(string): void)|null  $onOutput  Streams incremental output.
     * @return array{exit_code: int, output: string, timed_out: bool}
     */
    public function execute(array $arguments, int $timeout, ?string $cwd, array $env, ?callable $onOutput): array;
}
