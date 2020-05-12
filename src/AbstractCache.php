<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

abstract class AbstractCache
{
    /**
     * The zone name this field belongs to
     */
    protected $zone = null;

    /**
     * Expire cached value after given seconds
     */
    protected $expire = null;

    /**
     * @type Backend\AbstractBackend
     */
    protected $backend = null;

    /**
     * Restored value from the cache backend
     *
     * @type CachedValue
     */
    protected $cached_value = null;

    /**
     * The cache key
     */
    protected $key = null;

    function __construct($config)
    {
        if (empty($config['zone'])) {
            $this->zone = 'default';
        } else {
            $this->zone = $config['zone'];
        }

        $this->backend = $config['backend'] ?? null;

        if (!empty($config['expire'])) {
            $this->expire = intval($config['expire']);
        }
    }

    /**
     * Get the raw cached out of the CachedValue container
     */
    function get_cached_data()
    {
        if (!$this->has_hit()) {
            throw new \Error(
                'No cached value available. Check first with "FieldCache#has_hit()"'
            );
        }

        return $this->cached_value->get_data();
    }

    /**
     * Get the cache key
     */
    function get_cache_key(): string
    {
        if (null === $this->key) {
            throw new \Error(
                'Cache key not generated yet. FieldCache#get_cache_key() can be called only after the graphql_pre_resolve_field filter'
            );
        }

        return $this->key;
    }

    /**
     * Read data from the cache backend but discard immediately it if has been expired
     */
    function read_cache()
    {
        $this->cached_value = $this->backend->get(
            $this->zone,
            $this->get_cache_key()
        );

        if ($this->cached_value && $this->has_expired()) {
            Utils::log('EXPIRED ' . $this->get_cache_key());
            $this->delete();
        }
    }

    /**
     * Delete the current key from the cache
     */
    function delete()
    {
        $this->cached_value = null;
        $this->backend->delete($this->zone, $this->get_cache_key());
    }

    /**
     * Clear the used zone from the backend
     */
    function clear_zone()
    {
        $this->cached_value = null;
        $this->backend->clear_zone($this->zone);
    }

    /**
     * Check if the value has been expired
     */
    function has_expired(): bool
    {
        if (empty($this->expire)) {
            return false;
        }

        $age = microtime(true) - $this->cached_value->get_created();
        $max_age = $this->expire;
        return $age > $max_age;
    }

    /**
     * Get the backend instance
     */
    function get_backend(): Backend\AbstractBackend
    {
        return $this->backend;
    }

    /**
     * Retrns true when the field cache has warm cache hit
     */
    function has_hit(): bool
    {
        return $this->cached_value instanceof CachedValue;
    }
}
