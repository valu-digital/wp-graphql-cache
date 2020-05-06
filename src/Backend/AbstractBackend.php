<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache\Backend;

use WPGraphQL\Extensions\Cache\CachedValue;

/**
 * Abstract class that every WPGraphQL Cache Backend must extend from
 */
abstract class AbstractBackend
{
    /**
     * Write data to the cache with the given zone and key.
     *
     * The $expire param is only for backends that can automatically handle
     * expirations such as Redis. If the backend type does not support
     * expiration it can be safely ignored because the WPGraphQL Cache
     * implements expiration in PHP too.
     *
     * The $data param is a CachedValue instance. This instance must be
     * serialized because it contains invaluable metadata
     */
    abstract public function set(
        string $zone,
        string $key,
        CachedValue $data,
        $expire = null
    ): void;

    /**
     * Restore the CachedValue from instance from the backend. Use
     * unserialize() to get the CachedValue instance
     */
    abstract public function get(string $zone, string $key): ?CachedValue;

    /**
     * Delete cache key from the given zone
     */
    abstract public function delete(string $zone, string $key): bool;

    /**
     * Delete all keys stored the zone
     */
    abstract public function clear_zone(string $zone): bool;

    /**
     * Clear all cached intries from backend in every zone
     */
    abstract public function clear(): bool;
}
