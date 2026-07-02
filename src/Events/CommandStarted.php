<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Events;

use Farsi\NovaCommandCenter\Data\CommandDefinition;
use Farsi\NovaCommandCenter\Data\ExecutionResult;

final class CommandStarted
{
    public function __construct(
        public readonly CommandDefinition $command,
        public readonly ExecutionResult $result,
        public readonly ?string $ranBy,
    ) {}
}
