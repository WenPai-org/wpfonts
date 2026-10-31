<?php
/**
 * Allowlisted Windfonts stylesheet URLs.
 *
 * Public CSS is https://{cn|hk|en}.windfonts.com/api/css . The live API
 * returns JSON, not CSS, unless family is set, so a font short name or a
 * project slug is required before the front prints a link. The API key is
 * never read here.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and checks stylesheet URLs.
 */
class WPFonts_Link {

	/**
	 * Region id => hostname. Nothing else is allowed.
	 *
	 * @var array<string, string>
	 */
	const HOSTS = array(
		'cn' => 'cn.windfonts.com',
		'hk' => 'hk.windfonts.com',
		'en' => 'en.windfonts.com',
	);

	/**
	 * Subset ids the public CSS API accepts.
	 *
	 * @var string[]
	 */
	const SUBSETS = array( 'en', 'zh-common', 'zh', 'full' );

	/**
	 * Fixed health-check URL. CDN hosts do not serve this path.
	 *
	 * @var string
	 */
	const KEY_STATUS_URL = 'https://app.windfonts.com/api/key/status';

	/**
	 * Console where fonts are uploaded. The plugin does not upload.
	 *
	 * @var string
	 */
	const CONSOLE_URL = 'https://app.windfonts.com';

	/**
	 * WenFeng site home. Footer link only; not an upload button.
	 *
	 * @var string
	 */
	const SITE_URL = 'https://wenfeng.org';

	/**
	 * Keep a region id, or fall back to mainland China.
	 *
	 * @param mixed $value Raw region.
	 * @return string
	 */
	public static function region( $value ) {
		$value = strtolower( (string) $value );
		return isset( self::HOSTS[ $value ] ) ? $value : 'cn';
	}

	/**
	 * Keep a known subset id.
	 *
	 * @param mixed $value Raw subset.
	 * @return string
	 */
	public static function subset( $value ) {
		$value = strtolower( (string) $value );
		return in_array( $value, self::SUBSETS, true ) ? $value : 'zh-common';
	}

