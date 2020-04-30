<?php


class FieldCache {
	protected $group = null;
	protected $queryName = null;
	protected $fieldName = null;
	protected $expire = null;

	protected $hit = false;
	protected $match = false;
	protected $key = null;

	function __construct( $config ) {
		$this->group = $config['group'] ?? null;
		$this->expire = $config['expire'] ?? null;

		$this->queryName = $config['queryName'];
		$this->fieldName = $config['fieldName'];
	}

	function activate() {
		add_filter('pre_graphql_resolve_field', [$this, '__filter_pre_graphql_resolve_field'], 10, 9);
		add_action('graphql_return_response', [$this, '__action_graphql_return_response'], 10, 6);
	}

	function __filter_pre_graphql_resolve_field($result, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver) {
		// Check query name

		if ($this->fieldName !== $field_key) {
			return;
		}

		$this->match = true;

		// XXX Add hash of  args
		$this->key = "graphql-field:$this->queryName:$this->fieldName";

		$cached = get_transient($this->key);

		if (false !== $cached) {
			$this->hit = true;
			return $cached;
		}

	}

	function __action_graphql_return_response($filtered_response, $response, $schema, $operation, $query, $variables) {
		if ($this->hit) {
			return;
		}

		if (!$this->match) {
			return;
		}

		$data = $filtered_response->data[ $this->fielName ];

		set_transient($this->key, $data, $this->group);
	}

}


class Cache {

	static $configs = [];

	static function register_graphql_field_cache( $config ) {
		self::$configs = $config;
	}



}

Cache::register_graphql_field_cache( [
	'group' => 'ding',
	'queryName' => 'Dongs',
	'fieldName' => 'menuItems',
	'expire' => 60 * 5,
] );
