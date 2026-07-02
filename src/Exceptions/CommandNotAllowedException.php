<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Exceptions;

final class CommandNotAllowedException extends CommandCenterException
{
    public static function bashDisabled(): self
    {
        return new self('Shell command execution is disabled. Enable it in config/nova-command-center.php.');
    }

    public static function customType(string $type): self
    {
        return new self("Ad-hoc [{$type}] commands are not permitted by the current configuration.");
    }

    public static function locked(string $name): self
    {
        return new self("The command [{$name}] (or its group) is already running.");
    }
}
