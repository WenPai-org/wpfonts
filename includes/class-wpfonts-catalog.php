<?php
/**
 * Read-only Windfonts font catalog.
 *
 * Admin only. The front never calls this. Results are cached; a failed
 * request does not replace a cached page. A forced refresh waits out a
 * short cooldown.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches GET /api/fonts and keeps fontFamily names for the settings screen.
 */
class WPFonts_Catalog {

	/**
	 * Catalog endpoint. Anonymous reads are allowed; a 401 is retried with the saved key.
	 *
	 * @var string
	 */
	const FONTS_URL = 'https://app.windfonts.com/api/fonts';

	/**
	 * Success cache lifetime: 8 hours (inside the 6–12 hour window).
	 *
	 * @var int
	 */
	const TTL = 28800;

	/**
	 * HTTP timeout in seconds. Short, so a dead directory does not stall the screen.
	 *
	 * @var int
	 */
	const TIMEOUT = 6;

	/**
	 * Minimum seconds between forced refreshes.
	 *
	 * @var int
	 */
	const REFRESH_COOLDOWN = 60;

	/**
	 * Transient that stores the last forced-refresh timestamp.
	 *
	 * @var string
	 */
	const REFRESH_KEY = 'wpfonts_cat_refresh';

	/**
	 * Read one catalog page.
	 *
	 * @param int    $page   Page number, starting at 1.
	 * @param string $search Optional search sent as the API search parameter.
	 * @param bool   $force  Bypass a fresh cache. Still cooled down, and a failure keeps the old cache.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool}
	 */
	public static function read( $page, $search = '', $force = false ) {
		$page   = self::page_number( $page );
		$search = self::clean_search( $search );
		if ( defined( 'WPFONTS_FIXTURE' ) && isset( $GLOBALS['wpfonts_catalog_fixture'] ) && is_array( $GLOBALS['wpfonts_catalog_fixture'] ) ) {
			return self::normalize( $GLOBALS['wpfonts_catalog_fixture'] );
		}
		if ( self::denied() ) {
			return self::failed( $page, 0 );
		}

		$key    = self::cache_key( $page, $search );
		$cached = self::cached_payload( $key );
		$force  = (bool) $force;
		if ( ! $force && null !== $cached ) {
			$cached['cached'] = true;
			return $cached;
		}
		if ( $force && self::refresh_cooling() ) {
			if ( null !== $cached ) {
				$cached['cached']  = true;
				$cached['cooling'] = true;
				return $cached;
			}
			$failed            = self::failed( $page, 0 );
			$failed['cooling'] = true;
			return $failed;
		}

		if ( $force ) {
			self::mark_refresh();
		}
		$fresh = self::fetch( $page, $search );
		if ( ! empty( $fresh['ok'] ) ) {
			if ( function_exists( 'set_transient' ) ) {
				set_transient( $key, self::store_shape( $fresh ), self::TTL );
			}
			return $fresh;
		}
		if ( null !== $cached ) {
			$cached['cached'] = true;
			$cached['stale']  = true;
			return $cached;
		}
		return $fresh;
	}

	/**
	 * Parse a catalog body. Public so tests can check fontFamily without HTTP.
	 *
	 * @param string $body Response body.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool}
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

		$fonts = array();
		foreach ( $list as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$family = WPFonts_Link::token( isset( $row['fontFamily'] ) ? $row['fontFamily'] : '', 80 );
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

		return array(
			'ok'        => true,
			'fonts'     => $fonts,
			'page'      => isset( $data['page'] ) ? max( 1, (int) $data['page'] ) : 1,
			'pageTotal' => isset( $data['pageTotal'] ) ? max( 0, (int) $data['pageTotal'] ) : 0,
			'total'     => isset( $data['total'] ) ? max( 0, (int) $data['total'] ) : count( $fonts ),
			'code'      => 200,
			'cached'    => false,
			'cooling'   => false,
			'stale'     => false,
		);
	}

	/**
	 * Whether this request must not touch the directory.
	 *
	 * The front is refused here as well as by never calling read() from the
	 * public enqueue. Admin AJAX counts as admin.
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
	 * Anonymous GET, then one retry with the saved key when the directory says 401.
	 *
	 * @param int    $page   Page number.
	 * @param string $search Clean search.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool}
	 */
	private static function fetch( $page, $search ) {
		$first = self::remote( $page, $search, '' );
		if ( ! empty( $first['ok'] ) || 401 !== (int) $first['code'] ) {
			return $first;
		}
		$key = self::api_key();
		if ( '' === $key ) {
			return $first;
		}
		return self::remote( $page, $search, $key );
	}

