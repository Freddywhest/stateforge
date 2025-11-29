<?php

namespace Roddy\StateForge\Contracts;

interface Middleware
{
    /**
     * Process the state update
     *
     * @param callable $updater The state updater function
     * @param array $state The current state
     * @return array The new state
     */
    public function __invoke(callable $updater, array $state): array;
}
