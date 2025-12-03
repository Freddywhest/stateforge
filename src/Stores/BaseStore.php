<?php

namespace Roddy\StateForge\Stores;

use Roddy\StateForge\ClientIdentifier;
use Roddy\StateForge\Contracts\Store;
use Roddy\StateForge\Middlewares\CachePersistMiddleware;
use Roddy\StateForge\Middlewares\FilePersistMiddleware;
use Roddy\StateForge\Middlewares\SessionPersistMiddleware;

abstract class BaseStore implements Store
{
    use DataKeyTrait;

    protected array $state = [];
    protected array $listeners = [];
    protected string $persistenceType = 'file';
    protected array $middlewares = [];

    final public function __construct()
    {
        $this->state = $this->initializeState();
        $this->loadUpdaterEvents(function ($previousState, $newState) {
            $this->onUpdate($previousState, $newState);
        });
        $this->middlewares = $this->middlewares();
        $this->loadUpdatedDataFromMiddleware();
    }

    abstract protected function initializeState(): array;

    abstract protected function onUpdate(array $previousState, array $newState): void;

    abstract protected function middlewares(): array;

    final public function setState(callable $updater): void
    {
        $this->loadUpdatedDataFromMiddleware();

        $updater = $this->applyMiddlewares($updater);
        $previousState = $this->state;
        $this->state = $updater($this->state);

        $this->notifyListeners($previousState, $this->state);
    }

    final protected function applyMiddlewares(callable $updater): callable
    {
        $class = class_basename(static::class);
        $clientId = (new ClientIdentifier())->getClientId();
        if (str_contains($this->persistenceType, 'file')) {
            $arg = $this->getStoreFilePath($class, $clientId);
            $persistenceClass = FilePersistMiddleware::class;
            $middleware = new $persistenceClass($arg);
            $updater = fn($state) => $middleware($updater, $state);
        } else if (str_contains($this->persistenceType, 'cache')) {
            $arg = $this->getCacheKey($class, $clientId, config('stateforge.cache.prefix', 'stateforge'));
            $persistenceClass = CachePersistMiddleware::class;
            $middleware = new $persistenceClass($arg);
            $updater = fn($state) => $middleware($updater, $state);
        } else if (str_contains($this->persistenceType, 'session')) {
            $arg = $this->getSessionKey($class, $clientId, config('stateforge.persistence.session.prefix', 'stateforge'));
            $persistenceClass = SessionPersistMiddleware::class;
            $middleware = new $persistenceClass($arg);
            $updater = fn($state) => $middleware($updater, $state);
        }

        foreach ($this->middlewares as $middleware) {
            $middleware = is_string($middleware) ? new $middleware : $middleware;
            $updater = fn($state) => $middleware($updater, $state);
        }

        return $updater;
    }

    final public function getState(): array
    {
        return $this->state;
    }

    private function loadUpdaterEvents(callable $listener): callable
    {
        $this->listeners[] = $listener;
        return $listener;
    }

    final protected function notifyListeners(array $previousState, array $newState): void
    {
        foreach ($this->listeners as $listener) {
            $listener($previousState, $newState);
        }
    }

    final public function __get($property)
    {
        $this->loadUpdatedDataFromMiddleware();
        return $this->state[$property] ?? null;
    }

    final public function __set($property, $value)
    {
        $this->setState(fn($state) => array_merge($state, [$property => $value]));
    }

    final public function __call($method, $args)
    {
        if (isset($this->state[$method]) && is_callable($this->state[$method])) {
            return call_user_func_array($this->state[$method], $args);
        }

        throw new \BadMethodCallException("Method {$method} not found in store");
    }

    final public function reset(): void
    {
        $this->state = $this->initializeState();
    }

    final public function getPersistenceType(): string
    {
        if (str_contains($this->persistenceType, 'file')) {
            return 'file';
        } else if (str_contains($this->persistenceType, 'cache')) {
            return 'cache';
        } else if (str_contains($this->persistenceType, 'session')) {
            return 'session';
        }
        return 'none';
    }

    private function loadUpdatedDataFromMiddleware()
    {
        $class = class_basename(static::class);
        $clientId = (new ClientIdentifier())->getClientId();

        if ($this->getPersistenceType() === 'file') {
            $path = $this->getStoreFilePath($class, $clientId);
            $this->state = (new FilePersistMiddleware($path))->getUpdatedStateFromFile($this->state);
        } else if ($this->getPersistenceType() === 'cache') {
            $key = $this->getCacheKey($class, $clientId, config('stateforge.cache.prefix', 'stateforge'));
            $this->state = (new CachePersistMiddleware($key))->getUpdatedStateFromCache($this->state);
        } else if ($this->getPersistenceType() === 'session') {
            $key = $this->getSessionKey($class, $clientId, config('stateforge.persistence.session.prefix', 'stateforge'));
            $this->state = (new \Roddy\StateForge\Middlewares\SessionPersistMiddleware($key))->getUpdatedStateFromSession($this->state);
        }
    }
}
