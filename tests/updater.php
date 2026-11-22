<?php
/**
 * Unit tests for WPFonts_Updater (`updates.wenpai.net`).
 *
 * Covers:
 * - Plugin header Update URI and unchanged RC version (`2.0.0-rc.22`)
 * - Hook registration and cooperative `wpfonts_updater_override` / `wenpai_updater_override`
 * - `update_plugins_updates.wenpai.net` update available (`2.0.0-rc.22` -> `2.0.0` / `2.0.1`)
 * - `update_plugins_updates.wenpai.net` no update (`1.1.0`, `2.0.0-rc.22`, `2.0.0-rc.21`, empty `plugins`)
 * - Graceful degradation and negative caching on WP_Error, HTTP 404, HTTP 500, invalid JSON
 * - Positive caching and cache purge on `upgrader_process_complete`
 * - Custom API URL via `wpfonts_updater_api_url` filter
 * - `plugins_api` formatted response for `wpfonts`, local fallback on failure, pass-through for other slugs/actions
 * - Safe icon/banner/URL normalization (drops broken `svg`, rejects non-HTTP schemes)
 * - `WP_HTTP_BLOCK_EXTERNAL` and `WP_ACCESSIBLE_HOSTS` enforcement
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_PLUGIN_DIR', dirname( dirname( __DIR__ ) ) );

/**
 * Minimal WP_Error stub.
 */
class WP_Error {
	/**
	 * Error code.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = (string) $code;
		$this->message = (string) $message;
	}

	/**
	 * @return string
	 */
	public function get_error_code() {
		return $this->code;
	}

	/**
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}

$GLOBALS['wpfonts_test_filters']    = array();
$GLOBALS['wpfonts_test_actions']    = array();
$GLOBALS['wpfonts_test_transients'] = array();
$GLOBALS['wpfonts_test_http_log']   = array();
$GLOBALS['wpfonts_test_http_queue'] = array();

/**
 * @param mixed $thing Candidate value.
 * @return bool
 */
function is_wp_error( $thing ) {
	return ( $thing instanceof WP_Error );
}

/**
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return void
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );
	$GLOBALS['wpfonts_test_filters'][ $hook ][] = $callback;
}

/**
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return void
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $priority, $args );
	$GLOBALS['wpfonts_test_actions'][ $hook ][] = $callback;
}

/**
 * @param string $hook  Hook name.
 * @param mixed  $value Value.
 * @param mixed  ...$args Extra args.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$args ) {
	if ( empty( $GLOBALS['wpfonts_test_filters'][ $hook ] ) ) {
		return $value;
	}
	foreach ( $GLOBALS['wpfonts_test_filters'][ $hook ] as $cb ) {
		$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
	}
	return $value;
}

/**
 * @param string $hook Hook name.
 * @param mixed  ...$args Action args.
 * @return void
 */
function do_action( $hook, ...$args ) {
	if ( empty( $GLOBALS['wpfonts_test_actions'][ $hook ] ) ) {
		return;
	}
	foreach ( $GLOBALS['wpfonts_test_actions'][ $hook ] as $cb ) {
		call_user_func_array( $cb, $args );
	}
}

/**
 * @param string $key Transient key.
 * @return mixed
 */
function get_site_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['wpfonts_test_transients'] )
		? $GLOBALS['wpfonts_test_transients'][ $key ]['value']
		: false;
}

/**
 * @param string $key   Transient key.
 * @param mixed  $value Value.
 * @param int    $ttl   Expiration seconds.
 * @return bool
 */
function set_site_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['wpfonts_test_transients'][ $key ] = array(
		'value' => $value,
		'ttl'   => (int) $ttl,
	);
	return true;
}

/**
 * @param string $key Transient key.
 * @return bool
 */
function delete_site_transient( $key ) {
	unset( $GLOBALS['wpfonts_test_transients'][ $key ] );
	return true;
}

/**
 * @param mixed $data Data to encode.
 * @return string|false
 */
function wp_json_encode( $data ) {
	return json_encode( $data );
}

