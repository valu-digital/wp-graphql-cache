<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

/**
 * Very simple performance measurement tool that adds a "x-graphql-duration"
 * header with duration of the graphql query as the value in milliseconds
 */
class MeasurePerformance
{
    static $started = null;
    static $duration = null;

    static function init()
    {
        if (!\is_graphql_http_request()) {
            return;
        }

        add_action(
            'do_graphql_request',
            [self::class, '__action_do_graphql_request'],
            -10000
        );

        add_action(
            'graphql_return_response',
            [self::class, '__action_graphql_return_response'],
            10000
        );

        add_action(
            'graphql_cache_early_response',
            [self::class, '__action_graphql_cache_early_response'],
            10000
        );

        add_action('graphql_response_set_headers', [
            self::class,
            '__action_graphql_response_set_headers',
        ]);
    }

    static function __action_do_graphql_request()
    {
        self::$started = microtime(true);
    }

    static function __action_graphql_return_response()
    {
        self::end();
    }

    static function __action_graphql_cache_early_response()
    {
        self::end();
        self::send_headers();
    }

    static function __action_graphql_response_set_headers()
    {
        self::send_headers();
    }

    static function end()
    {
        self::$duration = microtime(true) - self::$started;
    }

    static function send_headers()
    {
        header('x-graphql-duration: ' . round(self::$duration * 1000) . 'ms');
    }
}
