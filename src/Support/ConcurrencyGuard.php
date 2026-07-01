<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Support;

use Farsidev\NovaCommandCenter\Data\CommandDefinition;
use Farsidev\NovaCommandCenter\Exceptions\CommandNotAllowedException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Prevents overlapping execution of the same command or group using atomic
 * cache locks. Degrades gracefully when the cache store cannot provide locks.
 */
final class ConcurrencyGuard
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly Cache $cache,
        private readonly array $config,
    ) {}

    /**
     * Run the callback while holding any required locks.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function run(CommandDefinition $command, int $timeout, callable $callback)
    {
        $store = $this->lockProvider();

        if ($store === null) {
            return $callback();
        }

        /** @var list<Lock> $locks */
        $locks = [];

        foreach ($this->lockNames($command) as $name) {
            $lock = $store->lock($name, max(1, $timeout) + 10);

            if (!$lock->get()) {
                foreach ($locks as $acquired) {
                    $acquired->release();
                }

                throw CommandNotAllowedException::locked($command->name);
            }

            $locks[] = $lock;
        }

        try {
            return $callback();
        } finally {
            foreach ($locks as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * @return list<string>
     */
    private function lockNames(CommandDefinition $command): array
    {
        $names = [];

        $commands = $this->config['commands'] ?? [];
        $groups = $this->config['groups'] ?? [];

        if (is_array($commands) && in_array($command->name, $commands, true)) {
            $names[] = 'nova-command-center:lock:command:'.$command->id;
        }

        if (is_array($groups) && in_array($command->group, $groups, true)) {
            $names[] = 'nova-command-center:lock:group:'.md5($command->group);
        }

        return $names;
    }

    private function lockProvider(): ?LockProvider
    {
        $store = $this->cache->getStore();

        return $store instanceof LockProvider ? $store : null;
    }
}