/**
 * @param string   $url       URL.
 * @param int|null $component Component.
 * @return mixed
 */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

/**
 * @param string $url       URL.
 * @param array  $protocols Allowed protocols.
 * @return string
 */
function esc_url_raw( $url, $protocols = null ) {
	unset( $protocols );
	$url = trim( (string) $url );
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		return '';
	}
	return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
}

/**
 * @param string $text Raw text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * @param string $url  URL.
 * @param array  $args Request args.
 * @return array|WP_Error
 */
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['wpfonts_test_http_log'][] = array(
		'method' => 'POST',
		'url'    => $url,
		'args'   => $args,
	);
	if ( empty( $GLOBALS['wpfonts_test_http_queue'] ) ) {
		return new WP_Error( 'http_request_failed', 'No queued mock HTTP response.' );
	}
	return array_shift( $GLOBALS['wpfonts_test_http_queue'] );
}

/**
 * @param string $url  URL.
 * @param array  $args Request args.
 * @return array|WP_Error
 */
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['wpfonts_test_http_log'][] = array(
		'method' => 'GET',
		'url'    => $url,
		'args'   => $args,
	);
	if ( empty( $GLOBALS['wpfonts_test_http_queue'] ) ) {
		return new WP_Error( 'http_request_failed', 'No queued mock HTTP response.' );
	}
	return array_shift( $GLOBALS['wpfonts_test_http_queue'] );
}

/**
 * @param array $response HTTP response.
 * @return int
 */
function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

/**
 * @param array $response HTTP response.
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}

/**
 * Minimal get_plugin_data stub for local fallback test.
 *
 * @param string $plugin_file File path.
 * @param bool   $markup      Markup flag.
 * @param bool   $translate   Translate flag.
 * @return array
 */
function get_plugin_data( $plugin_file, $markup = true, $translate = true ) {
	unset( $markup, $translate );
	$raw    = is_readable( $plugin_file ) ? (string) file_get_contents( $plugin_file ) : '';
	$fields = array(
		'Name'        => 'Plugin Name',
		'PluginURI'   => 'Plugin URI',
		'Version'     => 'Version',
		'Description' => 'Description',
		'AuthorName'  => 'Author',
		'RequiresWP'  => 'Requires at least',
		'RequiresPHP' => 'Requires PHP',
	);
	$out    = array();
	foreach ( $fields as $key => $header ) {
		if ( preg_match( '/^ \* ' . preg_quote( $header, '/' ) . ':\s*(.+)$/m', $raw, $m ) ) {
			$out[ $key ] = trim( $m[1] );
		} else {
			$out[ $key ] = '';
		}
	}
	return $out;
}

require dirname( __DIR__ ) . '/includes/class-wpfonts-updater.php';

