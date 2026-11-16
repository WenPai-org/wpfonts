<?php
/**
 * Admin-only front-end font preview. No WordPress and no network.
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['wpfonts_enqueued'] = array();
$GLOBALS['wpfonts_inline']   = array();
$GLOBALS['wpfonts_logged_in'] = false;
$GLOBALS['wpfonts_can_admin'] = false;
$GLOBALS['wpfonts_settings']  = array(
	'cdn_enabled' => 0,
	'region'      => 'cn',
	'subset'      => 'zh-common',
);

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
 * @param string $text Text.
 * @param string $domain Domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

/**
 * @return bool
 */
function is_admin() {
	return false;
}

/**
 * @return bool
 */
function is_user_logged_in() {
	return $GLOBALS['wpfonts_logged_in'];
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
 * @param string $value Value.
 * @return string
 */
function sanitize_text_field( $value ) {
	return trim( (string) preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $value ) ) );
}

/**
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return $value;
}

/**
 * @return bool
 */
function is_singular() {
	return false;
}

/**
 * @param string $handle Handle.
 * @param string $src Source.
 * @param array  $deps Dependencies.
 * @param mixed  $ver Version.
 * @return void
 */
function wp_enqueue_style( $handle, $src, $deps = array(), $ver = false ) {
	$GLOBALS['wpfonts_enqueued'][ $handle ] = $src;
	unset( $deps, $ver );
}

/**
 * @param string $handle Handle.
 * @param string $css CSS.
 * @return bool
 */
function wp_add_inline_style( $handle, $css ) {
	$GLOBALS['wpfonts_inline'][ $handle ] = $css;
	return true;
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
 * @param string $text Text.
 * @param string $domain Text domain.
 * @return string
 */
function esc_attr( $text, $domain = 'default' ) {
	unset( $domain );
	return (string) $text;
}

/**
 * @param string $text Text.
 * @param string $domain Text domain.
 * @return string
 */
function esc_html__( $text, $domain = 'default' ) {
	unset( $domain );
	return (string) $text;
}

/**
 * @param array $query Query args.
 * @return string
 */
function remove_query_arg( $query ) {
	unset( $query );
	$args = $_GET;
	unset( $args['wpfonts_preview'] );
	return 'https://example.test/?' . http_build_query( $args );
}

/**
 * @return array
 */
class WPFonts_Settings {
	/**
	 * @return array
	 */
	public static function get() {
		return $GLOBALS['wpfonts_settings'];
	}
}

/**
 * Package enqueue is not under test.
 */
class WPFonts_Packages {
	/**
	 * @return void
	 */
	public static function enqueue_styles() {
	}
}

require dirname( __DIR__ ) . '/includes/class-wpfonts-link.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-embed.php';

$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$reset = static function () {
	$GLOBALS['wpfonts_enqueued']  = array();
	$GLOBALS['wpfonts_inline']    = array();
	$GLOBALS['wpfonts_logged_in'] = false;
	$GLOBALS['wpfonts_can_admin'] = false;
	unset( $_GET['wpfonts_preview'] );
};

$expected_url = 'https://cn.windfonts.com/api/css?family=wenfeng-ibmps&subset=zh-common';

// Visitor with the query arg: nothing is enqueued and no badge prints.
$reset();
$_GET['wpfonts_preview'] = 'wenfeng-ibmps';
WPFonts_Embed::enqueue();
WPFonts_Embed::print_license_note();
ob_start();
WPFonts_Embed::print_preview_badge();
$badge = ob_get_clean();
$assert( ! isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'visitor received the preview stylesheet' );
$assert( '' === $badge, 'visitor received the preview badge' );

// Logged-in non-admin: same silence.
$reset();
$GLOBALS['wpfonts_logged_in'] = true;
$_GET['wpfonts_preview']      = 'wenfeng-ibmps';
WPFonts_Embed::enqueue();
ob_start();
WPFonts_Embed::print_preview_badge();
$badge = ob_get_clean();
$assert( ! isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'non-admin received the preview stylesheet' );
$assert( '' === $badge, 'non-admin received the preview badge' );

// Admin without the param: nothing.
$reset();
$GLOBALS['wpfonts_logged_in'] = true;
$GLOBALS['wpfonts_can_admin'] = true;
WPFonts_Embed::enqueue();
$assert( ! isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'admin without param received the preview stylesheet' );

// Admin with a valid token: stylesheet plus scoped font CSS and badge.
$reset();
$GLOBALS['wpfonts_logged_in'] = true;
$GLOBALS['wpfonts_can_admin'] = true;
$_GET['wpfonts_preview']      = 'wenfeng-ibmps';
WPFonts_Embed::enqueue();
$assert( isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'admin preview stylesheet was not enqueued' );
if ( isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ) ) {
	$assert( $expected_url === $GLOBALS['wpfonts_enqueued']['wpfonts-preview'], 'preview URL mismatch: ' . $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] );
}
$assert( isset( $GLOBALS['wpfonts_inline']['wpfonts-preview'] ), 'preview font CSS was not attached' );
if ( isset( $GLOBALS['wpfonts_inline']['wpfonts-preview'] ) ) {
	$assert( false !== strpos( $GLOBALS['wpfonts_inline']['wpfonts-preview'], 'font-family:"wenfeng-ibmps"' ), 'preview CSS lacks the font-family rule' );
}
ob_start();
WPFonts_Embed::print_license_note();
$note = ob_get_clean();
$assert( false !== strpos( $note, WPFonts_Link::CDN_LICENSE_NOTE ), 'preview request lost the license comment' );
$assert( false !== strpos( $note, 'https://cn.windfonts.com' ), 'preview request lost the preconnect origin' );
ob_start();
WPFonts_Embed::print_preview_badge();
$badge = ob_get_clean();
$assert( false !== strpos( $badge, 'wenfeng-ibmps' ), 'preview badge lacks the family token' );
$assert( false === strpos( $badge, 'wpfonts_preview=' ), 'preview badge exit link still carries the param' );

