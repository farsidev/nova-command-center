<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Support;

use Farsidev\NovaCommandCenter\Contracts\CommandSource;
use Farsidev\NovaCommandCenter\Data\CommandDefinition;
use Farsidev\NovaCommandCenter\Exceptions\CommandNotAllowedException;
use Farsidev\NovaCommandCenter\Exceptions\CommandNotFoundException;
use Farsidev\NovaCommandCenter\Support\Sources\ConfigCommandSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Loads the allow-list — from whatever {@see CommandSource} is bound — into typed
 * {@see CommandDefinition} objects and provides safe lookups. This is the only
 * place raw definitions are interpreted, guaranteeing the rest of the package
 * works with clean data regardless of where the definitions come from.
 */
final class CommandRepository
{
    /**
     * @var array<string, CommandDefinition>|null
     */
    private ?array $commands = null;

    private readonly CommandSource $source;

    /**
     * @param  array<string, mixed>  $config
     * @param  CommandSource|null  $source  Where command definitions are read from.
     *                                      Defaults to the config file for backward
     *                                      compatibility and the safest posture.
     */
    public function __construct(private readonly array $config, ?CommandSource $source = null)
    {
        $this->source = $source ?? new ConfigCommandSource($config);
    }

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

        foreach ($this->source->definitions() as $name => $definition) {
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
            'timeout' => Cast::int($defaults['timeout'] ?? null, 60),
            'output_size' => Cast::int($defaults['output_size'] ?? null, 25),
            'type' => Cast::string($defaults['type'] ?? null, 'primary'),
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

    /**
     * The Eloquent models permitted to back a "model" variable's search
     * endpoint. Nothing is searchable until explicitly allow-listed here —
     * the same posture as {@see self::allowedCustomTypes()}. A configured
     * value that isn't a real Model subclass is silently dropped rather than
     * risking an attempt to query/instantiate an arbitrary class string.
     *
     * @return list<class-string<Model>>
     */
    public function searchableModels(): array
    {
        $models = $this->config['searchable_models'] ?? [];

        if (!is_array($models)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($model): string => is_string($model) ? $model : '', $models),
            static fn (string $model): bool => $model !== '' && is_subclass_of($model, Model::class),
        ));
    }

    public function historySize(): int
    {
        return max(0, Cast::int($this->config['history'] ?? null));
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