$failures = array();
$checks   = 0;
$assert   = static function ( $condition, $message ) use ( &$failures, &$checks ) {
	++$checks;
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$reset_env = static function () {
	$GLOBALS['wpfonts_test_filters']    = array();
	$GLOBALS['wpfonts_test_actions']    = array();
	$GLOBALS['wpfonts_test_transients'] = array();
	$GLOBALS['wpfonts_test_http_log']   = array();
	$GLOBALS['wpfonts_test_http_queue'] = array();
};

// 1. Plugin header check: Update URI and unchanged RC version.
$main_src = (string) file_get_contents( dirname( __DIR__ ) . '/wpfonts.php' );
$assert(
	1 === preg_match( '/^ \* Update URI:\s*https:\/\/updates\.wenpai\.net$/m', $main_src ),
	'Plugin header Update URI must equal https://updates.wenpai.net.'
);
$assert(
	1 === preg_match( '/^ \* Version:\s*2\.0\.0-rc\.22$/m', $main_src ),
	'Plugin header Version must stay 2.0.0-rc.22.'
);
$assert(
	false === strpos( $main_src, 'plugin-update-checker' ) && false === strpos( $main_src, 'updates.weixiaoduo.com' ),
	'wpfonts.php must not reference legacy plugin-update-checker or updates.weixiaoduo.com.'
);

// 2. Cooperative override via wpfonts_updater_override and wenpai_updater_override.
$reset_env();
add_filter( 'wpfonts_updater_override', static function () { return true; } );
new WPFonts_Updater( 'wpfonts/wpfonts.php', '2.0.0-rc.22' );
$assert( empty( $GLOBALS['wpfonts_test_filters']['update_plugins_updates.wenpai.net'] ), 'wpfonts_updater_override must suppress hook registration.' );

$reset_env();
add_filter( 'wenpai_updater_override', static function () { return true; } );
new WPFonts_Updater( 'wpfonts/wpfonts.php', '2.0.0-rc.22' );
$assert( empty( $GLOBALS['wpfonts_test_filters']['update_plugins_updates.wenpai.net'] ), 'wenpai_updater_override must suppress hook registration.' );

// 3. Standard hook registration + update available (2.0.0-rc.22 -> 2.0.0).
$reset_env();
$updater = new WPFonts_Updater( 'wpfonts/wpfonts.php', '2.0.0-rc.22' );
$assert( ! empty( $GLOBALS['wpfonts_test_filters']['update_plugins_updates.wenpai.net'] ), 'update_plugins_updates.wenpai.net filter not registered.' );
$assert( ! empty( $GLOBALS['wpfonts_test_filters']['plugins_api'] ), 'plugins_api filter not registered.' );
$assert( ! empty( $GLOBALS['wpfonts_test_actions']['upgrader_process_complete'] ), 'upgrader_process_complete action not registered.' );

$GLOBALS['wpfonts_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode(
		array(
			'plugins'   => array(
				'wpfonts/wpfonts.php' => array(
					'id'           => 'wenpai:plugin:wpfonts',
					'slug'         => 'wpfonts',
					'plugin'       => 'wpfonts/wpfonts.php',
					'version'      => '2.0.0',
					'url'          => 'https://wpcy.com/wpfonts',
					'package'      => 'https://downloads.feicode.com/WenPai-org/wpfonts/releases/download/v2.0.0/wpfonts-2.0.0.zip',
					'icons'        => array(
						'1x'  => 'https://updates.wenpai.net/assets/wpfonts/icon-128x128.png',
						'2x'  => 'https://updates.wenpai.net/assets/wpfonts/icon-256x256.png',
						'svg' => 'https://updates.wenpai.net/assets/wpfonts/icon-256x256.svg',
						'bad' => 'javascript:alert(1)',
					),
					'banners'      => array(
						'high' => 'https://updates.wenpai.net/assets/wpfonts/banner-1544x500.png',
						'bad'  => 'data:text/html,oops',
					),
					'requires'     => '6.5',
					'tested'       => '7.1',
					'requires_php' => '7.4',
				),
			),
			'no_update' => array(),
		)
	),
);

$update = apply_filters( 'update_plugins_updates.wenpai.net', false, array(), 'wpfonts/wpfonts.php', array( 'zh_CN' ) );
$assert( is_object( $update ), 'Expected update object when remote 2.0.0 > 2.0.0-rc.22.' );
$assert( '2.0.0' === ( $update->version ?? '' ) && '2.0.0' === ( $update->new_version ?? '' ), 'Update version mismatch for 2.0.0.' );
$assert( 'wpfonts' === ( $update->slug ?? '' ), 'Update slug mismatch.' );
$assert( 'wpfonts/wpfonts.php' === ( $update->plugin ?? '' ), 'Update plugin basename mismatch.' );
$assert( 'https://downloads.feicode.com/WenPai-org/wpfonts/releases/download/v2.0.0/wpfonts-2.0.0.zip' === ( $update->package ?? '' ), 'Update package mismatch.' );
$assert( ! isset( $update->icons['svg'] ), 'Broken svg icon must be stripped.' );
$assert( ! isset( $update->icons['bad'] ), 'Non-HTTP icon URL must be stripped.' );
$assert( isset( $update->icons['1x'], $update->icons['2x'] ), 'Valid PNG icons must be preserved.' );
$assert( ! isset( $update->banners['bad'] ) && isset( $update->banners['high'] ), 'Banner normalization failed.' );
$assert( 1 === count( $GLOBALS['wpfonts_test_http_log'] ), 'Expected exactly 1 HTTP request.' );
$assert( 'POST' === $GLOBALS['wpfonts_test_http_log'][0]['method'], 'update-check must use POST.' );
$assert( 'https://updates.wenpai.net/api/v1/update-check' === $GLOBALS['wpfonts_test_http_log'][0]['url'], 'update-check URL mismatch.' );

