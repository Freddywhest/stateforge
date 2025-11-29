<?php

namespace Roddy\StateForge\Stores;

trait DataKeyTrait
{
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
}
