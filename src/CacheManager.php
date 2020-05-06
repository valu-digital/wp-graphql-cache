<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

class CacheManager
{
    static $fields = [];

    static $backend = null;

    static function init()
    {
        error_log('boo');

        self::$backend = new Backend\FileSystem();
    }

    static function register_graphql_field_cache($config)
    {
        if (empty($config['backend'])) {
            $config['backend'] = self::$backend;
        }

        $field = new FieldCache($config);
        $field->activate();
        self::$fields[] = $field;
        return $field;
    }
}
