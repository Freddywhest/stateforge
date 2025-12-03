<?php

namespace Roddy\StateForge\Contracts;

interface Store
{
    public function setState(callable $updater): void;
    public function getState(): array;
    public function reset(): void;
    public function getPersistenceType(): string;
}
