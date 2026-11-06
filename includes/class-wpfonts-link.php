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
	 * License comment required before every generated CDN link.
	 *
	 * Storefront constant WFProto.CDN_LICENSE_NOTE. Do not paraphrase.
	 *
	 * @var string
	 */
	const CDN_LICENSE_NOTE = '<!-- 此中文网页字体由文风字体（Windfonts）免费提供，您可以自由引用，请务必保留此授权许可标注 https://wenfeng.org/license -->';

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
	 * Package short => CDN fonts-packages directory.
	 *
	 * CSS, Font Library family tokens, and package install ids stay
	 * wenfeng-*. Only the specimen URL path uses this alias.
	 *
	 * @var array<string, string>
	 */
	private const CDN_SPECIMEN_ALIASES = array(
		'xxzt'    => 'xzt',
		'syhtcjk' => 'syhtsc',
	);

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
	 * Lowercase short after wenfeng- for a package id.
	 *
	 * wenfeng-xxzt stays xxzt. The CSS id and the install id stay
	 * wenfeng-*. A zip-only id is empty, so it does not invent a CDN
	 * specimen. Specimen paths use package_cdn_norm(), which may point a
	 * historical short at another fonts-packages folder.
	 *
	 * @param mixed $slug Package id or catalog fontFamily.
	 * @return string
	 */
	public static function package_norm( $slug ) {
		$token  = self::token( $slug, 80 );
		$prefix = 'wenfeng-';
		if ( '' === $token || 0 !== strpos( $token, $prefix ) ) {
			return '';
		}
		return self::token( substr( $token, strlen( $prefix ) ), 64 );
	}

	/**
	 * CDN fonts-packages directory for a wenfeng-* slug.
	 *
	 * The raw short from package_norm() is kept when it already matches the
	 * CDN folder. A listed historical short is replaced with that folder.
	 *
	 * @param mixed $slug Package id or catalog fontFamily.
	 * @return string
	 */
	public static function package_cdn_norm( $slug ) {
		$norm = self::package_norm( $slug );
		if ( '' === $norm ) {
			return '';
		}
		if ( isset( self::CDN_SPECIMEN_ALIASES[ $norm ] ) ) {
			return self::token( self::CDN_SPECIMEN_ALIASES[ $norm ], 64 );
		}
		return $norm;
	}

	/**
	 * CDN specimen image for one wenfeng-* family.
	 *
	 * The host is WPFonts_Link::HOSTS for the site region, the same map as
	 * public CSS. Nothing else is allowed. The path is
	 * /fonts-packages/{cdn norm}/preview/{preview|card|og|fontpreview}.{webp|png}.
	 * The directory is package_cdn_norm(), so a historical short such as
	 * xxzt uses the CDN folder xzt. fontpreview is the long-text specimen
	 * (same 720×400 frame as preview). The row key for that file stays
	 * fontPreviewUrl. Ask for webp first. png is the sibling when webp is
	 * missing.
	 *
	 * @param mixed  $slug   Package id or catalog fontFamily.
	 * @param string $asset  preview, card, og, or fontpreview.
	 * @param string $format webp or png.
	 * @param mixed  $region Region id. Unknown values use cn.
	 * @return string
	 */
	public static function package_preview_url( $slug, $asset = 'preview', $format = 'webp', $region = 'cn' ) {
		$norm = self::package_cdn_norm( $slug );
		if ( '' === $norm ) {
			return '';
		}
		$asset = self::specimen_asset( $asset );
		if ( '' === $asset ) {
			return '';
		}
		$format = strtolower( (string) $format );
		if ( ! in_array( $format, array( 'webp', 'png' ), true ) ) {
			return '';
		}
		return 'https://' . self::host( $region ) . '/fonts-packages/' . $norm . '/preview/' . $asset . '.' . $format;
	}

	/**
	 * previewUrl, cardPreviewUrl, and fontPreviewUrl for a slug, preferring webp.
	 *
	 * Derived from the CDN folder (package_cdn_norm()). A row may already
	 * carry previewUrl, cardPreviewUrl, fontPreviewUrl, or the same keys
	 * under assets. A supplied webp replaces the derived URL only when it
	 * is https, on an existing CDN host, for this same CDN folder, and has
	 * no query. A raw historical short in that path is not kept. A supplied
	 * png does not replace webp. The catalog snapshot itself stays name +
	 * fontFamily; this is the read path for a row that already has the fields.
	 * fontPreviewUrl is the long-text specimen at fontpreview.webp. It does
	 * not replace previewUrl. The installed package detail paints
	 * cardPreviewUrl only and does not render fontPreviewUrl. Catalog
	 * choice cards use the card strip. Font Library families use previewUrl.
	 *
	 * @param mixed $slug   Package id or catalog fontFamily.
	 * @param mixed $region Region id.
	 * @param mixed $row    Catalog row or metadata, or null.
	 * @return array{previewUrl:string,cardPreviewUrl:string,fontPreviewUrl:string}
	 */
	public static function package_specimen_urls( $slug, $region = 'cn', $row = null ) {
		$norm = self::package_cdn_norm( $slug );
		$out  = array(
			'previewUrl'     => '',
			'cardPreviewUrl' => '',
			'fontPreviewUrl' => '',
		);
		if ( '' === $norm ) {
			return $out;
		}
		foreach ( array( 'preview', 'card', 'fontpreview' ) as $asset ) {
			$key     = self::specimen_meta_key( $asset );
			$derived = self::package_preview_url( $slug, $asset, 'webp', $region );
			$given   = self::specimen_url_from_row( $row, $asset );
			if ( '' !== $given && self::is_package_specimen_url( $given, $norm, $asset ) && '.webp' === substr( $given, -5 ) ) {
				$out[ $key ] = $given;
				continue;
			}
			$out[ $key ] = $derived;
		}
		return $out;
	}

	/**
	 * png sibling of a webp specimen on an existing CDN host, or empty.
	 *
	 * @param string $webp webp specimen URL.
	 * @return string
	 */
	public static function specimen_png_url( $webp ) {
		$webp = (string) $webp;
		if ( '.webp' !== substr( $webp, -5 ) ) {
			return '';
		}
		$png = substr( $webp, 0, -5 ) . '.png';
		if ( ! preg_match( '#/fonts-packages/([a-z0-9-]+)/preview/(preview|card|og|fontpreview)\.png$#', $png, $matches ) ) {
			return '';
		}
		if ( ! self::is_package_specimen_url( $png, $matches[1], $matches[2] ) ) {
			return '';
		}
		return $png;
	}

	/**
	 * Whether this URL is a specimen image for this norm on an existing host.
	 *
	 * @param string $url   Candidate URL.
	 * @param string $norm  Package norm.
	 * @param string $asset preview, card, og, or fontpreview.
	 * @return bool
	 */
	public static function is_package_specimen_url( $url, $norm, $asset ) {
		$norm  = self::token( $norm, 64 );
		$asset = self::specimen_asset( $asset );
		if ( '' === $norm || '' === $asset ) {
			return false;
		}
		$parts = parse_url( (string) $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Same shape as is_allowlisted(). The CLI test has no WordPress.
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return false;
		}
		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ! empty( $parts['port'] ) || ! empty( $parts['query'] ) || ! empty( $parts['fragment'] ) ) {
			return false;
		}
		$host = strtolower( (string) $parts['host'] );
		if ( ! in_array( $host, self::HOSTS, true ) ) {
			return false;
		}
		$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		$expect = '/fonts-packages/' . $norm . '/preview/' . $asset;
		return $path === $expect . '.webp' || $path === $expect . '.png';
	}

	/**
	 * CDN file name for a specimen asset, or empty.
	 *
	 * preview, card, og, and fontpreview stay lowercase. The live object is
	 * fontpreview.webp. fontPreview is accepted as the same asset name. The
	 * row key stays fontPreviewUrl.
	 *
	 * @param mixed $asset Raw asset name.
	 * @return string
	 */
	private static function specimen_asset( $asset ) {
		$asset = strtolower( (string) $asset );
		if ( in_array( $asset, array( 'preview', 'card', 'og', 'fontpreview' ), true ) ) {
			return $asset;
		}
		return '';
	}

	/**
	 * Row key for a specimen asset.
	 *
	 * The CDN file is fontpreview. The field name stays fontPreviewUrl.
	 *
	 * @param string $asset preview, card, or fontpreview.
	 * @return string
	 */
	private static function specimen_meta_key( $asset ) {
		$asset = self::specimen_asset( $asset );
		if ( 'card' === $asset ) {
			return 'cardPreviewUrl';
		}
		if ( 'fontpreview' === $asset ) {
			return 'fontPreviewUrl';
		}
		return 'previewUrl';
	}

	/**
	 * previewUrl, cardPreviewUrl, or fontPreviewUrl already present on a row.
	 *
	 * Top-level fields win over assets.*, matching index.json either flat
	 * or nested. The caller still rejects a URL that is not this norm.
	 *
	 * @param mixed  $row   Catalog row or metadata.
	 * @param string $asset preview, card, or fontpreview.
	 * @return string
	 */
	private static function specimen_url_from_row( $row, $asset ) {
		if ( ! is_array( $row ) ) {
			return '';
		}
		$key        = self::specimen_meta_key( $asset );
		$candidates = array();
		if ( isset( $row[ $key ] ) && is_string( $row[ $key ] ) ) {
			$candidates[] = $row[ $key ];
		}
		if ( isset( $row['assets'] ) && is_array( $row['assets'] ) && isset( $row['assets'][ $key ] ) && is_string( $row['assets'][ $key ] ) ) {
			$candidates[] = $row['assets'][ $key ];
		}
		foreach ( $candidates as $url ) {
			$url = trim( $url );
			if ( '' !== $url ) {
				return $url;
			}
		}
		return '';
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
	 * Public CSS for one wenfeng-* family. The project path is not used.
	 *
	 * @param array  $settings Settings row. Supplies the region when $subset is empty.
	 * @param string $family   Catalog token.
	 * @param string $subset   Subset id. Empty uses the settings subset.
	 * @return string
	 */
	public static function family_stylesheet_url( array $settings, $family, $subset = '' ) {
		$token = self::token( $family, 80 );
		if ( '' === $token || 0 !== strpos( $token, 'wenfeng-' ) ) {
			return '';
		}
		$host   = self::host( isset( $settings['region'] ) ? $settings['region'] : 'cn' );
		$subset = self::subset( '' !== (string) $subset ? $subset : ( isset( $settings['subset'] ) ? $settings['subset'] : 'zh-common' ) );
		return 'https://' . $host . '/api/css?family=' . rawurlencode( $token ) . '&subset=' . rawurlencode( $subset );
	}

	/**
	 * Stylesheet URLs to print when the CDN is on and the key check passed.
	 *
	 * The saved project or primary family is returned when that address is
	 * confirmed. Each additional confirmed wenfeng-* face adds its own public
	 * CSS URL. Tokens found only in post content are not added. A recorded
	 * miss returns nothing for that face.
	 *
	 * @param array  $settings Saved public settings.
	 * @param string $html     Post content, or empty.
	 * @param mixed  $globals  Font family settings from Global Styles, or empty.
	 * @return string[]
	 */
	public static function front_stylesheets( array $settings, $html = '', $globals = array() ) {
		if ( empty( $settings['cdn_enabled'] ) ) {
			return array();
		}
		$status = isset( $settings['key_status'] ) ? (string) $settings['key_status'] : '';
		if ( 'valid' !== $status ) {
			return array();
		}

		unset( $html, $globals );
		$urls    = array();
		$primary = self::embed_url( $settings );
		if ( '' !== $primary ) {
			$urls[] = $primary;
		}
		if ( class_exists( 'WPFonts_Settings', false ) ) {
			foreach ( WPFonts_Settings::confirmed_faces( $settings ) as $face ) {
				$subset = isset( $face['subset'] ) ? (string) $face['subset'] : '';
				$url    = self::family_stylesheet_url( $settings, $face['family'], $subset );
				if ( '' === $url || ! self::is_allowlisted( $url ) || in_array( $url, $urls, true ) ) {
					continue;
				}
				$urls[] = $url;
			}
		}
		return $urls;
	}

	/**
	 * Copyable embed: license comment, preconnect, then the stylesheet.
	 *
	 * Empty when the URL is not an allowlisted CDN stylesheet. The host is
	 * taken from that URL, so preconnect cannot point anywhere else.
	 *
	 * @param string $url Stylesheet URL.
	 * @return string
	 */
	public static function embed_snippet( $url ) {
		$origin = self::preconnect_origin( $url );
		if ( '' === $origin ) {
			return '';
		}
		return self::CDN_LICENSE_NOTE . "\n"
			. '<link rel="preconnect" href="' . $origin . '" crossorigin>' . "\n"
			. '<link rel="stylesheet" href="' . $url . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Copyable preview text. The front enqueues this URL.
	}

	/**
	 * https origin for an allowlisted stylesheet, or empty.
	 *
	 * @param string $url Stylesheet URL.
	 * @return string
	 */
	public static function preconnect_origin( $url ) {
		if ( ! self::is_allowlisted( $url ) ) {
			return '';
		}
		$parts = parse_url( (string) $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Same shape as is_allowlisted(). The CLI test has no WordPress.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$host = strtolower( (string) $parts['host'] );
		if ( ! in_array( $host, self::HOSTS, true ) ) {
			return '';
		}
		return 'https://' . $host;
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
		$css_status = isset( $settings['css_status'] ) ? (string) $settings['css_status'] : '';
		if ( 'missing' === $css_status ) {
			return '';
		}
		$url = self::stylesheet_url( $settings );
		return self::is_allowlisted( $url ) ? $url : '';
	}

	/**
	 * Read one stylesheet and record whether it opened, plus the face name.
	 *
	 * A 404 is missing. A timeout or other failure is unchecked and does not
	 * invent a face name. The request is one allowlisted URL, not the catalog.
	 *
	 * @param array $settings Public settings.
	 * @return array{status:string,face:string}
	 */
	public static function probe_stylesheet( array $settings ) {
		return self::probe_url( self::stylesheet_url( $settings ) );
	}

	/**
	 * Read one family stylesheet. Does not use the project path.
	 *
	 * @param array  $settings Public settings.
	 * @param string $family   wenfeng-* token.
	 * @param string $subset   Subset id.
	 * @return array{status:string,face:string}
	 */
	public static function probe_family( array $settings, $family, $subset = '' ) {
		return self::probe_url( self::family_stylesheet_url( $settings, $family, $subset ) );
	}

	/**
	 * GET one allowlisted stylesheet.
	 *
	 * @param string $url Absolute URL.
	 * @return array{status:string,face:string}
	 */
	private static function probe_url( $url ) {
		if ( ! self::is_allowlisted( $url ) || ! function_exists( 'wp_remote_get' ) ) {
			return array(
				'status' => 'unchecked',
				'face'   => '',
			);
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 6,
				'redirection'         => 0,
				'limit_response_size' => 65536,
				'headers'             => array(
					'Accept' => 'text/css',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 'unchecked',
				'face'   => '',
			);
		}
		$code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : 0;
		$body = function_exists( 'wp_remote_retrieve_body' ) ? (string) wp_remote_retrieve_body( $response ) : '';
		return self::interpret_stylesheet( $code, $body );
	}

	/**
	 * Map a stylesheet response to ok, missing, or unchecked.
	 *
	 * 404 means this family and subset do not exist. A CSS body contributes
	 * the first @font-face family, which is the name the browser actually uses.
	 *
	 * @param int    $code HTTP status. 0 when there was no response.
	 * @param string $body Response body.
	 * @return array{status:string,face:string}
	 */
	public static function interpret_stylesheet( $code, $body ) {
		$code = (int) $code;
		if ( 404 === $code ) {
			return array(
				'status' => 'missing',
				'face'   => '',
			);
		}
		if ( $code < 200 || $code >= 300 || ! is_string( $body ) || '' === $body ) {
			return array(
				'status' => 'unchecked',
				'face'   => '',
			);
		}
		if ( ! preg_match( '/@font-face\s*\{[^}]*font-family\s*:\s*([^;}]+)/is', $body, $matches ) ) {
			return array(
				'status' => 'unchecked',
				'face'   => '',
			);
		}
		$face = self::face_name( $matches[1] );
		if ( '' === $face ) {
			return array(
				'status' => 'unchecked',
				'face'   => '',
			);
		}
		return array(
			'status' => 'ok',
			'face'   => $face,
		);
	}

	/**
	 * CSS font-family name from a stylesheet, without quotes.
	 *
	 * @param mixed $value Raw declaration value.
	 * @return string
	 */
	public static function face_name( $value ) {
		$value = trim( (string) $value );
		$parts = explode( ',', $value );
		$value = trim( $parts[0] );
		$value = trim( $value, " \t\n\r\0\x0B\"'" );
		$value = preg_replace( '/[\x00-\x1F\x7F<>{};\\\\]/', '', $value );
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, 80 );
		}
		if ( strlen( $value ) > 80 ) {
			return substr( $value, 0, 80 );
		}
		return $value;
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
		} elseif ( array_key_exists( 'family', $input ) ) {
			// The font name field is on the simple form. The project name is not.
			$next['family'] = self::token( $input['family'], 80 );
		}
		return $next;
	}
}
