<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

class CacheManager
{
    static $fields = [];

    static $backend = null;

    static function init()
    {
        self::$backend = apply_filters(
            'graphql_cache_backend',
            new Backend\FileSystem()
        );
    }

    static function register_graphql_field_cache($config)
    {
        if (empty($config['backend'])) {
            $config['backend'] = self::$backend;
        }

        $field = new FieldCache($config);
        self::$fields[] = $field;

        $is_active = apply_filters('graphql_cache_active', true);

        if ($is_active) {
            $field->activate();
        }

        return $field;
    }

    function clear_zone(string $zone): bool
    {
        return self::$backend->clear_zone($zone);
    }

    function clear(): bool
    {
        return self::$backend->clear();
    }
}

function register_graphql_field_cache($config)
{
    CacheManager::register_graphql_field_cache($config);
}
