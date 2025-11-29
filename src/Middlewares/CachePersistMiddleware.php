<?php

namespace Roddy\StateForge\Middlewares;

use Roddy\StateForge\Contracts\Middleware;
use Illuminate\Contracts\Cache\Repository as Cache;

class CachePersistMiddleware implements Middleware
{
    public function __construct(
        protected string $cacheKey,
        protected int $ttl = 3600,
        protected ?string $driver = null,
        protected ?Cache $cache = null
    ) {
        if (!$this->cache) {
            $this->cache = $driver ? cache()->driver($driver) : cache();
        }
    }

    public function __invoke(callable $updater, array $state): array
    {
        if ($this->cache->has($this->cacheKey)) {
            $loadedState = $this->loadFromCache();
            if ($loadedState) {
                // Only merge non-closure keys
                $state = array_merge(
                    $state,
                    array_intersect_key($loadedState, array_filter($state, fn($v) => !$v instanceof \Closure && !is_object($v)))
                );
            }
        }
        $newState = $updater($state);
        $this->persistToCache($newState);
        return $newState;
    }

    protected function persistToCache(array $state): void
    {
        $data = [
            'state' => $state,
            'updated_at' => now()->toISOString(),
            'checksum' => md5(serialize(array_keys($state)))
        ];

        $this->cache->put($this->cacheKey, $data, $this->ttl);
    }

    public function loadFromCache(): ?array
    {
        $data = $this->cache->get($this->cacheKey);

        if (!$data) {
            return null;
        }

        if (isset($data['state']) && isset($data['checksum'])) {
            $currentChecksum = md5(serialize(array_keys($data['state'])));
            if ($currentChecksum === $data['checksum']) {
                return $data['state'];
            }
        }

        return null;
    }

    public function getUpdatedStateFromCache($state)
    {
        if ($this->cache->has($this->cacheKey)) {
            $loadedState = $this->loadFromCache();
            if ($loadedState) {
                $state = array_merge(
                    $state,
                    array_intersect_key($loadedState, array_filter($state, fn($v) => !$v instanceof \Closure && !is_object($v)))
                );

                return $state;
            }
        }

        return $state;
    }
}
