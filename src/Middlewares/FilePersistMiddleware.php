<?php

namespace Roddy\StateForge\Middlewares;

use Roddy\StateForge\Contracts\Middleware;

class FilePersistMiddleware implements Middleware
{
    public function __construct(protected string $filePath) {}

    public function __invoke(callable $updater, array $state): array
    {
        if (file_exists($this->filePath)) {
            $loadedState = $this->loadFromFile();
            if ($loadedState) {
                // Only merge non-closure keys
                $state = array_merge(
                    $state,
                    array_intersect_key($loadedState, array_filter($state, fn($v) => !$v instanceof \Closure && !is_object($v)))
                );
            }
        }

        $newState = $updater($state);
        $this->persistToFile($newState);
        return $newState;
    }

    protected function persistToFile(array $state): void
    {
        $data = [
            'state' => $state,
            'updated_at' => now()->toISOString(),
            'checksum' => md5(serialize(array_keys($state)))
        ];

        file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function loadFromFile(): ?array
    {
        if (!file_exists($this->filePath)) {
            return null;
        }

        $data = json_decode(file_get_contents($this->filePath), true);

        if (isset($data['state']) && isset($data['checksum'])) {
            $currentChecksum = md5(serialize(array_keys($data['state'])));
            if ($currentChecksum === $data['checksum']) {
                return $data['state'];
            }
        }

        return null;
    }

    public function getUpdatedStateFromFile($state)
    {
        if (file_exists($this->filePath)) {
            $loadedState = $this->loadFromFile();
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
