<?php

use WPGraphQL\Extensions\Cache\CacheManager;

if (!defined('WPTT_INSTALL')) {
    return;
}

add_action('plugins_loaded', function () {
    if (isset($_GET['test_query_cache_config'])) {
        $config = json_decode($_GET['test_query_cache_config'], true);
        error_log('Registering query cache with ' . print_r($config, true));
        CacheManager::register_graphql_query_cache($config);
    }

    if (isset($_GET['test_query_field_config'])) {
        $config = json_decode($_GET['test_field_cache_config'], true);
        error_log('Registering field cache with ' . print_r($config, true));
        CacheManager::register_graphql_field_cache($config);
    }
});
