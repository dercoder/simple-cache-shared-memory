# SimpleCache SharedMemory

A simple cache implementation using shared memory in PHP.

## Installation

You can install the package using Composer:

```bash
composer require dercoder/simple-cache-shared-memory
```

## Usage

```php
use DerCoder\SimpleCache\SharedMemory\SharedMemoryCache;
use Psr\SimpleCache\CacheInterface;

$cache = new SharedMemoryCache('1M'); // 1MB shared memory segment

// Set cache items
$cache->set('key1', 'value1');
$cache->set('key2', [1, 2, 3], 60); // with TTL of 60 seconds

// Get cache items
$value1 = $cache->get('key1');
$value2 = $cache->get('key2');

// Check if a cache item exists
$hasKey1 = $cache->has('key1');

// Delete cache items
$cache->delete('key1');

```

## TODO

 - Lock functions so multiple threads will not overwrite values
 - Second release for PHP 8+

## Contributing

Contributions are welcome! Please feel free to open an issue or submit a pull request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
