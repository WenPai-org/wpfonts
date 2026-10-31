<?php
/**
 * Font catalog parsing, cache, and failure. No WordPress, no network.
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['is_admin']    = true;
$GLOBALS['transients']  = array();
$GLOBALS['http_log']    = array();
$GLOBALS['http_queue']  = array();
$GLOBALS['http_writes'] = array();

/**
 * Transport error stand-in.
 */
class WP_Error {

	/**
	 * @param string $code Code.
	 * @param string $message Message.
	 */
	public function __construct( $code = '', $message = '' ) {
		unset( $code, $message );
	}
}

/**
 * @return bool
 */
function is_admin() {
	return ! empty( $GLOBALS['is_admin'] );
}

/**
 * @param mixed $thing Thing.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * @param string $url URL.
 * @param array  $args Args.
 * @return array|WP_Error
 */
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['http_log'][] = array(
		'url'  => $url,
		'args' => $args,
	);
	if ( array() === $GLOBALS['http_queue'] ) {
		return new WP_Error( 'http', 'empty' );
	}
	return array_shift( $GLOBALS['http_queue'] );
}

/**
 * @param array $response Response.
 * @return int
 */
function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['code'] ) ? (int) $response['code'] : 0;
}

/**
 * @param array $response Response.
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}

/**
 * @param string $key Key.
 * @return mixed
 */
function get_transient( $key ) {
	if ( array_key_exists( $key, $GLOBALS['transients'] ) ) {
		return $GLOBALS['transients'][ $key ];
	}
	return false;
}

/**
 * @param string $key Key.
 * @param mixed  $value Value.
 * @param int    $ttl TTL.
 * @return bool
 */
function set_transient( $key, $value, $ttl ) {
	$GLOBALS['transients'][ $key ] = $value;
	$GLOBALS['http_writes'][]      = array( $key, $value, (int) $ttl );
	return true;
}

/**
 * @param array  $args Args.
 * @param string $url URL.
 * @return string
 */
function add_query_arg( $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}

/**
 * Saved key for the 401 retry. Not the real settings class.
 */
class WPFonts_Settings {

	/**
	 * @return string
	 */
	public static function api_key() {
		return isset( $GLOBALS['wpfonts_test_key'] ) ? (string) $GLOBALS['wpfonts_test_key'] : '';
	}
}

require dirname( __DIR__ ) . '/includes/class-wpfonts-link.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-catalog.php';

$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$body = '{"code":200,"data":{"total":1,"page":1,"pageTotal":1,"dataList":[{"id":"7f48fdc1-8032-4d2d-8f94-5f64cce36943","normalizedName":"zhi-mang-xing","name":"钟齐志莽行书","fontFamily":"wenfeng-zhi-mang-xing"}]}}';
$parsed = WPFonts_Catalog::parse_body( $body );
$assert( ! empty( $parsed['ok'] ), 'catalog JSON should parse' );
$assert( isset( $parsed['fonts'][0]['fontFamily'] ) && 'wenfeng-zhi-mang-xing' === $parsed['fonts'][0]['fontFamily'], 'parsed fontFamily mismatch' );
$assert( isset( $parsed['fonts'][0]['name'] ) && '钟齐志莽行书' === $parsed['fonts'][0]['name'], 'parsed name mismatch' );
$keys = array_keys( $parsed['fonts'][0] );
sort( $keys );
$assert( array( 'fontFamily', 'name' ) === $keys, 'parsed row kept id or normalizedName' );
$assert( '7f48fdc1-8032-4d2d-8f94-5f64cce36943' !== $parsed['fonts'][0]['fontFamily'], 'UUID was stored as the family' );
$assert( 'zhi-mang-xing' !== $parsed['fonts'][0]['fontFamily'], 'normalizedName was stored as the family' );
$assert( empty( WPFonts_Catalog::parse_body( 'not-json' )['ok'] ), 'bad JSON should fail' );
$assert( empty( WPFonts_Catalog::parse_body( '{"code":500,"data":{}}' )['ok'] ), 'non-200 catalog code should fail' );

$GLOBALS['http_queue'] = array(
	array(
		'code' => 200,
		'body' => $body,
	),
);
$GLOBALS['http_log']   = array();
$GLOBALS['http_writes'] = array();
$first = WPFonts_Catalog::read( 1, '' );
$assert( ! empty( $first['ok'] ), 'first read should succeed' );
$assert( 'wenfeng-zhi-mang-xing' === $first['fonts'][0]['fontFamily'], 'live read dropped fontFamily' );
$assert( 1 === count( $GLOBALS['http_log'] ), 'first read should request once' );
$assert( false === strpos( $GLOBALS['http_log'][0]['url'], 'api_key' ), 'catalog URL contains a key' );
$assert( empty( $GLOBALS['http_log'][0]['args']['headers']['X-API-Key'] ), 'anonymous read sent the API key' );
$assert( WPFonts_Catalog::FONTS_URL === 'https://app.windfonts.com/api/fonts', 'catalog host drifted' );

