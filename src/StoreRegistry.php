<?php

namespace Roddy\StateForge;

use Illuminate\Support\Collection;
use Illuminate\Foundation\Application;

class StoreRegistry
{
    protected Collection $stores;

    public function __construct(protected Application $app)
    {
        $this->stores = new Collection();
        $this->discoverStores();
    }

    protected function discoverStores(): void
    {
        $storePath = app_path('Stores');

        if (!is_dir($storePath)) {
            return;
        }

        $files = scandir($storePath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $className = 'App\\Stores\\' . pathinfo($file, PATHINFO_FILENAME);

                if (class_exists($className) && is_subclass_of($className, Stores\BaseStore::class)) {
                    $this->stores->put($className, $className);
                }
            }
        }
    }

    public function all(): Collection
    {
        return $this->stores;
    }

    public function get(string $storeClass): ?string
    {
        return $this->stores->get($storeClass);
    }

    public function exists(string $storeClass): bool
    {
        return $this->stores->has($storeClass);
    }

    public function register(string $storeClass): void
    {
        $this->stores->put($storeClass, $storeClass);
    }
}
