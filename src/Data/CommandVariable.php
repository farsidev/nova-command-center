<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Data;

use Farsidev\NovaCommandCenter\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A single user-supplied value referenced by a command via a {placeholder}.
 *
 * @implements Arrayable<string, mixed>
 */
final class CommandVariable implements Arrayable
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     * @param  list<string>  $rules
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly array $options = [],
        public readonly bool $required = true,
        public readonly ?string $default = null,
        public readonly array $rules = [],
        public readonly ?string $help = null,
        public readonly ?string $placeholder = null,
    ) {}

    /**
     * Build a variable from its raw config definition.
     *
     * @param  mixed  $definition
     */
    public static function fromConfig(string $name, $definition): self
    {
        // Shorthand: 'key' => 'Label'
        if (is_string($definition)) {
            $definition = ['label' => $definition];
        }

        if (!is_array($definition)) {
            $definition = [];
        }

        $type = isset($definition['type']) && is_string($definition['type'])
            ? $definition['type']
            : 'text';

        return new self(
            name: $name,
            label: isset($definition['label']) && is_string($definition['label'])
                ? $definition['label']
                : self::humanize($name),
            type: in_array($type, ['text', 'select'], true) ? $type : 'text',
            options: self::normalizeOptions($definition['options'] ?? []),
            required: (bool) ($definition['required'] ?? true),
            default: isset($definition['default']) ? Cast::nullableString($definition['default']) : null,
            rules: self::normalizeRules($definition['rules'] ?? []),
            help: isset($definition['help']) && is_string($definition['help'])
                ? $definition['help']
                : null,
            placeholder: isset($definition['placeholder']) && is_string($definition['placeholder'])
                ? $definition['placeholder']
                : null,
        );
    }

    /**
     * The validation rules for this field, including required/nullable state.
     *
     * @return list<string>
     */
    public function validationRules(): array
    {
        $rules = $this->rules;

        array_unshift($rules, $this->required ? 'required' : 'nullable');

        if ($this->type === 'select' && $this->options !== []) {
            $allowed = array_map(static fn (array $option): string => $option['value'], $this->options);
            $rules[] = 'in:'.implode(',', $allowed);
        }

        return array_values(array_unique($rules));
    }

    /**
     * @param  mixed  $options
     * @return list<array{value: string, label: string}>
     */
    private static function normalizeOptions($options): array
    {
        if (!is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                $normalized[] = [
                    'value' => Cast::string($value['value']),
                    'label' => Cast::string($value['label'] ?? $value['value']),
                ];

                continue;
            }

            // ['on' => 'Enabled'] or [0 => 'foo']
            $normalized[] = is_string($key)
                ? ['value' => $key, 'label' => Cast::string($value)]
                : ['value' => Cast::string($value), 'label' => Cast::string($value)];
        }

        return $normalized;
    }

    /**
     * @param  mixed  $rules
     * @return list<string>
     */
    private static function normalizeRules($rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        if (!is_array($rules)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($rule): string => is_string($rule) ? $rule : '', $rules),
            static fn (string $rule): bool => $rule !== '',
        ));
    }

    private static function humanize(string $value): string
    {
        return ucfirst(trim(str_replace(['_', '-', '.'], ' ', $value)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $this->options,
            'required' => $this->required,
            'default' => $this->default,
            'help' => $this->help,
            'placeholder' => $this->placeholder,
        ];
    }
}
