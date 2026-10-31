<?php
/**
 * Front-end gate for Plugin Update Checker.
 *
 * Stubs the few WordPress functions the main file calls, then invokes the
 * updater callback as a normal front-end request would. The library must
 * stay unloaded. Admin, cron, and CLI still load it inside WordPress.
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$hooks = array();

/**
 * Record hook registrations.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @return void
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $hooks;
	unset( $accepted_args );
	$hooks[] = array( $hook, $callback, $priority );
}

/**
 * Record filters. The front-end gate does not run them.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $accepted_args Accepted args.
 * @return void
 */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	add_action( $hook, $callback, $priority, $accepted_args );
}

/**
 * @param string $file Plugin file.
 * @return string
 */
function plugin_dir_url( $file ) {
	unset( $file );
	return 'http://example.test/wp-content/plugins/wpfonts/';
}

/**
 * @param string $file Plugin file.
 * @return string
 */
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

/**
 * @param string $file Plugin file.
 * @return string
 */
function plugin_basename( $file ) {
	return 'wpfonts/' . basename( $file );
}

/**
 * @return void
 */
function load_plugin_textdomain() {
}

/**
 * Front-end request.
 *
 * @return bool
 */
function is_admin() {
	return false;
}

/**
 * Not WP-Cron.
 *
 * @return bool
 */
function wp_doing_cron() {
	return false;
}

/**
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

/**
 * @param string $base Base path.
 * @param string $path Relative path.
 * @return string
 */
function path_join( $base, $path ) {
	return rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );
}

require dirname( __DIR__ ) . '/wpfonts.php';

if ( ! function_exists( 'wp_fonts_initialize_update_checker' ) ) {
	fwrite( STDERR, "Updater callback was not defined.\n" );
	exit( 1 );
}

wp_fonts_initialize_update_checker();

$loaded = false;
foreach ( get_included_files() as $included ) {
	if ( false !== strpos( $included, 'plugin-update-checker.php' ) ) {
		$loaded = true;
	}
}

if ( $loaded || class_exists( '\YahnisElsts\PluginUpdateChecker\v5p3\PucFactory', false ) ) {
	fwrite( STDERR, "Front-end request loaded Plugin Update Checker.\n" );
	exit( 1 );
}

if ( '2.0.0-rc.1' !== WPFONTS_VERSION ) {
	fwrite( STDERR, "Unexpected version constant.\n" );
	exit( 1 );
}

$src = file_get_contents( dirname( __DIR__ ) . '/wpfonts.php' );
if ( ! preg_match( '/^ \* Update URI: https:\/\/updates\.wenpai\.net$/m', $src ) ) {
	fwrite( STDERR, "Update URI changed.\n" );
	exit( 1 );
}
if ( false === strpos( $src, 'https://updates.weixiaoduo.com/wpfonts.json' ) ) {
	fwrite( STDERR, "Updater metadata URL changed.\n" );
	exit( 1 );
}

echo "PASS: front-end request did not load Plugin Update Checker.\n";
