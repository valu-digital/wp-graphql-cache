<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

/**
 * Value container for cached values to distinguish them from null and false.
 */
class Value
{
    public $value = null;

    function __construct($value)
    {
        $this->value = $value;
    }
}