	/**
	 * Keep a short slug token: letters, digits, hyphens.
	 *
	 * @param mixed $value Raw token.
	 * @param int   $max   Maximum length.
	 * @return string
	 */
	public static function token( $value, $max ) {
		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9-]/', '', $value );
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value, '-' );
		$max   = (int) $max;
		if ( $max > 0 && strlen( $value ) > $max ) {
			$value = rtrim( substr( $value, 0, $max ), '-' );
		}
		return $value;
	}

	/**
	 * Hostname for a region id.
	 *
	 * @param mixed $region Region id.
	 * @return string
	 */
	public static function host( $region ) {
		$region = self::region( $region );
		return self::HOSTS[ $region ];
	}

	/**
	 * Project slug, or empty.
	 *
	 * @param array $settings Settings row.
	 * @return string
	 */
	public static function project_slug( array $settings ) {
		$raw = isset( $settings['project_slug'] ) ? $settings['project_slug'] : '';
		return self::token( $raw, 64 );
	}

	/**
	 * Font short name, or empty.
	 *
	 * @param array $settings Settings row.
	 * @return string
	 */
	public static function family( array $settings ) {
		$raw = isset( $settings['family'] ) ? $settings['family'] : '';
		return self::token( $raw, 80 );
	}

	/**
	 * Whether the saved names can point at a real stylesheet.
	 *
	 * @param array $settings Settings row.
	 * @return bool
	 */
	public static function has_target( array $settings ) {
		return '' !== self::project_slug( $settings ) || '' !== self::family( $settings );
	}

	/**
	 * Candidate stylesheet URL. Empty only when the host cannot be built.
	 *
	 * A public URL without a font short name is still returned so the admin
	 * can explain it. embed_url() refuses to print that URL on the front.
	 *
	 * @param array $settings Settings row.
	 * @return string
	 */
	public static function stylesheet_url( array $settings ) {
		$host = self::host( isset( $settings['region'] ) ? $settings['region'] : 'cn' );
		$slug = self::project_slug( $settings );
		if ( '' !== $slug ) {
			return 'https://' . $host . '/p/' . rawurlencode( $slug ) . '/index.css';
		}

		$subset = self::subset( isset( $settings['subset'] ) ? $settings['subset'] : 'zh-common' );
		$family = self::family( $settings );
		$query  = 'subset=' . rawurlencode( $subset );
		if ( '' !== $family ) {
			$query = 'family=' . rawurlencode( $family ) . '&' . $query;
		}
		return 'https://' . $host . '/api/css?' . $query;
	}

	/**
	 * Stylesheet URL safe to print on the front, or empty.
	 *
	 * @param array $settings Settings row. May include api_key; it is ignored.
	 * @return string
	 */
	public static function embed_url( array $settings ) {
		if ( empty( $settings['cdn_enabled'] ) ) {
			return '';
		}
		$status = isset( $settings['key_status'] ) ? (string) $settings['key_status'] : '';
		if ( 'valid' !== $status ) {
			return '';
		}
		if ( ! self::has_target( $settings ) ) {
			return '';
		}
		$url = self::stylesheet_url( $settings );
		return self::is_allowlisted( $url ) ? $url : '';
	}

	/**
	 * Whether a URL is an https stylesheet on one of the three hosts.
	 *
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	public static function is_allowlisted( $url ) {
		// wp_parse_url() is not available when the CLI allowlist test loads this file.
		$parts = parse_url( (string) $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Same shape for these https URLs, and the CLI test has no WordPress.
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( 'https' !== $parts['scheme'] ) {
			return false;
		}
		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ! empty( $parts['port'] ) ) {
			return false;
		}
		$host = strtolower( (string) $parts['host'] );
		if ( ! in_array( $host, self::HOSTS, true ) ) {
			return false;
		}
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		if ( false !== strpos( $path, '..' ) || false !== strpos( $path, '\\' ) ) {
			return false;
		}
		$public  = '/api/css' === $path;
		$project = (bool) preg_match( '#^/p/[a-z0-9-]+/index\\.css$#', $path );
		if ( ! $public && ! $project ) {
			return false;
		}
		if ( ! empty( $parts['query'] ) && preg_match( '/(^|&)(api[_-]?key|key|token|authorization)=/i', (string) $parts['query'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Merge a settings form onto the saved public row.
	 *
	 * key_status from the request is ignored. The API key is not part of this row.
	 *
	 * @param array  $prev    Saved public settings.
	 * @param array  $input   Submitted fields.
	 * @param string $surface simple|advanced.
	 * @return array
	 */
	public static function merge_public( array $prev, array $input, $surface ) {
		$surface = ( 'advanced' === $surface ) ? 'advanced' : 'simple';
		$status  = isset( $prev['key_status'] ) ? (string) $prev['key_status'] : '';
		if ( ! in_array( $status, array( '', 'valid', 'invalid', 'unreachable' ), true ) ) {
			$status = '';
		}
		$next = array(
			'cdn_enabled'  => ( isset( $input['cdn_enabled'] ) && '1' === (string) $input['cdn_enabled'] ) ? 1 : 0,
			'region'       => self::region( isset( $input['region'] ) ? $input['region'] : '' ),
			'subset'       => self::subset( isset( $input['subset'] ) ? $input['subset'] : '' ),
			'project_slug' => isset( $prev['project_slug'] ) ? self::token( $prev['project_slug'], 64 ) : '',
			'family'       => isset( $prev['family'] ) ? self::token( $prev['family'], 80 ) : '',
			'key_status'   => $status,
			'key_checked'  => isset( $prev['key_checked'] ) ? (int) $prev['key_checked'] : 0,
		);
		if ( 'advanced' === $surface ) {
			$next['project_slug'] = self::token( isset( $input['project_slug'] ) ? $input['project_slug'] : '', 64 );
			$next['family']       = self::token( isset( $input['family'] ) ? $input['family'] : '', 80 );
		}
		return $next;
	}
}
