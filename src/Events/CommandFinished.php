<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Events;

use Farsidev\NovaCommandCenter\Data\CommandDefinition;
use Farsidev\NovaCommandCenter\Data\ExecutionResult;

final class CommandFinished
{
    public function __construct(
        public readonly CommandDefinition $command,
        public readonly ExecutionResult $result,
        public readonly ?string $ranBy,
    ) {}
}
