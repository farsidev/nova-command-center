<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Support;

use Farsidev\NovaCommandCenter\Data\CommandDefinition;
use Farsidev\NovaCommandCenter\Exceptions\CommandNotAllowedException;
use Farsidev\NovaCommandCenter\Exceptions\CommandNotFoundException;

/**
 * Loads the configured allow-list into typed {@see CommandDefinition} objects
 * and provides safe lookups. This is the only place the raw config array is
 * interpreted, guaranteeing the rest of the package works with clean data.
 */
final class CommandRepository
{
    /**
     * @var array<string, CommandDefinition>|null
     */
    private ?array $commands = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * @return array<string, CommandDefinition>
     */
    public function all(): array
    {
        if ($this->commands !== null) {
            return $this->commands;
        }

        $defaults = $this->defaults();
        $commands = [];

        foreach ($this->arrayConfig('commands') as $name => $definition) {
            if (!is_string($name)) {
                continue;
            }

            $command = CommandDefinition::fromConfig($name, $definition, $defaults);

            if ($command->run === '') {
                continue;
            }

            $commands[$command->id] = $command;
        }

        return $this->commands = $commands;
    }

    /**
     * @return list<CommandDefinition>
     */
    public function visible(): array
    {
        return array_values($this->all());
    }

    public function find(string $id): ?CommandDefinition
    {
        return $this->all()[$id] ?? null;
    }

    public function findOrFail(string $id): CommandDefinition
    {
        return $this->find($id) ?? throw CommandNotFoundException::forId($id);
    }

    /**
     * Build a definition for an ad-hoc command typed by an operator. Only the
     * command types explicitly listed under "custom_commands" are permitted.
     */
    public function makeCustom(string $type, string $run): CommandDefinition
    {
        $type = strtolower(trim($type));

        if (!in_array($type, $this->allowedCustomTypes(), true)) {
            throw CommandNotAllowedException::customType($type);
        }

        return CommandDefinition::fromConfig(
            name: 'Custom: '.$run,
            definition: [
                'run' => $run,
                'command_type' => $type,
                'group' => 'Custom',
            ],
            defaults: $this->defaults(),
        );
    }

    /**
     * @return array{timeout: int, output_size: int, type: string}
     */
    private function defaults(): array
    {
        $defaults = $this->arrayConfig('defaults');

        return [
            'timeout' => (int) ($defaults['timeout'] ?? 60),
            'output_size' => (int) ($defaults['output_size'] ?? 25),
            'type' => (string) ($defaults['type'] ?? 'primary'),
        ];
    }

    public function bashEnabled(): bool
    {
        $bash = $this->config['bash'] ?? [];

        return is_array($bash) && (bool) ($bash['enabled'] ?? false);
    }

    /**
     * @return list<string>
     */
    public function allowedCustomTypes(): array
    {
        $types = $this->config['custom_commands'] ?? [];

        if (!is_array($types)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($type): string => is_string($type) ? strtolower($type) : '', $types),
            static fn (string $type): bool => in_array($type, ['artisan', 'bash'], true),
        ));
    }

    public function historySize(): int
    {
        return max(0, (int) ($this->config['history'] ?? 0));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayConfig(string $key): array
    {
        $value = $this->config[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
