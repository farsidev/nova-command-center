<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * An optional, operator-defined flag rendered as a checkbox.
 *
 * @implements Arrayable<string, mixed>
 */
final class CommandFlag implements Arrayable
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $flag,
        public readonly bool $default = false,
        public readonly ?string $help = null,
    ) {}

    /**
     * @param  mixed  $definition
     */
    public static function fromConfig(int|string $key, $definition): self
    {
        // Shorthand: '--force' => 'Force'  or  'Force' => '--force'
        if (is_string($definition)) {
            [$flag, $label] = str_starts_with($definition, '-')
                ? [$definition, (string) $key]
                : [(string) $key, $definition];

            return new self(
                key: self::keyFor($flag),
                label: $label,
                flag: $flag,
                default: false,
            );
        }

        if (!is_array($definition)) {
            $definition = [];
        }

        $flag = isset($definition['flag']) && is_string($definition['flag'])
            ? $definition['flag']
            : (is_string($key) ? $key : '');

        return new self(
            key: self::keyFor($flag),
            label: isset($definition['label']) && is_string($definition['label'])
                ? $definition['label']
                : $flag,
            flag: $flag,
            default: (bool) ($definition['default'] ?? false),
            help: isset($definition['help']) && is_string($definition['help'])
                ? $definition['help']
                : null,
        );
    }

    private static function keyFor(string $flag): string
    {
        $key = preg_replace('/[^a-z0-9]+/i', '_', ltrim($flag, '-'));

        return trim((string) $key, '_') ?: 'flag';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'flag' => $this->flag,
            'default' => $this->default,
            'help' => $this->help,
        ];
    }
}
