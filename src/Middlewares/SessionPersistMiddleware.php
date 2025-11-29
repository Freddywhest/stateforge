<?php

namespace Roddy\StateForge\Middlewares;

use Roddy\StateForge\Contracts\Middleware;
use \Illuminate\Session\SessionManager as Session;

class SessionPersistMiddleware implements Middleware
{
    public function __construct(
        protected string $sessionKey,
        protected ?Session $session = null
    ) {
        if (!$this->session) {
            $this->session = session();
        }
    }

    public function __invoke(callable $updater, array $state): array
    {
        if ($this->session->has($this->sessionKey)) {
            $loadedState = $this->loadFromSession();
            if ($loadedState) {
                // Only merge non-closure keys
                $state = array_merge(
                    $state,
                    array_intersect_key($loadedState, array_filter($state, fn($v) => !$v instanceof \Closure && !is_object($v)))
                );
            }
        }
        $newState = $updater($state);
        $this->persistToSession($newState);
        return $newState;
    }

    protected function persistToSession(array $state): void
    {
        $data = [
            'state' => $state,
            'updated_at' => now()->toISOString(),
            'checksum' => md5(serialize(array_keys($state)))
        ];

        $this->session->put($this->sessionKey, $data);
    }

    public function loadFromSession(): ?array
    {
        $data = $this->session->get($this->sessionKey);

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

    public function getUpdatedStateFromSession($state): array
    {
        if ($this->session->has($this->sessionKey)) {
            $loadedState = $this->loadFromSession();
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
