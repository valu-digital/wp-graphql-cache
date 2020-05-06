<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache\Backend;

use WPGraphQL\Extensions\Cache\CachedValue;

class FileSystem extends AbstractBackend
{
    protected $base_directory = null;

    function __construct($config = [])
    {
        $this->base_directory =
            $config['base_directory'] ?? '/tmp/wp-graphql-cache';
    }

    protected function get_path(string $zone, string $key): string
    {
        return "{$this->base_directory}/$zone/$key";
    }

    protected function write_file(string $zone, string $key, string $contents)
    {
        $full_path = $this->get_path($zone, $key);
        $dir = dirname($full_path);

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($full_path, $contents, LOCK_EX);

        chmod($full_path, 0600);
    }

    protected function read_file(string $zone, string $key): ?string
    {
        $full_path = $this->get_path($zone, $key);

        // it is cool to not exists
        $contents = @file_get_contents($full_path);

        if (false === $contents) {
            return null;
        }

        return $contents;
    }

    function set(
        string $zone,
        string $key,
        CachedValue $cached_value,
        $expire = null
    ): void {
        $contents = serialize($cached_value);
        $this->write_file($zone, $key, $contents);
    }

    function get(string $zone, string $key): ?CachedValue
    {
        $contents = $this->read_file($zone, $key);
        if (null === $contents) {
            return null;
        }

        $cached_value = unserialize($contents);

        if ($cached_value instanceof CachedValue) {
            return $cached_value;
        }

        return null;
    }

    function delete(string $zone, string $key): bool
    {
        $full_path = $this->get_path($zone, $key);
        return @unlink($full_path);
    }

    function clear_zone(string $zone): bool
    {
        // XXX
        return false;
    }

    function clear(): bool
    {
        // XXX
        return false;
    }
}
