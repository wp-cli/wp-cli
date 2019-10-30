<?php
/**
 * A modified version of wp-settings.php, tailored for CLI use.
 *
 * @phpcs:disable WordPress.WP.GlobalVariablesOverride -- Setting the globals is the point of this file.
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- These are WP native constants which are needed.
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- All hook calls in this file are to WP native hooks.
 * @phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Ensuring native WP variables are available.
 */

use WP_CLI\Utils;

/**
 * Stores the location of the WordPress directory of functions, classes, and core content.
 *
 * @since 1.0.0
 */
define( 'WPINC', 'wp-includes' );

/*
 * These can't be directly globalized in version.php. When updating,
 * we're including version.php from another installation and don't want
 * these values to be overridden if already set.
 */
global $wp_version, $wp_db_version, $tinymce_version, $required_php_version, $required_mysql_version, $wp_local_package;
require ABSPATH . WPINC . '/version.php';
require ABSPATH . WPINC . '/load.php';

// Check for the required PHP version and for the MySQL extension or a database drop-in.
wp_check_php_mysql_versions();

// Include files required for initialization.
Utils\maybe_require( '5.2-alpha-44973', ABSPATH . WPINC . '/class-wp-paused-extensions-storage.php' );
Utils\maybe_require( '5.2-alpha-44962', ABSPATH . WPINC . '/class-wp-fatal-error-handler.php' );
Utils\maybe_require( '5.2-alpha-44973', ABSPATH . WPINC . '/class-wp-recovery-mode-cookie-service.php' );
Utils\maybe_require( '5.2-alpha-44973', ABSPATH . WPINC . '/class-wp-recovery-mode-key-service.php' );
Utils\maybe_require( '5.2-alpha-44973', ABSPATH . WPINC . '/class-wp-recovery-mode-link-service.php' );
Utils\maybe_require( '5.2-alpha-44973', ABSPATH . WPINC . '/class-wp-recovery-mode-email-service.php' );
Utils\maybe_require( '5.2-alpha-44973', ABSPATH . WPINC . '/class-wp-recovery-mode.php' );
Utils\maybe_require( '5.2-alpha-44973', ABSPATH . WPINC . '/error-protection.php' );
require ABSPATH . WPINC . '/default-constants.php';
require_once ABSPATH . WPINC . '/plugin.php';

/**
 * If not already configured, `$blog_id` will default to 1 in a single site
 * configuration. In multisite, it will be overridden by default in ms-settings.php.
 *
 * @global int $blog_id
 * @since 2.0.0
 */
global $blog_id;

// Set initial default constants including WP_MEMORY_LIMIT, WP_MAX_MEMORY_LIMIT, WP_DEBUG, SCRIPT_DEBUG, WP_CONTENT_DIR and WP_CACHE.
wp_initial_constants();

// Make sure we register the shutdown handler for fatal errors as soon as possible.
if ( function_exists( 'wp_register_fatal_error_handler' ) ) {
	wp_register_fatal_error_handler();
}

// Disable magic quotes at runtime. Magic quotes are added using wpdb later in wp-settings.php.
// phpcs:disable PHPCompatibility.IniDirectives.RemovedIniDirectives,WordPress.PHP.IniSet.Risky
ini_set( 'magic_quotes_runtime', 0 );
ini_set( 'magic_quotes_sybase', 0 );
// phpc:enable PHPCompatibility.IniDirectives.RemovedIniDirectives,WordPress.PHP.IniSet

// WordPress calculates offsets from UTC.
// phpcs:ignore WordPress.WP.TimezoneChange.timezone_change_date_default_timezone_set
date_default_timezone_set( 'UTC' );

// Turn register_globals off.
wp_unregister_GLOBALS();

// Standardize $_SERVER variables across setups.
wp_fix_server_vars();

// Check if we have received a request due to missing favicon.ico
if ( function_exists( 'wp_favicon_request' ) ) {
	wp_favicon_request();
}

// Check if we're in maintenance mode.
// WP-CLI: run enable_maintenance_mode filter early for compat with < WP 4.6
/**
 * Filters whether to enable maintenance mode.
 *
 * This filter runs before it can be used by plugins. It is designed for
 * non-web runtimes. If this filter returns true, maintenance mode will be
 * active and the request will end. If false, the request will be allowed to
 * continue processing even if maintenance mode should be active.
 *
 * @since 4.6.0
 *
 * @param bool $enable_checks Whether to enable maintenance mode. Default true.
 * @param int  $upgrading     The timestamp set in the .maintenance file.
 */
