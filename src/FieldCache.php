<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

use WPGraphQL\Extensions\Cache\Backend\AbstractBackend;

/**
 * Class that takes care of caching individual field per graphql request
 */
class FieldCache extends AbstractCache
{
    /**
     * GraphQL Query name this cache should match against
     */
    protected $query_name = null;

    /**
     * Root field name to match on
     */
    protected $field_name = null;

    /**
     * True when running againts matched field
     */
    protected $match = false;

    /**
     * The current graphql query as string
     */
    protected $query = null;

    function __construct($config)
    {
        parent::__construct($config);
        $this->query_name = $config['query_name'];
        $this->field_name = $config['field_name'];
    }

    /**
     * Activate the cache with the given backend if the cache did not have own
     * custom backend.
     */
    function activate(AbstractBackend $backend)
    {
        if (!$this->backend) {
            $this->backend = $backend;
        }

        add_action(
            'do_graphql_request',
            [$this, '__action_do_graphql_request'],
            10,
            2
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

    function __action_do_graphql_request(string $query, $operation_name)
    {
        // Capture from operation name if available
        if ($operation_name === $this->query_name) {
            $this->query = $query;
            return;
        }

        $current_query_name = Utils::get_query_name($query);
        if ($current_query_name === $this->query_name) {
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

        $this->key = "field-{$query_name}-${field_name}-${user_id}-{$query_hash}-${args_hash}";

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
        if (!\is_graphql_http_request()) {
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
            // The response is array when called from PHP using the graphql() function
            if (isset($response['errors'])) {
                // Do not cache if there were errors
                return;
            }
            if (!isset($response['data'][$this->field_name])) {
                return;
            }
            $data = $response['data'][$this->field_name];
        } else {
            if (!empty($response->errors)) {
                return;
            }

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
}
