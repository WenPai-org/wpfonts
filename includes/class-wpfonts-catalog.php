<?php
/**
 * Local Windfonts font catalog.
 *
 * The settings screen reads one JSON snapshot and does not call the directory
 * on a normal render. WP-Cron rewrites that file about once a month. The
 * settings button can refresh it sooner, with a short cooldown. A failed
 * refresh leaves a good snapshot in place.
 *
 * Snapshot path: wp-content/uploads/wpfonts/catalog.json
 * Shape: { "updated_at": unix, "fonts": [ { "name": "…", "fontFamily": "wenfeng-…" } ] }
 *
 * Page transients from older builds are not read.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores fontFamily names for the settings screen.
 */
class WPFonts_Catalog {

	/**
	 * Catalog endpoint. Anonymous reads are allowed; a 401 is retried with the saved key.
	 *
	 * @var string
	 */
	const FONTS_URL = 'https://app.windfonts.com/api/fonts';

	/**
	 * Path under the uploads base directory.
	 *
	 * @var string
	 */
	const SNAPSHOT_RELATIVE = 'wpfonts/catalog.json';

	/**
	 * Cron hook that rewrites the snapshot.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'wpfonts_catalog_refresh';

	/**
	 * About thirty days. There is no core monthly schedule.
	 *
	 * @var int
	 */
	const MONTH_INTERVAL = 2592000;

	/**
	 * HTTP timeout in seconds. Short, so a dead directory does not stall cron.
	 *
	 * @var int
	 */
	const TIMEOUT = 6;

	/**
	 * Minimum seconds between manual refreshes.
	 *
	 * @var int
	 */
	const REFRESH_COOLDOWN = 60;

	/**
	 * Transient that stores the last manual-refresh timestamp.
	 *
	 * @var string
	 */
	const REFRESH_KEY = 'wpfonts_cat_refresh';

	/**
	 * Stop after this many pages instead of writing a truncated snapshot.
	 *
	 * @var int
	 */
	const MAX_PAGES = 100;

	/**
	 * Read the local snapshot. This does not call the directory.
	 *
	 * `$page` remains so existing callers keep working. The snapshot is the
	 * full list, so the argument does not select a remote page.
	 *
	 * @param int    $page   Ignored for remote requests.
	 * @param string $search Optional local filter. Matched against name and fontFamily.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int}
	 */
	public static function read( $page = 1, $search = '' ) {
		unset( $page );
		$search = self::clean_search( $search );
		if ( defined( 'WPFONTS_FIXTURE' ) && isset( $GLOBALS['wpfonts_catalog_fixture'] ) && is_array( $GLOBALS['wpfonts_catalog_fixture'] ) ) {
			$payload = self::normalize( $GLOBALS['wpfonts_catalog_fixture'] );
			if ( '' !== $search && ! empty( $payload['ok'] ) ) {
				$payload['fonts'] = self::filter_fonts( $payload['fonts'], $search );
				$payload['total'] = count( $payload['fonts'] );
			}
			return $payload;
		}
		if ( self::denied() ) {
			return self::failed();
		}
		$snapshot = self::load_snapshot();
		if ( null === $snapshot ) {
			return self::failed();
		}
		$fonts = $snapshot['fonts'];
		if ( '' !== $search ) {
			$fonts = self::filter_fonts( $fonts, $search );
		}
		return array(
			'ok'         => true,
			'fonts'      => $fonts,
			'page'       => 1,
			'pageTotal'  => 1,
			'total'      => count( $fonts ),
			'code'       => 200,
			'cached'     => true,
			'cooling'    => false,
			'stale'      => false,
			'updated_at' => $snapshot['updated_at'],
		);
	}

