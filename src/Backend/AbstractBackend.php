<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache\Backend;

use WPGraphQL\Extensions\Cache\CachedValue;

abstract class AbstractBackend
{
    abstract public function set(
        string $zone,
        string $key,
        $data,
        $expire = null
    ): void;

    abstract public function get(string $zone, string $key): ?CachedValue;

    abstract public function delete(string $zone, string $key): boolean;

    abstract public function delete_zone(string $zone): boolean;
}
