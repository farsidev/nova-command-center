<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Exceptions;

final class CommandNotFoundException extends CommandCenterException
{
    public static function forId(string $id): self
    {
        return new self("No allow-listed command matches [{$id}].");
    }
}