	/**
	 * One HTTP GET. The key is a header, never part of the URL.
	 *
	 * @param int    $page   Page number.
	 * @param string $search Clean search.
	 * @param string $key    API key, or empty for an anonymous read.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool}
	 */
	private static function remote( $page, $search, $key ) {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return self::failed( $page, 0 );
		}
		$query = array( 'page' => $page );
		if ( '' !== $search ) {
			$query['search'] = $search;
		}
		$url     = add_query_arg( $query, self::FONTS_URL );
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
				'limit_response_size' => 262144,
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
			$parsed['page'] = $page;
			$parsed['code'] = $code;
		}
		return $parsed;
	}

	/**
	 * Saved API key, or empty. Not written into the cache or the URL.
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
	 * A previously stored successful page, or null.
	 *
	 * @param string $key Transient name.
	 * @return array|null
	 */
	private static function cached_payload( $key ) {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}
		$cached = get_transient( $key );
		if ( ! is_array( $cached ) || empty( $cached['ok'] ) || ! isset( $cached['fonts'] ) || ! is_array( $cached['fonts'] ) ) {
			return null;
		}
		return self::normalize( $cached );
	}

	/**
	 * Drop request-only flags before writing the cache.
	 *
	 * @param array $payload Successful read.
	 * @return array
	 */
	private static function store_shape( array $payload ) {
		unset( $payload['cached'], $payload['cooling'], $payload['stale'] );
		$payload['cached']  = false;
		$payload['cooling'] = false;
		$payload['stale']   = false;
		return $payload;
	}

	/**
	 * Fill keys the screen expects.
	 *
	 * @param array $payload Partial payload.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool}
	 */
	private static function normalize( array $payload ) {
		$fonts = array();
		if ( isset( $payload['fonts'] ) && is_array( $payload['fonts'] ) ) {
			foreach ( $payload['fonts'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$family = WPFonts_Link::token( isset( $row['fontFamily'] ) ? $row['fontFamily'] : '', 80 );
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
		}
		$page = isset( $payload['page'] ) ? max( 1, (int) $payload['page'] ) : 1;
		return array(
			'ok'        => ! empty( $payload['ok'] ),
			'fonts'     => $fonts,
			'page'      => $page,
			'pageTotal' => isset( $payload['pageTotal'] ) ? max( 0, (int) $payload['pageTotal'] ) : 0,
			'total'     => isset( $payload['total'] ) ? max( 0, (int) $payload['total'] ) : count( $fonts ),
			'code'      => isset( $payload['code'] ) ? (int) $payload['code'] : 0,
			'cached'    => ! empty( $payload['cached'] ),
			'cooling'   => ! empty( $payload['cooling'] ),
			'stale'     => ! empty( $payload['stale'] ),
		);
	}

	/**
	 * Failed read. Fonts stay an empty list; callers still render the manual field.
	 *
	 * @param int $page Page number.
	 * @param int $code HTTP status, or 0 when there was no response.
	 * @return array{ok:bool,fonts:array<int,array{name:string,fontFamily:string}>,page:int,pageTotal:int,total:int,code:int,cached:bool,cooling:bool,stale:bool}
	 */
	private static function failed( $page, $code ) {
		return array(
			'ok'        => false,
			'fonts'     => array(),
			'page'      => self::page_number( $page ),
			'pageTotal' => 0,
			'total'     => 0,
			'code'      => (int) $code,
			'cached'    => false,
			'cooling'   => false,
			'stale'     => false,
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
		if ( $page > 50 ) {
			return 50;
		}
		return $page;
	}

	/**
	 * Search text for the query string. Not a font token: Chinese names must survive.
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
	 * Cache key. The page and the search are both part of it, so a miss cannot reuse another page.
	 *
	 * @param int    $page   Page number.
	 * @param string $search Clean search.
	 * @return string
	 */
	private static function cache_key( $page, $search ) {
		return 'wpfonts_cat_' . $page . '_' . substr( hash( 'sha256', (string) $search ), 0, 16 );
	}

	/**
	 * Whether a forced refresh has to wait.
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
	 * Start the forced-refresh cooldown.
	 *
	 * @return void
	 */
	private static function mark_refresh() {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::REFRESH_KEY, time(), self::REFRESH_COOLDOWN );
		}
	}
}