// Invalid tokens are ignored even for admins.
foreach ( array( 'evil', 'wenfeng-', 'https://evil.example/x', '' ) as $bad ) {
	$reset();
	$GLOBALS['wpfonts_logged_in'] = true;
	$GLOBALS['wpfonts_can_admin'] = true;
	$_GET['wpfonts_preview']      = $bad;
	WPFonts_Embed::enqueue();
	ob_start();
	WPFonts_Embed::print_preview_badge();
	$badge = ob_get_clean();
	$assert( ! isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'invalid token was accepted: ' . $bad );
	$assert( '' === $badge, 'invalid token produced a badge: ' . $bad );
}

// Markup in the param is stripped before tokenization; the URL stays allowlisted.
$reset();
$GLOBALS['wpfonts_logged_in'] = true;
$GLOBALS['wpfonts_can_admin'] = true;
$_GET['wpfonts_preview']      = 'wenfeng-ibmps"><script>';
WPFonts_Embed::enqueue();
if ( isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ) ) {
	$assert( WPFonts_Link::is_allowlisted( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'markup token escaped the allowlist' );
	$assert( false === strpos( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'], '<' ), 'markup leaked into the preview URL' );
} else {
	$failures[] = 'markup token produced no preview stylesheet';
}

// A non-wenfeng family that survives tokenization is still rejected.
$reset();
$GLOBALS['wpfonts_logged_in'] = true;
$GLOBALS['wpfonts_can_admin'] = true;
$_GET['wpfonts_preview']      = 'IBM Plex Sans';
WPFonts_Embed::enqueue();
$assert( ! isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'non-wenfeng token was accepted' );

// Region setting is honored for the preview URL.
$reset();
$GLOBALS['wpfonts_logged_in'] = true;
$GLOBALS['wpfonts_can_admin'] = true;
$GLOBALS['wpfonts_settings']['region'] = 'hk';
$_GET['wpfonts_preview']               = 'wenfeng-ibmps';
WPFonts_Embed::enqueue();
if ( isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ) ) {
	$assert( 0 === strpos( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'], 'https://hk.windfonts.com/' ), 'preview ignored the hk region' );
} else {
	$failures[] = 'hk region preview was not enqueued';
}

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, "FAIL: {$failure}\n" );
	}
	exit( 1 );
}

echo "embed-preview: PASS\n";
