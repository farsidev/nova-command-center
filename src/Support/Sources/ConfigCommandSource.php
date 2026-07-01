<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Support\Sources;

use Farsidev\NovaCommandCenter\Contracts\CommandSource;

/**
 * The default source: command definitions come from the published config file.
 *
 * This keeps the allow-list version-controlled and immutable at runtime, which
 * is the safest posture and the recommended default.
 */
final class ConfigCommandSource implements CommandSource
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function definitions(): iterable
    {
        $commands = $this->config['commands'] ?? [];

        return is_array($commands) ? $commands : [];
    }
}
