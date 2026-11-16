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
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['wpfonts_enqueued']       = array();
$GLOBALS['wpfonts_inline']         = array();
$GLOBALS['wpfonts_adminbar_nodes'] = array();
$GLOBALS['wpfonts_transients']     = array();
$GLOBALS['wpfonts_logged_in']      = false;
$GLOBALS['wpfonts_can_admin']      = false;
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
 * @param string $text Text.
 * @param string $domain Text domain.
 * @return string
 */
function esc_attr__( $text, $domain = 'default' ) {
	unset( $domain );
	return (string) $text;
}

/**
 * Records admin bar nodes for assertions.
 */
class WP_Admin_Bar {
	/**
	 * @param array $args Node args.
	 * @return void
	 */
	public function add_node( $args ) {
		$GLOBALS['wpfonts_adminbar_nodes'][] = $args;
	}
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
 * @param string $key Key.
 * @return mixed
 */
function get_transient( $key ) {
	return isset( $GLOBALS['wpfonts_transients'][ $key ] ) ? $GLOBALS['wpfonts_transients'][ $key ] : false;
}

/**
 * @param string $key Key.
 * @param mixed  $value Value.
 * @param int    $ttl TTL.
 * @return bool
 */
function set_transient( $key, $value, $ttl = 0 ) {
	unset( $ttl );
	$GLOBALS['wpfonts_transients'][ $key ] = $value;
	return true;
}

/**
 * Fake stylesheet fetch: returns one @font-face named TestFace.
 *
 * @param string $url URL.
 * @param array  $args Args.
 * @return array
 */
function wp_remote_get( $url, $args = array() ) {
	unset( $url, $args );
	return array( 'body' => '@font-face{font-family:"TestFace";src:url(x.woff2)}', 'response' => array( 'code' => 200 ) );
}

/**
 * @param mixed $thing Thing.
 * @return bool
 */
function is_wp_error( $thing ) {
	return false;
}

/**
 * @param array $response Response.
 * @return int
 */
function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

/**
 * @param array $response Response.
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
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

	/**
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function confirmed_faces( $settings ) {
		unset( $settings );
		return array();
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
	$GLOBALS['wpfonts_enqueued']      = array();
	$GLOBALS['wpfonts_inline']        = array();
	$GLOBALS['wpfonts_adminbar_nodes'] = array();
	$GLOBALS['wpfonts_logged_in']     = false;
	$GLOBALS['wpfonts_can_admin']     = false;
	unset( $_GET['wpfonts_preview'] );
};

$bar = new WP_Admin_Bar();

$expected_url = 'https://cn.windfonts.com/api/css?family=wenfeng-ibmps&subset=zh-common';

// Visitor with the query arg: nothing is enqueued and no admin bar node.
$reset();
$_GET['wpfonts_preview'] = 'wenfeng-ibmps';
WPFonts_Embed::enqueue();
WPFonts_Embed::print_license_note();
WPFonts_Embed::preview_admin_bar( $bar );
$assert( ! isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'visitor received the preview stylesheet' );
$assert( array() === $GLOBALS['wpfonts_adminbar_nodes'], 'visitor received an admin bar node' );

// Logged-in non-admin: same silence.
$reset();
$GLOBALS['wpfonts_logged_in'] = true;
$_GET['wpfonts_preview']      = 'wenfeng-ibmps';
WPFonts_Embed::enqueue();
WPFonts_Embed::preview_admin_bar( $bar );
$assert( ! isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'non-admin received the preview stylesheet' );
$assert( array() === $GLOBALS['wpfonts_adminbar_nodes'], 'non-admin received an admin bar node' );

// Admin without the param: nothing.
$reset();
$GLOBALS['wpfonts_logged_in'] = true;
$GLOBALS['wpfonts_can_admin'] = true;
WPFonts_Embed::enqueue();
$assert( ! isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'admin without param received the preview stylesheet' );

// Admin with a valid token: stylesheet plus scoped font CSS and admin bar node.
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
	$assert( false !== strpos( $GLOBALS['wpfonts_inline']['wpfonts-preview'], 'font-family:"TestFace"' ), 'preview CSS lacks the probed font-family rule' );
}
ob_start();
WPFonts_Embed::print_license_note();
$note = ob_get_clean();
$assert( false !== strpos( $note, WPFonts_Link::CDN_LICENSE_NOTE ), 'preview request lost the license comment' );
$assert( false !== strpos( $note, 'https://cn.windfonts.com' ), 'preview request lost the preconnect origin' );
WPFonts_Embed::preview_admin_bar( $bar );
$assert( 1 === count( $GLOBALS['wpfonts_adminbar_nodes'] ), 'admin bar node missing for valid preview' );
if ( 1 === count( $GLOBALS['wpfonts_adminbar_nodes'] ) ) {
	$node = $GLOBALS['wpfonts_adminbar_nodes'][0];
	$assert( false !== strpos( $node['title'], 'wenfeng-ibmps' ), 'admin bar node lacks the family token' );
	$assert( false === strpos( $node['href'], 'wpfonts_preview=' ), 'admin bar exit link still carries the param' );
}

// Invalid tokens are ignored even for admins.
foreach ( array( 'evil', 'wenfeng-', 'https://evil.example/x', '' ) as $bad ) {
	$reset();
	$GLOBALS['wpfonts_logged_in'] = true;
	$GLOBALS['wpfonts_can_admin'] = true;
	$_GET['wpfonts_preview']      = $bad;
	WPFonts_Embed::enqueue();
	WPFonts_Embed::preview_admin_bar( $bar );
	$assert( ! isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'invalid token was accepted: ' . $bad );
	$assert( array() === $GLOBALS['wpfonts_adminbar_nodes'], 'invalid token produced an admin bar node: ' . $bad );
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

// Previewing the already-configured font: no duplicate handle, but the
// scoped font CSS still attaches to the handle that carries the URL.
$reset();
$GLOBALS['wpfonts_logged_in'] = true;
$GLOBALS['wpfonts_can_admin'] = true;
$GLOBALS['wpfonts_settings'] = array(
	'cdn_enabled' => 1,
	'key_status'  => 'valid',
	'family'      => 'wenfeng-ibmps',
	'region'      => 'cn',
	'subset'      => 'zh-common',
);
$_GET['wpfonts_preview'] = 'wenfeng-ibmps';
WPFonts_Embed::enqueue();
$assert( ! isset( $GLOBALS['wpfonts_enqueued']['wpfonts-preview'] ), 'configured preview font got a duplicate handle' );
$assert( isset( $GLOBALS['wpfonts_inline']['wpfonts-cdn'] ), 'configured preview font lost the scoped font CSS' );
if ( isset( $GLOBALS['wpfonts_inline']['wpfonts-cdn'] ) ) {
	$assert( false !== strpos( $GLOBALS['wpfonts_inline']['wpfonts-cdn'], 'font-family:"TestFace"' ), 'configured preview CSS lacks the font-family rule' );
}

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, "FAIL: {$failure}\n" );
	}
	exit( 1 );
}

echo "embed-preview: PASS\n";
