<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Support;

use Closure;

/**
 * Converts every accepted variables/flags storage shape into the block list
 * Nova's Repeater field expects ([{type: ..., fields: {...}}]), so commands
 * written as config-style maps — e.g. rows seeded from a config file when
 * migrating to the database source — open cleanly in the structured editor.
 *
 * Kept free of Nova imports so the conversion logic is unit-testable and
 * statically analysed even though it exists for the Nova resource.
 */
final class RepeaterBlocks
{
    /**
     * @return Closure(mixed): list<array{type: string, fields: array<string, mixed>}>
     */
    public static function variables(): Closure
    {
        return static function ($raw): array {
            if (!is_array($raw)) {
                return [];
            }

            $blocks = [];

            foreach ($raw as $key => $item) {
                // Already the repeater shape — pass through untouched.
                if (is_int($key) && is_array($item) && is_array($item['fields'] ?? null)) {
                    $blocks[] = ['type' => 'variable', 'fields' => self::stringifyVariable($item['fields'])];

                    continue;
                }

                // A list of objects carrying their own name.
                if (is_int($key) && is_array($item)) {
                    $blocks[] = ['type' => 'variable', 'fields' => self::stringifyVariable($item)];

                    continue;
                }

                // The classic config map: 'club' => [...] or 'club' => 'Label'.
                if (is_string($key)) {
                    $fields = is_array($item) ? $item : (is_string($item) ? ['label' => $item] : []);
                    $fields['name'] ??= $key;

                    $blocks[] = ['type' => 'variable', 'fields' => self::stringifyVariable($fields)];
                }
            }

            return $blocks;
        };
    }

    /**
     * @return Closure(mixed): list<array{type: string, fields: array<string, mixed>}>
     */
    public static function flags(): Closure
    {
        return static function ($raw): array {
            if (!is_array($raw)) {
                return [];
            }

            $blocks = [];

            foreach ($raw as $key => $item) {
                if (is_array($item) && is_array($item['fields'] ?? null)) {
                    $blocks[] = ['type' => 'flag', 'fields' => $item['fields']];

                    continue;
                }

                if (is_array($item)) {
                    $blocks[] = ['type' => 'flag', 'fields' => $item];

                    continue;
                }

                // Shorthand: '--force' => 'Force' or 'Force' => '--force'.
                if (is_string($item)) {
                    [$flag, $label] = str_starts_with($item, '-')
                        ? [$item, is_string($key) ? $key : $item]
                        : [is_string($key) ? $key : $item, $item];

                    $blocks[] = ['type' => 'flag', 'fields' => ['label' => $label, 'flag' => $flag, 'default' => false]];
                }
            }

            return $blocks;
        };
    }

    /**
     * The structured editor uses plain string inputs for options, rules and
     * search columns; array values from config-style definitions are folded
     * into those string shorthands ("value:Label" lines, "a|b", "a,b").
     *
     * @param  array<array-key, mixed>  $fields
     * @return array<string, mixed>
     */
    private static function stringifyVariable(array $fields): array
    {
        $result = [];

        foreach ($fields as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        // Backfill the DTO's defaults so the editor shows what actually
        // applies: a variable without an explicit "required" is required, and
        // one without a "type" is text. Otherwise saving a legacy definition
        // would silently flip such a variable to optional.
        $result['required'] ??= true;
        $result['type'] ??= 'text';

        if (is_array($result['options'] ?? null)) {
            $lines = [];

            foreach ($result['options'] as $key => $option) {
                if (is_array($option) && isset($option['value'])) {
                    $value = Cast::string($option['value']);
                    $label = Cast::string($option['label'] ?? $option['value']);
                } elseif (is_string($key)) {
                    $value = $key;
                    $label = Cast::string($option);
                } else {
                    $value = Cast::string($option);
                    $label = $value;
                }

                $lines[] = $value === $label ? $value : $value.':'.$label;
            }

            $result['options'] = implode("\n", $lines);
        }

        if (is_array($result['rules'] ?? null)) {
            $result['rules'] = implode('|', array_filter($result['rules'], 'is_string'));
        }

        if (is_array($result['search_columns'] ?? null)) {
            $result['search_columns'] = implode(',', array_filter($result['search_columns'], 'is_string'));
        }

        return $result;
    }
}
