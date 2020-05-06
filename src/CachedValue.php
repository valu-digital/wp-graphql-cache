<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

/**
 * Value container for cached values to distinguish them from null and false.
 */
class CachedValue
{
    public $data = null;

    function __construct($data)
    {
        $this->data = $data;
    }
}
