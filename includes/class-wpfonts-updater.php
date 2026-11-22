<?php
/**
 * WenPai Bridge (`updates.wenpai.net`) native plugin updater for WPFonts.
 *
 * Uses WordPress 5.8+ `Update URI: https://updates.wenpai.net` via the
 * `update_plugins_updates.wenpai.net` and `plugins_api` filters.
 *
 * Cooperatively yields when WP-China-Yes centralized updates are active via
 * `wpfonts_updater_override` or `wenpai_updater_override`.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WPFonts_Updater' ) ) {
	return;
}

/**
 * Handles update checks and plugin details modal data against `updates.wenpai.net`.
 */
class WPFonts_Updater {

	/**
	 * Updater implementation version.
	 *
	 * @var string
	 */
	const VERSION = '1.2.0';

	/**
	 * Default WenPai Bridge API base URL.
	 *
	 * @var string
	 */
	const API_URL = 'https://updates.wenpai.net/api/v1';

	/**
	 * Default public plugin homepage URL.
	 *
	 * @var string
	 */
	const PLUGIN_HOME_URL = 'https://wpcy.com/wpfonts';

	/**
	 * Transient key for update-check responses.
	 *
	 * @var string
	 */
	const UPDATE_CACHE_KEY = 'wpfonts_updater_check_cache';

	/**
	 * Transient key for plugin-info responses.
	 *
	 * @var string
	 */
	const INFO_CACHE_KEY = 'wpfonts_updater_info_cache';

	/**
	 * Positive cache TTL in seconds (6 hours).
	 *
	 * @var int
	 */
	const CACHE_TTL = 21600;

	/**
	 * Negative cache TTL in seconds (10 minutes) for failed/404 requests.
	 *
	 * @var int
	 */
	const FAILURE_CACHE_TTL = 600;

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	const HTTP_TIMEOUT = 10;

