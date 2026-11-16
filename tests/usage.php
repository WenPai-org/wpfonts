<?php
/**
 * Monthly unique-visitor usage counter. No WordPress and no network.
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['wpfonts_options']   = array();
$GLOBALS['wpfonts_cookies']    = array();
$GLOBALS['wpfonts_is_admin']   = false;
$GLOBALS['wpfonts_can_admin']  = true;
$GLOBALS['wpfonts_user_id']    = 7;
$GLOBALS['wpfonts_user_meta']  = array();
$GLOBALS['wpfonts_settings']   = array(
	'cdn_enabled' => 1,
	'key_status'  => '',
);
$GLOBALS['wpfonts_redirect']   = '';

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
 * @param string $name Option name.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['wpfonts_options'] ) ? $GLOBALS['wpfonts_options'][ $name ] : $default;
}

/**
 * @param string $name Option name.
 * @param mixed  $value Value.
 * @return bool
 */
function add_option( $name, $value, $deprecated = '', $autoload = 'yes' ) {
	unset( $deprecated, $autoload );
	if ( array_key_exists( $name, $GLOBALS['wpfonts_options'] ) ) {
		return false;
	}
	$GLOBALS['wpfonts_options'][ $name ] = $value;
	return true;
}

/**
 * @param string $name Option name.
 * @param mixed  $value Value.
 * @return bool
 */
function update_option( $name, $value ) {
	$GLOBALS['wpfonts_options'][ $name ] = $value;
	return true;
}

/**
 * @return bool
 */
function is_ssl() {
	return false;
}

/**
 * @return bool
 */
function is_admin() {
	return $GLOBALS['wpfonts_is_admin'];
}

/**
 * @return int
 */
function get_current_user_id() {
	return $GLOBALS['wpfonts_user_id'];
}

/**
 * @param string $capability Capability.
 * @return bool
 */
function current_user_can( $capability ) {
	unset( $capability );
	return $GLOBALS['wpfonts_can_admin'];
}

/**
 * @param int    $user_id User id.
 * @param string $key Key.
 * @param bool   $single Single.
 * @return mixed
 */
function get_user_meta( $user_id, $key, $single = false ) {
	unset( $single );
	return isset( $GLOBALS['wpfonts_user_meta'][ $user_id ][ $key ] ) ? $GLOBALS['wpfonts_user_meta'][ $user_id ][ $key ] : '';
}

/**
 * @param int    $user_id User id.
 * @param string $key Key.
 * @param mixed  $value Value.
 * @return bool
 */
function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['wpfonts_user_meta'][ $user_id ][ $key ] = $value;
	return true;
}

/**
 * @param string $action Action.
 * @param string $query_arg Query arg.
 * @return void
 */
function check_admin_referer( $action, $query_arg = '_wpnonce' ) {
	unset( $action, $query_arg );
}

/**
 * @param string $url URL.
 * @return void
 */
function wp_safe_redirect( $url ) {
	$GLOBALS['wpfonts_redirect'] = $url;
}

/**
 * @param array|string $args Query args.
 * @param string       $url URL.
 * @return string
 */
function add_query_arg( $args, $url = '' ) {
	unset( $args, $url );
	return 'https://example.test/wp-admin/';
}

/**
 * @param string $url URL.
 * @return string
 */
function remove_query_arg( $args ) {
	unset( $args );
	return 'https://example.test/wp-admin/';
}

/**
 * @param string $url URL.
 * @param string $action Action.
 * @return string
 */
function wp_nonce_url( $url, $action ) {
	unset( $action );
	return $url;
}

/**
 * @param string $path Path.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
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
 * @param string $text Text.
 * @param string $domain Domain.
 * @return string
 */
