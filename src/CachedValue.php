<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

/**
 * Value container for cached values to distinguish them from null and false.
 *
 * Also stores the creation time for PHP based expiration checks.
 */
class CachedValue
{
    /**
     * The raw cached data
     */
    private $data = null;

    /**
     * Time in micro seconds when this cache entry was created
     */
    private $created = null;

    function __construct($data)
    {
        $this->data = $data;
        $this->created = microtime(true);
    }

    /**
     * Get the raw data
     */
    function get_data()
    {
        return $this->data;
    }

    /**
     * Return creation time in micro seconds
     */
    function get_created()
    {
        return $this->created;
    }
}
