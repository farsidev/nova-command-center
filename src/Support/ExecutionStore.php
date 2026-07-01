<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Support;

use Farsidev\NovaCommandCenter\Data\ExecutionResult;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Holds the live state of an in-flight (typically queued) execution so the UI
 * can poll for incremental output while a command runs on a worker.
 */
final class ExecutionStore
{
    private const PREFIX = 'nova-command-center:execution:';

    private const PROGRESS_PREFIX = 'nova-command-center:progress:';

    private const TTL = 3600;

    public function __construct(private readonly Cache $cache) {}

    /**
     * @param  array{current: int, total: int, percentage: int|null, message: string|null}  $progress
     */
    public function putProgress(string $id, array $progress): void
    {
        $this->cache->put(self::PROGRESS_PREFIX.$id, $progress, self::TTL);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProgress(string $id): ?array
    {
        $data = $this->cache->get(self::PROGRESS_PREFIX.$id);

        return is_array($data) ? Cast::stringKeyedArray($data) : null;
    }

    public function forgetProgress(string $id): void
    {
        $this->cache->forget(self::PROGRESS_PREFIX.$id);
    }

    public function put(ExecutionResult $result): void
    {
        $this->cache->put(self::PREFIX.$result->id, $result->toArray(), self::TTL);
    }

    public function get(string $id): ?ExecutionResult
    {
        $data = $this->cache->get(self::PREFIX.$id);

        return is_array($data) ? ExecutionResult::fromArray(Cast::stringKeyedArray($data)) : null;
    }

    public function forget(string $id): void
    {
        $this->cache->forget(self::PREFIX.$id);
    }
}