// 4. Positive cache hit: second call should NOT issue another HTTP request.
$cached_update = apply_filters( 'update_plugins_updates.wenpai.net', false, array(), 'wpfonts/wpfonts.php', array( 'zh_CN' ) );
$assert( is_object( $cached_update ) && '2.0.0' === $cached_update->new_version, 'Cached update object mismatch.' );
$assert( 1 === count( $GLOBALS['wpfonts_test_http_log'] ), 'Second update check should hit transient cache without HTTP call.' );

// 5. Cache purge via upgrader_process_complete -> next call hits API again (2.0.1).
do_action(
	'upgrader_process_complete',
	new stdClass(),
	array(
		'action'  => 'update',
		'type'    => 'plugin',
		'plugins' => array( 'wpfonts/wpfonts.php' ),
	)
);
$assert( false === get_site_transient( WPFonts_Updater::UPDATE_CACHE_KEY ), 'upgrader_process_complete must purge update cache.' );

$GLOBALS['wpfonts_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode(
		array(
			'plugins' => array(
				'wpfonts/wpfonts.php' => array(
					'version' => '2.0.1',
					'package' => 'https://downloads.feicode.com/WenPai-org/wpfonts/releases/download/v2.0.1/wpfonts-2.0.1.zip',
					'url'     => '',
				),
			),
		)
	),
);
$update_201 = apply_filters( 'update_plugins_updates.wenpai.net', false, array(), 'wpfonts/wpfonts.php', array() );
$assert( is_object( $update_201 ) && '2.0.1' === $update_201->new_version, 'Expected 2.0.1 update after cache purge.' );
$assert( WPFonts_Updater::PLUGIN_HOME_URL === $update_201->url, 'Empty remote URL must fall back to PLUGIN_HOME_URL.' );
$assert( 2 === count( $GLOBALS['wpfonts_test_http_log'] ), 'Expected second HTTP call after cache purge.' );

// 6. Unrelated plugin_file passes through untouched without HTTP call.
$other = apply_filters( 'update_plugins_updates.wenpai.net', false, array(), 'other-plugin/other-plugin.php', array() );
$assert( false === $other && 2 === count( $GLOBALS['wpfonts_test_http_log'] ), 'Other plugin basename must pass through untouched.' );

// 7. Remote version <= current version returns false/unchanged (1.1.0, 2.0.0-rc.22, 2.0.0-rc.21, empty plugins).
foreach ( array( '1.1.0', '2.0.0-rc.22', '2.0.0-rc.21' ) as $older_version ) {
	$updater->clear_cache();
	$GLOBALS['wpfonts_test_http_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body'     => json_encode(
			array(
				'plugins' => array(
					'wpfonts/wpfonts.php' => array(
						'version' => $older_version,
						'package' => 'https://downloads.feicode.com/WenPai-org/wpfonts/releases/download/v' . $older_version . '/wpfonts.zip',
					),
				),
			)
		),
	);
	$res = apply_filters( 'update_plugins_updates.wenpai.net', false, array(), 'wpfonts/wpfonts.php', array() );
	$assert( false === $res, 'Expected false when remote version ' . $older_version . ' <= 2.0.0-rc.22.' );
}

