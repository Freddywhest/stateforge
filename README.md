# StateForge 🔥

> **Elegant, Isolated State Management for Laravel**

[![Latest Version](https://img.shields.io/packagist/v/roddy/stateforge.svg?style=flat-square)](https://packagist.org/packages/roddy/stateforge)
[![License](https://img.shields.io/packagist/l/roddy/stateforge.svg?style=flat-square)](https://packagist.org/packages/roddy/stateforge)

StateForge is a powerful, elegant state management package for Laravel that provides isolated, persistent state stores with automatic discovery and multiple persistence options. Built with developer experience in mind, it brings the simplicity of client-side state management to your Laravel applications.

## 📖 Table of Contents

- [Features](#-features)
- [Installation](#-installation)
- [Quick Start](#-quick-start)
- [Core Concepts](#-core-concepts)
- [Store Lifecycle](#-store-lifecycle)
- [Store Examples](#-store-examples)
- [Events & Hooks](#-events--hooks)
- [API Reference](#-api-reference)
- [Persistence Options](#-persistence-options)
- [Advanced Usage](#-advanced-usage)
- [Configuration](#-configuration)
- [Maintenance](#-maintenance)
- [Best Practices](#-best-practices)
- [Troubleshooting](#-troubleshooting)
- [FAQ](#-faq)
- [Contributing](#-contributin)
- [License](#-license)

## ✨ Features

- 🚀 **Auto-discovery** - Stores automatically discovered and registered
- 🔒 **User Isolation** - Each browser/user gets completely isolated stores
- 💾 **Multiple Persistence** - File, cache, session, or in-memory storage
- 🎯 **Type Safety** - Use class references instead of magic strings
- 🔄 **Persistence Across Sessions** - State survives browser restarts
- 🧩 **Lifecycle Hooks** - `onUpdate()` for automatic state change handling
- 🔌 **Custom Middlewares** - Extend store behavior with middleware arrays
- 🎭 **Event System** - Before/after hooks and global events
- 🛠 **Artisan Commands** - Generate stores and manage cleanup
- 📦 **Zero Configuration** - Works out of the box with sensible defaults
- 🔍 **Browser Fingerprinting** - Fallback identification when cookies are cleared
- 🛡️ **Data Integrity** - Checksum verification for persisted data

## 🚀 Installation

### Requirements

- PHP 8.0 or higher
- Laravel 9.0 or higher

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

This creates `app/Stores/CounterStore.php` with the enhanced architecture:

```php
<?php

namespace App\Stores;

use Roddy\StateForge\Stores\BaseStore;

class CounterStore extends BaseStore
{
    protected string $persistenceType = 'file';

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

            'resetState' => function () {
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

    protected function middlewares(): array
    {
        return []; // Add custom middlewares here. Closure or Class
    }

    protected function onUpdate(array $previousState, array $newState): void
    {
        // Automatic logging on every state change
        \Log::info('Counter state updated', [
            'previous_count' => $previousState['count'] ?? 0,
            'new_count' => $newState['count'] ?? 0,
            'changes' => array_diff_assoc($newState, $previousState)
        ]);
    }
}
```

### 2. Use in Controller

```php
<?php

namespace App\Http\Controllers;

use Roddy\StateForge\Facades\StateForge;
use App\Stores\CounterStore;

class CounterController extends Controller
{
    public function show()
    {
        $counter = StateForge::get(CounterStore::class); // or useStore(CounterStore::class)

        return response()->json([
            'count' => $counter->count,
            'info' => $counter->getInfo()
        ]);
    }

    public function increment()
    {
        $counter = StateForge::get(CounterStore::class); // or useStore(CounterStore::class)
        $counter->increment();

        // onUpdate() is automatically called with logging!

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

Visit `/counter` in your browser and see the counter in action! Each browser will have its own isolated counter, and all state changes are automatically logged.

## 🧠 Core Concepts

### Enhanced Store Architecture

StateForge 2.0 introduces a more structured store architecture:

```php
class YourStore extends BaseStore
{
    // 1. Define persistence type (file, cache, session, none)
    protected string $persistenceType = 'file';

    // 2. Initialize your state and methods
    protected function initializeState(): array { /* ... */ }

    // 3. Add custom middlewares
    protected function middlewares(): array { /* ... */ }

    // 4. Handle state changes automatically
    protected function onUpdate(array $previousState, array $newState): void { /* ... */ }
}
```

### Lifecycle Methods

1. **`initializeState()`** - Define your state properties and methods
2. **`middlewares()`** - Return array of custom middlewares
3. **`onUpdate()`** - Called automatically after every state change
4. **Automatic Persistence** - Based on `$persistenceType`

### User Isolation

StateForge automatically isolates stores per user/browser combination:

- ✅ Same browser, different tabs: Same store
- ✅ Browser restart: Same store (persisted)
- ✅ Different browser: Different store
- ✅ Different device: Different store

### Auto-discovery

Stores placed in `app/Stores/` are automatically discovered and registered. No manual configuration needed!

## 🔄 Store Lifecycle

### Complete Lifecycle Flow

```php
class UserStore extends BaseStore
{
    protected string $persistenceType = 'file';

    protected function initializeState(): array
    {
        return [
            'user' => null,
            'is_logged_in' => false,
            // ... methods
        ];
    }

    protected function middlewares(): array
    {
        return [
            // Custom middleware that runs on every state change
            function(callable $updater, array $state) {
                // Called before state update
                Log::debug('Middleware: Before update');
                $newState = $updater($state);
                Log::debug('Middleware: After update');
                return $newState;
            }
        ];
    }

    protected function onUpdate(array $previousState, array $newState): void
    {
        // Automatic handling after every state change
        if ($previousState['is_logged_in'] !== $newState['is_logged_in']) {
            event(new UserLoginStatusChanged(
                $newState['is_logged_in'],
                $newState['user']
            ));
        }
    }
}
```

### Middleware System

Add custom behavior to your stores:

```php
protected function middlewares(): array
{
    return [
        // Validation middleware
        function(callable $updater, array $state) {
            $newState = $updater($state);

            // Validate state
            if (isset($newState['count']) && $newState['count'] < 0) {
                throw new \InvalidArgumentException('Count cannot be negative');
            }

            return $newState;
        },

        // Logging middleware
        function(callable $updater, array $state) {
            $start = microtime(true);
            $newState = $updater($state);
            $duration = microtime(true) - $start;

            Log::debug('State update completed', [
                'duration' => $duration,
                'changes' => array_diff_assoc($newState, $state)
            ]);

            return $newState;
        },

        // Analytics middleware
        function(callable $updater, array $state) {
            $newState = $updater($state);

            if (app()->environment('production')) {
                Analytics::track('state_updated', [
                    'store' => static::class,
                    'changes' => array_keys(array_diff_assoc($newState, $state))
                ]);
            }

            return $newState;
        }
    ];
}
```

### Automatic onUpdate() Handling

The `onUpdate()` method is called automatically after every state change:

```php
protected function onUpdate(array $previousState, array $newState): void
{
    // Example 1: Automatic syncing with database
    if (isset($newState['user']) && $newState['user'] !== $previousState['user']) {
        // Sync user preferences to database
        DB::table('user_preferences')->updateOrCreate(
            ['user_id' => $newState['user']['id']],
            ['preferences' => json_encode($newState['preferences'])]
        );
    }

    // Example 2: Real-time broadcasting
    if (isset($newState['cart_items']) && $newState['cart_items'] !== $previousState['cart_items']) {
        broadcast(new CartUpdated($newState['cart_items']));
    }

    // Example 3: Cache invalidation
    if (isset($newState['settings'])) {
        Cache::forget('user_settings_' . auth()->id());
    }

    // Example 4: Audit logging
    AuditLog::create([
        'event' => 'state_update',
        'store' => static::class,
        'old_state' => $previousState,
        'new_state' => $newState,
        'changes' => array_diff_assoc($newState, $previousState)
    ]);
}
```

## 🏗️ Store Examples

### Shopping Cart Store with Lifecycle Hooks

```bash
php artisan make:store Cart
```

```php
<?php

namespace App\Stores;

use Roddy\StateForge\Stores\BaseStore;

class CartStore extends BaseStore
{
    protected string $persistenceType = 'file';

    protected function initializeState(): array
    {
        return [
            'items' => [],
            'total' => 0,
            'item_count' => 0,
            'coupon' => null,
            'discount' => 0,
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

            'applyCoupon' => function ($code) {
                $this->setState(function($state) use ($code) {
                    $coupon = \App\Models\Coupon::where('code', $code)->valid()->first();
                    $discount = $coupon ? $coupon->calculateDiscount($state['total']) : 0;

                    return array_merge($state, [
                        'coupon' => $coupon,
                        'discount' => $discount,
                        'updated_at' => now()->toISOString()
                    ]);
                });
            },

            'clearCart' => function () {
                $this->setState(fn($state) => array_merge($state, [
                    'items' => [],
                    'total' => 0,
                    'item_count' => 0,
                    'coupon' => null,
                    'discount' => 0,
                    'updated_at' => now()->toISOString()
                ]));
            },

            'getSummary' => function () {
                return [
                    'item_count' => $this->item_count,
                    'total' => $this->total,
                    'discount' => $this->discount,
                    'final_total' => $this->total - $this->discount,
                    'items' => $this->items,
                    'persistence' => $this->getPersistenceType()
                ];
            }
        ];
    }

    protected function middlewares(): array
    {
        return [
            // Validate cart items
            function(callable $updater, array $state) {
                $newState = $updater($state);

                // Ensure quantities are positive
                foreach ($newState['items'] as $item) {
                    if ($item['quantity'] < 1) {
                        throw new \InvalidArgumentException('Item quantity must be at least 1');
                    }
                }

                return $newState;
            }
        ];
    }

    protected function onUpdate(array $previousState, array $newState): void
    {
        // Log cart changes
        \Log::info('Cart updated', [
            'previous_items' => count($previousState['items']),
            'new_items' => count($newState['items']),
            'total_change' => $newState['total'] - $previousState['total']
        ]);

        // Broadcast real-time updates
        if ($newState['items'] !== $previousState['items']) {
            broadcast(new \App\Events\CartUpdated(
                auth()->user(),
                $newState['items'],
                $newState['total']
            ));
        }

        // Sync to database if user is logged in
        if (auth()->check() && $newState['items'] !== $previousState['items']) {
            \App\Jobs\SyncCartToDatabase::dispatch(
                auth()->id(),
                $newState['items']
            );
        }
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
        $subtotal = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $items));

        return array_merge($state, [
            'item_count' => $item_count,
            'subtotal' => $subtotal,
            'total' => $subtotal - ($state['discount'] ?? 0)
        ]);
    }
}
```

### User Preferences Store with Automatic Syncing

```bash
php artisan make:store UserPreferences
```

```php
<?php

namespace App\Stores;

use Roddy\StateForge\Stores\BaseStore;

class UserPreferencesStore extends BaseStore
{
    protected string $persistenceType = 'cache';

    protected function initializeState(): array
    {
        return [
            'theme' => 'light',
            'language' => 'en',
            'notifications' => true,
            'font_size' => 'medium',
            'timezone' => 'UTC',
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

            'updateAll' => function (array $preferences) {
                $this->setState(fn($state) => array_merge($state, [
                    ...$preferences,
                    'updated_at' => now()->toISOString()
                ]));
            },

            'getPreferences' => function () {
                return [
                    'theme' => $this->theme,
                    'language' => $this->language,
                    'notifications' => $this->notifications,
                    'font_size' => $this->font_size,
                    'timezone' => $this->timezone,
                    'persistence' => $this->getPersistenceType()
                ];
            }
        ];
    }

    protected function middlewares(): array
    {
        return [
            // Validate preferences
            function(callable $updater, array $state) {
                $newState = $updater($state);

                $allowedThemes = ['light', 'dark', 'system'];
                $allowedLanguages = ['en', 'es', 'fr', 'de'];
                $allowedFontSizes = ['small', 'medium', 'large'];

                if (isset($newState['theme']) && !in_array($newState['theme'], $allowedThemes)) {
                    throw new \InvalidArgumentException("Invalid theme: {$newState['theme']}");
                }

                if (isset($newState['language']) && !in_array($newState['language'], $allowedLanguages)) {
                    throw new \InvalidArgumentException("Invalid language: {$newState['language']}");
                }

                if (isset($newState['font_size']) && !in_array($newState['font_size'], $allowedFontSizes)) {
                    throw new \InvalidArgumentException("Invalid font size: {$newState['font_size']}");
                }

                return $newState;
            }
        ];
    }

    protected function onUpdate(array $previousState, array $newState): void
    {
        // Sync to database when user is logged in
        if (auth()->check()) {
            \App\Jobs\SyncPreferencesToDatabase::dispatch(
                auth()->id(),
                $newState
            );
        }

        // Update session based on preferences
        if ($newState['theme'] !== $previousState['theme']) {
            session(['theme' => $newState['theme']]);
        }

        if ($newState['language'] !== $previousState['language']) {
            session(['locale' => $newState['language']]);
            app()->setLocale($newState['language']);
        }

        // Log preference changes
        \Log::info('User preferences updated', [
            'user_id' => auth()->id(),
            'changes' => array_diff_assoc($newState, $previousState)
        ]);
    }
}
```

## 📚 API Reference

### StateForge Facade

```php
use Roddy\StateForge\Facades\StateForge;
use App\Stores\CounterStore;

// Get or create store (auto-discovered)
$store = StateForge::get(CounterStore::class);

// Create store with custom configuration
$store = StateForge::create(CounterStore::class, [
    'persistence' => 'cache',
    'cache_ttl' => 3600
]);

// Store management
StateForge::all(); // Get all stores
StateForge::exists(CounterStore::class); // Check if store exists
StateForge::reset(CounterStore::class); // Reset specific store
StateForge::reset(); // Reset all stores
StateForge::getStoreInfo(); // Get store information
StateForge::getClientId(); // Get client identifier

// Change persistence at runtime
StateForge::setPersistence(CounterStore::class, 'file');
```

### Store Instance Methods

```php
$counter = StateForge::get(CounterStore::class);  // or useStore(CounterStore::class)

// Access state properties
echo $counter->count;
echo $counter->created_at;

// Call store methods
$counter->increment(5);
$counter->decrement(2);
$counter->resetState();

// Get state
$state = $counter->getState();

// Hooks
$counter->before('method', $callback);
$counter->after('method', $callback);

// Events
$counter->on('event', $listener);
$counter->off('event', $listener);

// Lifecycle info
$persistence = $counter->getPersistenceType(); // 'file', 'cache', 'session', 'none'
$hooks = $counter->getHookInfo(); // Get hook information
$listeners = $counter->getEventListeners(); // Get event listeners
```

## 💾 Persistence Options

### File Persistence (Default)

```php
protected string $persistenceType = 'file';
```

- **Survives**: Browser restarts, system reboots
- **Storage**: JSON files in `storage/app/private/stateforge`
- **Best for**: Long-term data, user preferences, shopping carts

### Cache Persistence

```php
protected string $persistenceType = 'cache';
```

- **Survives**: Browser restarts (with TTL)
- **Storage**: Laravel cache (Redis, Memcached, etc.)
- **Best for**: Temporary data, session-like data

### Session Persistence

```php
protected string $persistenceType = 'session';
```

- **Survives**: Page refreshes only
- **Storage**: Laravel session
- **Best for**: Flash data, temporary calculations

### No Persistence

```php
protected string $persistenceType = 'none';
```

- **Survives**: Current request only
- **Storage**: Memory
- **Best for**: Volatile data, calculations

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
            'path' => storage_path('app/private/stateforge'),
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
        'cookie_lifetime' => 60 * 24 * 365, // 1 year
        'cleanup_after_days' => 30,
    ],

    'auto_discovery' => [
        'enabled' => true,
        'path' => app_path('Stores'),
    ],
];
```

## 🚀 Advanced Usage

### Using in Livewire Components

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use Roddy\StateForge\Facades\StateForge;
use App\Stores\CounterStore;

class CounterComponent extends Component
{
    public $count = 0;

    protected $counter;

    public function mount()
    {
        $this->counter = StateForge::get(CounterStore::class); // or useStore(CounterStore::class)
        $this->count = $this->counter->count;

        // onUpdate() will handle automatic syncing
    }

    public function increment()
    {
        $this->counter->increment();
        $this->count = $this->counter->count;
    }

    public function render()
    {
        return view('livewire.counter-component');
    }
}
```

### Using in Blade Views

```blade
@php
    $counter = useStore(\App\Stores\CounterStore::class);
    $cart = useStore(\App\Stores\CartStore::class);
@endphp

<div class="dashboard">
    <div class="counter">
        <h3>Count: {{ $counter->count }}</h3>
        <button onclick="incrementCounter()">+</button>
    </div>

    <div class="cart">
        <h4>Cart Items: {{ $cart->item_count }}</h4>
        <p>Total: ${{ number_format($cart->total, 2) }}</p>
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
    });
}
</script>
```

### Custom Middleware Classes

```php
<?php

namespace App\StateForge\Middlewares;

use Roddy\StateForge\Contracts\Middleware;

class ValidationMiddleware implements Middleware
{
    public function __invoke(callable $updater, array $state): array
    {
        $newState = $updater($state);

        // Add validation logic
        if (isset($newState['count']) && $newState['count'] < 0) {
            throw new \InvalidArgumentException('Count cannot be negative');
        }

        return $newState;
    }
}

class AnalyticsMiddleware implements Middleware
{
    public function __invoke(callable $updater, array $state): array
    {
        $newState = $updater($state);

        // Track state changes
        if (app()->environment('production')) {
            \App\Jobs\TrackStateChange::dispatch(
                get_class($this),
                array_keys(array_diff_assoc($newState, $state))
            );
        }

        return $newState;
    }
}

// Usage in store
protected function middlewares(): array
{
    return [
        \App\StateForge\Middlewares\ValidationMiddleware::class,
        \App\StateForge\Middlewares\AnalyticsMiddleware::class,
    ];
}
```

## 🧹 Maintenance

### Cleanup Commands

```bash
# Clean up stores for clients not seen in 30 days (default)
php artisan stateforge:cleanup

# Clean up stores for clients not seen in 60 days
php artisan stateforge:cleanup --days=60

# Schedule cleanup (in app/Console/Kernel.php)
$schedule->command('stateforge:cleanup --days=30')->daily();
```

### Store Creation

```bash
# Create a new store
php artisan make:store ProductCatalog

# Stores are automatically discovered from app/Stores/
```

## 🏆 Best Practices

### Store Design

- Keep stores focused on a single responsibility
- Use descriptive method names
- Leverage `onUpdate()` for side effects
- Add validation in middlewares

### Performance

- Choose appropriate persistence type
- Use cache persistence for frequently accessed data
- Clean up old stores regularly
- Monitor store sizes

### Security

- Never store sensitive data without encryption
- Validate all inputs in middlewares
- Use appropriate persistence for data sensitivity

## 🐛 Troubleshooting

### Store returns `null`

```php
// ❌ Wrong - might create new instance
$store = new CounterStore();

// ✅ Correct - use facade
$store = StateForge::get(CounterStore::class);
```

### Hooks not working

- Ensure you're using the same store instance
- Check that hooks are added before calling methods
- Verify method signatures match

### Persistence issues

- Check storage permissions for file persistence
- Verify cache configuration for cache persistence
- Ensure session is configured for session persistence

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

## 📄 License

StateForge is open-sourced software licensed under the MIT license.

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

- Inspired by modern state management libraries like Zustand and Redux
- Built with the Laravel community in mind
- Thanks to all contributors and users

---

**StateForge** - Forge your application state with elegance and power! 🔥

For more information, visit the [GitHub repository](https://github.com/FreddyWhest/stateforge) or [join our community](https://github.com/FreddyWhest/stateforge/discussions).
