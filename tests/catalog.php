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
 * @return bool
 */
function wp_doing_cron() {
	return ! empty( $GLOBALS['wp_doing_cron'] );
}

/**
 * @param mixed $data Data.
 * @param int   $options Options.
 * @param int   $depth Depth.
 * @return string|false
 */
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

/**
 * @param string $hook Hook.
 * @return int|false
 */
function wp_next_scheduled( $hook ) {
	if ( isset( $GLOBALS['wpfonts_cron_events'][ $hook ] ) ) {
		return $GLOBALS['wpfonts_cron_events'][ $hook ];
	}
	return false;
}

/**
 * @param int    $timestamp Timestamp.
 * @param string $recurrence Recurrence.
 * @param string $hook Hook.
 * @return bool
 */
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	$GLOBALS['wpfonts_cron_events'][ $hook ] = $timestamp;
	$GLOBALS['wpfonts_schedule_log'][]       = array( (int) $timestamp, (string) $recurrence, (string) $hook );
	return true;
}

$catalog_dir = sys_get_temp_dir() . '/wpfonts-catalog-' . getmypid();
if ( ! is_dir( $catalog_dir ) && ! mkdir( $catalog_dir, 0777, true ) && ! is_dir( $catalog_dir ) ) {
	fwrite( STDERR, "Could not create catalog temp dir.\n" );
	exit( 1 );
}
define( 'WPFONTS_CATALOG_PATH', $catalog_dir . '/catalog.json' );
$GLOBALS['wpfonts_cron_events'] = array();
$GLOBALS['wpfonts_schedule_log'] = array();
$GLOBALS['wp_doing_cron']        = false;

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

$catalog_body = static function ( $page, $page_total, array $rows ) {
	return (string) json_encode(
		array(
			'code' => 200,
			'data' => array(
				'total'     => count( $rows ),
				'page'      => $page,
				'pageTotal' => $page_total,
				'dataList'  => $rows,
			),
		)
	);
};

$reset = static function () {
	$GLOBALS['transients']    = array();
	$GLOBALS['http_log']      = array();
	$GLOBALS['http_queue']    = array();
	$GLOBALS['http_writes']   = array();
	$GLOBALS['wp_doing_cron'] = false;
	$GLOBALS['is_admin']      = true;
	foreach ( array( WPFONTS_CATALOG_PATH, WPFONTS_CATALOG_PATH . '.tmp' ) as $path ) {
		if ( is_file( $path ) ) {
			unlink( $path );
		}
	}
};

$body = $catalog_body(
	1,
	1,
	array(
		array(
			'id'             => '7f48fdc1-8032-4d2d-8f94-5f64cce36943',
			'normalizedName' => 'zhi-mang-xing',
			'name'           => '钟齐志莽行书',
			'fontFamily'     => 'wenfeng-zhi-mang-xing',
		),
		array(
			'id'             => '7f48fdc1-8032-4d2d-8f94-5f64cce36943',
			'normalizedName' => 'not-a-family',
			'name'           => '丢弃',
			'fontFamily'     => 'zhi-mang-xing',
		),
		array(
			'name'       => '也丢弃',
			'fontFamily' => '7f48fdc1-8032-4d2d-8f94-5f64cce36943',
		),
		array(
			'name'       => '空前缀',
			'fontFamily' => 'wenfeng-',
		),
	)
);
$parsed = WPFonts_Catalog::parse_body( $body );
$assert( ! empty( $parsed['ok'] ), 'catalog JSON should parse' );
$assert( 1 === count( $parsed['fonts'] ), 'dirty catalog rows were kept' );
$assert( isset( $parsed['fonts'][0]['fontFamily'] ) && 'wenfeng-zhi-mang-xing' === $parsed['fonts'][0]['fontFamily'], 'parsed fontFamily mismatch' );
$assert( isset( $parsed['fonts'][0]['name'] ) && '钟齐志莽行书' === $parsed['fonts'][0]['name'], 'parsed name mismatch' );
$keys = array_keys( $parsed['fonts'][0] );
sort( $keys );
$assert( array( 'fontFamily', 'name' ) === $keys, 'parsed row kept id or normalizedName' );
$assert( '7f48fdc1-8032-4d2d-8f94-5f64cce36943' !== $parsed['fonts'][0]['fontFamily'], 'UUID was stored as the family' );
$assert( 'zhi-mang-xing' !== $parsed['fonts'][0]['fontFamily'], 'normalizedName was stored as the family' );
$assert( empty( WPFonts_Catalog::parse_body( 'not-json' )['ok'] ), 'bad JSON should fail' );
$assert( empty( WPFonts_Catalog::parse_body( '{"code":500,"data":{}}' )['ok'] ), 'non-200 catalog code should fail' );
$assert( WPFonts_Catalog::FONTS_URL === 'https://app.windfonts.com/api/fonts', 'catalog host drifted' );
$assert( 'wpfonts/catalog.json' === WPFonts_Catalog::SNAPSHOT_RELATIVE, 'snapshot path drifted' );
$assert( 2592000 === WPFonts_Catalog::MONTH_INTERVAL, 'monthly interval is not 30 days' );

