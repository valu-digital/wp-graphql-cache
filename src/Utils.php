<?php

namespace WPGraphQL\Extensions\Cache;

class Utils
{
    /**
     * Sanitize GraphQL query and field names to be safe for file system and
     * headers
     */
    static function sanitize(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', $name);
    }

    /**
     * Generate stable string of graphql query variables which can be used to
     * generate stable cache keys
     */
    static function stable_string(array $variables): string
    {
        // XXX Not good enough. This cares about the key order.
        return serialize($variables);
    }

    static function hash(string $string): string
    {
        return sha1($string);
    }

    static function log(string $msg, $data = null)
    {
        if (null !== $data) {
            $msg .= ' ' . print_r($data, true);
        }

        error_log("wpgql-cache: $msg");
    }
}
