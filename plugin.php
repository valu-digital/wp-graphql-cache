<?php
/**
 * Plugin Name: WPGraphQL Cache
 * Plugin URI: https://github.com/valu-digital/wp-graphql-cache
 * Description: Add caching for WPGraphQL
 * Author: Esa-Matti Suuronen, Valu Digital Oy
 * Version: 0.1.0
 */

// To make this plugin work properly for both Composer users and non-composer
// users we must detect whether the project is using a global autoloader. We
// can do that by checking whether our autoloadable classes will autoload with
// class_exists(). If not it means there's no global autoloader in place and
// the user is not using composer. In that case we can safely require the
// bundled autoloader code.
if (!\class_exists('\WPGraphQL\Extensions\Cache')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
// This way we can add the vendor/ directory to git and have the plugin "just
// work" when it is cloned to wp-content/plugins. But be careful when checking
// the vendor/ into git so you won't add all development dependencies too. Eg.
// before checking it in you should always run "composer install --no-dev" first.

\WPGraphQL\Extensions\Cache\CacheManager::init();
