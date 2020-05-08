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

        // First intialize with an empty file which cannot be read by anyone
        // else
        touch($full_path);
        chmod($full_path, 0600);

        file_put_contents($full_path, $contents, LOCK_EX);
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
        if (empty($contents)) {
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
        $dir = $this->base_directory . "/$zone";
        $deleted_something = self::rmdir_r($dir);

        if (is_dir($dir)) {
            rmdir($dir);
            $deleted_something = true;
        }

        return $deleted_something;
    }

    function clear(): bool
    {
        return self::rmdir_r($this->base_directory);
    }

    /**
     * Recursively delete contents of a directory leaving the directory itself
     */
    static function rmdir_r($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $deleted_something = false;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dir,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $deleted_something = true;
            $todo($fileinfo->getRealPath());
        }

        return $deleted_something;
    }
}
