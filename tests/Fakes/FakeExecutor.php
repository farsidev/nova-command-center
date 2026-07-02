<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Tests\Fakes;

use Farsi\NovaCommandCenter\Contracts\CommandExecutor;

/**
 * A deterministic test double that records the argument vector it was given
 * without ever touching the operating system.
 */
final class FakeExecutor implements CommandExecutor
{
    /** @var list<string> */
    public array $arguments = [];

    /** @var list<array{arguments: list<string>, timeout: int, cwd: string|null, env: array<string, string>}> */
    public array $calls = [];

    public int $exitCode = 0;

    public string $output = "done\n";

    public bool $timedOut = false;

    public function execute(array $arguments, int $timeout, ?string $cwd, array $env, ?callable $onOutput): array
    {
        $this->arguments = $arguments;
        $this->calls[] = compact('arguments', 'timeout', 'cwd', 'env');

        if ($onOutput !== null) {
            $onOutput($this->output);
        }

        return [
            'exit_code' => $this->exitCode,
            'output' => $this->output,
            'timed_out' => $this->timedOut,
        ];
    }
}
