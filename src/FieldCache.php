<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

/**
 * Class that takes care of caching individual field per graphql request
 */
class FieldCache
{
    ////////////////////////
    // CONFIG PROPERTIES  //
    ////////////////////////

    /**
     * The zone name this field belongs to
     */
    protected $zone = null;

    /**
     * GraphQL Query name this cache should match against
     */
    protected $query_name = null;

    /**
     * Root field name to match on
     */
    protected $field_name = null;

    /**
     * Expire cached value after given seconds
     */
    protected $expire = null;

    /**
     * @type Backend\AbstractBackend
     */
    protected $backend = null;

    ///////////////////////
    // STATE PROPERTIES  //
    ///////////////////////

    /**
     * Restored value from the cache backend
     *
     * @type CachedValue
     */
    protected $cached_value = null;

    /**
     * True when running againts matched field
     */
    protected $match = false;

    /**
     * The cache key
     */
    protected $key = null;

    /**
     * The current graphql query as string
     */
    protected $query = null;

    function __construct($config)
    {
        $this->zone = $config['zone'];
        $this->query_name = $config['query_name'];
        $this->field_name = $config['field_name'];
        $this->backend = $config['backend'];

        if (!empty($config['expire'])) {
            $this->expire = intval($config['expire']);
        }
    }

    function activate()
    {
        add_action(
            'do_graphql_request',
            [$this, '__action_do_graphql_request'],
            2,
            10
        );

        add_filter(
            'graphql_pre_resolve_field',
            [$this, '__filter_graphql_pre_resolve_field'],
            10,
            9
        );

        add_filter(
            'graphql_request_results',
            [$this, '__filter_graphql_return_response'],
            // Use large value as this should be the last response filter
            // because we want to save the last version of the response to the
            // cache.
            1000,
            5
        );
    }

    function __action_do_graphql_request(string $query, $query_name)
    {
        // Capture query if its name matches with configured field cache
        if ($query_name === $this->query_name) {
            $this->query = $query;
        }
    }

    function __filter_graphql_pre_resolve_field(
        $nil,
        $source,
        $args,
        $context,
        $info,
        $type_name,
        $field_key,
        $field,
        $field_resolver
    ) {
        // No query, no query name match
        if (!$this->query) {
            return $nil;
        }

        // Only interested in root queries
        if (count($info->path) !== 1) {
            return $nil;
        }

        // Check it matches with configured field name
        if ($this->field_name !== $info->path[0]) {
            return $nil;
        }

        // Mark as mached ie. this field should be cached
        $this->match = true;

        $query_name = Utils::sanitize($this->query_name);
        $field_name = Utils::sanitize($this->field_name);
        $args_hash = Utils::hash(Utils::stable_string($args));
        $query_hash = Utils::hash($this->query);
        $user_id = get_current_user_id();

        $this->key = "{$query_name}-${field_name}-${user_id}-{$query_hash}-${args_hash}";

        $this->read_cache();

        // Completely skip resolving this field if we got cache hit. We'll use
        // the actual cached value in the graphql_return_response filter later.
        if ($this->has_hit()) {
            return null;
        }

        // Cache miss. Let the field to resolve normally
        return $nil;
    }

    function __filter_graphql_return_response(
        $response,
        $schema,
        $operation,
        $query,
        $variables
    ) {
        $response = $this->respond($response);

        // Reset state only if this graphql request via PHP using graphql()
        // because only in that case this instance is reused. For HTTP requests
        // we want to keep the state around so we can set the status response
        // headers
        if (!is_graphql_http_request()) {
            $this->reset_state();
        }
        return $response;
    }

    /**
     * Handle the final graphql reponse object
     */
    function respond($response)
    {
        if (!$this->match) {
            return $response;
        }

        if ($this->has_hit()) {
            Utils::log('HIT ' . $this->get_cache_key());
            return $this->respond_with_cache($response);
        }

        Utils::log('MISS ' . $this->get_cache_key());
        $this->cache_field_from_response($response);

        return $response;
    }

    /**
     * Reset instance state so this instance can be reused with multiple
     * graphql() calls
     */
    function reset_state()
    {
        $this->cached_value = null;
        $this->key = null;
        $this->query = null;
        $this->match = false;
    }

    /**
     * Save the field data from the response the cache
     */
    function cache_field_from_response($response)
    {
        $data = null;

        if (is_array($response)) {
            // The reponse is array when called from PHP using the graphql() function
            if (!isset($response['data'][$this->field_name])) {
                return;
            }
            $data = $response['data'][$this->field_name];
        } else {
            // From HTTP request the respones is an object with data property
            if (!isset($response->data[$this->field_name])) {
                return;
            }
            $data = $response->data[$this->field_name];
        }

        $this->backend->set(
            $this->zone,
            $this->get_cache_key(),
            new CachedValue($data),
            $this->expire
        );
    }

    /**
     * Restore cached data to the field of the response which was skipped
     * during resolving
     */
    function respond_with_cache($response)
    {
        if (is_array($response)) {
            // The reponse is array when called from PHP using the graphql() function
            $response['data'][$this->field_name] = $this->get_cached_data();
        } else {
            // From HTTP request the respones is an object with data property
            $response->data[$this->field_name] = $this->get_cached_data();
        }

        return $response;
    }

    /**
     * Get the backend instance
     */
    function get_backend(): Backend\AbstractBackend
    {
        return $this->backend;
    }

    /**
     * Retrns true when the field cache has warm cache hit
     */
    function has_hit(): bool
    {
        return $this->cached_value instanceof CachedValue;
    }

    /**
     * Returns true when this field should be cached
     */
    function has_match(): bool
    {
        return $this->match;
    }

    /**
     * Return the name of the cached field name
     */
    function get_field_name()
    {
        return $this->field_name;
    }

    /**
     * Get the raw cached out of the CachedValue container
     */
    function get_cached_data()
    {
        if (!$this->has_hit()) {
            throw new \Error(
                'No cached value available. Check first with "FieldCache#has_hit()"'
            );
        }

        return $this->cached_value->get_data();
    }

    /**
     * Get the cache key
     */
    function get_cache_key(): string
    {
        if (null === $this->key) {
            throw new \Error(
                'Cache key not generated yet. FieldCache#get_cache_key() can be called only after the graphql_pre_resolve_field filter'
            );
        }

        return $this->key;
    }

    /**
     * Read data from the cache backend but discard immediately it if has been expired
     */
    function read_cache()
    {
        $this->cached_value = $this->backend->get(
            $this->zone,
            $this->get_cache_key()
        );

        if ($this->cached_value && $this->has_expired()) {
            Utils::log('EXPIRED ' . $this->get_cache_key());
            $this->delete();
        }
    }

    /**
     * Delete the current key from the cache
     */
    function delete()
    {
        $this->cached_value = null;
        $this->backend->delete($this->zone, $this->get_cache_key());
    }

    /**
     * Clear the used zone from the backend
     */
    function clear_zone()
    {
        $this->cached_value = null;
        $this->backend->clear_zone($this->zone);
    }

    /**
     * Check if the value has been expired
     */
    function has_expired(): bool
    {
        if (empty($this->expire)) {
            return false;
        }

        $age = microtime(true) - $this->cached_value->get_created();
        $max_age = $this->expire;
        return $age > $max_age;
    }
}
