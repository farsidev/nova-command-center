<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Data;

/**
 * A ready-to-run command expressed as an argument vector.
 *
 * The argument vector is executed with Symfony Process, which passes each entry
 * to the operating system as a discrete argument. Nothing is ever handed to a
 * shell, so user-supplied values cannot inject additional commands.
 */
final class BuiltCommand
{
    /**
     * @param  list<string>  $arguments  The argv passed to Symfony Process.
     */
    public function __construct(
        public readonly array $arguments,
        public readonly string $display,
    ) {}
}