$reset();
$GLOBALS['http_queue'] = array(
	array(
		'code' => 200,
		'body' => $body,
	),
);
$miss = WPFonts_Catalog::read( 1, '' );
$assert( empty( $miss['ok'] ), 'missing snapshot should not look successful' );
$assert( array() === $miss['fonts'], 'missing snapshot invented fonts' );
$assert( array() === $GLOBALS['http_log'], 'settings read called the directory' );
$assert( ! is_file( WPFONTS_CATALOG_PATH ), 'settings read wrote a snapshot' );

$page_two = $catalog_body(
	2,
	2,
	array(
		array(
			'id'             => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
			'normalizedName' => 'hcwjt',
			'name'           => '寒蝉无机体',
			'fontFamily'     => 'wenfeng-hcwjt',
		),
	)
);
$reset();
$GLOBALS['http_queue'] = array(
	array(
		'code' => 200,
		'body' => $catalog_body(
			1,
			2,
			array(
				array(
					'id'             => '7f48fdc1-8032-4d2d-8f94-5f64cce36943',
					'normalizedName' => 'zhi-mang-xing',
					'name'           => '钟齐志莽行书',
					'fontFamily'     => 'wenfeng-zhi-mang-xing',
				),
				array(
					'name'       => '丢弃',
					'fontFamily' => 'zhi-mang-xing',
				),
			)
		),
	),
	array(
		'code' => 200,
		'body' => $page_two,
	),
);
$written = WPFonts_Catalog::refresh( true );
$assert( ! empty( $written['ok'] ), 'manual refresh should succeed' );
$assert( empty( $written['stale'] ), 'successful refresh was marked stale' );
$assert( 2 === count( $written['fonts'] ), 'manual refresh did not merge pages' );
$families = array();
foreach ( $written['fonts'] as $font ) {
	$families[] = $font['fontFamily'];
}
$assert( array( 'wenfeng-zhi-mang-xing', 'wenfeng-hcwjt' ) === $families, 'merged snapshot lost fontFamily' );
$assert( 2 === count( $GLOBALS['http_log'] ), 'manual refresh did not request both pages' );
$assert( false === strpos( $GLOBALS['http_log'][0]['url'], 'api_key' ), 'catalog URL contains a key' );
$assert( false === strpos( $GLOBALS['http_log'][0]['url'], 'search=' ), 'snapshot refresh sent a search query' );
$assert( empty( $GLOBALS['http_log'][0]['args']['headers']['X-API-Key'] ), 'anonymous refresh sent the API key' );
$assert( false !== strpos( $GLOBALS['http_log'][1]['url'], 'page=2' ), 'second page was not requested' );
$raw = (string) file_get_contents( WPFONTS_CATALOG_PATH );
$saved = json_decode( $raw, true );
$assert( is_array( $saved ) && isset( $saved['updated_at'] ) && is_int( $saved['updated_at'] ) && $saved['updated_at'] > 0, 'snapshot updated_at missing' );
$assert( isset( $saved['fonts'][0]['fontFamily'] ) && 'wenfeng-zhi-mang-xing' === $saved['fonts'][0]['fontFamily'], 'snapshot file lost fontFamily' );
$assert( false === strpos( $raw, 'normalizedName' ), 'snapshot file stored normalizedName' );
$assert( false === strpos( $raw, '7f48fdc1-8032-4d2d-8f94-5f64cce36943' ), 'snapshot file stored a UUID' );
$assert( false === strpos( $raw, 'wf_catalog_key_123' ), 'snapshot file stored the API key' );