function esc_html__( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

/**
 * @param string $url URL.
 * @return string
 */
function esc_url( $url ) {
	return (string) $url;
}

/**
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return (string) $text;
}

/**
 * @param int $number Number.
 * @return string
 */
function number_format_i18n( $number ) {
	return (string) $number;
}

/**
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return $value;
}

/**
 * @param string $value Value.
 * @return string
 */
function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

/**
 * Minimal settings stand-in; the usage class only reads get().
 */
class WPFonts_Settings {
	/**
	 * @return array
	 */
	public static function get() {
		return $GLOBALS['wpfonts_settings'];
	}
}

require dirname( __DIR__ ) . '/includes/class-wpfonts-usage.php';

$failures = array();
$assert   = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

// maybe_dismiss() ends in exit like wp-admin redirects do, so results are
// reported from shutdown instead of the bottom of the file.
register_shutdown_function(
	static function () use ( &$failures ) {
		// maybe_dismiss() exits after storing; verify its effects here.
		$month = gmdate( 'Y-m' );
		if ( $month !== ( $GLOBALS['wpfonts_user_meta'][7]['wpfonts_usage_dismiss'] ?? '' ) ) {
			$failures[] = 'dismiss did not store the month';
		}
		if ( '' === (string) ( $GLOBALS['wpfonts_redirect'] ?? '' ) ) {
			$failures[] = 'dismiss did not redirect';
		}
		foreach ( $failures as $failure ) {
			fwrite( STDERR, "FAIL: {$failure}\n" );
		}
		if ( ! $failures ) {
			echo "usage checks passed\n";
		}
		exit( $failures ? 1 : 0 );
	}
);

$month = gmdate( 'Y-m' );

// First visit counts once and sets the month cookie.
// PHP's setcookie() is a no-op under CLI, so dedup is exercised through
// $_COOKIE, which is what record() actually reads on the next request.
WPFonts_Usage::record();
$assert( 1 === WPFonts_Usage::get()['visitors'], 'first visit did not count' );

// Same visitor, same month: cookie short-circuits.
$_COOKIE[ WPFonts_Usage::COOKIE ] = $month;
WPFonts_Usage::record();
$assert( 1 === WPFonts_Usage::get()['visitors'], 'same visitor counted twice in one month' );

// New visitor (no cookie) counts again.
unset( $_COOKIE[ WPFonts_Usage::COOKIE ] );
WPFonts_Usage::record();
$assert( 2 === WPFonts_Usage::get()['visitors'], 'second visitor did not count' );

// Stale cookie from an earlier month counts as a new visitor.
$_COOKIE[ WPFonts_Usage::COOKIE ] = '2000-01';
WPFonts_Usage::record();
$assert( 3 === WPFonts_Usage::get()['visitors'], 'stale month cookie did not count as new' );

// Admin context never counts. headers_sent() is a PHP built-in that stays
// false under CLI, so that branch is covered by the is_admin() guard only.
$_COOKIE = array();
$GLOBALS['wpfonts_is_admin'] = true;
WPFonts_Usage::record();
$GLOBALS['wpfonts_is_admin'] = false;
$assert( 3 === WPFonts_Usage::get()['visitors'], 'admin request was counted' );

// Threshold: over the line only above LIMIT; notice is admin-only and
// suppressed while a valid key is stored or the month was dismissed.
$GLOBALS['wpfonts_options'][ WPFonts_Usage::OPTION ] = array(
	'month'    => $month,
	'visitors' => WPFonts_Usage::LIMIT,
);
$assert( false === WPFonts_Usage::over_limit(), 'exactly at the limit should not warn' );
$GLOBALS['wpfonts_options'][ WPFonts_Usage::OPTION ]['visitors'] = WPFonts_Usage::LIMIT + 1;
$assert( true === WPFonts_Usage::over_limit(), 'above the limit should warn' );

ob_start();
WPFonts_Usage::admin_notice();
$notice = (string) ob_get_clean();
$assert( false !== strpos( $notice, 'notice-warning' ), 'over-limit admin notice missing' );
$assert( false !== strpos( $notice, '本月不再提醒' ), 'dismiss link missing from notice' );
$assert( false !== strpos( $notice, 'fonts-packages' ) || false !== strpos( $notice, '字体包' ), 'package link missing' );

$GLOBALS['wpfonts_settings']['key_status'] = 'valid';
ob_start();
WPFonts_Usage::admin_notice();
$assert( '' === (string) ob_get_clean(), 'keyed site still saw the usage notice' );
$GLOBALS['wpfonts_settings']['key_status'] = '';

$GLOBALS['wpfonts_user_meta'][7]['wpfonts_usage_dismiss'] = $month;
ob_start();
WPFonts_Usage::admin_notice();
$assert( '' === (string) ob_get_clean(), 'dismissed month still showed the notice' );
unset( $GLOBALS['wpfonts_user_meta'][7]['wpfonts_usage_dismiss'] );

$GLOBALS['wpfonts_can_admin'] = false;
ob_start();
WPFonts_Usage::admin_notice();
$assert( '' === (string) ob_get_clean(), 'non-admin saw the usage notice' );
$GLOBALS['wpfonts_can_admin'] = true;

// Old-month dismissal does not suppress this month.
$GLOBALS['wpfonts_user_meta'][7]['wpfonts_usage_dismiss'] = '2000-01';
ob_start();
WPFonts_Usage::admin_notice();
$assert( false !== strpos( (string) ob_get_clean(), 'notice-warning' ), 'last-month dismissal suppressed this month' );
unset( $GLOBALS['wpfonts_user_meta'][7]['wpfonts_usage_dismiss'] );

// Last: maybe_dismiss() ends in exit like wp-admin redirects do; its
// effects are asserted in the shutdown handler above.
$_GET['wpfonts_usage_dismiss'] = '1';
WPFonts_Usage::maybe_dismiss();
