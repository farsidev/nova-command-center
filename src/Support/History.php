<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Support;

use Farsidev\NovaCommandCenter\Data\ExecutionResult;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Stores the most recent executions in the cache (no database migration).
 */
final class History
{
    private const KEY = 'nova-command-center:history';

    public function __construct(
        private readonly Cache $cache,
        private readonly int $size,
    ) {}

    public function push(ExecutionResult $result): void
    {
        if ($this->size <= 0) {
            return;
        }

        $items = $this->raw();

        // Replace an existing entry (a queued command updates in place) or prepend.
        $items = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['id'] ?? null) !== $result->id,
        ));

        array_unshift($items, $result->toArray());

        $this->cache->forever(self::KEY, array_slice($items, 0, $this->size));
    }

    /**
     * @return list<ExecutionResult>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $item): ExecutionResult => ExecutionResult::fromArray($item),
            $this->raw(),
        );
    }

    public function find(string $id): ?ExecutionResult
    {
        foreach ($this->raw() as $item) {
            if (($item['id'] ?? null) === $id) {
                return ExecutionResult::fromArray($item);
            }
        }

        return null;
    }

    public function clear(): void
    {
        $this->cache->forget(self::KEY);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function raw(): array
    {
        $items = $this->cache->get(self::KEY, []);

        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_array'));
    }
}
