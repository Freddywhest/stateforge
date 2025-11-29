<?php

namespace Roddy\StateForge;

use Illuminate\Support\ServiceProvider;

class StateForgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/stateforge.php',
            'stateforge'
        );

        $this->app->singleton(ClientIdentifier::class, function ($app) {
            return new ClientIdentifier();
        });

        $this->app->singleton(StateForgeManager::class, function ($app) {
            return new StateForgeManager(
                $app['files'],
                $app['cache'],
                $app['session'],
                $app[ClientIdentifier::class]
            );
        });

        $this->app->singleton(StoreRegistry::class, function ($app) {
            return new StoreRegistry($app);
        });

        $this->registerDiscoveredStores();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/stateforge.php' => config_path('stateforge.php'),
            ], 'stateforge-config');

            $this->commands([
                Commands\MakeStoreCommand::class,
                Commands\CleanupStoresCommand::class,
            ]);
        }

        $this->app['router']->pushMiddlewareToGroup('web', Http\Middleware\StateForgeMiddleware::class);
    }

    protected function registerDiscoveredStores(): void
    {
        $this->app->afterResolving(StateForgeManager::class, function ($manager) {
            $registry = $this->app->make(StoreRegistry::class);

            foreach ($registry->all() as $storeClass) {
                $this->app->singleton("stateforge.{$storeClass}", function () use ($manager, $storeClass) {
                    return $manager->create($storeClass);
                });
            }
        });
    }
}
