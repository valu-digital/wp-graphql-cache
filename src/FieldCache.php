<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

class FieldCache
{
    protected $zone = null;
    protected $query_name = null;
    protected $field_name = null;
    protected $expire = null;
    protected $backend = null;

    /**
     * @type CachedValue
     */
    protected $cached_value = null;
    protected $match = false;
    protected $key = null;

    function __construct($config)
    {
        $this->zone = $config['zone'];
        $this->query_name = $config['query_name'];
        $this->field_name = $config['field_name'];
        $this->backend = $config['backend'];

        $this->expire = $config['expire'] ?? null;
    }

    function activate()
    {
        add_filter(
            'graphql_pre_resolve_field',
            [$this, '__filter_graphql_pre_resolve_field'],
            10,
            9
        );
        add_action(
            'graphql_request_results',
            [$this, '__filter_graphql_return_response'],
            // Use large value as this should be the last response filter
            // because we want to save the last version of the response to the
            // cache.
            1000,
            5
        );
    }

    function get_backend(): Backend\AbstractBackend
    {
        return $this->backend;
    }

    function has_hit()
    {
        return $this->cached_value instanceof CachedValue;
    }

    function get_value()
    {
        if (!$this->has_hit()) {
            throw new \Error(
                'No cached value available. Check first with "has_hit()"'
            );
        }
        return $this->cached_value->data;
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
        // XXX Check query name

        // XXX only check root field
        if ($this->field_name !== $field_key) {
            return $nil;
        }

        $this->match = true;

        // XXX Add hash of  args
        $this->key = "graphql-field:$this->query_name:$this->field_name";

        // Read value from cache
        $this->cached_value = $this->backend->get($this->zone, $this->key);

        // Completely skip resolving this field if we got cache hit.
        // We'll use the actual cached value in the graphql_return_response filter.
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
        if (!$this->match) {
            return $response;
        }

        // Cache miss. Write the field to the cache.
        if (!$this->has_hit()) {
            $this->backend->set(
                $this->zone,
                $this->key,
                $response->data[$this->field_name],
                $this->expire
            );
            return $response;
        }

        // Cache hit. Restore value from the cache to the skipped field
        $response->data[$this->field_name] = $this->get_value();

        return $response;
    }
}
