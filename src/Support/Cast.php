<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Support;

/**
 * Safe coercion for untrusted values read from config, JSON caches or request
 * input. Every input is `mixed`; these helpers narrow it before casting so an
 * unexpected type falls back to a sane default instead of producing a surprising
 * cast (e.g. an array becoming the string "Array"). Centralising it here keeps
 * the DTOs free of repetitive type guards.
 */
final class Cast
{
    public static function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    public static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Narrow a decoded cache/JSON value to a string-keyed array, dropping any
     * non-string keys. Serialised payloads round-trip through `array-key`, so
     * this restores the `array<string, mixed>` shape the DTOs expect.
     *
     * @return array<string, mixed>
     */
    public static function stringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
