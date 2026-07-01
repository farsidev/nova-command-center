<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Support;

use Farsidev\NovaCommandCenter\Contracts\CommandExecutor;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Executes an argument vector with Symfony Process. Because the process is
 * constructed from an array, the operating system receives each element as a
 * discrete argument and no shell ever parses the input.
 */
final class ProcessExecutor implements CommandExecutor
{
    public function execute(array $arguments, int $timeout, ?string $cwd, array $env, ?callable $onOutput): array
    {
        $process = new Process(
            command: $arguments,
            cwd: $cwd,
            env: $env === [] ? null : $env,
            timeout: $timeout > 0 ? (float) $timeout : null,
        );

        $timedOut = false;

        try {
            $process->run(function (string $type, string $buffer) use ($onOutput): void {
                if ($onOutput !== null) {
                    $onOutput($buffer);
                }
            });
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        }

        return [
            'exit_code' => (int) ($process->getExitCode() ?? 1),
            'output' => $process->getOutput().$process->getErrorOutput(),
            'timed_out' => $timedOut,
        ];
    }
}
