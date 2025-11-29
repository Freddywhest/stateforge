<?php

use Roddy\StateForge\Facades\StateForge;

if (!function_exists('useStore')) {
    /**
     * Get the StateForge manager instance.
     *
     * @return \Roddy\StateForge\Stores\BaseStore
     */
    function useStore($class, array $config = [])
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Store class {$class} does not exist.");
        }

        if (!is_subclass_of($class, \Roddy\StateForge\Stores\BaseStore::class)) {
            throw new InvalidArgumentException("Store class {$class} must extend BaseStore.");
        }

        $options = !empty($config) ? $config : [
            'persistence' => config('stateforge.default.persistence', 'file'),
        ];

        return StateForge::create($class, $options);
    }
}
