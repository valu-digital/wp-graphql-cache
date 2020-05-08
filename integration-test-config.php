<?php

use WPGraphQL\Extensions\Cache\CacheManager;

CacheManager::register_graphql_field_cache([
    'zone' => 'functional_test',
    'query_name' => 'getPosts',
    'field_name' => 'post',
]);