$updater->clear_cache();
$GLOBALS['wpfonts_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode(
		array(
			'plugins'   => array(),
			'no_update' => array(
				'wpfonts/wpfonts.php' => array( 'version' => '1.1.0' ),
			),
		)
	),
);
$res_no_update = apply_filters( 'update_plugins_updates.wenpai.net', false, array(), 'wpfonts/wpfonts.php', array() );
$assert( false === $res_no_update, 'Expected false when API responds with empty plugins and no_update.' );

// 8. Graceful degradation + negative caching on WP_Error, HTTP 404, HTTP 500, and invalid JSON.
$error_cases = array(
	'wp_error'     => new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ),
	'http_404'     => array( 'response' => array( 'code' => 404 ), 'body' => '{"error":"not_found"}' ),
	'http_500'     => array( 'response' => array( 'code' => 500 ), 'body' => 'Internal Server Error' ),
	'invalid_json' => array( 'response' => array( 'code' => 200 ), 'body' => 'not-valid-json{{' ),
);
foreach ( $error_cases as $label => $mock_response ) {
	$updater->clear_cache();
	$before_count                         = count( $GLOBALS['wpfonts_test_http_log'] );
	$GLOBALS['wpfonts_test_http_queue'][] = $mock_response;

	$first = apply_filters( 'update_plugins_updates.wenpai.net', false, array(), 'wpfonts/wpfonts.php', array() );
	$assert( false === $first, 'Failure case ' . $label . ' must return false unchanged.' );
	$assert( ( $before_count + 1 ) === count( $GLOBALS['wpfonts_test_http_log'] ), 'Failure case ' . $label . ' expected 1 HTTP call.' );

	$cached_entry = $GLOBALS['wpfonts_test_transients'][ WPFonts_Updater::UPDATE_CACHE_KEY ] ?? null;
	$assert(
		is_array( $cached_entry ) && 'error' === ( $cached_entry['value']['status'] ?? '' ) && WPFonts_Updater::FAILURE_CACHE_TTL === ( $cached_entry['ttl'] ?? 0 ),
		'Failure case ' . $label . ' must store negative cache with FAILURE_CACHE_TTL.'
	);

	// Immediate retry must hit negative cache and skip HTTP.
	$second = apply_filters( 'update_plugins_updates.wenpai.net', false, array(), 'wpfonts/wpfonts.php', array() );
	$assert( false === $second && ( $before_count + 1 ) === count( $GLOBALS['wpfonts_test_http_log'] ), 'Failure case ' . $label . ' retry must hit negative cache.' );
}

// 9. Custom API URL via wpfonts_updater_api_url filter.
$updater->clear_cache();
add_filter(
	'wpfonts_updater_api_url',
	static function () {
		return 'https://mirror.example.test/api/v1/';
	}
);
$GLOBALS['wpfonts_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'plugins' => array() ) ),
);
apply_filters( 'update_plugins_updates.wenpai.net', false, array(), 'wpfonts/wpfonts.php', array() );
$last_req = end( $GLOBALS['wpfonts_test_http_log'] );
$assert( 'https://mirror.example.test/api/v1/update-check' === $last_req['url'], 'wpfonts_updater_api_url filter did not override endpoint URL.' );

// 10. plugins_api: formatted plugin info for wpfonts + pass-through for other slugs/actions.
$reset_env();
$updater                              = new WPFonts_Updater( 'wpfonts/wpfonts.php', '2.0.0-rc.22' );
$GLOBALS['wpfonts_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode(
		array(
			'name'          => '文派字库',
			'slug'          => 'wpfonts',
			'version'       => '2.0.0',
			'author'        => 'WenPai',
			'homepage'      => 'https://wpcy.com/wpfonts',
			'download_link' => 'https://downloads.feicode.com/WenPai-org/wpfonts/releases/download/v2.0.0/wpfonts-2.0.0.zip',
			'requires'      => '6.5',
			'tested'        => '7.1',
			'requires_php'  => '7.4',
			'last_updated'  => '2026-11-22',
			'icons'         => array(
				'1x'  => 'https://updates.wenpai.net/assets/wpfonts/icon-128x128.png',
				'svg' => 'https://updates.wenpai.net/assets/wpfonts/icon-256x256.svg',
			),
			'banners'       => array(
				'low' => 'https://updates.wenpai.net/assets/wpfonts/banner-772x250.png',
			),
			'sections'      => array(
				'description' => '中日韩系统字体与文风云字体。',
				'changelog'   => "## WPFonts v2.0.0\n\n### What's Changed\n\n- Switch updater to **updates.wenpai.net** (`WPFonts_Updater`)\n- See [WPCY](https://wpcy.com/wpfonts)\n",
			),
		)
	),
);

