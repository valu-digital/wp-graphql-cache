<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

class FieldContext
{
    protected $zone = null;
    protected $query_name = null;
    protected $field_name = null;
    protected $expire = null;
    protected $backend = null;

    protected $hit = false;
    protected $match = false;
    protected $key = null;

    function __construct($config)
    {
        $this->zone = $config['zone'] ?? null;
        $this->expire = $config['expire'] ?? null;

        $this->query_name = $config['query_name'];
        $this->field_name = $config['field_name'];
    }

    function activate()
    {
        add_filter(
            'pre_graphql_resolve_field',
            [$this, '__filter_pre_graphql_resolve_field'],
            10,
            9
        );
        add_action(
            'graphql_return_response',
            [$this, '__action_graphql_return_response'],
            10,
            6
        );
    }

    function __filter_pre_graphql_resolve_field(
        $result,
        $source,
        $args,
        $context,
        $info,
        $type_name,
        $field_key,
        $field,
        $field_resolver
    ) {
        // Check query name

        if ($this->field_name !== $field_key) {
            return;
        }

        $this->match = true;

        // XXX Add hash of  args
        $this->key = "graphql-field:$this->query_name:$this->field_name";

        $cached = get_transient($this->key);

        if (false !== $cached) {
            $this->hit = true;
            return $cached;
        }
    }

    function __action_graphql_return_response(
        $filtered_response,
        $response,
        $schema,
        $operation,
        $query,
        $variables
    ) {
        if ($this->hit) {
            return;
        }

        if (!$this->match) {
            return;
        }

        $data = $filtered_response->data[$this->fielName];

        set_transient($this->key, $data, $this->zone);
    }
}
