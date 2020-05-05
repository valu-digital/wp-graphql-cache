<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache\Backend;

use WPGraphQL\Extensions\Cache\Value;

class FileSystem extends AbstractBackend
{
    protected $base_directory = null;

    function __construct($config)
    {
        $this->base_directory =
            $config['base_directory'] ?? '/tmp/wp-graphql-cache';
    }

    protected function get_path(string $zone, string $key): string
    {
        return "{$this->base_directory}/$zone/$key";
    }

    function set(string $zone, string $key, $data, $expire = null)
    {
        $full_path = $this->get_path($zone, $key);
        mkdir(dirname($full_path), 0700, true);
        file_put_contents($full_path, serialize($data), LOCK_EX);
        chmod($full_path, 0600);
    }

    function get(string $zone, string $key, $data): ?Value
    {
        $full_path = $this->get_path($zone, $key);

        // it is cool to not exists
        $data = @file_get_contents($full_path);

        if (false === $data) {
            return null;
        }

        return new Value(unserialize($data));
    }

    function delete(string $zone, string $key): boolean
    {
        return false;
    }

    function delete_zone(string $zone): boolean
    {
        return false;
    }
}