if ( apply_filters( 'enable_maintenance_mode', true ) ) {
	wp_maintenance();
}

// Start loading timer.
timer_start();

// WP-CLI: run enable_wp_debug_mode_checks filter early for compat with < WP 4.6
/**
 * Filters whether to allow the debug mode check to occur.
 *
 * This filter runs before it can be used by plugins. It is designed for
 * non-web run-times. Returning false causes the `WP_DEBUG` and related
 * constants to not be checked and the default php values for errors
 * will be used unless you take care to update them yourself.
 *
 * @since 4.6.0
 *
 * @param bool $enable_debug_mode Whether to enable debug mode checks to occur. Default true.
 */
if ( apply_filters( 'enable_wp_debug_mode_checks', true ) ) {
	wp_debug_mode();
}

/**
 * Filters whether to enable loading of the advanced-cache.php drop-in.
 *
 * This filter runs before it can be used by plugins. It is designed for non-web
 * run-times. If false is returned, advanced-cache.php will never be loaded.
 *
 * @since 4.6.0
 *
 * @param bool $enable_advanced_cache Whether to enable loading advanced-cache.php (if present).
 *                                    Default true.
 */
if ( WP_CACHE && apply_filters( 'enable_loading_advanced_cache_dropin', true ) && file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
	// For an advanced caching plugin to use. Uses a static drop-in because you would only want one.
	include WP_CONTENT_DIR . '/advanced-cache.php';

	// Re-initialize any hooks added manually by advanced-cache.php
	if ( $wp_filter && class_exists( 'WP_Hook' ) && method_exists( 'WP_Hook', 'build_preinitialized_hooks' ) ) {
		$wp_filter = WP_Hook::build_preinitialized_hooks( $wp_filter );
	}
}

// Define WP_LANG_DIR if not set.
wp_set_lang_dir();

// Load early WordPress files.
require ABSPATH . WPINC . '/compat.php';
Utils\maybe_require( '4.6-alpha-37128', ABSPATH . WPINC . '/class-wp-list-util.php' );
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/meta.php';
require ABSPATH . WPINC . '/functions.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-meta-query.php' );
Utils\maybe_require( '4.6-alpha-38470', ABSPATH . WPINC . '/class-wp-matchesmapregex.php' );
require ABSPATH . WPINC . '/class-wp.php';
require ABSPATH . WPINC . '/class-wp-error.php';
require ABSPATH . WPINC . '/pomo/mo.php';

// Include the wpdb class and, if present, a db.php database drop-in.
require_wp_db();

// WP-CLI: Handle db error ourselves, instead of waiting for dead_db()
global $wpdb;
if ( ! empty( $wpdb->error ) ) {
	wp_die( $wpdb->error );
}

// Set the database table prefix and the format specifiers for database table columns.
$GLOBALS['table_prefix'] = $table_prefix;
wp_set_wpdb_vars();

// Start the WordPress object cache, or an external object cache if the drop-in is present.
wp_start_object_cache();

// Attach the default filters.
require ABSPATH . WPINC . '/default-filters.php';

// Initialize multisite if enabled.
if ( is_multisite() ) {
	Utils\maybe_require( '4.6-alpha-37575', ABSPATH . WPINC . '/class-wp-site-query.php' );
	Utils\maybe_require( '4.6-alpha-37896', ABSPATH . WPINC . '/class-wp-network-query.php' );
	require ABSPATH . WPINC . '/ms-blogs.php';
	require ABSPATH . WPINC . '/ms-settings.php';
} elseif ( ! defined( 'MULTISITE' ) ) {
	define( 'MULTISITE', false );
}

register_shutdown_function( 'shutdown_action_hook' );

// Stop most of WordPress from being loaded if we just want the basics.
if ( SHORTINIT ) {
	return false;
}

// Load the L10n library.
require_once ABSPATH . WPINC . '/l10n.php';
maybe_require( '4.6-alpha-38496', ABSPATH . WPINC . '/class-wp-locale.php' );
maybe_require( '4.6-alpha-38961', ABSPATH . WPINC . '/class-wp-locale-switcher.php' );

// WP-CLI: Permit Utils\wp_not_installed() to run on < WP 4.0
apply_filters( 'nocache_headers', array() );

