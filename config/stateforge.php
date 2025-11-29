<?php

return [
    'default' => [
        'persistence' => 'file', // 'file', 'cache', 'session', 'none'
        'auto_persist' => true,
    ],

    'persistence' => [
        'file' => [
            'path' => storage_path('app/private/stateforge'),
            'auto_cleanup' => true,
            'cleanup_after_days' => 30,
        ],

        'cache' => [
            'driver' => null, // null = default cache driver
            'prefix' => 'stateforge',
            'ttl' => 3600 * 24 * 30, // 30 days for persistent storage
        ],

        'session' => [
            'prefix' => 'stateforge',
        ],
    ],

    'client' => [
        'cookie_name' => 'stateforge_client_id',
        'cookie_lifetime' => 60 * 24 * 365, // 1 year in minutes
        'cleanup_after_days' => 30,
    ],

    'auto_discovery' => [
        'enabled' => true,
        'path' => app_path('Stores'),
    ],
];