$GLOBALS['http_log']   = array();
$GLOBALS['http_queue'] = array(
	array(
		'code' => 500,
		'body' => '',
	),
);
$again = WPFonts_Catalog::read( 1, '' );
$assert( array() === $GLOBALS['http_log'], 'snapshot hit requested the directory' );
$assert( ! empty( $again['ok'] ), 'snapshot hit failed' );
$assert( ! empty( $again['cached'] ), 'snapshot hit was not marked cached' );
$assert( 'wenfeng-zhi-mang-xing' === $again['fonts'][0]['fontFamily'], 'snapshot hit lost fontFamily' );
$assert( 1 === count( $GLOBALS['http_queue'] ), 'snapshot hit consumed the HTTP queue' );

$filtered = WPFonts_Catalog::read( 1, '寒蝉' );
$assert( array() === $GLOBALS['http_log'], 'local search requested the directory' );
$assert( 1 === count( $filtered['fonts'] ), 'local search did not filter the snapshot' );
$assert( 'wenfeng-hcwjt' === $filtered['fonts'][0]['fontFamily'], 'local search returned the wrong family' );

$bytes                 = $raw;
$GLOBALS['transients'] = array();
$GLOBALS['http_log']   = array();
$GLOBALS['http_queue'] = array(
	array(
		'code' => 500,
		'body' => '',
	),
);
$stale = WPFonts_Catalog::refresh( true );
$assert( ! empty( $stale['ok'] ), 'failed refresh discarded the good snapshot' );
$assert( ! empty( $stale['stale'] ), 'failed refresh was not marked stale' );
$assert( 'wenfeng-zhi-mang-xing' === $stale['fonts'][0]['fontFamily'], 'stale snapshot lost fontFamily' );
$assert( $bytes === (string) file_get_contents( WPFONTS_CATALOG_PATH ), 'failed refresh overwrote the good snapshot' );

$GLOBALS['transients']  = array();
$GLOBALS['http_queue']  = array(
	array(
		'code' => 200,
		'body' => $catalog_body( 1, 2, array( array( 'name' => '钟齐志莽行书', 'fontFamily' => 'wenfeng-zhi-mang-xing' ) ) ),
	),
	array(
		'code' => 500,
		'body' => '',
	),
);
$GLOBALS['http_log']    = array();
$partial                = WPFonts_Catalog::refresh( true );
$assert( ! empty( $partial['stale'] ), 'partial refresh should keep the previous snapshot' );
$assert( $bytes === (string) file_get_contents( WPFONTS_CATALOG_PATH ), 'partial refresh overwrote the good snapshot' );
$assert( 2 === count( $GLOBALS['http_log'] ), 'partial refresh stopped before the failing page' );

$reset();
$GLOBALS['http_queue'] = array( new WP_Error( 'http', 'down' ) );
$down                  = WPFonts_Catalog::refresh( true );
$assert( empty( $down['ok'] ), 'transport failure should not look successful' );
$assert( array() === $down['fonts'], 'transport failure should not invent fonts' );
$assert( ! is_file( WPFONTS_CATALOG_PATH ), 'transport failure wrote a snapshot' );

file_put_contents(
	WPFONTS_CATALOG_PATH,
	'{"updated_at":10,"fonts":[{"name":"钟齐志莽行书","fontFamily":"wenfeng-zhi-mang-xing"},{"name":"坏","fontFamily":"not-wenfeng"}]}'
);
$cleaned = WPFonts_Catalog::read( 1, '' );
$assert( 1 === count( $cleaned['fonts'] ), 'stored non-wenfeng family was returned' );
$assert( 'wenfeng-zhi-mang-xing' === $cleaned['fonts'][0]['fontFamily'], 'stored snapshot lost fontFamily' );

$GLOBALS['wpfonts_test_key'] = 'wf_catalog_key_123';
$reset();
$GLOBALS['http_queue']       = array(
	array(
		'code' => 401,
		'body' => '{"errorCode":"missing_api_key"}',
	),
	array(
		'code' => 200,
		'body' => $catalog_body(
			1,
			1,
			array(
				array(
					'name'       => '钟齐志莽行书',
					'fontFamily' => 'wenfeng-zhi-mang-xing',
				),
			)
		),
	),
);
$GLOBALS['http_log']         = array();
$authed                      = WPFonts_Catalog::refresh( true );
$assert( ! empty( $authed['ok'] ), '401 retry should accept the second body' );
$assert( 2 === count( $GLOBALS['http_log'] ), '401 should retry once' );
$assert( empty( $GLOBALS['http_log'][0]['args']['headers']['X-API-Key'] ), 'first catalog try should stay anonymous' );
$assert( isset( $GLOBALS['http_log'][1]['args']['headers']['X-API-Key'] ) && 'wf_catalog_key_123' === $GLOBALS['http_log'][1]['args']['headers']['X-API-Key'], 'retry did not send X-API-Key' );
$assert( false === strpos( $GLOBALS['http_log'][1]['url'], 'wf_catalog_key_123' ), 'API key leaked into the catalog URL' );
$assert( false === strpos( (string) file_get_contents( WPFONTS_CATALOG_PATH ), 'wf_catalog_key_123' ), 'API key was written into the snapshot' );

