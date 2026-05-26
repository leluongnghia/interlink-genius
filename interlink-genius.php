<?php
/**
 * Plugin Name:       InterLink Genius
 * Plugin URI:        https://github.com/google-deepmind
 * Description:       A powerful standalone AI-powered internal linking assistant that offers link auditing, bulk link editing, keyword auto-linking, and smart link suggestions.
 * Version:           1.0.7
 * Author:            Google Deepmind
 * Author URI:        https://github.com/google-deepmind
 * Text Domain:       interlink-genius
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// Define Constants
define( 'INTERLINK_GENIUS_FILE', __FILE__ );
define( 'INTERLINK_GENIUS_DIR', plugin_dir_path( __FILE__ ) );
define( 'INTERLINK_GENIUS_URL', plugin_dir_url( __FILE__ ) );
define( 'INTERLINK_GENIUS_VERSION', '1.0.7' );

defined( 'CONTENT_AI_URL' ) || define( 'CONTENT_AI_URL', 'https://contentai.rankmath.com' );

// Load bundled dependencies (chỉ load nếu chưa được định nghĩa bởi plugin khác)
if ( ! class_exists( 'WP_Async_Request', false ) ) {
	require_once INTERLINK_GENIUS_DIR . 'libs/wp-async-request.php';
}
if ( ! class_exists( 'WP_Background_Process', false ) ) {
	require_once INTERLINK_GENIUS_DIR . 'libs/wp-background-process.php';
}

// Register Autoloader
spl_autoload_register( function ( $class ) {
	$prefix = 'InterLinkGenius\\';
	$len    = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );

	// Support looking up compatibility helpers inside the Compatibility directory
	if ( strpos( $relative_class, 'Compatibility\\' ) === 0 ) {
		$class_name = basename( str_replace( '\\', '/', $relative_class ) );
		$path       = INTERLINK_GENIUS_DIR . 'Compatibility/';
	} else {
		$parts      = explode( '\\', $relative_class );
		$class_name = array_pop( $parts );
		$path       = INTERLINK_GENIUS_DIR;
		if ( ! empty( $parts ) ) {
			$path .= implode( '/', $parts ) . '/';
		}
	}

	$file_base  = strtolower( str_replace( '_', '-', $class_name ) );
	$class_file = $path . 'class-' . $file_base . '.php';
	$trait_file = $path . 'trait-' . $file_base . '.php';

	// Linux case-sensitivity fallback (ví dụ: Blocks -> blocks)
	if ( ! file_exists( $class_file ) && ! file_exists( $trait_file ) && ! empty( $parts ) ) {
		$lower_parts = array_map( 'strtolower', $parts );
		$path_lower  = INTERLINK_GENIUS_DIR . implode( '/', $lower_parts ) . '/';
		$class_file_lower = $path_lower . 'class-' . $file_base . '.php';
		$trait_file_lower = $path_lower . 'trait-' . $file_base . '.php';

		if ( file_exists( $class_file_lower ) ) {
			$class_file = $class_file_lower;
		} elseif ( file_exists( $trait_file_lower ) ) {
			$trait_file = $trait_file_lower;
		}
	}

	if ( file_exists( $class_file ) ) {
		require_once $class_file;
	} elseif ( file_exists( $trait_file ) ) {
		require_once $trait_file;
	}
} );

// Activation Hook: Initialize Database Schema
register_activation_hook( __FILE__, function() {
	if ( class_exists( 'InterLinkGenius\Data\Table_Extension' ) ) {
		InterLinkGenius\Data\Table_Extension::initialize_schema();
	}
} );

// Deactivation Hook: Clean up Scheduled Cron Jobs
register_deactivation_hook( __FILE__, function() {
	$cron_hooks = [
		'interlink_genius_cleanup_exports',
		'interlink_genius_start_crawler',
		'interlink_genius_dispatch_crawler',
		'interlink_genius_queue_pending_links',
		'interlink_genius_cleanup_orphaned_link_status',
	];
	foreach ( $cron_hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
} );

// Boot Plugin
add_action( 'plugins_loaded', function() {
	new InterLinkGenius\Link_Genius();
} );
