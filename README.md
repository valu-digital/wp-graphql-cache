WARNING: This is very much incomplete and at pre-ALPHA stage. Proceed with
caution.

# WPGraphQL Cache

Flexible caching framework for WPGraphQL

## Installation

TODO

## Field Level Caching

Lets say you have a big query fetching various things where most of them are
reasonably fast but one of the is too slow. You can target that individual
field with `register_graphql_field_cache()`.

```php
use WPGraphQL\Extensions\Cache\CacheManager;

CacheManager::register_graphql_field_cache([
    'zone' => 'menus',
    'query_name' => 'MyBigQuery',
    'field_name' => 'menuItems',
    'expire' => 120, // sec
]);
```

This will start caching the `menuItems` root query on a GraphQL query named
`MyBigQuery` for 120 seconds.

The `zone` is a caching zone the cache will be stored to. This is to have the
ability to manually clear the cache before it expires. Zones are needed
because the cached values are written to multiple cache keys.

Multiple cache keys are used because these things can change between
queries:

-   GraphQL variables
-   Current user id
-   The actual GraphQL query content

This also means that caches are not shared between different versions of the
queries or between users.

The zone can be cleared with `CacheManager::clear_zone()`

```php
/**
 * Clear the zone 'menus' where the 'menuItems' is cached when the menus are
 * updated
 */
add_action('wp_update_nav_menu', function () {
    CacheManager::clear_zone('menus');
});
```

You can also share the same zone between multiple caches.

## Full Query Caching

TODO

## Storage Backends

There are couple storage backends availables which can be configured using
the `graphql_cache_backend` filter.

```php
add_filter('graphql_cache_backend', function () {
    return new \WPGraphQL\Extensions\Cache\FileSystem('/custom/path');
});
```

### FileSystem

This is the default backend which writes the cache to
`/tmp/wp-graphql-cache`. It not super fast but it can perform reasonably when
backed by a RAM disk.

### OPCache

TODO

### Custom Backends

A custom backend can be also returned as long as it extends from
`\WPGraphQL\Extensions\Cache\Backend\AbstractBackend`.
