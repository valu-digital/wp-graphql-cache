<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

class CacheManager
{
    static $configs = [];

    static function init()
    {
        error_log('boo');
    }

    static function register_graphql_field_cache($config)
    {
        self::$configs = $config;
    }
}
