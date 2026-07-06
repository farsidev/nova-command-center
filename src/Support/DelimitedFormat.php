<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Support;

use Farsi\NovaCommandCenter\Data\CommandVariable;

/**
 * Bidirectional conversions between the array shapes {@see CommandVariable}
 * expects (options as {value,label} pairs, rules/search_columns as string
 * lists) and the plain-text shorthand the bundled Command resource's
 * structured editor uses (options as "value:Label" lines, rules as "a|b",
 * columns as "a,b"). Centralised so each format's parsing rules exist in
 * exactly one place: CommandVariable reads it in the string -> array
 * direction, {@see RepeaterBlocks} in the array -> string direction.
 */
final class DelimitedFormat
{
    /**
     * Parse "value:Label" (or bare "value") lines into option pairs.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function optionsFromLines(string $lines): array
    {
        return array_values(array_filter(array_map(
            static function (string $line): array {
                [$value, $label] = array_pad(explode(':', trim($line), 2), 2, null);

                return ['value' => trim((string) $value), 'label' => trim($label ?? (string) $value)];
            },
            preg_split('/\r\n|\r|\n/', $lines) ?: [],
        ), static fn (array $option): bool => $option['value'] !== ''));
    }

    /**
     * Join option pairs back into "value:Label" lines (bare "value" when the
     * label matches it).
     *
     * @param  list<array{value: string, label: string}>  $pairs
     */
    public static function optionsToLines(array $pairs): string
    {
        return implode("\n", array_map(
            static fn (array $pair): string => $pair['value'] === $pair['label'] ? $pair['value'] : $pair['value'].':'.$pair['label'],
            $pairs,
        ));
    }

    /**
     * Normalize any accepted "array of options" shape — a list of
     * {value,label} arrays, an associative ['on' => 'Enabled'] map, or a
     * plain value list — into option pairs.
     *
     * @param  array<array-key, mixed>  $options
     * @return list<array{value: string, label: string}>
     */
    public static function normalizeOptionPairs(array $options): array
    {
        $normalized = [];

        foreach ($options as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                $normalized[] = [
                    'value' => Cast::string($value['value']),
                    'label' => Cast::string($value['label'] ?? $value['value']),
                ];

                continue;
            }

            $normalized[] = is_string($key)
                ? ['value' => $key, 'label' => Cast::string($value)]
                : ['value' => Cast::string($value), 'label' => Cast::string($value)];
        }

        return $normalized;
    }

    /**
     * Join a list back into a delimited string, dropping non-string items.
     *
     * @param  array<array-key, mixed>  $list
     */
    public static function listToString(array $list, string $delimiter): string
    {
        return implode($delimiter, array_filter($list, 'is_string'));
    }

    /**
     * Trim and drop empty entries from a list of (possibly non-string) items.
     *
     * @param  array<array-key, mixed>  $list
     * @return list<string>
     */
    public static function normalizeStringList(array $list): array
    {
        return array_values(array_filter(
            array_map(static fn ($item): string => is_string($item) ? trim($item) : '', $list),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
