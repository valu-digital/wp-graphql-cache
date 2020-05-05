<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache\Backend;

abstract class AbstractBackend
{
    abstract public function set(
        string $zone,
        string $key,
        $data,
        $expire = null
    ): void;

    abstract public function get(string $zone, string $key, $data): ?Value;

    abstract public function delete(string $zone, string $key): boolean;

    abstract public function delete_zone(string $zone): boolean;
}
