<?php
/**
 * WPCY 4.0 non-intrusive suite filter hooks (`wpcy_service_status`, `wenpai_plugin_display`).
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPFONTS_VERSION', '2.0.0-rc.22' );
define( 'WPFONTS_BASENAME', 'wpfonts/wpfonts.php' );
define( 'WPFONTS_DIR_URL', 'https://example.test/wp-content/plugins/wpfonts/' );
define( 'WPFONTS_DIR_PATH', dirname( __DIR__ ) . '/' );

$GLOBALS['wpfonts_options'] = array();
$GLOBALS['wpfonts_filters'] = array();

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted_args Args.
 * @return void
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
}

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted_args Args.
 * @return void
 */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $priority, $accepted_args );
	$GLOBALS['wpfonts_filters'][ $hook ][] = $callback;
}

/**
 * @param string $hook Hook.
 * @param mixed  $value Value.
 * @param mixed  ...$args Extra args.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$args ) {
	if ( empty( $GLOBALS['wpfonts_filters'][ $hook ] ) ) {
		return $value;
	}
	foreach ( $GLOBALS['wpfonts_filters'][ $hook ] as $cb ) {
		$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
	}
	return $value;
}

/**
 * @param string $text Text.
 * @param string $domain Domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

/**
 * @param string $raw Key.
 * @return string
 */
function sanitize_key( $raw ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $raw ) );
}

/**
 * @param string $str String.
 * @return string
 */
function sanitize_text_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

/**
 * @param string $url URL.
 * @return string
 */
function esc_url_raw( $url ) {
	return filter_var( (string) $url, FILTER_SANITIZE_URL );
}

/**
 * @param string $path Path.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

/**
 * @param array  $args Query args.
 * @param string $url  Base URL.
 * @return string
 */
function add_query_arg( $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}

/**
 * @param string $name Option.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['wpfonts_options'] ) ? $GLOBALS['wpfonts_options'][ $name ] : $default;
}

require dirname( __DIR__ ) . '/includes/class-wpfonts-link.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-settings.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-packages.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-admin.php';

$failures = array();
$assert   = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$assert( isset( $GLOBALS['wpfonts_filters']['wpcy_service_status'] ), 'wpcy_service_status filter was not registered' );

// 1. Default state: CDN off, 0 local packages.
$status_default = apply_filters( 'wpcy_service_status', array( 'compliance' => array( 'state' => 'ok' ) ), array() );
$assert( isset( $status_default['compliance'] ), 'existing status entries were clobbered' );
$assert( isset( $status_default['windfonts'] ), 'windfonts status entry missing' );
$assert( '系统字体' === $status_default['windfonts']['pill'], 'default pill mismatch' );
$assert( 'ok' === $status_default['windfonts']['state'], 'default state mismatch' );
$assert( false !== strpos( $status_default['windfonts']['summary'], 'cn.windfonts.com' ), 'default summary missing node' );
$assert( false !== strpos( $status_default['windfonts']['summary'], '0 款' ), 'default summary missing 0 count' );
$assert( 'https://example.test/wp-admin/options-general.php?page=wpfonts' === $status_default['windfonts']['action_url'], 'action_url mismatch' );

// 2. CDN on, HK node, 2 confirmed cloud faces + 1 enabled package = 3 active fonts.
$GLOBALS['wpfonts_options']['wpfonts_settings'] = array(
	'cdn_enabled' => 1,
	'region'      => 'hk',
	'subset'      => 'zh-common',
	'faces'       => array(
		array(
			'family'   => 'wenfeng-ibmps',
			'name'     => 'IBM Plex',
			'css_face' => 'windfonts-ibmps',
			'subset'   => 'zh-common',
		),
		array(
			'family'   => 'wenfeng-xwwkb',
			'name'     => '霞鹜文楷 Bright',
			'css_face' => 'windfonts-xwwkb',
			'subset'   => 'zh-common',
		),
	),
);
$GLOBALS['wpfonts_options']['wpfonts_packages'] = array(
	array(
		'id'      => 'wenfeng-xzt',
		'name'    => '文风·玄宗体',
		'family'  => 'wenfeng-xzt',
		'enabled' => 1,
	),
	array(
		'id'      => 'wenfeng-paused',
		'name'    => '停用包',
		'family'  => 'wenfeng-paused',
		'enabled' => 0,
	),
);
$status_active = apply_filters( 'wpcy_service_status', array(), array() );
$assert( '文风云已开启' === $status_active['windfonts']['pill'], 'active CDN pill mismatch' );
$assert( 'ok' === $status_active['windfonts']['state'], 'active CDN state mismatch' );
$assert( false !== strpos( $status_active['windfonts']['summary'], '中国香港（hk.windfonts.com）' ), 'active CDN summary missing HK node' );
$assert( false !== strpos( $status_active['windfonts']['summary'], '3 款' ), 'active CDN summary did not sum cloud + enabled package fonts' );

// 3. CDN on, JP node, 0 faces selected yet -> warn state.
$GLOBALS['wpfonts_options']['wpfonts_settings'] = array(
	'cdn_enabled' => 1,
	'region'      => 'jp',
	'subset'      => 'zh-common',
	'faces'       => array(),
);
$GLOBALS['wpfonts_options']['wpfonts_packages'] = array();
$status_incomplete = apply_filters( 'wpcy_service_status', array(), array() );
$assert( 'warn' === $status_incomplete['windfonts']['state'], 'incomplete CDN should report warn state' );
$assert( false !== strpos( $status_incomplete['windfonts']['summary'], '日本（jp.windfonts.com）' ), 'JP node label missing from summary' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: WPCY 4.0 service status hook reports CDN state, node, and font counts without altering WPFonts UI.\n";
