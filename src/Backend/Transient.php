<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache\Backend;

use WPGraphQL\Extensions\Cache\CachedValue;

/**
 * WPGraphQL Cache transients backend.
 *
 * By default WordPress will store transients as options in the database. However when an object-cache
 * is installed this will be stored in the object cache backend. This is the most flexible cache storage
 * backend in WordPress as the true backend storage can be configured at a platform level.
 *
 * For example a single server environment might use a APC object cache where as a HA environment might
 * have a Memcached or Redis backend.
 */
class Transient extends AbstractBackend
{

	protected $cached_value;

	function set(string $zone, string $key, CachedValue $cached_value, $expire = null): void
	{

		$cached_value_serialized = serialize($cached_value);

		$transient_key = $this->get_transient_key($zone, $key);

		set_transient($transient_key, $cached_value_serialized, $expire);

		$this->add_zone($zone);
		$this->add_key_to_zone($zone, $key);

	}

	function get(string $zone, string $key): ?CachedValue
    {

		$transient_key = $this->get_transient_key($zone, $key);

		$cached_value_serialized = get_transient($transient_key);
		if ( $cached_value_serialized ) {

			$cached_value = unserialize($cached_value_serialized);
			if ( $cached_value ) {

				$this->cached_value = $cached_value;
			}
		}

		return $this->cached_value;

	}

	function delete(string $zone, string $cache_key): bool
    {

		$transient_key = $this->get_transient_key($zone, $cache_key);

		return delete_transient( $transient_key );

	}

	function clear_zone(string $zone): bool
    {

		$deleted = false;

		$zone_cache_keys = $this->get_keys_in_zone($zone);
		foreach ($zone_cache_keys as $cache_key) {
			$transient_key = $this->get_transient_key($zone, $cache_key);
			$deleted = $deleted || $this->delete($zone, $transient_key);
		}

		return $deleted;

	}

	function clear(): bool
    {
		
		$deleted = false;

		$zones = $this->get_zones();
		foreach ($zones as $zone) {
			if ( $zone && is_string($zone) ) {
				$deleted = $deleted || $this->clear_zone($zone);
			}
		}

		return $deleted;

	}

	/**
	 * Add the given zone to the list of known zones.
	 *
	 * @param string $zone The cache zone.
	 */
	protected function add_zone(string $zone): void
	{

		$zone_list_transient_key = $this->get_zone_list_transient_key($zone);

		// Add the cache key to the list of known cache keys for the zone.
		$zones = $this->get_zones($zone);
		$zones[] = $zone;
		$zones = array_unique( $zones );

		// Store the updated list of zone cache keys.
		set_transient($zone_list_transient_key, $zones);

	}

	/**
	 * Get all known cache zones.
	 *
	 * @return array
	 */
	protected function get_zones(): array
	{

		$zone_list_transient_key = $this->get_zone_list_transient_key($zone);

		$zone_list_transient_value = get_transient($zone_list_transient_key);
		if (!$zone_list_transient_value || !is_array($zone_list_transient_value)) {
			$zone_list_transient_value = array();
		}

		return $zone_list_transient_value;

	}

	/**
	 * Add the given cache key to the list of known cache keys associated with the given cache zone.
	 *
	 * @param string $zone The cache zone.
	 * @param string $cache_key The cache key.
	 */
	protected function add_key_to_zone(string $zone, string $cache_key): void
	{

		$zone_transient_key = $this->get_zone_transient_key($zone);

		// Add the cache key to the list of known cache keys for the zone.
		$zone_keys = $this->get_keys_in_zone($zone);
		$zone_keys[] = $cache_key;
		$zone_keys = array_unique( $zone_keys );

		// Store the updated list of zone cache keys.
		set_transient($zone_transient_key, $zone_keys);

	}

	/**
	 * Get all cache keys known to be associated with the given cache zone.
	 *
	 * @param string $zone The cache zone.
	 * @return array
	 */
	protected function get_keys_in_zone(string $zone): array
	{

		$zone_transient_key = $this->get_zone_transient_key($zone);

		$zone_transient_value = get_transient($zone_transient_key);
		if (!$zone_transient_value || !is_array($zone_transient_value)) {
			$zone_transient_value = array();
		}

		return $zone_transient_value;

	}

	/**
	 * Get the transient key used to store all known zones.
	 * I.e. via the use of add_zone().
	 *
	 * @return string
	 */
	protected function get_zone_list_transient_key(): string
	{

		return 'wp-graphql-zones';

	}

	/**
	 * Translate the given zone name to the transient key used to store the cache keys associated with it.
	 * I.e. via the use of add_key_to_zone().
	 *
	 * @param string $zone The cache zone.
	 * @return string
	 */
	protected function get_zone_transient_key(string $zone): string
	{

		$zone_transient_key = sprintf(
			'wp-graphql-zone-%s',
			md5($zone)
		);

		return $zone_transient_key;

	}

	/**
	 * Translate the zone & cache key to the transient key we use to store the cached value.
	 *
	 * @param string $zone The cache zone.
	 * @param string $cache_key The cache key.
	 * @return string
	 */
	protected function get_transient_key(string $zone, string $cache_key): string
	{

		// Hash the real cache key to ensure the transient key will always be below the limit of 172 characters.
		$transient_key = sprintf(
			'wp-graphql-cache-%s',
			md5($zone),
			md5($cache_key)
		);

		return $transient_key;

	}
}
