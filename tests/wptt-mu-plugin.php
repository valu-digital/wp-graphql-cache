<?php

use WPGraphQL\Extensions\Cache\CacheManager;

add_action('plugins_loaded', function () {
    CacheManager::register_graphql_field_cache([
        'zone' => 'functional_test',
        'query_name' => 'getPosts',
        'field_name' => 'post',
    ]);
});
