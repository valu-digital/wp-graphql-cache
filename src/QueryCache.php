<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

use WPGraphQL\Extensions\Cache\Backend\AbstractBackend;

/**
 * Class that takes care of caching of full queries
 */
class QueryCache extends AbstractCache
{
    /**
     * GraphQL Query name this cache should match against
     */
    protected $query_name = null;

    /**
     * True when running againts matched query name
     */
    protected $match = false;

    function __construct($config)
    {
        parent::__construct($config);
        $this->query_name = $config['query_name'];
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
            4
        );

        add_action(
            'graphql_process_http_request_response',
            [$this, '__action_graphql_process_http_request_response'],
            // Use large value as this should be the last response filter
            // because we want to save the last version of the response to the
            // cache.
            1000,
            5
        );

        add_action('graphql_response_set_headers', [
            $this,
            '__action_graphql_response_set_headers',
        ]);
    }

    function __action_do_graphql_request(
        string $query,
        $operation,
        $variables,
        $params
    ) {
        $user_id = get_current_user_id();

        $args_hash = empty($variables)
            ? 'null'
            : Utils::hash(Utils::stable_string($variables));

        $query_hash = Utils::hash($query);

        $this->key = "query-{$this->query_name}-${user_id}-{$query_hash}-${args_hash}";

        $this->read_cache();

        if ($this->has_hit()) {
            // Respond from the cache as early as possible to avoid graphql
            // query parsing etc.
            Utils::log('HIT query cache');
            $this->respond_and_exit();
        }

        $current_query_name = Utils::get_query_name($query);

        // If wildcard is passed just mark the cache as matched
        if ($this->query_name === '*') {
            $this->match = true;
            return;
        }

        // Otherwise check it matches with registered query name
        $this->match = $this->query_name === $current_query_name;
    }

    function __action_graphql_response_set_headers()
    {
        if (!$this->has_match()) {
            return;
        }

        // Just add MISS header if we have match and have not already exited
        // with the cached response. respond_and_exit() handles the HIT header
        header('x-graphql-query-cache: MISS');
    }

    function __action_graphql_process_http_request_response(
        $response,
        $result,
        $operation_name,
        $query,
        $variables
    ) {
        if (!$this->has_match()) {
            return;
        }

        if (!empty($response->errors)) {
            return;
        }

        // Save results as pre encoded json
        $this->backend->set(
            $this->zone,
            $this->get_cache_key(),
            new CachedValue(wp_json_encode($response)),
            $this->expire
        );
        Utils::log('Writing QueryCache ' . $this->key);
    }

    function respond_and_exit()
    {
        header(
            'Content-Type: application/json; charset=' .
                get_option('blog_charset')
        );
        header('x-graphql-query-cache: HIT');

        do_action('graphql_cache_early_response');

        // We stored the encoded JSON string so we can just respond with it here
        echo $this->get_cached_data();
        die();
    }

    /**
     * Returns true when query should be cached
     */
    function has_match(): bool
    {
        return $this->match;
    }
}
