<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Support;

use Farsi\NovaCommandCenter\Data\ExecutionResult;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Stores the most recent executions in the cache (no database migration).
 */
final class History
{
    private const KEY = 'nova-command-center:history';

    private const LOCK_KEY = 'nova-command-center:history:lock';

    private const LOCK_TTL = 10;

    public function __construct(
        private readonly Cache $cache,
        private readonly int $size,
    ) {}

    public function push(ExecutionResult $result): void
    {
        if ($this->size <= 0) {
            return;
        }

        $this->withLock(function () use ($result): void {
            $items = $this->raw();

            // Replace an existing entry (a queued command updates in place) or prepend.
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => ($item['id'] ?? null) !== $result->id,
            ));

            array_unshift($items, $result->toArray());

            $this->cache->forever(self::KEY, array_slice($items, 0, $this->size));
        });
    }

    /**
     * Run a read-modify-write against the history list while holding an atomic
     * lock, when the cache store supports one. Two executions finishing close
     * together (routine with queued/concurrent commands) would otherwise race:
     * both read the same list, both write back, and one entry silently
     * disappears — the same hazard {@see ConcurrencyGuard} guards against for
     * command execution itself. Degrades to a best-effort, unlocked write (the
     * pre-existing behaviour) when the store can't lock, or when the lock
     * can't be acquired in time — losing a history entry is preferable to
     * failing the operator's command.
     */
    private function withLock(callable $callback): void
    {
        $store = $this->cache->getStore();

        if (!$store instanceof LockProvider) {
            $callback();

            return;
        }

        try {
            $store->lock(self::LOCK_KEY, self::LOCK_TTL)->block(5, $callback);
        } catch (LockTimeoutException) {
            $callback();
        }
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

        return array_values(array_map(
            Cast::stringKeyedArray(...),
            array_filter($items, 'is_array'),
        ));
    }
}