// Run the installer if WordPress is not installed.
wp_not_installed();

// Load most of WordPress.
require ABSPATH . WPINC . '/class-wp-walker.php';
require ABSPATH . WPINC . '/class-wp-ajax-response.php';
require ABSPATH . WPINC . '/capabilities.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-roles.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-role.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-user.php' );
require ABSPATH . WPINC . '/query.php';
if ( file_exists( ABSPATH . WPINC . '/class-wp-date-query.php' ) ) {
	require ABSPATH . WPINC . '/class-wp-date-query.php';
} else {
	Utils\maybe_require( '3.7-alpha-25139', ABSPATH . WPINC . '/date.php' );
}
require ABSPATH . WPINC . '/theme.php';
require ABSPATH . WPINC . '/class-wp-theme.php';
require ABSPATH . WPINC . '/template.php';
if ( file_exists( ABSPATH . WPINC . '/class-wp-user-request.php' ) ) {
	require ABSPATH . WPINC . '/class-wp-user-request.php';
} else {
	require ABSPATH . WPINC . '/user.php';
}
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-user-query.php' );
if ( file_exists( ABSPATH . WPINC . '/class-wp-session-tokens.php' ) ) {
	require ABSPATH . WPINC . '/class-wp-session-tokens.php';
	require ABSPATH . WPINC . '/class-wp-user-meta-session-tokens.php';
} else {
	Utils\maybe_require( '4.0', ABSPATH . WPINC . '/session.php' );
}
Utils\maybe_require( '4.5-alpha-35776', ABSPATH . WPINC . '/class-wp-metadata-lazyloader.php' );
require ABSPATH . WPINC . '/general-template.php';
require ABSPATH . WPINC . '/link-template.php';
require ABSPATH . WPINC . '/author-template.php';
require ABSPATH . WPINC . '/post.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-walker-page.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-walker-page-dropdown.php' );
Utils\maybe_require( '4.6-alpha-37890', ABSPATH . WPINC . '/class-wp-post-type.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-post.php' );
require ABSPATH . WPINC . '/post-template.php';
Utils\maybe_require( '3.6-alpha-23451', ABSPATH . WPINC . '/revision.php' );
Utils\maybe_require( '3.6-alpha-23451', ABSPATH . WPINC . '/post-formats.php' );
require ABSPATH . WPINC . '/post-thumbnail-template.php';
require ABSPATH . WPINC . '/category.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-walker-category.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-walker-category-dropdown.php' );
require ABSPATH . WPINC . '/category-template.php';
require ABSPATH . WPINC . '/comment.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-comment.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-comment-query.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-walker-comment.php' );
require ABSPATH . WPINC . '/comment-template.php';
require ABSPATH . WPINC . '/rewrite.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-rewrite.php' );
require ABSPATH . WPINC . '/feed.php';
require ABSPATH . WPINC . '/bookmark.php';
require ABSPATH . WPINC . '/bookmark-template.php';
require ABSPATH . WPINC . '/kses.php';
require ABSPATH . WPINC . '/cron.php';
require ABSPATH . WPINC . '/deprecated.php';
require ABSPATH . WPINC . '/script-loader.php';
if ( file_exists( ABSPATH . WPINC . '/class-wp-taxonomy.php' ) ) {
	require ABSPATH . WPINC . '/class-wp-taxonomy.php';
} else {
	require ABSPATH . WPINC . '/taxonomy.php';
}
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-term.php' );
Utils\maybe_require( '4.6-alpha-37575', ABSPATH . WPINC . '/class-wp-term-query.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-tax-query.php' );
require ABSPATH . WPINC . '/update.php';
require ABSPATH . WPINC . '/canonical.php';
require ABSPATH . WPINC . '/shortcodes.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/embed.php' );
require ABSPATH . WPINC . '/class-wp-embed.php';
Utils\require_if_exists( ABSPATH . WPINC . '/class-oembed.php' );
Utils\require_if_exists( ABSPATH . WPINC . '/class-wp-oembed.php' );
Utils\require_if_exists( ABSPATH . WPINC . '/class-wp-oembed-controller.php' );
require ABSPATH . WPINC . '/media.php';
Utils\maybe_require( '4.4-alpha-34903', ABSPATH . WPINC . '/class-wp-oembed-controller.php' );
require ABSPATH . WPINC . '/http.php';
require_once ABSPATH . WPINC . '/class-http.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-streams.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-curl.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-proxy.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-cookie.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-encoding.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-http-response.php' );
Utils\maybe_require( '4.6-alpha-37438', ABSPATH . WPINC . '/class-wp-http-requests-response.php' );
Utils\maybe_require( '4.7-alpha-39212', ABSPATH . WPINC . '/class-wp-http-requests-hooks.php' );
require ABSPATH . WPINC . '/widgets.php';
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-widget.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/class-wp-widget-factory.php' );
require ABSPATH . WPINC . '/nav-menu.php';
require ABSPATH . WPINC . '/nav-menu-template.php';
require ABSPATH . WPINC . '/admin-bar.php';
Utils\maybe_require( '4.4-alpha-34928', ABSPATH . WPINC . '/rest-api.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/rest-api/class-wp-rest-server.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/rest-api/class-wp-rest-response.php' );
Utils\maybe_require( '4.4-beta4-35719', ABSPATH . WPINC . '/rest-api/class-wp-rest-request.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-posts-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-attachments-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-post-types-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-post-statuses-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-revisions-controller.php' );
Utils\maybe_require( '5.0-alpha-44126', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-autosaves-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-taxonomies-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-terms-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-users-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-comments-controller.php' );
Utils\maybe_require( '5.0-alpha-44107', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-search-controller.php' );
Utils\maybe_require( '5.0-alpha-44150', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-blocks-controller.php' );
Utils\maybe_require( '5.0-alpha-44150', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-renderer-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-settings-controller.php' );
Utils\maybe_require( '5.0-alpha-43985', ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-themes-controller.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-meta-fields.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-comment-meta-fields.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-post-meta-fields.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-term-meta-fields.php' );
Utils\maybe_require( '4.7-alpha-38832', ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-user-meta-fields.php' );
Utils\maybe_require( '5.0-alpha-44107', ABSPATH . WPINC . '/rest-api/search/class-wp-rest-search-handler.php' );
Utils\maybe_require( '5.0-alpha-44107', ABSPATH . WPINC . '/rest-api/search/class-wp-rest-post-search-handler.php' );
Utils\maybe_require( '5.0-alpha-44108', ABSPATH . WPINC . '/class-wp-block-type.php' );
Utils\maybe_require( '5.3-alpha-46111', ABSPATH . WPINC . '/class-wp-block-styles-registry.php' );
Utils\maybe_require( '5.0-alpha-44108', ABSPATH . WPINC . '/class-wp-block-type-registry.php' );
Utils\maybe_require( '5.0-alpha-44116', ABSPATH . WPINC . '/class-wp-block-parser.php' );
Utils\maybe_require( '5.0-alpha-44108', ABSPATH . WPINC . '/blocks.php' );
Utils\maybe_require( '5.0-alpha-44118', ABSPATH . WPINC . '/blocks/archives.php' );
Utils\maybe_require( '5.0-alpha-44118', ABSPATH . WPINC . '/blocks/block.php' );
Utils\maybe_require( '5.0-alpha-44808', ABSPATH . WPINC . '/blocks/calendar.php' );
Utils\maybe_require( '5.0-alpha-44118', ABSPATH . WPINC . '/blocks/categories.php' );
Utils\maybe_require( '5.0-alpha-44118', ABSPATH . WPINC . '/blocks/latest-comments.php' );
Utils\maybe_require( '5.0-alpha-44118', ABSPATH . WPINC . '/blocks/latest-posts.php' );
Utils\maybe_require( '5.0-alpha-44808', ABSPATH . WPINC . '/blocks/rss.php' );
Utils\maybe_require( '5.0-alpha-44808', ABSPATH . WPINC . '/blocks/search.php' );
Utils\maybe_require( '5.0-alpha-44118', ABSPATH . WPINC . '/blocks/shortcode.php' );
Utils\maybe_require( '5.0-alpha-44808', ABSPATH . WPINC . '/blocks/tag-cloud.php' );

if ( class_exists( 'WP_Embed' ) && empty( $GLOBALS['wp_embed'] ) ) {
	$GLOBALS['wp_embed'] = new WP_Embed();
}

// Load multisite-specific files.
if ( is_multisite() ) {
	require ABSPATH . WPINC . '/ms-functions.php';
	require ABSPATH . WPINC . '/ms-default-filters.php';
	require ABSPATH . WPINC . '/ms-deprecated.php';
}

// Define constants that rely on the API to obtain the default value.
// Define must-use plugin directory constants, which may be overridden in the sunrise.php drop-in.
wp_plugin_directory_constants();

$symlinked_plugins_supported = function_exists( 'wp_register_plugin_realpath' );
if ( $symlinked_plugins_supported ) {
	$GLOBALS['wp_plugin_paths'] = [];
}

// Load must-use plugins.
foreach ( wp_get_mu_plugins() as $mu_plugin ) {
	include_once $mu_plugin;

	/**
	 * Fires once a single must-use plugin has loaded.
	 *
	 * @since 5.1.0
	 *
	 * @param string $mu_plugin Full path to the plugin's main file.
	 */
	do_action( 'mu_plugin_loaded', $mu_plugin );
}
unset( $mu_plugin );

// Load network activated plugins.
if ( is_multisite() ) {
	foreach ( wp_get_active_network_plugins() as $network_plugin ) {
		if ( $symlinked_plugins_supported ) {
			wp_register_plugin_realpath( $network_plugin );
		}
		include_once $network_plugin;

		/**
		 * Fires once a single network-activated plugin has loaded.
		 *
		 * @since 5.1.0
		 *
		 * @param string $network_plugin Full path to the plugin's main file.
		 */
		do_action( 'network_plugin_loaded', $network_plugin );
	}
	unset( $network_plugin );
}

/**
 * Fires once all must-use and network-activated plugins have loaded.
 *
 * @since 2.8.0
 */
do_action( 'muplugins_loaded' );

if ( is_multisite() ) {
	ms_cookie_constants();
}

// Define constants after multisite is loaded.
wp_cookie_constants();

// Define and enforce our SSL constants.
wp_ssl_constants();

// Create common globals.
require ABSPATH . WPINC . '/vars.php';

// Make taxonomies and posts available to plugins and themes.
// @plugin authors: warning: these get registered again on the init hook.
create_initial_taxonomies();
create_initial_post_types();

if ( function_exists( 'wp_start_scraping_edited_file_errors' ) ) {
	wp_start_scraping_edited_file_errors();
}

// Register the default theme directory root
register_theme_directory( get_theme_root() );

if ( ! is_multisite() && function_exists( 'wp_recovery_mode' ) && method_exists( 'WP_Recovery_Mode', 'initialize' ) ) {
	// Handle users requesting a recovery mode link and initiating recovery mode.
	wp_recovery_mode()->initialize();
}

// Load active plugins.
foreach ( wp_get_active_and_valid_plugins() as $plugin ) {
	if ( $symlinked_plugins_supported ) {
		wp_register_plugin_realpath( $plugin );
	}
	include_once $plugin;

	/**
	 * Fires once a single activated plugin has loaded.
	 *
	 * @since 5.1.0
	 *
	 * @param string $plugin Full path to the plugin's main file.
	 */
	do_action( 'plugin_loaded', $plugin );
}
unset( $plugin, $symlinked_plugins_supported );

// Load pluggable functions.
require ABSPATH . WPINC . '/pluggable.php';
require ABSPATH . WPINC . '/pluggable-deprecated.php';

// Set internal encoding.
wp_set_internal_encoding();

// Run wp_cache_postload() if object cache is enabled and the function exists.
if ( WP_CACHE && function_exists( 'wp_cache_postload' ) ) {
	wp_cache_postload();
}

/**
 * Fires once activated plugins have loaded.
 *
 * Pluggable functions are also available at this point in the loading order.
 *
 * @since 1.5.0
 */
do_action( 'plugins_loaded' );

// Define constants which affect functionality if not already defined.
wp_functionality_constants();

// Add magic quotes and set up $_REQUEST ( $_GET + $_POST )
wp_magic_quotes();

/**
 * Fires when comment cookies are sanitized.
 *
 * @since 2.0.11
 */
do_action( 'sanitize_comment_cookies' );

/**
 * WordPress Query object
 *
 * @global WP_Query $wp_the_query WordPress Query object.
 * @since 2.0.0
 */
$GLOBALS['wp_the_query'] = new WP_Query();

/**
 * Holds the reference to @see $wp_the_query
 * Use this global for WordPress queries
 *
 * @global WP_Query $wp_query WordPress Query object.
 * @since 1.5.0
 */
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];

/**
 * Holds the WordPress Rewrite object for creating pretty URLs
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 * @since 1.5.0
 */
$GLOBALS['wp_rewrite'] = new WP_Rewrite();

/**
 * WordPress Object
 *
 * @global WP $wp Current WordPress environment instance.
 * @since 2.0.0
 */
$GLOBALS['wp'] = new WP();

/**
 * WordPress Widget Factory Object
 *
 * @global WP_Widget_Factory $wp_widget_factory
 * @since 2.8.0
 */
$GLOBALS['wp_widget_factory'] = new WP_Widget_Factory();

/**
 * WordPress User Roles
 *
 * @global WP_Roles $wp_roles WordPress role management object.
 * @since 2.0.0
 */
$GLOBALS['wp_roles'] = new WP_Roles();

/**
 * Fires before the theme is loaded.
 *
 * @since 2.6.0
 */
do_action( 'setup_theme' );

// Define the template related constants.
wp_templating_constants();

// Load the default text localization domain.
load_default_textdomain();

$locale      = get_locale();
$locale_file = WP_LANG_DIR . "/$locale.php";
if ( ( 0 === validate_file( $locale ) ) && is_readable( $locale_file ) ) {
	require $locale_file;
}
unset( $locale_file );

// Pull in locale data after loading text domain.
require_once ABSPATH . WPINC . '/locale.php';

/**
 * WordPress Locale object for loading locale domain date and various strings.
 *
 * @global WP_Locale $wp_locale WordPress date and time locale object.
 * @since 2.1.0
 */
$GLOBALS['wp_locale'] = new WP_Locale();

if ( class_exists( 'WP_Locale_Switcher' ) ) {
	/**
	 *  WordPress Locale Switcher object for switching locales.
	 *
	 * @since 4.7.0
	 *
	 * @global WP_Locale_Switcher $wp_locale_switcher WordPress locale switcher object.
	 */
	$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
	$GLOBALS['wp_locale_switcher']->init();
}

// Load the functions for the active theme, for both parent and child theme if applicable.
if ( function_exists( 'wp_get_active_and_valid_themes' ) ) {
	foreach ( wp_get_active_and_valid_themes() as $theme ) {
		if ( file_exists( $theme . '/functions.php' ) ) {
			include $theme . '/functions.php';
		}
	}
	unset( $theme );
} else {
	// phpcs:disable WordPress.WP.DiscouragedConstants.STYLESHEETPATHUsageFound,WordPress.WP.DiscouragedConstants.TEMPLATEPATHUsageFound
	global $pagenow;
	if ( ! defined( 'WP_INSTALLING' ) || 'wp-activate.php' === $pagenow ) {
		if ( TEMPLATEPATH !== STYLESHEETPATH && file_exists( STYLESHEETPATH . '/functions.php' ) ) {
			include STYLESHEETPATH . '/functions.php';
		}
		if ( file_exists( TEMPLATEPATH . '/functions.php' ) ) {
			include TEMPLATEPATH . '/functions.php';
		}
	}
	// phpcs:enable WordPress.WP.DiscouragedConstants
}

/**
 * Fires after the theme is loaded.
 *
 * @since 3.0.0
 */
do_action( 'after_setup_theme' );

// Set up current user.
$GLOBALS['wp']->init();

/**
 * Fires after WordPress has finished loading but before any headers are sent.
 *
 * Most of WP is loaded at this stage, and the user is authenticated. WP continues
 * to load on the {@see 'init'} hook that follows (e.g. widgets), and many plugins instantiate
 * themselves on it for all sorts of reasons (e.g. they need a user, a taxonomy, etc.).
 *
 * If you wish to plug an action once WP is loaded, use the {@see 'wp_loaded'} hook below.
 *
 * @since 1.5.0
 */
do_action( 'init' );

// Check site status
# if ( is_multisite() ) {  // WP-CLI
if ( is_multisite() && ! defined( 'WP_INSTALLING' ) ) {
	$file = ms_site_check();
	if ( true !== $file ) {
		require $file;
		die();
	}
	unset( $file );
}

/**
 * This hook is fired once WP, all plugins, and the theme are fully loaded and instantiated.
 *
 * Ajax requests should use wp-admin/admin-ajax.php. admin-ajax.php can handle requests for
 * users not logged in.
 *
 * @link https://codex.wordpress.org/AJAX_in_Plugins
 *
 * @since 3.0.0
 */
do_action( 'wp_loaded' );