$GLOBALS['http_log'] = array();
$again               = WPFonts_Catalog::read( 1, '' );
$assert( 0 === count( $GLOBALS['http_log'] ), 'transient hit requested the directory again' );
$assert( ! empty( $again['cached'] ), 'transient hit was not marked cached' );
$assert( 'wenfeng-zhi-mang-xing' === $again['fonts'][0]['fontFamily'], 'cached row lost fontFamily' );

$GLOBALS['http_queue'] = array(
	array(
		'code' => 500,
		'body' => '',
	),
);
$writes_before = count( $GLOBALS['http_writes'] );
$stale         = WPFonts_Catalog::read( 1, '', true );
$assert( ! empty( $stale['ok'] ), 'failed refresh discarded the good cache' );
$assert( ! empty( $stale['stale'] ), 'failed refresh was not marked stale' );
$assert( 'wenfeng-zhi-mang-xing' === $stale['fonts'][0]['fontFamily'], 'stale cache lost fontFamily' );
$cache_rewritten = false;
foreach ( array_slice( $GLOBALS['http_writes'], $writes_before ) as $write ) {
	if ( 0 === strpos( $write[0], 'wpfonts_cat_1_' ) ) {
		$cache_rewritten = true;
	}
}
$assert( ! $cache_rewritten, 'failed refresh overwrote the good cache' );

$GLOBALS['transients']  = array();
$GLOBALS['http_queue']  = array( new WP_Error( 'http', 'down' ) );
$GLOBALS['http_log']    = array();
$GLOBALS['http_writes'] = array();
$down                   = WPFonts_Catalog::read( 1, '' );
$assert( empty( $down['ok'] ), 'transport failure should not look successful' );
$assert( array() === $down['fonts'], 'transport failure should not invent fonts' );
$assert( array() === $GLOBALS['http_writes'], 'transport failure wrote a cache' );

$GLOBALS['wpfonts_test_key'] = 'wf_catalog_key_123';
$GLOBALS['http_queue']       = array(
	array(
		'code' => 401,
		'body' => '{"errorCode":"missing_api_key"}',
	),
	array(
		'code' => 200,
		'body' => $body,
	),
);
$GLOBALS['http_log']         = array();
$authed                      = WPFonts_Catalog::read( 2, '志莽' );
$assert( ! empty( $authed['ok'] ), '401 retry should accept the second body' );
$assert( 2 === count( $GLOBALS['http_log'] ), '401 should retry once' );
$assert( empty( $GLOBALS['http_log'][0]['args']['headers']['X-API-Key'] ), 'first catalog try should stay anonymous' );
$assert( isset( $GLOBALS['http_log'][1]['args']['headers']['X-API-Key'] ) && 'wf_catalog_key_123' === $GLOBALS['http_log'][1]['args']['headers']['X-API-Key'], 'retry did not send X-API-Key' );
$assert( false !== strpos( $GLOBALS['http_log'][1]['url'], 'page=2' ), 'page was dropped from the catalog URL' );
$assert( false !== strpos( $GLOBALS['http_log'][1]['url'], 'search=' ), 'search was dropped from the catalog URL' );
$assert( false === strpos( $GLOBALS['http_log'][1]['url'], 'wf_catalog_key_123' ), 'API key leaked into the catalog URL' );

$GLOBALS['transients'][ WPFonts_Catalog::REFRESH_KEY ] = time();
$GLOBALS['http_log']                                   = array();
$cooled = WPFonts_Catalog::read( 2, '志莽', true );
$assert( 0 === count( $GLOBALS['http_log'] ), 'forced refresh ignored the cooldown' );
$assert( ! empty( $cooled['cooling'] ), 'cooldown was not reported' );
$assert( 'wenfeng-zhi-mang-xing' === $cooled['fonts'][0]['fontFamily'], 'cooled read dropped the cached family' );

$GLOBALS['is_admin'] = false;
$GLOBALS['http_log'] = array();
$front               = WPFonts_Catalog::read( 1, '' );
$assert( empty( $front['ok'] ), 'front read should fail closed' );
$assert( 0 === count( $GLOBALS['http_log'] ), 'front read called the directory' );

$admin = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-admin.php' );
$js    = file_get_contents( dirname( __DIR__ ) . '/assets/js/wpfonts-admin.js' );
$embed = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-embed.php' );
$link  = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-link.php' );
$assert( false === strpos( $embed, 'WPFonts_Catalog' ), 'front embed references the catalog' );
$assert( false === strpos( $embed, '/api/fonts' ), 'front embed references the catalog URL' );
$assert( false === strpos( $link, '/api/fonts' ), 'allowlist link references the catalog URL' );
$assert( false === strpos( $admin, 'wp_ajax_nopriv_wpfonts_catalog' ), 'catalog AJAX is open to logged-out users' );
$assert( false !== strpos( $admin, 'wp_ajax_wpfonts_catalog' ), 'catalog AJAX hook missing' );
$assert( false !== strpos( $js, 'family.value = target.value' ), 'picking a font does not write the radio value into the font name field' );
$assert( false === strpos( $js, 'normalizedName' ), 'catalog script reads normalizedName' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: catalog keeps fontFamily, serves a cached page without a second request, and does not replace a good cache with a failure.\n";
