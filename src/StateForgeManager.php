<?php

namespace Roddy\StateForge;

use Roddy\StateForge\Stores\BaseStore;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Cache\CacheManager as Cache;
use Illuminate\Session\SessionManager as Session;

class StateForgeManager
{
    protected Collection $stores;
    protected ClientIdentifier $clientIdentifier;

    public function __construct(
        protected Filesystem $files,
        protected Cache $cache,
        protected Session $session,
        ClientIdentifier $clientIdentifier
    ) {
        $this->stores = new Collection();
        $this->clientIdentifier = $clientIdentifier;
    }

    public function create(string $storeClass, array $config = []): BaseStore
    {
        if (!$this->stores->has($storeClass)) {
            $store = new $storeClass();

            $persistence = $config['persistence'] ?? config('stateforge.default.persistence', 'file');
            $this->applyPersistenceMiddleware($store, $storeClass, $persistence, $config);

            $this->stores->put($storeClass, $store);
        }

        return $this->stores->get($storeClass);
    }

    protected function applyPersistenceMiddleware(BaseStore $store, string $storeClass, string $persistence, array $config): void
    {
        $storeName = class_basename($storeClass);
        $clientId = $this->clientIdentifier->getClientId();

        switch ($persistence) {
            case 'file':
                $store->use(new Middlewares\FilePersistMiddleware(
                    $this->getStoreFilePath($storeName, $clientId)
                ));
                break;

            case 'cache':
                $prefix = $config['cache_prefix'] ?? config('stateforge.persistence.cache.prefix', 'stateforge');
                $ttl = $config['cache_ttl'] ?? config('stateforge.persistence.cache.ttl', 3600);
                $driver = $config['cache_driver'] ?? config('stateforge.persistence.cache.driver');

                $store->use(new Middlewares\CachePersistMiddleware(
                    $this->getCacheKey($storeName, $clientId, $prefix),
                    $ttl,
                    $driver
                ));
                break;

            case 'session':
                $prefix = $config['session_prefix'] ?? config('stateforge.persistence.session.prefix', 'stateforge');

                $store->use(new Middlewares\SessionPersistMiddleware(
                    $this->getSessionKey($storeName, $clientId, $prefix)
                ));
                break;

            case 'none':
                break;

            default:
                throw new \InvalidArgumentException("Invalid persistence type: {$persistence}");
        }
    }

    protected function getStoreFilePath(string $storeName, string $clientId): string
    {
        $storagePath = config('stateforge.persistence.file.path', storage_path('app/private/stateforge'));
        return "{$storagePath}/{$clientId}_{$storeName}.json";
    }

    protected function getCacheKey(string $storeName, string $clientId, string $prefix): string
    {
        return "{$prefix}:{$clientId}:{$storeName}";
    }

    protected function getSessionKey(string $storeName, string $clientId, string $prefix): string
    {
        return "{$prefix}.{$clientId}.{$storeName}";
    }

    public function get(string $storeClass): ?BaseStore
    {
        return $this->stores->get($storeClass);
    }

    public function all(): Collection
    {
        return $this->stores;
    }

    public function reset(?string $storeClass = null): void
    {
        if ($storeClass) {
            $store = $this->stores->get($storeClass);
            if ($store) {
                $store->reset();
            }
            $this->stores->forget($storeClass);
            $this->clearPersistedData(class_basename($storeClass));
        } else {
            $this->stores = new Collection();
            $this->clearAllPersistedData();
        }
    }

    protected function clearPersistedData(string $storeName): void
    {
        $clientId = $this->clientIdentifier->getClientId();

        $filePath = $this->getStoreFilePath($storeName, $clientId);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $cacheKey = $this->getCacheKey($storeName, $clientId, config('stateforge.persistence.cache.prefix', 'stateforge'));
        $this->cache->forget($cacheKey);

        $sessionKey = $this->getSessionKey($storeName, $clientId, config('stateforge.persistence.session.prefix', 'stateforge'));
        $this->session->forget($sessionKey);
    }

    protected function clearAllPersistedData(): void
    {
        $clientId = $this->clientIdentifier->getClientId();
        $storagePath = config('stateforge.persistence.file.path', storage_path('app/private/stateforge'));

        $pattern = "{$storagePath}/{$clientId}_*.json";
        $files = glob($pattern);

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $sessionPrefix = config('stateforge.persistence.session.prefix', 'stateforge');
        $sessionKeys = array_filter(
            array_keys($this->session->all()),
            fn($key) => str_starts_with($key, "{$sessionPrefix}.{$clientId}.")
        );

        foreach ($sessionKeys as $key) {
            $this->session->forget($key);
        }
    }

    public function exists(string $storeClass): bool
    {
        return $this->stores->has($storeClass);
    }

    public function getStoreInfo(): array
    {
        $clientId = $this->clientIdentifier->getClientId();

        return [
            'client_id' => $clientId,
            'active_stores' => $this->stores->keys()->toArray(),
            'persistence_stats' => $this->getPersistenceStats()
        ];
    }

    protected function getPersistenceStats(): array
    {
        $stats = ['file' => 0, 'cache' => 0, 'session' => 0, 'none' => 0];

        foreach ($this->stores as $store) {
            $stats[$store->getPersistenceType()]++;
        }

        return $stats;
    }

    public function setPersistence(string $storeClass, string $persistence, array $config = []): void
    {
        if ($this->stores->has($storeClass)) {
            $store = $this->stores->get($storeClass);

            $store->middlewares = array_filter($store->middlewares, function ($middleware) {
                return !($middleware instanceof Middlewares\FilePersistMiddleware) &&
                    !($middleware instanceof Middlewares\CachePersistMiddleware) &&
                    !($middleware instanceof Middlewares\SessionPersistMiddleware);
            });

            $this->applyPersistenceMiddleware($store, $storeClass, $persistence, $config);
        }
    }

    public function getClientId(): string
    {
        return $this->clientIdentifier->getClientId();
    }

    public function getClientIdentifier(): ClientIdentifier
    {
        return $this->clientIdentifier;
    }
}
