WARNING: This is an ALPHA release and not feature complete yet.

# WPGraphQL Cache

Flexible caching framework for WPGraphQL v0.9.0 or later

## Installation

    composer require valu/wp-graphql-cache

Or you can clone it from Github to your plugins using the stable branch

    cd wp-content/plugins
    git clone --branch stable https://github.com/valu-digital/wp-graphql-cache.git

## Query Caching

If you want to just start caching all your queries for a given period of time
you can just add this to your theme's `functions.php` or to a mu-plugin:

```php
use WPGraphQL\Extensions\Cache\CacheManager;

CacheManager::register_graphql_query_cache([
    'query_name' => '*',
    'expire' => 120, // sec
]);
```

or you can target specific queries

```php
use WPGraphQL\Extensions\Cache\CacheManager;

CacheManager::register_graphql_query_cache([
    'query_name' => 'MySlowQuery',
    'expire' => 120,
]);
```

## Field Caching

Lets say you have a big query fetching various things where most of them are
reasonably fast but one of the is too slow. You can target that individual
root field with `register_graphql_field_cache()`.

```php
use WPGraphQL\Extensions\Cache\CacheManager;

CacheManager::register_graphql_field_cache([
    'query_name' => 'MyBigQuery',
    'field_name' => 'menuItems',
    'expire' => 120, // sec
]);
```

This will start caching the `menuItems` root field on a GraphQL query named
`MyBigQuery` for 120 seconds.

## Cache Control with Zones

You can clear all GraphQL caches with `CacheManager::clear()` but if you want
to be more specific with cache clearing you must pass in a `zone` property to
`register_graphql_query_cache` and `register_graphql_field_cache` and you can
clear that zone with `CacheManager::clear_zone($zone)`.

The `zone` is a caching zone the cache will be stored to. Zones are needed
because the cached responses are written to multiple cache keys because graphql
variables and the current user can change between calls to the same query

The zone can be cleared with `CacheManager::clear_zone()`

```php
/**
 * Register cache to a 'menus' zone
 */
CacheManager::register_graphql_field_cache([
    'zone' => 'menus', // 👈
    'query_name' => 'MyBigQuery',
    'field_name' => 'menuItems',
    'expire' => 120, // sec
]);

/**
 * Clear the zone 'menus' when the menus are updated
 */
add_action('wp_update_nav_menu', function () {
    CacheManager::clear_zone('menus');
});
```

You can also share the same zone between multiple caches.

### WP CLI

The zones can be cleared using the WP CLI too

```
$ wp graphql-cache clear # clear all zones
$ wp graphql-cache clear --zone=menus
```

## Measuring Performance

WPGraphQL Cache comes with very simple build query performance tool which
adds a `x-graphql-duration` header to the `/graphql` responses. It contains
the duration of the actual GraphQL response **resolving** in milliseconds.
When no caches are hit this is the theoretical maximun this plugin can take
of from the response times. Everything else is spend in setting up WP and
WPGraphQL itself before the GraphQL resolver execution.

If you want to go beyond that you can enable GET requests with Persisted
Queries in the [WPGraphQL Lock][] plugin and cache the whole response in your
edge server (nginx, varnish, CDN etc.). This will be the absolute best
performing cache because the PHP interpreter is not invoked at all on cache
hit.

[wpgraphql lock]: https://github.com/valu-digital/wp-graphql-lock

## Storage Backends

There are couple storage backends availables which can be configured using
the `graphql_cache_backend` filter.

```php
use WPGraphQL\Extensions\Cache\Backend\FileSystem;

add_filter('graphql_cache_backend', function () {
    return new FileSystem('/custom/path');
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
