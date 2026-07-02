<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Data;

use Farsidev\NovaCommandCenter\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

/**
 * An immutable, fully-typed representation of a single allow-listed command.
 *
 * Every value read from the raw config array is coerced to a safe default here
 * so the rest of the package never has to worry about missing or null keys.
 *
 * @implements Arrayable<string, mixed>
 */
final class CommandDefinition implements Arrayable
{
    /**
     * @param  array<string, CommandVariable>  $variables
     * @param  list<CommandFlag>  $flags
     * @param  array{connection: string|null, queue: string|null}|null  $queue
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $run,
        public readonly string $type,
        public readonly string $commandType,
        public readonly string $group,
        public readonly ?string $help,
        public readonly int $timeout,
        public readonly int $outputSize,
        public readonly ?array $queue,
        public readonly ?string $can,
        public readonly ?bool $confirm,
        public readonly array $variables,
        public readonly array $flags,
    ) {}

    /**
     * @param  mixed  $definition
     * @param  array{timeout?: int, output_size?: int, type?: string}  $defaults
     */
    public static function fromConfig(string $name, $definition, array $defaults = []): self
    {
        if (is_string($definition)) {
            $definition = ['run' => $definition];
        }

        if (!is_array($definition)) {
            $definition = [];
        }

        $commandType = isset($definition['command_type']) && is_string($definition['command_type'])
            ? strtolower($definition['command_type'])
            : 'artisan';

        return new self(
            id: (string) Str::of($name)->slug().'-'.substr(md5($name), 0, 8),
            name: $name,
            run: isset($definition['run']) && is_string($definition['run']) ? trim($definition['run']) : '',
            type: isset($definition['type']) && is_string($definition['type'])
                ? $definition['type']
                : (string) ($defaults['type'] ?? 'primary'),
            commandType: in_array($commandType, ['artisan', 'bash'], true) ? $commandType : 'artisan',
            group: isset($definition['group']) && is_string($definition['group'])
                ? $definition['group']
                : 'General',
            help: isset($definition['help']) && is_string($definition['help']) ? $definition['help'] : null,
            timeout: Cast::int($definition['timeout'] ?? $defaults['timeout'] ?? null, 60),
            outputSize: Cast::int($definition['output_size'] ?? $defaults['output_size'] ?? null, 25),
            queue: self::normalizeQueue($definition['queue'] ?? false),
            can: isset($definition['can']) && is_string($definition['can']) ? $definition['can'] : null,
            confirm: isset($definition['confirm']) && is_bool($definition['confirm']) ? $definition['confirm'] : null,
            variables: self::normalizeVariables($definition['variables'] ?? []),
            flags: self::normalizeFlags($definition['flags'] ?? []),
        );
    }

    public function isArtisan(): bool
    {
        return $this->commandType === 'artisan';
    }

    public function isBash(): bool
    {
        return $this->commandType === 'bash';
    }

    public function shouldQueue(): bool
    {
        return $this->queue !== null;
    }

    /**
     * Whether the UI should ask for confirmation before running this command.
     * An explicit `confirm` config value always wins; otherwise it falls back
     * to the command's button type (danger/warning imply a risky action).
     */
    public function requiresConfirmation(): bool
    {
        return $this->confirm ?? in_array($this->type, ['danger', 'warning'], true);
    }

    /**
     * @param  mixed  $queue
     * @return array{connection: string|null, queue: string|null}|null
     */
    private static function normalizeQueue($queue): ?array
    {
        if ($queue === false || $queue === null) {
            return null;
        }

        if ($queue === true) {
            return ['connection' => null, 'queue' => null];
        }

        if (is_array($queue)) {
            return [
                'connection' => isset($queue['connection']) && is_string($queue['connection'])
                    ? $queue['connection']
                    : null,
                'queue' => isset($queue['queue']) && is_string($queue['queue'])
                    ? $queue['queue']
                    : null,
            ];
        }

        return null;
    }

    /**
     * @param  mixed  $variables
     * @return array<string, CommandVariable>
     */
    private static function normalizeVariables($variables): array
    {
        if (!is_array($variables)) {
            return [];
        }

        $normalized = [];

        foreach ($variables as $name => $definition) {
            // Support a plain list of names: ['key', 'value'].
            if (is_int($name) && is_string($definition)) {
                $name = $definition;
                $definition = [];
            }

            if (!is_string($name)) {
                continue;
            }

            $normalized[$name] = CommandVariable::fromConfig($name, $definition);
        }

        return $normalized;
    }

    /**
     * @param  mixed  $flags
     * @return list<CommandFlag>
     */
    private static function normalizeFlags($flags): array
    {
        if (!is_array($flags)) {
            return [];
        }

        $normalized = [];

        foreach ($flags as $key => $definition) {
            $normalized[] = CommandFlag::fromConfig($key, $definition);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'run' => $this->run,
            'type' => $this->type,
            'command_type' => $this->commandType,
            'group' => $this->group,
            'help' => $this->help,
            'queued' => $this->shouldQueue(),
            'needs_confirm' => $this->requiresConfirmation(),
            'variables' => array_values(array_map(
                static fn (CommandVariable $variable): array => $variable->toArray(),
                $this->variables,
            )),
            'flags' => array_map(
                static fn (CommandFlag $flag): array => $flag->toArray(),
                $this->flags,
            ),
        ];
    }
}