$GLOBALS['transients'][ WPFonts_Catalog::REFRESH_KEY ] = time();
$GLOBALS['http_log']                                   = array();
$GLOBALS['http_queue']                                 = array(
	array(
		'code' => 500,
		'body' => '',
	),
);
$cooled = WPFonts_Catalog::refresh( true );
$assert( array() === $GLOBALS['http_log'], 'manual refresh ignored the cooldown' );
$assert( ! empty( $cooled['cooling'] ), 'cooldown was not reported' );
$assert( 'wenfeng-zhi-mang-xing' === $cooled['fonts'][0]['fontFamily'], 'cooled refresh dropped the snapshot family' );

$GLOBALS['wp_doing_cron'] = true;
$GLOBALS['http_log']      = array();
$GLOBALS['http_queue']    = array(
	array(
		'code' => 200,
		'body' => $catalog_body(
			1,
			1,
			array(
				array(
					'name'       => '寒蝉无机体',
					'fontFamily' => 'wenfeng-hcwjt',
				),
			)
		),
	),
);
$cron_write = WPFonts_Catalog::refresh( false );
$assert( ! empty( $cron_write['ok'] ), 'cron refresh should ignore the manual cooldown' );
$assert( 1 === count( $GLOBALS['http_log'] ), 'cron refresh did not call the directory' );
$assert( false !== strpos( (string) file_get_contents( WPFONTS_CATALOG_PATH ), 'wenfeng-hcwjt' ), 'cron refresh did not write fontFamily' );

$GLOBALS['wp_doing_cron'] = false;
$held                     = (string) file_get_contents( WPFONTS_CATALOG_PATH );
$GLOBALS['http_log']      = array();
$GLOBALS['http_queue']    = array(
	array(
		'code' => 200,
		'body' => $body,
	),
);
$blocked = WPFonts_Catalog::refresh( false );
$assert( empty( $blocked['ok'] ), 'refresh outside cron should not succeed' );
$assert( array() === $GLOBALS['http_log'], 'refresh outside cron called the directory' );
$assert( $held === (string) file_get_contents( WPFONTS_CATALOG_PATH ), 'refresh outside cron wrote the snapshot' );

$GLOBALS['is_admin'] = false;
$GLOBALS['http_log'] = array();
$front               = WPFonts_Catalog::read( 1, '' );
$assert( empty( $front['ok'] ), 'front read should fail closed' );
$assert( array() === $GLOBALS['http_log'], 'front read called the directory' );
$front_refresh = WPFonts_Catalog::refresh( true );
$assert( empty( $front_refresh['ok'] ), 'front refresh should fail closed' );
$assert( array() === $GLOBALS['http_log'], 'front refresh called the directory' );
$GLOBALS['is_admin'] = true;

$schedules = WPFonts_Catalog::cron_schedules( array() );
$assert( isset( $schedules['monthly']['interval'] ) && 2592000 === $schedules['monthly']['interval'], 'monthly schedule was not registered' );
$short = WPFonts_Catalog::cron_schedules(
	array(
		'monthly' => array(
			'interval' => 86400,
			'display'  => 'Daily',
		),
	)
);
$assert( 2592000 === $short['monthly']['interval'], 'a shorter monthly schedule was kept' );
$kept = WPFonts_Catalog::cron_schedules(
	array(
		'monthly' => array(
			'interval' => 2592000,
			'display'  => 'Already monthly',
		),
	)
);
$assert( 'Already monthly' === $kept['monthly']['display'], 'an existing monthly schedule was replaced' );