	/**
	 * Download every catalog page and replace the snapshot.
	 *
	 * Manual calls require an admin and wait out the cooldown. Cron calls
	 * ignore the cooldown. A failure returns the previous snapshot when one
	 * exists and does not write over it.
	 *
	 * @param bool $manual True for the settings button. False for WP-Cron.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int}
	 */
	public static function refresh( $manual = false ) {
		if ( $manual ) {
			if ( self::denied() ) {
				return self::failed();
			}
			if ( self::refresh_cooling() ) {
				return self::cooled();
			}
			self::mark_refresh();
		} elseif ( ! self::cron_context() ) {
			return self::failed();
		}

		$fresh = self::fetch_all();
		if ( empty( $fresh['ok'] ) ) {
			return self::keep_or_fail( $fresh );
		}
		$updated = self::save_snapshot( $fresh['fonts'] );
		if ( ! is_int( $updated ) ) {
			return self::keep_or_fail( self::failed() );
		}
		$fresh['updated_at'] = $updated;
		return $fresh;
	}

	/**
	 * Parse a catalog body. Public so tests can check fontFamily without HTTP.
	 *
	 * Rows without a wenfeng- fontFamily are dropped.
	 *
	 * @param string $body Response body.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int}
	 */
	public static function parse_body( $body ) {
		$empty = self::failed( 1, 0 );
		if ( ! is_string( $body ) || '' === $body ) {
			return $empty;
		}
		$json = json_decode( $body, true );
		if ( ! is_array( $json ) || ! isset( $json['code'] ) || 200 !== (int) $json['code'] ) {
			return $empty;
		}
		$data = isset( $json['data'] ) && is_array( $json['data'] ) ? $json['data'] : array();
		$list = isset( $data['dataList'] ) && is_array( $data['dataList'] ) ? $data['dataList'] : null;
		if ( null === $list ) {
			return $empty;
		}

		$fonts = self::rows_from_api( $list );
		return array(
			'ok'         => true,
			'fonts'      => $fonts,
			'page'       => isset( $data['page'] ) ? max( 1, (int) $data['page'] ) : 1,
			'pageTotal'  => isset( $data['pageTotal'] ) ? max( 0, (int) $data['pageTotal'] ) : 0,
			'total'      => isset( $data['total'] ) ? max( 0, (int) $data['total'] ) : count( $fonts ),
			'code'       => 200,
			'cached'     => false,
			'cooling'    => false,
			'stale'      => false,
			'updated_at' => 0,
		);
	}