	/**
	 * Plugin basename (e.g. `wpfonts/wpfonts.php`).
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin directory slug (e.g. `wpfonts`).
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Currently installed plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize updater hooks unless overridden by centralized WenPai updates.
	 *
	 * @param string $plugin_file Plugin basename (`plugin_basename( __FILE__ )`).
	 * @param string $version     Currently installed plugin version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file = (string) $plugin_file;
		$this->slug        = dirname( $this->plugin_file );
		$this->version     = (string) $version;

		if ( ! function_exists( 'apply_filters' ) || ! function_exists( 'add_filter' ) ) {
			return;
		}

		$is_overridden = (bool) apply_filters( 'wpfonts_updater_override', false, $this->slug );
		if ( ! $is_overridden ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WenPai ecosystem hook (wp-china-yes centralized updates).
			$is_overridden = (bool) apply_filters( 'wenpai_updater_override', false, $this->slug );
		}

		if ( ! $is_overridden ) {
			$this->register_hooks();
		}
	}

	/**
	 * Register WordPress update and plugin information hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_filter( 'update_plugins_updates.wenpai.net', array( $this, 'check_update' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

		if ( function_exists( 'add_action' ) ) {
			add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 2 );
		}
	}

	/**
	 * Check `updates.wenpai.net` for a newer plugin release.
	 *
	 * Triggered by WordPress 5.8+ when the plugin header sets
	 * `Update URI: https://updates.wenpai.net`.
	 *
	 * @param array|false $update      Existing update payload.
	 * @param array       $plugin_data Plugin header data.
	 * @param string      $plugin_file Plugin basename being checked.
	 * @param string[]    $locales     Installed locales.
	 * @return object|array|false Update object when newer version exists, otherwise `$update`.
	 */
	public function check_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $plugin_data, $locales );

		if ( $plugin_file !== $this->plugin_file ) {
			return $update;
		}

		$api_url = $this->get_api_url();
		$cached  = $this->get_cache( self::UPDATE_CACHE_KEY );
		$hit     = is_array( $cached )
			&& isset( $cached['status'], $cached['version'], $cached['api_url'] )
			&& $cached['version'] === $this->version
			&& $cached['api_url'] === $api_url;

		if ( $hit ) {
			if ( 'ok' !== $cached['status'] || ! isset( $cached['data'] ) || ! is_array( $cached['data'] ) ) {
				return $update;
			}
			$response = $cached['data'];
		} else {
			$response = $this->api_request(
				'update-check',
				array(
					'plugins' => array(
						$this->plugin_file => array(
							'Version' => $this->version,
						),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->set_cache(
					self::UPDATE_CACHE_KEY,
					array(
						'status'  => 'error',
						'version' => $this->version,
						'api_url' => $api_url,
					),
					self::FAILURE_CACHE_TTL
				);
				return $update;
			}

			$this->set_cache(
				self::UPDATE_CACHE_KEY,
				array(
					'status'  => 'ok',
					'version' => $this->version,
					'api_url' => $api_url,
					'data'    => $response,
				),
				self::CACHE_TTL
			);
		}

		if ( empty( $response['plugins'][ $this->plugin_file ] ) || ! is_array( $response['plugins'][ $this->plugin_file ] ) ) {
			return $update;
		}

		$data           = $response['plugins'][ $this->plugin_file ];
		$remote_version = isset( $data['version'] ) ? trim( (string) $data['version'] ) : '';
		if ( '' === $remote_version || ! version_compare( $remote_version, $this->version, '>' ) ) {
			return $update;
		}

		$media = self::normalize_update_assets(
			isset( $data['icons'] ) && is_array( $data['icons'] ) ? $data['icons'] : array(),
			isset( $data['banners'] ) && is_array( $data['banners'] ) ? $data['banners'] : array(),
			isset( $data['url'] ) ? (string) $data['url'] : ''
		);

		return (object) array(
			'id'           => isset( $data['id'] ) && '' !== (string) $data['id'] ? (string) $data['id'] : 'wenpai:plugin:' . $this->slug,
			'slug'         => isset( $data['slug'] ) && '' !== (string) $data['slug'] ? (string) $data['slug'] : $this->slug,
			'plugin'       => $this->plugin_file,
			'version'      => $remote_version,
			'new_version'  => $remote_version,
			'url'          => $media['url'],
			'package'      => isset( $data['package'] ) ? (string) $data['package'] : '',
			'icons'        => $media['icons'],
			'banners'      => $media['banners'],
			'requires'     => isset( $data['requires'] ) ? (string) $data['requires'] : '',
			'tested'       => isset( $data['tested'] ) ? (string) $data['tested'] : '',
			'requires_php' => isset( $data['requires_php'] ) ? (string) $data['requires_php'] : '',
		);
	}

	/**
	 * Provide plugin details modal data for WordPress `plugins_api`.
	 *
	 * @param false|object|array $result Current filter value.
	 * @param string             $action API action name.
	 * @param object             $args   API request arguments.
	 * @return false|object|array Formatted plugin info object or `$result`.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! is_object( $args ) || ! isset( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$api_url = $this->get_api_url();
		$cached  = $this->get_cache( self::INFO_CACHE_KEY );
		$hit     = is_array( $cached )
			&& isset( $cached['status'], $cached['api_url'] )
			&& $cached['api_url'] === $api_url;

		if ( $hit ) {
			$response = ( 'ok' === $cached['status'] && isset( $cached['data'] ) && is_array( $cached['data'] ) )
				? $cached['data']
				: new WP_Error( 'wpfonts_updater_cached_failure', 'Cached WenPai Bridge info failure.' );
		} else {
			$response = $this->api_request( 'plugins/' . $this->slug . '/info' );

			if ( is_wp_error( $response ) || ! empty( $response['error'] ) || empty( $response['name'] ) ) {
				$this->set_cache(
					self::INFO_CACHE_KEY,
					array(
						'status'  => 'error',
						'api_url' => $api_url,
					),
					self::FAILURE_CACHE_TTL
				);
				$response = new WP_Error( 'wpfonts_updater_info_unavailable', 'Plugin info unavailable.' );
			} else {
				$this->set_cache(
					self::INFO_CACHE_KEY,
					array(
						'status'  => 'ok',
						'api_url' => $api_url,
						'data'    => $response,
					),
					self::CACHE_TTL
				);
			}
		}

		if ( ! is_wp_error( $response ) ) {
			$media = self::normalize_update_assets(
				isset( $response['icons'] ) && is_array( $response['icons'] ) ? $response['icons'] : array(),
				isset( $response['banners'] ) && is_array( $response['banners'] ) ? $response['banners'] : array(),
				isset( $response['homepage'] ) ? (string) $response['homepage'] : ''
			);

			$raw_sections = isset( $response['sections'] ) && is_array( $response['sections'] ) ? $response['sections'] : array();
			$sections     = array();
			foreach ( $raw_sections as $section_key => $section_value ) {
				if ( is_string( $section_value ) ) {
					$sections[ (string) $section_key ] = $this->markdown_to_html( $section_value );
				}
			}

			$info                = new stdClass();
			$info->name          = (string) $response['name'];
			$info->slug          = isset( $response['slug'] ) && '' !== (string) $response['slug'] ? (string) $response['slug'] : $this->slug;
			$info->version       = isset( $response['version'] ) ? (string) $response['version'] : $this->version;
			$info->author        = isset( $response['author'] ) ? (string) $response['author'] : '';
			$info->homepage      = $media['url'];
			$info->download_link = isset( $response['download_link'] ) ? (string) $response['download_link'] : '';
			$info->requires      = isset( $response['requires'] ) ? (string) $response['requires'] : '';
			$info->tested        = isset( $response['tested'] ) ? (string) $response['tested'] : '';
			$info->requires_php  = isset( $response['requires_php'] ) ? (string) $response['requires_php'] : '';
			$info->last_updated  = isset( $response['last_updated'] ) ? (string) $response['last_updated'] : '';
			$info->icons         = $media['icons'];
			$info->banners       = $media['banners'];
			$info->sections      = $sections;
			$info->external      = true;

			return $info;
		}

		return $this->local_fallback_plugin_info( $result );
	}

	/**
	 * Purge cached update metadata after WordPress finishes upgrading plugins.
	 *
	 * @param object $upgrader WP_Upgrader instance.
	 * @param array  $options  Upgrader action options.
	 * @return void
	 */
	public function purge_cache( $upgrader, $options ) {
		unset( $upgrader );

		if ( ! is_array( $options ) ) {
			return;
		}

		$action = isset( $options['action'] ) ? (string) $options['action'] : '';
		$type   = isset( $options['type'] ) ? (string) $options['type'] : '';
		if ( 'plugin' !== $type || ! in_array( $action, array( 'update', 'install' ), true ) ) {
			return;
		}

		$matches = false;
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$matches = in_array( $this->plugin_file, $options['plugins'], true );
		} elseif ( ! empty( $options['plugin'] ) && is_string( $options['plugin'] ) ) {
			$matches = ( $this->plugin_file === $options['plugin'] );
		} else {
			$matches = true;
		}

		if ( $matches ) {
			$this->clear_cache();
		}
	}

	/**
	 * Delete both updater transients.
	 *
	 * @return void
	 */
	public function clear_cache() {
		$this->delete_cache( self::UPDATE_CACHE_KEY );
		$this->delete_cache( self::INFO_CACHE_KEY );
	}

	/**
	 * Normalize remote icons, banners, and plugin homepage URL.
	 *
	 * Drops `svg` icon keys (which WordPress prefers even when broken on the
	 * bridge) and discards non-HTTP(S) URLs.
	 *
	 * @param array  $icons   Icon map from API.
	 * @param array  $banners Banner map from API.
	 * @param string $url     Plugin URL from API.
	 * @return array{icons: array<string, string>, banners: array<string, string>, url: string}
	 */
	public static function normalize_update_assets( array $icons, array $banners = array(), $url = '' ) {
		unset( $icons['svg'] );

		$clean_icons = array();
		foreach ( $icons as $key => $value ) {
			$sanitized = self::sanitize_http_url( $value );
			if ( '' !== $sanitized ) {
				$clean_icons[ (string) $key ] = $sanitized;
			}
		}

		$clean_banners = array();
		foreach ( $banners as $key => $value ) {
			$sanitized = self::sanitize_http_url( $value );
			if ( '' !== $sanitized ) {
				$clean_banners[ (string) $key ] = $sanitized;
			}
		}

		$clean_url = self::sanitize_http_url( $url );
		if ( '' === $clean_url ) {
			$clean_url = self::PLUGIN_HOME_URL;
		}

		return array(
			'icons'   => $clean_icons,
			'banners' => $clean_banners,
			'url'     => $clean_url,
		);
	}

	/**
	 * Build a local fallback plugin info object when the remote API is unreachable.
	 *
	 * @param false|object|array $default Value to return if local metadata cannot be read.
	 * @return false|object|array
	 */
	private function local_fallback_plugin_info( $default ) {
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			return $default;
		}

		$plugin_path = rtrim( (string) WP_PLUGIN_DIR, '/\\' ) . '/' . $this->plugin_file;
		if ( ! is_readable( $plugin_path ) ) {
			return $default;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			$plugin_inc = rtrim( (string) ABSPATH, '/\\' ) . '/wp-admin/includes/plugin.php';
			if ( is_readable( $plugin_inc ) ) {
				require_once $plugin_inc;
			}
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			return $default;
		}

		$plugin_data = get_plugin_data( $plugin_path, false, false );
		if ( ! is_array( $plugin_data ) || empty( $plugin_data['Name'] ) ) {
			return $default;
		}

		$info               = new stdClass();
		$info->name         = (string) $plugin_data['Name'];
		$info->slug         = $this->slug;
		$info->version      = $this->version;
		$info->author       = isset( $plugin_data['AuthorName'] ) ? (string) $plugin_data['AuthorName'] : '';
		$info->homepage     = ! empty( $plugin_data['PluginURI'] ) ? (string) $plugin_data['PluginURI'] : self::PLUGIN_HOME_URL;
		$info->icons        = array();
		$info->banners      = array();
		$info->requires     = isset( $plugin_data['RequiresWP'] ) ? (string) $plugin_data['RequiresWP'] : '';
		$info->requires_php = isset( $plugin_data['RequiresPHP'] ) ? (string) $plugin_data['RequiresPHP'] : '';
		$info->sections     = array(
			'description' => isset( $plugin_data['Description'] ) ? (string) $plugin_data['Description'] : '',
		);
		$info->external     = true;

		return $info;
	}

	/**
	 * Resolve the API base URL, allowing override via `wpfonts_updater_api_url`.
	 *
	 * @return string
	 */
	private function get_api_url() {
		$url = self::API_URL;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpfonts_updater_api_url', $url, $this->slug );
			if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
				$url = trim( $filtered );
			}
		}

		return rtrim( $url, '/' );
	}

	/**
	 * Check whether external HTTP requests to the target URL are blocked.
	 *
	 * Mirrors WordPress `WP_HTTP_BLOCK_EXTERNAL` / `WP_ACCESSIBLE_HOSTS` rules.
	 *
	 * @param string $url Request URL.
	 * @return bool True when blocked.
	 */
	private function is_external_http_blocked( $url ) {
		if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
			return false;
		}

		$host = function_exists( 'wp_parse_url' )
			? wp_parse_url( $url, PHP_URL_HOST )
			: parse_url( $url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Fallback when wp_parse_url is unavailable.

		if ( ! is_string( $host ) || '' === $host ) {
			return true;
		}

		$host = strtolower( $host );

		if ( ! defined( 'WP_ACCESSIBLE_HOSTS' ) || ! is_string( WP_ACCESSIBLE_HOSTS ) || '' === trim( WP_ACCESSIBLE_HOSTS ) ) {
			return true;
		}

		$accessible_hosts = explode( ',', WP_ACCESSIBLE_HOSTS );
		foreach ( $accessible_hosts as $pattern ) {
			$pattern = strtolower( trim( $pattern ) );
			if ( '' === $pattern ) {
				continue;
			}
			if ( $pattern === $host ) {
				return false;
			}
			if ( 0 === strpos( $pattern, '*.' ) ) {
				$suffix = substr( $pattern, 1 );
				if ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Send a JSON request to the WenPai Bridge API.
	 *
	 * @param string     $endpoint Relative endpoint path without `/api/v1/` prefix.
	 * @param array|null $body     Optional POST JSON payload; null uses GET.
	 * @return array|WP_Error Decoded JSON array or WP_Error.
	 */
	private function api_request( $endpoint, $body = null ) {
		$url = $this->get_api_url() . '/' . ltrim( (string) $endpoint, '/' );

		if ( $this->is_external_http_blocked( $url ) ) {
			return new WP_Error(
				'wpfonts_updater_blocked',
				'External HTTP requests are blocked by WP_HTTP_BLOCK_EXTERNAL.'
			);
		}

		$args = array(
			'timeout' => self::HTTP_TIMEOUT,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);

		if ( null !== $body ) {
			if ( ! function_exists( 'wp_remote_post' ) ) {
				return new WP_Error( 'wpfonts_updater_no_http', 'WordPress HTTP API is unavailable.' );
			}
			$encoded                         = function_exists( 'wp_json_encode' ) ? wp_json_encode( $body ) : json_encode( $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Fallback when wp_json_encode is unavailable.
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = $encoded;
			$response                        = wp_remote_post( $url, $args );
		} else {
			if ( ! function_exists( 'wp_remote_get' ) ) {
				return new WP_Error( 'wpfonts_updater_no_http', 'WordPress HTTP API is unavailable.' );
			}
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'wpfonts_bridge_http_error',
				sprintf( 'WenPai Bridge API returned HTTP %d.', $code )
			);
		}

		$raw_body = (string) wp_remote_retrieve_body( $response );
		$data     = json_decode( $raw_body, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'wpfonts_bridge_parse_error',
				'Invalid JSON response from WenPai Bridge.'
			);
		}

		return $data;
	}

	/**
	 * Read a cached value from site transients (with transient fallback).
	 *
	 * @param string $key Transient key.
	 * @return mixed
	 */
	private function get_cache( $key ) {
		if ( function_exists( 'get_site_transient' ) ) {
			return get_site_transient( $key );
		}
		if ( function_exists( 'get_transient' ) ) {
			return get_transient( $key );
		}
		return false;
	}

	/**
	 * Store a cached value in site transients (with transient fallback).
	 *
	 * @param string $key   Transient key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Expiration in seconds.
	 * @return void
	 */
	private function set_cache( $key, $value, $ttl ) {
		if ( function_exists( 'set_site_transient' ) ) {
			set_site_transient( $key, $value, (int) $ttl );
			return;
		}
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, $value, (int) $ttl );
		}
	}

	/**
	 * Delete a cached value from site transients (with transient fallback).
	 *
	 * @param string $key Transient key.
	 * @return void
	 */
	private function delete_cache( $key ) {
		if ( function_exists( 'delete_site_transient' ) ) {
			delete_site_transient( $key );
			return;
		}
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $key );
		}
	}

	/**
	 * Sanitize an HTTP(S) URL string or return empty string.
	 *
	 * @param mixed $raw Candidate URL.
	 * @return string
	 */
	private static function sanitize_http_url( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$url = trim( $raw );
		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		if ( function_exists( 'esc_url_raw' ) ) {
			$url = esc_url_raw( $url, array( 'http', 'https' ) );
		}
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Convert simple Markdown sections from the bridge API into safe HTML.
	 *
	 * Supports headings (h2-h4), unordered lists, bold, italic, inline code,
	 * and HTTP(S) links. Existing HTML is sanitized with `wp_kses_post` when available.
	 *
	 * @param string $text Raw section text.
	 * @return string
	 */
	private function markdown_to_html( $text ) {
		$text = (string) $text;
		if ( '' === trim( $text ) ) {
			return '';
		}

		if ( 0 === strpos( ltrim( $text ), '<' ) ) {
			return function_exists( 'wp_kses_post' ) ? wp_kses_post( $text ) : $text;
		}

		$text = (string) preg_replace( '/---\s*\n\s*<!--\s*feicode-ai.*$/s', '', $text );
		$text = (string) preg_replace( '/^###?\s+Checksums\s*\n+(\|.*\n)+/mi', '', $text );
		$text = (string) preg_replace( '/^##\s+\S+\s+v[\d.]+[^\n]*\n+/m', '', $text );
		$text = (string) preg_replace( '/^###?\s+What\'s Changed\s*\n+/mi', '', $text );

		$lines = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $text ) );
		$html  = '';
		$in_ul = false;

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			if ( '' === $trimmed ) {
				if ( $in_ul ) {
					$html .= "</ul>\n";
					$in_ul = false;
				}
				continue;
			}

			if ( preg_match( '/^(#{2,4})\s+(.+)$/', $trimmed, $m ) ) {
				if ( $in_ul ) {
					$html .= "</ul>\n";
					$in_ul = false;
				}
				$level = strlen( $m[1] );
				$html .= sprintf( "<h%d>%s</h%d>\n", $level, $this->escape_html( $m[2] ), $level );
				continue;
			}

			if ( preg_match( '/^[-*]\s+(.+)$/', $trimmed, $m ) ) {
				if ( ! $in_ul ) {
					$html .= "<ul>\n";
					$in_ul = true;
				}
				$html .= '<li>' . $this->inline_markdown( $m[1] ) . "</li>\n";
				continue;
			}

			if ( $in_ul ) {
				$html .= "</ul>\n";
				$in_ul = false;
			}
			$html .= '<p>' . $this->inline_markdown( $trimmed ) . "</p>\n";
		}

		if ( $in_ul ) {
			$html .= "</ul>\n";
		}

		return $html;
	}

	/**
	 * Format inline Markdown spans safely.
	 *
	 * @param string $text Single line of Markdown.
	 * @return string
	 */
	private function inline_markdown( $text ) {
		$escaped = $this->escape_html( $text );
		$escaped = (string) preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped );
		$escaped = (string) preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $escaped );
		$escaped = (string) preg_replace( '/`(.+?)`/', '<code>$1</code>', $escaped );
		$escaped = (string) preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
			array( $this, 'format_markdown_link' ),
			$escaped
		);

		return $escaped;
	}

	/**
	 * Format a matched HTTP(S) Markdown link.
	 *
	 * @param array $matches Regex match array.
	 * @return string
	 */
	private function format_markdown_link( $matches ) {
		$href = self::sanitize_http_url( html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $href ) {
			return $matches[1];
		}
		return '<a href="' . $this->escape_html( $href ) . '">' . $matches[1] . '</a>';
	}

	/**
	 * Escape HTML text without requiring WordPress to be bootstrapped.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function escape_html( $text ) {
		if ( function_exists( 'esc_html' ) ) {
			return esc_html( $text );
		}
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
