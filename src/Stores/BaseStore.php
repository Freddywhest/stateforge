<?php

namespace Roddy\StateForge\Stores;

use Roddy\StateForge\ClientIdentifier;
use Roddy\StateForge\Contracts\Store;
use Roddy\StateForge\Middlewares\CachePersistMiddleware;
use Roddy\StateForge\Middlewares\FilePersistMiddleware;

abstract class BaseStore implements Store
{
    use DataKeyTrait;

    protected array $state = [];
    protected array $listeners = [];
    protected array $middlewares = [];

    public function __construct()
    {
        $this->state = $this->initializeState();
        $this->loadPersistedState();
    }

    abstract protected function initializeState(): array;

    protected function loadPersistedState(): void
    {
        foreach ($this->middlewares as $middleware) {
            $persistedState = null;

            if (method_exists($middleware, 'loadFromFile')) {
                $persistedState = $middleware->loadFromFile();
            } elseif (method_exists($middleware, 'loadFromCache')) {
                $persistedState = $middleware->loadFromCache();
            } elseif (method_exists($middleware, 'loadFromSession')) {
                $persistedState = $middleware->loadFromSession();
            }

            if ($persistedState) {
                $this->state = array_merge($this->state, $persistedState);
                break;
            }
        }
    }

    public function use(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function setState(callable $updater): void
    {
        $this->loadUpdatedDataFromMiddlewar();

        $updater = $this->applyMiddlewares($updater);
        $previousState = $this->state;
        $this->state = $updater($this->state);

        $this->notifyListeners($previousState, $this->state);
    }

    protected function applyMiddlewares(callable $updater): callable
    {
        $middlewares = array_reverse($this->middlewares);

        foreach ($middlewares as $middleware) {
            $updater = fn($state) => $middleware($updater, $state);
        }

        return $updater;
    }

    public function getState(): array
    {
        return $this->state;
    }

    public function subscribe(callable $listener): callable
    {
        $this->listeners[] = $listener;

        return function () use ($listener) {
            $this->listeners = array_filter($this->listeners, fn($l) => $l !== $listener);
        };
    }

    protected function notifyListeners(array $previousState, array $newState): void
    {
        foreach ($this->listeners as $listener) {
            $listener($previousState, $newState);
        }
    }

    public function __get($property)
    {
        $this->loadUpdatedDataFromMiddlewar();
        return $this->state[$property] ?? null;
    }

    public function __set($property, $value)
    {
        $this->setState(fn($state) => array_merge($state, [$property => $value]));
    }

    public function __call($method, $args)
    {
        if (isset($this->state[$method]) && is_callable($this->state[$method])) {
            return call_user_func_array($this->state[$method], $args);
        }

        throw new \BadMethodCallException("Method {$method} not found in store");
    }

    public function reset(): void
    {
        $this->state = $this->initializeState();
    }

    public function getPersistenceType(): string
    {
        foreach ($this->middlewares as $middleware) {
            if ($middleware instanceof \Roddy\StateForge\Middlewares\FilePersistMiddleware) {
                return 'file';
            } elseif ($middleware instanceof \Roddy\StateForge\Middlewares\CachePersistMiddleware) {
                return 'cache';
            } elseif ($middleware instanceof \Roddy\StateForge\Middlewares\SessionPersistMiddleware) {
                return 'session';
            }
        }

        return 'none';
    }

    private function loadUpdatedDataFromMiddlewar()
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