$other_slug = apply_filters( 'plugins_api', false, 'plugin_information', (object) array( 'slug' => 'akismet' ) );
$assert( false === $other_slug && 0 === count( $GLOBALS['wpfonts_test_http_log'] ), 'Other slug must pass through plugins_api untouched.' );

$other_action = apply_filters( 'plugins_api', false, 'query_plugins', (object) array( 'slug' => 'wpfonts' ) );
$assert( false === $other_action && 0 === count( $GLOBALS['wpfonts_test_http_log'] ), 'Non-plugin_information action must pass through plugins_api untouched.' );

$info = apply_filters( 'plugins_api', false, 'plugin_information', (object) array( 'slug' => 'wpfonts' ) );
$assert( is_object( $info ), 'plugins_api must return an object for wpfonts.' );
$assert( '文派字库' === $info->name && 'wpfonts' === $info->slug && '2.0.0' === $info->version, 'plugins_api core fields mismatch.' );
$assert( true === $info->external, 'plugins_api external flag must be true.' );
$assert( ! isset( $info->icons['svg'] ) && isset( $info->icons['1x'] ), 'plugins_api icons normalization failed.' );
$assert( false !== strpos( $info->sections['changelog'], '<strong>updates.wenpai.net</strong>' ), 'Changelog markdown bold conversion failed.' );
$assert( false !== strpos( $info->sections['changelog'], '<code>WPFonts_Updater</code>' ), 'Changelog markdown code conversion failed.' );
$assert( false !== strpos( $info->sections['changelog'], '<a href="https://wpcy.com/wpfonts">WPCY</a>' ), 'Changelog markdown link conversion failed.' );
$assert( 1 === count( $GLOBALS['wpfonts_test_http_log'] ) && 'https://updates.wenpai.net/api/v1/plugins/wpfonts/info' === $GLOBALS['wpfonts_test_http_log'][0]['url'], 'plugins_api GET URL mismatch.' );

// plugins_api local fallback when remote returns 404.
$updater->clear_cache();
$GLOBALS['wpfonts_test_http_queue'][] = array(
	'response' => array( 'code' => 404 ),
	'body'     => '{"error":"not_found"}',
);
$fallback = apply_filters( 'plugins_api', false, 'plugin_information', (object) array( 'slug' => 'wpfonts' ) );
$assert( is_object( $fallback ) && 'WPFonts' === $fallback->name && '2.0.0-rc.22' === $fallback->version, 'plugins_api local header fallback failed on 404.' );

// 11. WP_HTTP_BLOCK_EXTERNAL enforcement (defined at end of test so earlier tests are unaffected).
$updater->clear_cache();
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'WP_ACCESSIBLE_HOSTS', 'api.example.test' );
$before_blocked = count( $GLOBALS['wpfonts_test_http_log'] );
$blocked_res    = apply_filters( 'update_plugins_updates.wenpai.net', false, array(), 'wpfonts/wpfonts.php', array() );
$assert( false === $blocked_res && $before_blocked === count( $GLOBALS['wpfonts_test_http_log'] ), 'WP_HTTP_BLOCK_EXTERNAL must skip HTTP call when updates.wenpai.net is not in WP_ACCESSIBLE_HOSTS.' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

printf( "PASS: WPFonts_Updater verified (%d assertions: Update URI, update-check, semver RC comparison, caching, purge, fail-open, plugins_api, WP_HTTP_BLOCK_EXTERNAL).\n", $checks );
