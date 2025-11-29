# StateForge 🔥

> **Elegant, Isolated State Management for Laravel**

[![Latest Version](https://img.shields.io/packagist/v/roddy/stateforge.svg?style=flat-square)](https://packagist.org/packages/roddy/stateforge)
[![Total Downloads](https://img.shields.io/packagist/dt/roddy/laravel-stateforge.svg?style=flat-square)](https://packagist.org/packages/roddy/stateforge)
[![License](https://img.shields.io/packagist/l/roddy/stateforge.svg?style=flat-square)](https://packagist.org/packages/roddy/stateforge)

StateForge is a powerful, elegant state management package for Laravel that provides isolated, persistent state stores with multiple persistence options. Built with developer experience in mind, it brings the simplicity of client-side state management to your Laravel applications.

## 📖 Table of Contents

-   [Features](#-features)
-   [Installation](#-installation)
-   [Quick Start](#-quick-start)
-   [Core Concepts](#-core-concepts)
-   [Store Examples](#-store-examples)
-   [API Reference](#-api-reference)
-   [Persistence Options](#-persistence-options)
-   [Advanced Usage](#-advanced-usage)
-   [Configuration](#-configuration)
-   [Maintenance](#-features)
-   [Best Practices](#-best-practices)
-   [Troubleshooting](#-troubleshooting)
-   [FAQ](#-faq)
-   [Contributing](#-contributing)
-   [License](#-faq)

## ✨ Features

-   🚀 **Auto-discovery** - Stores automatically discovered and registered
-   🔒 **User Isolation** - Each browser/user gets completely isolated stores
-   💾 **Multiple Persistence** - File, cache, session, or in-memory storage
-   🎯 **Type Safety** - Use class references instead of magic strings
-   🔄 **Persistence Across Sessions** - State survives browser restarts
-   🧩 **Middleware Support** - Extend functionality with middleware
-   🛠 **Artisan Commands** - Generate stores and manage cleanup
-   📦 **Zero Configuration** - Works out of the box with sensible defaults
-   🔍 **Browser Fingerprinting** - Fallback identification when cookies are cleared
-   🛡️ **Data Integrity** - Checksum verification for persisted data

## 🚀 Installation

### Requirements

-   PHP 8.1 or higher
-   Laravel 10.0 or higher

### Install via Composer

```bash
composer require roddy/stateforge
```

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="Roddy\\StateForge\\StateForgeServiceProvider" --tag=stateforge-config
```

## 🎯 Quick Start

### 1. Create Your First Store

```bash
php artisan make:store Counter
```

This creates `app/Stores/CounterStore.php`:

```php
<?php

namespace App\Stores;

use Roddy\StateForge\Stores\BaseStore;

class CounterStore extends BaseStore
{
    protected function initializeState(): array
    {
        return [
            'count' => 0,
            'created_at' => now()->toISOString(),

            'increment' => function (int $by = 1) {
                $this->setState(fn($state) => array_merge($state, [
                    'count' => $state['count'] + $by,
                    'updated_at' => now()->toISOString()
                ]));
            },

            'decrement' => function (int $by = 1) {
                $this->setState(fn($state) => array_merge($state, [
                    'count' => $state['count'] - $by,
                    'updated_at' => now()->toISOString()
                ]));
            },

            'reset' => function () {
                $this->setState(fn($state) => array_merge($state, [
                    'count' => 0,
                    'updated_at' => now()->toISOString()
                ]));
            },

            'getInfo' => function () {
                return [
                    'count' => $this->count,
                    'created_at' => $this->created_at,
                    'persistence' => $this->getPersistenceType()
                ];
            }
        ];
    }
}
```

### 2. Create a Controller

```php
<?php

namespace App\Http\Controllers;

use Roddy\StateForge\Facades\StateForge;
use App\Stores\CounterStore;

class CounterController extends Controller
{
    public function show()
    {
        $counter = useStore(CounterStore::class);

        return response()->json([
            'count' => $counter->count,
            'info' => $counter->getInfo()
        ]);
    }

    public function increment()
    {
        $counter = useStore(CounterStore::class);
        $counter->increment();

        return response()->json([
            'success' => true,
            'new_count' => $counter->count
        ]);
    }
}
```

### 3. Add Routes

```php
// routes/web.php or routes/api.php
Route::get('/counter', [CounterController::class, 'show']);
Route::post('/counter/increment', [CounterController::class, 'increment']);
```

### 4. Test It Out!

Visit `/counter` in your browser and see the counter in action! Each browser will have its own isolated counter.

## 🧠 Core Concepts

### What is a Store?

A store is a self-contained unit of state with associated actions. Think of it like a mini-database for a specific part of your application.

### Store Structure

```php
protected function initializeState(): array
{
    return [
        // State properties
        'property' => 'value',

        // Actions (methods)
        'actionName' => function () {
            // Modify state using setState
        },

        // Getters
        'getData' => function () {
            return $this->property;
        }
    ];
}
```

### User Isolation

StateForge automatically isolates stores per user/browser combination:

-   ✅ Same browser, different tabs: Same store
-   ✅ Browser restart: Same store (persisted)
-   ✅ Different browser: Different store
-   ✅ Different device: Different store

## 🏗️ Store Examples

### Shopping Cart Store

```bash
php artisan make:store CartStore
```

```php
<?php

namespace App\Stores;

use Roddy\StateForge\Stores\BaseStore;

class CartStore extends BaseStore
{
    protected function initializeState(): array
    {
        return [
            'items' => [],
            'total' => 0,
            'item_count' => 0,
            'created_at' => now()->toISOString(),

            'addItem' => function ($productId, $name, $price, $quantity = 1) {
                $this->setState(function($state) use ($productId, $name, $price, $quantity) {
                    $items = $state['items'];
                    $existingIndex = $this->findItemIndex($items, $productId);

                    if ($existingIndex !== -1) {
                        $items[$existingIndex]['quantity'] += $quantity;
                    } else {
                        $items[] = [
                            'id' => $productId,
                            'name' => $name,
                            'price' => $price,
                            'quantity' => $quantity,
                            'added_at' => now()->toISOString()
                        ];
                    }

                    return $this->calculateCartTotals(array_merge($state, [
                        'items' => $items,
                        'updated_at' => now()->toISOString()
                    ]));
                });
            },

            'removeItem' => function ($productId) {
                $this->setState(function($state) use ($productId) {
                    $items = array_filter($state['items'], fn($item) => $item['id'] !== $productId);

                    return $this->calculateCartTotals(array_merge($state, [
                        'items' => array_values($items),
                        'updated_at' => now()->toISOString()
                    ]));
                });
            },

            'updateQuantity' => function ($productId, $quantity) {
                $this->setState(function($state) use ($productId, $quantity) {
                    $items = array_map(function($item) use ($productId, $quantity) {
                        if ($item['id'] === $productId) {
                            $item['quantity'] = max(0, $quantity);
                        }
                        return $item;
                    }, $state['items']);

                    $items = array_filter($items, fn($item) => $item['quantity'] > 0);

                    return $this->calculateCartTotals(array_merge($state, [
                        'items' => array_values($items),
                        'updated_at' => now()->toISOString()
                    ]));
                });
            },

            'clearCart' => function () {
                $this->setState(fn($state) => array_merge($state, [
                    'items' => [],
                    'total' => 0,
                    'item_count' => 0,
                    'updated_at' => now()->toISOString()
                ]));
            },

            'getSummary' => function () {
                return [
                    'item_count' => $this->item_count,
                    'total' => $this->total,
                    'items' => $this->items,
                    'persistence' => $this->getPersistenceType()
                ];
            }
        ];
    }

    private function findItemIndex(array $items, $productId): int
    {
        foreach ($items as $index => $item) {
            if ($item['id'] === $productId) {
                return $index;
            }
        }
        return -1;
    }

    private function calculateCartTotals(array $state): array
    {
        $items = $state['items'];
        $item_count = array_sum(array_column($items, 'quantity'));
        $total = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $items));

        return array_merge($state, [
            'item_count' => $item_count,
            'total' => $total
        ]);
    }
}
```

### User Preferences Store

```bash
php artisan make:store UserPreferencesStore
```

```php
<?php

namespace App\Stores;

use Roddy\StateForge\Stores\BaseStore;

class UserPreferencesStore extends BaseStore
{
    protected function initializeState(): array
    {
        return [
            'theme' => 'light',
            'language' => 'en',
            'notifications' => true,
            'font_size' => 'medium',
            'created_at' => now()->toISOString(),

            'setTheme' => function (string $theme) {
                $this->setState(fn($state) => array_merge($state, [
                    'theme' => $theme,
                    'updated_at' => now()->toISOString()
                ]));
            },

            'setLanguage' => function (string $language) {
                $this->setState(fn($state) => array_merge($state, [
                    'language' => $language,
                    'updated_at' => now()->toISOString()
                ]));
            },

            'toggleNotifications' => function () {
                $this->setState(fn($state) => array_merge($state, [
                    'notifications' => !$state['notifications'],
                    'updated_at' => now()->toISOString()
                ]));
            },

            'setFontSize' => function (string $size) {
                $this->setState(fn($state) => array_merge($state, [
                    'font_size' => $size,
                    'updated_at' => now()->toISOString()
                ]));
            },

            'getPreferences' => function () {
                return [
                    'theme' => $this->theme,
                    'language' => $this->language,
                    'notifications' => $this->notifications,
                    'font_size' => $this->font_size,
                    'persistence' => $this->getPersistenceType()
                ];
            }
        ];
    }
}
```

### Analytics Store

```bash
php artisan make:store AnalyticsStore
```

```php
<?php

namespace App\Stores;

use Roddy\StateForge\Stores\BaseStore;

class AnalyticsStore extends BaseStore
{
    protected function initializeState(): array
    {
        return [
            'page_views' => 0,
            'events' => [],
            'sessions' => 0,
            'created_at' => now()->toISOString(),

            'trackPageView' => function (string $page) {
                $this->setState(function($state) use ($page) {
                    $events = $state['events'];
                    $events[] = [
                        'type' => 'page_view',
                        'page' => $page,
                        'timestamp' => now()->toISOString()
                    ];

                    return array_merge($state, [
                        'page_views' => $state['page_views'] + 1,
                        'events' => $events,
                        'updated_at' => now()->toISOString()
                    ]);
                });
            },

            'trackEvent' => function (string $event, array $data = []) {
                $this->setState(function($state) use ($event, $data) {
                    $events = $state['events'];
                    $events[] = [
                        'type' => 'custom_event',
                        'event' => $event,
                        'data' => $data,
                        'timestamp' => now()->toISOString()
                    ];

                    return array_merge($state, [
                        'events' => $events,
                        'updated_at' => now()->toISOString()
                    ]);
                });
            },

            'newSession' => function () {
                $this->setState(fn($state) => array_merge($state, [
                    'sessions' => $state['sessions'] + 1,
                    'updated_at' => now()->toISOString()
                ]));
            },

            'getAnalytics' => function () {
                return [
                    'page_views' => $this->page_views,
                    'sessions' => $this->sessions,
                    'total_events' => count($this->events),
                    'persistence' => $this->getPersistenceType()
                ];
            },

            'flushEvents' => function () {
                $this->setState(fn($state) => array_merge($state, [
                    'events' => [],
                    'updated_at' => now()->toISOString()
                ]));
            }
        ];
    }
}
```

## 📚 API Reference

### StateForge Facade

#### Basic Usage

```php
use App\Stores\CounterStore;

// Get or create a store (auto-discovered)
$store = useStore(CounterStore::class);

// Create store with custom configuration
$store = useStore(CounterStore::class, [
    'persistence' => 'cache',
    'cache_ttl' => 3600
]);
```

### Store Instance Methods

```php
$counter = useStore(CounterStore::class);

// Access state properties
echo $counter->count;
echo $counter->created_at;

// Call store methods
$counter->increment(5);
$counter->decrement(2);
$counter->reset();

// Get full state
$state = $counter->getState();

// Subscribe to state changes
$counter->subscribe(function($previousState, $newState) {
    Log::info('State changed', [
        'from' => $previousState['count'],
        'to' => $newState['count']
    ]);
});

// Get persistence type
$persistence = $counter->getPersistenceType(); // 'file', 'cache', 'session', 'none'
```

## 💾 Persistence Options

### File Persistence (Default)

**Best for**: Long-term data that should survive browser restarts and system reboots

```php
useStore(CounterStore::class, [
    'persistence' => 'file'
]);
```

**Characteristics:**

-   ✅ Survives browser restarts
-   ✅ Survives system reboots
-   ✅ No expiration
-   ⚠️ Requires disk space
-   ⚠️ Needs cleanup

### Cache Persistence

**Best for**: Temporary data with expiration

```php
useStore(CounterStore::class, [
    'persistence' => 'cache',
    'cache_ttl' => 3600, // 1 hour
    'cache_driver' => 'redis' // optional
]);
```

**Characteristics:**

-   ✅ Survives browser restarts
-   ✅ Configurable TTL
-   ✅ Can use Redis, Memcached, etc.
-   ❌ Expires after TTL
-   ❌ Cache may be cleared

### Session Persistence

**Best for**: Data that should only live for a browsing session

```php
useStore(CounterStore::class, [
    'persistence' => 'session'
]);
```

**Characteristics:**

-   ✅ Survives page refreshes
-   ✅ Automatic cleanup
-   ❌ Lost on browser close
-   ❌ Limited storage

### No Persistence

**Best for**: Volatile, in-memory data

```php
useStore(CounterStore::class, [
    'persistence' => 'none'
]);
```

**Characteristics:**

-   ✅ Fastest performance
-   ✅ No storage overhead
-   ❌ Lost on page refresh
-   ❌ Not shared between requests

## 🚀 Advanced Usage

### Using in Blade Views

```blade
@php
    $counter = useStore(\App\Stores\CounterStore::class);
    $cart = useStore(\App\Stores\CartStore::class);
    $prefs = useStore(\App\Stores\UserPreferencesStore::class);
@endphp

<div class="dashboard">
    <div class="counter-widget">
        <h3>Count: <span id="counter-value">{{ $counter->count }}</span></h3>
        <button onclick="incrementCounter()" class="btn btn-primary">+</button>
        <button onclick="decrementCounter()" class="btn btn-secondary">-</button>
    </div>

    <div class="cart-widget">
        <h4>Cart: {{ $cart->item_count }} items</h4>
        <p>Total: ${{ number_format($cart->total, 2) }}</p>
    </div>

    <div class="preferences-widget">
        <p>Theme: {{ $prefs->theme }}</p>
        <p>Language: {{ $prefs->language }}</p>
    </div>
</div>

<script>
function incrementCounter() {
    fetch('/counter/increment', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('counter-value').textContent = data.new_count;
    });
}

function decrementCounter() {
    fetch('/counter/decrement', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('counter-value').textContent = data.new_count;
    });
}
</script>
```

### Using in Route Closures

```php
// routes/web.php
Route::get('/api/counter', function () {
    $counter = useStore(\App\Stores\CounterStore::class);
    return response()->json($counter->getInfo());
});

Route::post('/api/counter/increment', function () {
    $counter = useStore(\App\Stores\CounterStore::class);
    $counter->increment();
    return response()->json(['count' => $counter->count]);
});

Route::get('/api/cart/summary', function () {
    $cart = useStore(\App\Stores\CartStore::class);
    return response()->json($cart->getSummary());
});
```

### Custom Middleware

```php
<?php

namespace App\Middlewares;

use Roddy\StateForge\Contracts\Middleware;

class LoggingMiddleware implements Middleware
{
    public function __invoke(callable $updater, array $state): array
    {
        $start = microtime(true);
        $newState = $updater($state);
        $duration = round((microtime(true) - $start) * 1000, 2);

        \Log::debug('State update completed', [
            'duration_ms' => $duration,
            'changed_keys' => array_keys(array_diff_assoc($newState, $state))
        ]);

        return $newState;
    }
}

class AnalyticsMiddleware implements Middleware
{
    public function __invoke(callable $updater, array $state): array
    {
        $newState = $updater($state);

        // Track state changes for analytics
        if (app()->environment('production')) {
            \App\Jobs\TrackStateChange::dispatch(
                get_class($this),
                array_keys(array_diff_assoc($newState, $state))
            );
        }

        return $newState;
    }
}

// Usage
$store->use(new \App\Middlewares\LoggingMiddleware());
$store->use(new \App\Middlewares\AnalyticsMiddleware());
```

### Using in Services

```php
<?php

namespace App\Services;

use Roddy\StateForge\Facades\StateForge;
use App\Stores\CounterStore;
use App\Stores\CartStore;

class ShoppingService
{
    public function addToCart($productId, $name, $price, $quantity = 1)
    {
        $cart = useStore(CartStore::class);
        $cart->addItem($productId, $name, $price, $quantity);

        // Also track analytics
        $counter = useStore(CounterStore::class);
        $counter->increment();

        return $cart->getSummary();
    }

    public function getCartSummary()
    {
        $cart = useStore(CartStore::class);
        return $cart->getSummary();
    }
}
```

## ⚙️ Configuration

### Default Configuration (`config/stateforge.php`)

```php
return [
    'default' => [
        'persistence' => 'file',
        'auto_persist' => true,
    ],

    'persistence' => [
        'file' => [
            'path' => storage_path('temp/stateforge'),
            'auto_cleanup' => true,
            'cleanup_after_days' => 30,
        ],

        'cache' => [
            'driver' => null, // Use default cache driver
            'prefix' => 'stateforge',
            'ttl' => 3600 * 24 * 30, // 30 days
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
```

### Custom Configuration Example

```php
// config/stateforge.php
return [
    'default' => [
        'persistence' => 'cache', // Change default to cache
        'auto_persist' => true,
    ],

    'persistence' => [
        'file' => [
            'path' => storage_path('app/stateforge'), // Custom path
            'auto_cleanup' => true,
            'cleanup_after_days' => 7, // Clean up after 7 days
        ],

        'cache' => [
            'driver' => 'redis', // Use Redis
            'prefix' => 'myapp:state',
            'ttl' => 3600 * 24 * 7, // 1 week
        ],
    ],

    'client' => [
        'cookie_name' => 'myapp_client_id',
        'cookie_lifetime' => 60 * 24 * 30, // 30 days
        'cleanup_after_days' => 7,
    ],
];
```

## 🧹 Maintenance

### Cleanup Commands

```bash
# Clean up stores for clients not seen in 30 days (default)
php artisan stateforge:cleanup

# Clean up stores for clients not seen in 60 days
php artisan stateforge:cleanup --days=60
```

### Store Management Commands

```bash
# Create a new store
php artisan make:store ProductCatalog

# Create store with custom name
php artisan make:store UserShoppingCart
```

### Automated Cleanup

You can schedule cleanup in your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('stateforge:cleanup --days=30')->daily();
}
```

## 🏆 Best Practices

### Store Design

1. **Single Responsibility**: Each store should manage one logical unit of state
2. **Descriptive Names**: Use clear, action-oriented method names
3. **Immutable Updates**: Always return new state in `setState`
4. **Minimal State**: Only store what you need

```php
// ✅ Good - focused store
class CartStore extends BaseStore
{
    protected function initializeState(): array
    {
        return [
            'items' => [],
            'total' => 0,
            'addItem' => function() { /* ... */ },
            'removeItem' => function() { /* ... */ }
        ];
    }
}

// ❌ Avoid - mixed concerns
class UserStore extends BaseStore
{
    protected function initializeState(): array
    {
        return [
            'profile' => [],
            'cart' => [],
            'preferences' => [],
            // Too many responsibilities!
        ];
    }
}
```

### Method Naming

```php
// ✅ Good - clear action names
'incrementCounter' => function() { /* ... */ },
'addToCart' => function() { /* ... */ },
'toggleTheme' => function() { /* ... */ },

// ❌ Avoid - vague names
'doStuff' => function() { /* ... */ },
'update' => function() { /* ... */ },
'handle' => function() { /* ... */ }
```

### Persistence Strategy

| Use Case               | Recommended Persistence |
| ---------------------- | ----------------------- |
| Shopping cart          | `file` or `cache`       |
| User preferences       | `file`                  |
| Analytics data         | `cache` (with TTL)      |
| Form drafts            | `session`               |
| Temporary calculations | `none`                  |

## 🐛 Troubleshooting

### Common Issues

#### Method not found

**Problem:**

```php
$store->nonExistentMethod(); // BadMethodCallException
```

**Solution:**

-   Ensure method is defined in `initializeState()`
-   Method must be a closure
-   Check for typos in method names

#### Persistence not working

**File Persistence:**

-   Check `storage/temp/stateforge` directory permissions
-   Verify disk space

**Cache Persistence:**

-   Check cache configuration
-   Verify cache driver is working

**Session Persistence:**

-   Check session configuration
-   Verify session driver

## ❓ FAQ

### How does user isolation work?

StateForge uses a combination of:

1. **Persistent Cookie**: Survives browser restarts
2. **Browser Fingerprinting**: Fallback using user agent, language, etc.
3. **Client ID**: Unique identifier per browser/user combination

### Can I use StateForge with SPAs?

Yes! StateForge works perfectly with SPAs. Use the API endpoints to interact with stores from your frontend.

### How is data persisted?

Only state data is persisted - methods (closures) are automatically reattached when stores are loaded.

### Can I share stores between users?

No, stores are intentionally isolated per user. For shared state, use your database or cache directly.

### What's the performance impact?

Minimal. StateForge is optimized for performance with lazy loading and efficient persistence.

### Can I use multiple persistence types for different stores?

Yes! Each store can have its own persistence configuration.

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/FreddyWhest/stateforge.git
```

## 📄 License

StateForge is open-sourced software licensed under the [MIT license](LICENCE).

## 🙏 Acknowledgments

-   Inspired by modern state management libraries like Zustand and Redux
-   Built with the Laravel community in mind
-   Thanks to all contributors and users

---

**StateForge** - Forge your application state with elegance and power! 🔥

For more information, visit the [GitHub repository](https://github.com/FreddyWhest/stateforge) or [join our community](https://github.com/FreddyWhest/stateforge/discussions).
