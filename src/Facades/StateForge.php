<?php

namespace Roddy\StateForge\Facades;

use Illuminate\Support\Facades\Facade;
use Roddy\StateForge\StateForgeManager;
use Roddy\StateForge\Stores\BaseStore;

/**
 * @method static BaseStore create(string $storeClass, array $config = [])
 * @method static BaseStore get(string $storeClass)
 * @method static \Illuminate\Support\Collection all()
 * @method static void reset(string $storeClass = null)
 * @method static bool exists(string $storeClass)
 * @method static void setPersistence(string $storeClass, string $persistence, array $config = [])
 * @method static array getStoreInfo()
 * @method static string getClientId()
 * @method static \Roddy\StateForge\ClientIdentifier getClientIdentifier()
 *
 * @see \Roddy\StateForge\StateForgeManager
 */
class StateForge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StateForgeManager::class;
    }
}
