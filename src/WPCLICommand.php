<?php

declare(strict_types=1);

namespace WPGraphQL\Extensions\Cache;

class WPCLICommand
{
    static function init()
    {
        \WP_CLI::add_command('graphql-cache', self::class);
    }

    /**
     * Clears cache
     *
     * ## OPTIONS
     *
     * [--zone=<type>]
     * : Clear the given zone only
     *
     * ## EXAMPLES
     *
     *     wp graphql-cache clear --zone menus
     *
     */
    public function clear($args, $assoc_args)
    {
        if (isset($assoc_args['zone'])) {
            $zone = $assoc_args['zone'];
            CacheManager::clear_zone($zone);
            \WP_CLI::success("Cleared WPGraphQL Cache Zone '$zone'");
        } else {
            CacheManager::clear();
            \WP_CLI::success('All WPGraphQL Cache Zones cleared');
        }
    }
}