$GLOBALS['wpfonts_schedule_log'] = array();
WPFonts_Catalog::ensure_schedule();
WPFonts_Catalog::ensure_schedule();
$assert( 1 === count( $GLOBALS['wpfonts_schedule_log'] ), 'ensure_schedule registered more than one event' );
$assert( 'monthly' === $GLOBALS['wpfonts_schedule_log'][0][1], 'scheduled recurrence is not monthly' );
$assert( WPFonts_Catalog::CRON_HOOK === $GLOBALS['wpfonts_schedule_log'][0][2], 'scheduled hook drifted' );
$assert( abs( $GLOBALS['wpfonts_schedule_log'][0][0] - ( time() + 2592000 ) ) < 5, 'first cron run is sooner than a month' );
$assert( array() === $GLOBALS['http_log'], 'scheduling called the directory' );

$admin     = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-admin.php' );
$js        = file_get_contents( dirname( __DIR__ ) . '/assets/js/wpfonts-admin.js' );
$embed     = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-embed.php' );
$link      = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-link.php' );
$main      = file_get_contents( dirname( __DIR__ ) . '/wpfonts.php' );
$uninstall = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );
$assert( false === strpos( $embed, 'WPFonts_Catalog' ), 'front embed references the catalog' );
$assert( false === strpos( $embed, '/api/fonts' ), 'front embed references the catalog URL' );
$assert( false === strpos( $link, '/api/fonts' ), 'allowlist link references the catalog URL' );
$assert( false === strpos( $admin, 'wp_ajax_nopriv_wpfonts_catalog' ), 'catalog AJAX is open to logged-out users' );
$assert( false !== strpos( $admin, 'wp_ajax_wpfonts_catalog' ), 'catalog AJAX hook missing' );
$assert( 1 === substr_count( $admin, 'WPFonts_Catalog::refresh' ), 'directory refresh is not limited to the button endpoint' );
$assert( false !== strpos( $admin, 'WPFonts_Catalog::read(' ), 'settings screen does not read the snapshot' );
$assert( false !== strpos( $admin, '更新字体目录' ), 'refresh button copy drifted' );
$assert( false !== strpos( $admin, '正在更新字体目录…' ), 'refresh progress copy drifted' );
$assert( false !== strpos( $admin, '字体目录已更新。' ), 'refresh success copy drifted' );
$assert( false !== strpos( $admin, '暂时更新不了，仍显示上次保存的目录。稍后再试。' ), 'stale refresh copy drifted' );
$assert( false !== strpos( $admin, '暂时读不到字体目录。你可以手动填写字体名，稍后再试。' ), 'missing snapshot copy drifted' );
$assert( false !== strpos( $admin, '目录保存在本站，大约每月自动更新一次；也可点按钮立刻更新。' ), 'catalog note drifted' );
$assert( false === strpos( $admin, '去控制台上传' ), 'admin offered a console upload' );
$assert( false !== strpos( $js, 'family.value = target.value' ), 'picking a font does not write the radio value into the font name field' );
$assert( false === strpos( $js, 'normalizedName' ), 'catalog script reads normalizedName' );
$assert( false !== strpos( $js, 'body.append("refresh", "1")' ), 'refresh button does not post refresh=1' );
$assert( false !== strpos( $js, 'data-updating' ), 'refresh progress attribute missing' );
$assert( false !== strpos( $js, 'data-stale' ), 'stale refresh attribute missing' );
$catalog_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-catalog.php' );
$assert( false !== strpos( $catalog_src, "const CRON_HOOK = 'wpfonts_catalog_refresh'" ), 'cron hook name drifted' );
$assert( false !== strpos( $main, 'WPFonts_Catalog::CRON_HOOK' ), 'cron hook is not registered' );
$assert( false !== strpos( $main, "'WPFonts_Catalog', 'run_cron'" ), 'cron callback is not registered' );
$assert( false !== strpos( $main, 'register_activation_hook' ), 'activation does not schedule the catalog' );
$assert( false !== strpos( $main, 'register_deactivation_hook' ), 'deactivation does not clear the catalog cron' );
$assert( false !== strpos( $main, 'cron_schedules' ), 'monthly schedule filter missing' );
$assert( false !== strpos( $uninstall, 'wpfonts/catalog.json' ), 'uninstall does not delete the snapshot' );
$assert( false !== strpos( $uninstall, 'wpfonts_catalog_refresh' ), 'uninstall does not clear the catalog cron' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: catalog keeps fontFamily in a local JSON snapshot, skips HTTP on read, and does not replace a good snapshot with a failure.\n";