	/**
	 * Add the monthly schedule when WordPress does not already have one.
	 *
	 * A shorter schedule under the same name is replaced so this plugin does
	 * not refresh more often than about once a month.
	 *
	 * @param array $schedules Registered schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$existing = 0;
		if ( isset( $schedules['monthly'] ) && is_array( $schedules['monthly'] ) && isset( $schedules['monthly']['interval'] ) ) {
			$existing = (int) $schedules['monthly']['interval'];
		}
		if ( self::interval_is_monthly( $existing ) ) {
			return $schedules;
		}
		$schedules['monthly'] = array(
			'interval' => self::MONTH_INTERVAL,
			'display'  => 'Once a month',
		);
		return $schedules;
	}

	/**
	 * Schedule the monthly refresh. Activation and later requests both call this.
	 *
	 * The first run waits a month. Opening settings does not fetch the directory.
	 *
	 * @return void
	 */
	public static function ensure_schedule() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_event( time() + self::MONTH_INTERVAL, 'monthly', self::CRON_HOOK );
	}

	/**
	 * Schedule on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::ensure_schedule();
	}

	/**
	 * Remove the catalog event on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback.
	 *
	 * @return void
	 */
	public static function run_cron() {
		self::refresh( false );
	}

	/**
	 * Absolute snapshot path, or empty when uploads are unavailable.
	 *
	 * Tests may set WPFONTS_CATALOG_PATH.
	 *
	 * @return string
	 */
	public static function snapshot_path() {
		if ( defined( 'WPFONTS_CATALOG_PATH' ) ) {
			$path = WPFONTS_CATALOG_PATH;
			return is_string( $path ) ? $path : '';
		}
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}
		$uploads = wp_upload_dir();
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		$base = (string) $uploads['basedir'];
		if ( function_exists( 'trailingslashit' ) ) {
			return trailingslashit( $base ) . self::SNAPSHOT_RELATIVE;
		}
		return rtrim( $base, '/\\' ) . '/' . self::SNAPSHOT_RELATIVE;
	}

	/**
	 * Whether this request must not touch the snapshot or the directory.
	 *
	 * @return bool
	 */
	private static function denied() {
		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return true;
		}
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_options' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * True when a non-manual refresh is allowed to call the directory.
	 *
	 * @return bool
	 */
	private static function cron_context() {
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return false;
	}

	/**
	 * Whether an interval is about thirty days.
	 *
	 * @param int $interval Seconds.
	 * @return bool
	 */
	private static function interval_is_monthly( $interval ) {
		$interval = (int) $interval;
		$min      = 28 * 86400;
		$max      = 32 * 86400;
		return $interval >= $min && $interval <= $max;
	}

	/**
	 * Pull every page. One failed page discards the partial list.
	 *
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int}
	 */
	private static function fetch_all() {
		$page       = 1;
		$page_total = 1;
		$fonts      = array();
		$seen       = array();
		$key        = '';

		do {
			$result = self::fetch_page( $page, $key );
			if ( ! empty( $result['authed'] ) ) {
				$key = self::api_key();
			}
			if ( empty( $result['ok'] ) ) {
				$result['fonts'] = array();
				return $result;
			}
			foreach ( $result['fonts'] as $row ) {
				if ( isset( $seen[ $row['fontFamily'] ] ) ) {
					continue;
				}
				$seen[ $row['fontFamily'] ] = true;
				$fonts[]                    = $row;
			}
			$page_total = isset( $result['pageTotal'] ) ? (int) $result['pageTotal'] : 0;
			if ( $page_total < 1 ) {
				break;
			}
			if ( $page >= self::MAX_PAGES && $page < $page_total ) {
				return self::failed( $page, 0 );
			}
			++$page;
		} while ( $page <= $page_total );

		if ( array() === $fonts ) {
			return self::failed( 1, 200 );
		}

		return array(
			'ok'         => true,
			'fonts'      => $fonts,
			'page'       => 1,
			'pageTotal'  => 1,
			'total'      => count( $fonts ),
			'code'       => 200,
			'cached'     => false,
			'cooling'    => false,
			'stale'      => false,
			'updated_at' => 0,
		);
	}

	/**
	 * One page. An empty key tries anonymous first, then the saved key on 401.
	 *
	 * @param int    $page Page number.
	 * @param string $key  API key, or empty to try anonymous first.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int,authed?:bool}
	 */
	private static function fetch_page( $page, $key ) {
		if ( '' !== $key ) {
			$result           = self::remote( $page, $key );
			$result['authed'] = true;
			return $result;
		}
		$first           = self::remote( $page, '' );
		$first['authed'] = false;
		if ( ! empty( $first['ok'] ) || 401 !== (int) $first['code'] ) {
			return $first;
		}
		$saved = self::api_key();
		if ( '' === $saved ) {
			return $first;
		}
		$second           = self::remote( $page, $saved );
		$second['authed'] = ! empty( $second['ok'] );
		return $second;
	}

	/**
	 * One HTTP GET. The key is a header, never part of the URL.
	 *
	 * @param int    $page Page number.
	 * @param string $key  API key, or empty for an anonymous read.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int}
	 */
	private static function remote( $page, $key ) {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return self::failed( $page, 0 );
		}
		$url     = add_query_arg(
			array(
				'page' => $page,
			),
			self::FONTS_URL
		);
		$headers = array(
			'Accept' => 'application/json',
		);
		if ( '' !== $key ) {
			$headers['X-API-Key'] = $key;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => self::TIMEOUT,
				'redirection'         => 0,
				'limit_response_size' => 2097152,
				'headers'             => $headers,
			)
		);
		if ( is_wp_error( $response ) ) {
			return self::failed( $page, 0 );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return self::failed( $page, $code );
		}
		$parsed = self::parse_body( (string) wp_remote_retrieve_body( $response ) );
		if ( empty( $parsed['ok'] ) ) {
			$parsed['page'] = self::page_number( $page );
			$parsed['code'] = $code;
		}
		return $parsed;
	}

	/**
	 * Saved API key, or empty. Not written into the snapshot or the URL.
	 *
	 * @return string
	 */
	private static function api_key() {
		if ( ! class_exists( 'WPFonts_Settings', false ) ) {
			return '';
		}
		$key = WPFonts_Settings::api_key();
		return is_string( $key ) ? $key : '';
	}

	/**
	 * Keep the previous snapshot after a failed pull.
	 *
	 * @param array $failed Failed pull.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int}
	 */
	private static function keep_or_fail( array $failed ) {
		$existing = self::load_snapshot();
		if ( null === $existing ) {
			$failed['fonts'] = array();
			return self::normalize( $failed );
		}
		return self::payload_from_snapshot(
			$existing,
			array(
				'cached' => true,
				'stale'  => true,
				'code'   => isset( $failed['code'] ) ? (int) $failed['code'] : 0,
			)
		);
	}

	/**
	 * Cooldown response. No directory request.
	 *
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int}
	 */
	private static function cooled() {
		$existing = self::load_snapshot();
		if ( null === $existing ) {
			$failed            = self::failed();
			$failed['cooling'] = true;
			return $failed;
		}
		return self::payload_from_snapshot(
			$existing,
			array(
				'cached'  => true,
				'cooling' => true,
			)
		);
	}

	/**
	 * Read and clean the snapshot. Null when there is no usable file.
	 *
	 * @return array{updated_at:int,fonts:array<int,array{name:string,fontFamily:string}>}|null
	 */
	private static function load_snapshot() {
		$path = self::snapshot_path();
		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}
		$raw = self::read_file( $path );
		if ( '' === $raw ) {
			return null;
		}
		$json = json_decode( $raw, true );
		if ( ! is_array( $json ) || ! isset( $json['fonts'] ) || ! is_array( $json['fonts'] ) ) {
			return null;
		}
		$fonts = self::rows_from_stored( $json['fonts'] );
		if ( array() === $fonts ) {
			return null;
		}
		return array(
			'updated_at' => isset( $json['updated_at'] ) ? (int) $json['updated_at'] : 0,
			'fonts'      => $fonts,
		);
	}

	/**
	 * Write the snapshot atomically. Returns the unix time, or false.
	 *
	 * @param array<int,array{name:string,fontFamily:string}> $fonts Clean rows.
	 * @return int|false
	 */
	private static function save_snapshot( array $fonts ) {
		$path = self::snapshot_path();
		if ( '' === $path || array() === $fonts || ! function_exists( 'wp_json_encode' ) ) {
			return false;
		}
		$dir = dirname( $path );
		if ( ! self::ensure_dir( $dir ) ) {
			return false;
		}
		$updated = time();
		$payload = array(
			'updated_at' => $updated,
			'fonts'      => array_values( $fonts ),
		);
		$flags   = JSON_UNESCAPED_UNICODE;
		if ( defined( 'JSON_UNESCAPED_SLASHES' ) ) {
			$flags |= JSON_UNESCAPED_SLASHES;
		}
		$json = wp_json_encode( $payload, $flags );
		if ( ! is_string( $json ) || '' === $json ) {
			return false;
		}
		$tmp = $path . '.tmp';
		if ( ! self::put_file( $tmp, $json . "\n" ) ) {
			return false;
		}
		if ( ! self::replace_file( $tmp, $path ) ) {
			self::delete_file( $tmp );
			return false;
		}
		return $updated;
	}

	/**
	 * Create the uploads subdirectory.
	 *
	 * @param string $dir Directory.
	 * @return bool
	 */
	private static function ensure_dir( $dir ) {
		if ( is_dir( $dir ) ) {
			return true;
		}
		if ( function_exists( 'wp_mkdir_p' ) ) {
			return (bool) wp_mkdir_p( $dir );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- CLI tests have no wp_mkdir_p().
		return mkdir( $dir, 0755, true );
	}

	/**
	 * Read a local file.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function read_file( $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local snapshot only. WP_Filesystem may prompt for FTP during cron.
		$raw = file_get_contents( $path );
		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * Write a local file.
	 *
	 * @param string $path     Path.
	 * @param string $contents Body.
	 * @return bool
	 */
	private static function put_file( $path, $contents ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local snapshot only. WP_Filesystem may prompt for FTP during cron.
		return false !== file_put_contents( $path, $contents, LOCK_EX );
	}

	/**
	 * Replace the snapshot with the temp file.
	 *
	 * @param string $from Temp path.
	 * @param string $to   Snapshot path.
	 * @return bool
	 */
	private static function replace_file( $from, $to ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic replace in the same uploads directory.
		return rename( $from, $to );
	}

	/**
	 * Remove a temp file.
	 *
	 * @param string $path Path.
	 * @return void
	 */
	private static function delete_file( $path ) {
		if ( ! is_file( $path ) ) {
			return;
		}
		if ( function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $path );
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- CLI tests have no wp_delete_file().
		unlink( $path );
	}

	/**
	 * Build the array the screen expects from a loaded snapshot.
	 *
	 * @param array{updated_at:int,fonts:array<int,array{name:string,fontFamily:string}>} $snapshot Snapshot.
	 * @param array                                                                       $flags    cached, cooling, stale, code.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int}
	 */
	private static function payload_from_snapshot( array $snapshot, array $flags ) {
		return array(
			'ok'         => true,
			'fonts'      => $snapshot['fonts'],
			'page'       => 1,
			'pageTotal'  => 1,
			'total'      => count( $snapshot['fonts'] ),
			'code'       => isset( $flags['code'] ) ? (int) $flags['code'] : 200,
			'cached'     => ! empty( $flags['cached'] ),
			'cooling'    => ! empty( $flags['cooling'] ),
			'stale'      => ! empty( $flags['stale'] ),
			'updated_at' => $snapshot['updated_at'],
		);
	}

	/**
	 * API rows to name + fontFamily. Drops anything that is not wenfeng-*.
	 *
	 * @param array $rows dataList.
	 * @return array<int,array{name:string,fontFamily:string}>
	 */
	private static function rows_from_api( array $rows ) {
		$fonts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$family = self::accepted_family( isset( $row['fontFamily'] ) ? $row['fontFamily'] : '' );
			if ( '' === $family ) {
				continue;
			}
			$name = self::plain_label( isset( $row['name'] ) ? $row['name'] : '' );
			if ( '' === $name ) {
				$name = $family;
			}
			$fonts[] = array(
				'name'       => $name,
				'fontFamily' => $family,
			);
		}
		return $fonts;
	}

	/**
	 * Stored rows, cleaned again in case the file was edited.
	 *
	 * @param array $rows Stored fonts.
	 * @return array<int,array{name:string,fontFamily:string}>
	 */
	private static function rows_from_stored( array $rows ) {
		$fonts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$family = self::accepted_family( isset( $row['fontFamily'] ) ? $row['fontFamily'] : '' );
			if ( '' === $family ) {
				continue;
			}
			$name = self::plain_label( isset( $row['name'] ) ? $row['name'] : '' );
			if ( '' === $name ) {
				$name = $family;
			}
			$fonts[] = array(
				'name'       => $name,
				'fontFamily' => $family,
			);
		}
		return $fonts;
	}

	/**
	 * fontFamily that is safe to store and to write into the settings field.
	 *
	 * @param mixed $value Raw family.
	 * @return string
	 */
	private static function accepted_family( $value ) {
		$family = WPFonts_Link::token( $value, 80 );
		if ( '' === $family || 0 !== strpos( $family, 'wenfeng-' ) ) {
			return '';
		}
		return $family;
	}

	/**
	 * Local name / fontFamily filter.
	 *
	 * @param array<int,array{name:string,fontFamily:string}> $fonts  Rows.
	 * @param string                                          $search Clean search.
	 * @return array<int,array{name:string,fontFamily:string}>
	 */
	private static function filter_fonts( array $fonts, $search ) {
		$out = array();
		foreach ( $fonts as $font ) {
			$name   = isset( $font['name'] ) ? (string) $font['name'] : '';
			$family = isset( $font['fontFamily'] ) ? (string) $font['fontFamily'] : '';
			if ( self::contains_text( $name, $search ) || self::contains_text( $family, $search ) ) {
				$out[] = $font;
			}
		}
		return $out;
	}

	/**
	 * Case-insensitive substring match. Chinese is matched as written.
	 *
	 * @param string $haystack Text.
	 * @param string $needle   Search.
	 * @return bool
	 */
	private static function contains_text( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $haystack, $needle );
		}
		return false !== stripos( $haystack, $needle );
	}

	/**
	 * Fill keys the screen expects.
	 *
	 * @param array $payload Partial payload.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int}
	 */
	private static function normalize( array $payload ) {
		$fonts = array();
		if ( isset( $payload['fonts'] ) && is_array( $payload['fonts'] ) ) {
			$fonts = self::rows_from_stored( $payload['fonts'] );
		}
		$page = isset( $payload['page'] ) ? max( 1, (int) $payload['page'] ) : 1;
		return array(
			'ok'         => ! empty( $payload['ok'] ) && array() !== $fonts,
			'fonts'      => $fonts,
			'page'       => $page,
			'pageTotal'  => isset( $payload['pageTotal'] ) ? max( 0, (int) $payload['pageTotal'] ) : 0,
			'total'      => isset( $payload['total'] ) ? max( 0, (int) $payload['total'] ) : count( $fonts ),
			'code'       => isset( $payload['code'] ) ? (int) $payload['code'] : 0,
			'cached'     => ! empty( $payload['cached'] ),
			'cooling'    => ! empty( $payload['cooling'] ),
			'stale'      => ! empty( $payload['stale'] ),
			'updated_at' => isset( $payload['updated_at'] ) ? (int) $payload['updated_at'] : 0,
		);
	}

	/**
	 * Failed read. Fonts stay an empty list; callers still render the manual field.
	 *
	 * @param int $page Page number.
	 * @param int $code HTTP status, or 0 when there was no response.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool,updated_at:int}
	 */
	private static function failed( $page = 1, $code = 0 ) {
		return array(
			'ok'         => false,
			'fonts'      => array(),
			'page'       => self::page_number( $page ),
			'pageTotal'  => 0,
			'total'      => 0,
			'code'       => (int) $code,
			'cached'     => false,
			'cooling'    => false,
			'stale'      => false,
			'updated_at' => 0,
		);
	}

	/**
	 * Keep a page index inside the range we are willing to request.
	 *
	 * @param mixed $page Raw page.
	 * @return int
	 */
	private static function page_number( $page ) {
		$page = (int) $page;
		if ( $page < 1 ) {
			return 1;
		}
		if ( $page > self::MAX_PAGES ) {
			return self::MAX_PAGES;
		}
		return $page;
	}

	/**
	 * Search text. Not a font token: Chinese names must survive.
	 *
	 * @param mixed $search Raw search.
	 * @return string
	 */
	private static function clean_search( $search ) {
		$search = trim( (string) $search );
		$search = preg_replace( '/[\x00-\x1F\x7F]/', '', $search );
		if ( ! is_string( $search ) ) {
			return '';
		}
		if ( function_exists( 'sanitize_text_field' ) ) {
			$search = sanitize_text_field( $search );
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $search, 0, 80 );
		}
		if ( strlen( $search ) > 80 ) {
			return substr( $search, 0, 80 );
		}
		return $search;
	}

	/**
	 * Visible font name. Tags are removed. fontFamily is handled separately.
	 *
	 * @param mixed $value Raw name.
	 * @return string
	 */
	private static function plain_label( $value ) {
		$value = trim( (string) $value );
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$value = wp_strip_all_tags( $value );
		} else {
			$value = strip_tags( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Fixture and CLI tests have no wp_strip_all_tags().
		}
		$value = preg_replace( '/\s+/u', ' ', $value );
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, 80 );
		}
		return $value;
	}

	/**
	 * Whether a manual refresh has to wait.
	 *
	 * @return bool
	 */
	private static function refresh_cooling() {
		if ( ! function_exists( 'get_transient' ) ) {
			return false;
		}
		$stamp = get_transient( self::REFRESH_KEY );
		if ( ! is_numeric( $stamp ) ) {
			return false;
		}
		return ( time() - (int) $stamp ) < self::REFRESH_COOLDOWN;
	}

	/**
	 * Start the manual-refresh cooldown.
	 *
	 * @return void
	 */
	private static function mark_refresh() {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::REFRESH_KEY, time(), self::REFRESH_COOLDOWN );
		}
	}
}
