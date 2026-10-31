<?php
/**
 * Font Library collections for installed packages and the Windfonts catalog.
 *
 * Registering a collection does not write theme.json or Global Styles. WordPress
 * writes those only when someone installs a family from the Font Library.
 * Disabled and deleted packages are left out of the next registration.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and registers the two WPFonts collections.
 */
class WPFonts_Library {

	const PACKAGES_SLUG = 'wpfonts-packages';

	const CLOUD_SLUG = 'wpfonts-cloud';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Register the collections that currently have families.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'wp_register_font_collection' ) ) {
			return;
		}
		$packages = class_exists( 'WPFonts_Packages', false ) ? WPFonts_Packages::all() : array();
		self::sync_collection(
			self::PACKAGES_SLUG,
			self::packages_collection_args( self::package_families( $packages, self::css_map( $packages ) ) )
		);
		$settings = class_exists( 'WPFonts_Settings', false ) ? WPFonts_Settings::get() : array();
		$label    = self::catalog_label( $settings );
		self::sync_collection( self::CLOUD_SLUG, self::cloud_collection_args( self::confirmed_cloud_families( $settings, $label ) ) );
	}

	/**
	 * Display name for the saved catalog token, or empty.
	 *
	 * @param array $settings Public settings.
	 * @return string
	 */
	public static function catalog_label( array $settings ) {
		if ( ! class_exists( 'WPFonts_Catalog', false ) || ! class_exists( 'WPFonts_Link', false ) ) {
			return '';
		}
		$token = WPFonts_Link::family( $settings );
		if ( '' === $token ) {
			return '';
		}
		foreach ( WPFonts_Catalog::stored_fonts() as $font ) {
			if ( isset( $font['fontFamily'] ) && $font['fontFamily'] === $token && isset( $font['name'] ) ) {
				return trim( (string) $font['name'] );
			}
		}
		return '';
	}

	/**
	 * Enabled packages for the block editor, optionally filtered by search.
	 *
	 * @param string $search Search text. Empty returns every enabled package.
	 * @return array<int,array{name:string,family:string,stack:string}>
	 */
	public static function editor_packages( $search = '' ) {
		$search = trim( (string) $search );
		$out    = array();
		if ( ! class_exists( 'WPFonts_Packages', false ) ) {
			return $out;
		}
		foreach ( WPFonts_Packages::all() as $package ) {
			if ( empty( $package['enabled'] ) ) {
				continue;
			}
			if ( '' !== $search && ! self::package_matches( $package, $search ) ) {
				continue;
			}
			$plain = self::plain_family( isset( $package['family'] ) ? $package['family'] : '' );
			$stack = self::css_stack( $plain );
			if ( '' === $stack ) {
				continue;
			}
			$name = isset( $package['name'] ) ? trim( (string) $package['name'] ) : '';
			if ( '' === $name ) {
				$name = $plain;
			}
			$out[] = array(
				'name'   => $name,
				'family' => $plain,
				'stack'  => $stack,
			);
		}
		return $out;
	}

	/**
	 * Collection arguments for enabled packages.
	 *
	 * @param array $families Family definitions.
	 * @return array{name:string,description:string,font_families:array}
	 */
	public static function packages_collection_args( array $families ) {
		return array(
			'name'          => __( '文风字体包', 'wpfonts' ),
			'description'   => __( '本站已安装的文风字体包。关闭或删除后不再出现。', 'wpfonts' ),
			'font_families' => $families,
		);
	}

	/**
	 * Collection arguments for catalog families. Names only; no font files.
	 *
	 * @param array $families Family definitions.
	 * @return array{name:string,description:string,font_families:array}
	 */
	public static function cloud_collection_args( array $families ) {
		return array(
			'name'          => __( '文风云', 'wpfonts' ),
			'description'   => __( '已确认能打开的文风字体。安装后写入全站样式。', 'wpfonts' ),
			'font_families' => $families,
		);
	}

	/**
	 * One Font Library family when the saved stylesheet opened and named a face.
	 *
	 * The catalog token stays the slug and the URL key. The CSS font-family is
	 * the face the stylesheet declares, which is not the same string.
	 *
	 * @param array  $settings Public settings.
	 * @param string $label    Optional display name.
	 * @return array<int,array{font_family_settings:array}>
	 */
	public static function confirmed_cloud_families( array $settings, $label = '' ) {
		$status = isset( $settings['css_status'] ) ? (string) $settings['css_status'] : '';
		if ( 'ok' !== $status ) {
			return array();
		}
		$face = self::plain_family( isset( $settings['css_face'] ) ? $settings['css_face'] : '' );
		if ( '' === $face || ! class_exists( 'WPFonts_Link', false ) ) {
			return array();
		}
		$token = WPFonts_Link::family( $settings );
		$slug  = ( '' !== $token && 0 === strpos( $token, 'wenfeng-' ) ) ? $token : 'wpfonts-cloud';
		$name  = trim( (string) $label );
		if ( '' === $name ) {
			$name = $face;
		}
		$stack = self::css_stack( $face );
		if ( '' === $stack ) {
			return array();
		}
		return array(
			array(
				'font_family_settings' => array(
					'name'       => $name,
					'slug'       => $slug,
					'fontFamily' => $stack,
				),
			),
		);
	}

	/**
	 * One Font Library family per enabled package.
	 *
	 * `$css_by_id` is local.css text keyed by package id. Faces prefer the
	 * woff2 URLs already rewritten at install. A package with no readable CSS
	 * is still listed by name so the family can be installed.
	 *
	 * @param array $packages   Package rows.
	 * @param array $css_by_id  Package id => CSS text.
	 * @return array<int,array{font_family_settings:array}>
	 */
	public static function package_families( array $packages, array $css_by_id ) {
		$out  = array();
		$seen = array();
		foreach ( $packages as $package ) {
			if ( ! is_array( $package ) ) {
				continue;
			}
			$id     = isset( $package['id'] ) ? WPFonts_Packages::clean_id( $package['id'] ) : '';
			$css    = ( '' !== $id && isset( $css_by_id[ $id ] ) ) ? (string) $css_by_id[ $id ] : '';
			$family = self::family_definition( $package, $css );
			if ( null === $family ) {
				continue;
			}
			$slug = $family['font_family_settings']['slug'];
			if ( isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$out[]         = $family;
		}
		return $out;
	}

	/**
	 * One collection family, or null when the package is disabled or unnamed.
	 *
	 * @param array  $package Package row.
	 * @param string $css     local.css text, or empty.
	 * @return array{font_family_settings:array}|null
	 */
	public static function family_definition( array $package, $css ) {
		if ( empty( $package['enabled'] ) ) {
			return null;
		}
		$id = isset( $package['id'] ) ? WPFonts_Packages::clean_id( $package['id'] ) : '';
		if ( '' === $id ) {
			return null;
		}
		$plain = self::plain_family( isset( $package['family'] ) ? $package['family'] : '' );
		if ( '' === $plain && is_string( $css ) && '' !== $css ) {
			$plain = self::plain_family( WPFonts_Packages::family_from_css( $css ) );
		}
		$stack = self::css_stack( $plain );
		if ( '' === $stack ) {
			return null;
		}
		$name = isset( $package['name'] ) ? trim( (string) $package['name'] ) : '';
		if ( '' === $name ) {
			$name = $plain;
		}
		$settings = array(
			'name'       => $name,
			'slug'       => 'wpfonts-pkg-' . $id,
			'fontFamily' => $stack,
		);
		$faces    = self::faces_from_css( is_string( $css ) ? $css : '', $plain );
		if ( array() !== $faces ) {
			$settings['fontFace'] = $faces;
		}
		return array(
			'font_family_settings' => $settings,
		);
	}

	/**
	 * @font-face rules whose src is an absolute woff2 URL.
	 *
	 * The fontFamily on each face is the package family, so an install uses the
	 * same name local.css already serves.
	 *
	 * @param string $css    CSS text.
	 * @param string $family Canonical family name, unquoted.
	 * @return array<int,array<string,string>>
	 */
	public static function faces_from_css( $css, $family ) {
		$family = self::plain_family( $family );
		$faces  = array();
		if ( '' === $family || ! is_string( $css ) ) {
			return $faces;
		}
		if ( ! preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $css, $blocks ) ) {
			return $faces;
		}
		foreach ( $blocks[1] as $body ) {
			$weight = '400';
			$style  = 'normal';
			if ( preg_match( '/font-weight\s*:\s*([^;}]+)/i', $body, $weight_match ) ) {
				$candidate = strtolower( trim( $weight_match[1] ) );
				if ( preg_match( '/^(?:normal|bold|[1-9]00)(?:\s+(?:normal|bold|[1-9]00))?$/', $candidate ) ) {
					$weight = $candidate;
				}
			}
			if ( preg_match( '/font-style\s*:\s*([^;}]+)/i', $body, $style_match ) ) {
				$candidate = strtolower( trim( $style_match[1] ) );
				if ( in_array( $candidate, array( 'normal', 'italic', 'oblique' ), true ) ) {
					$style = $candidate;
				}
			}
			$display = '';
			if ( preg_match( '/font-display\s*:\s*([^;}]+)/i', $body, $display_match ) ) {
				$candidate = strtolower( trim( $display_match[1] ) );
				if ( in_array( $candidate, array( 'auto', 'block', 'fallback', 'swap', 'optional' ), true ) ) {
					$display = $candidate;
				}
			}
			$range = '';
			if ( preg_match( '/unicode-range\s*:\s*([^;}]+)/i', $body, $range_match ) ) {
				$candidate = trim( $range_match[1] );
				$range_ok  = '/^U\+(?:[0-9A-Fa-f?]{1,6})(?:-[0-9A-Fa-f?]{1,6})?(?:,\s*U\+[0-9A-Fa-f?]{1,6}(?:-[0-9A-Fa-f?]{1,6})?)*$/';
				if ( preg_match( $range_ok, $candidate ) ) {
					$range = $candidate;
				}
			}
			if ( ! preg_match_all( '/url\(\s*([\'"]?)(https?:\/\/[^\'")\s]+)\1\s*\)/i', $body, $urls ) ) {
				continue;
			}
			foreach ( $urls[2] as $url ) {
				if ( ! preg_match( '/\.woff2(?:$|\?)/i', $url ) ) {
					continue;
				}
				$face = array(
					'fontFamily' => $family,
					'fontStyle'  => $style,
					'fontWeight' => $weight,
					'src'        => $url,
				);
				if ( '' !== $display ) {
					$face['fontDisplay'] = $display;
				}
				if ( '' !== $range ) {
					$face['unicodeRange'] = $range;
				}
				$faces[] = $face;
			}
		}
		return $faces;
	}

	/**
	 * CSS font-family stack with a generic fallback.
	 *
	 * @param string $family Family name.
	 * @return string
	 */
	public static function css_stack( $family ) {
		$plain = self::plain_family( $family );
		if ( '' === $plain ) {
			return '';
		}
		return '"' . $plain . '", sans-serif';
	}

	/**
	 * Family name safe to quote inside a font stack.
	 *
	 * @param mixed $family Raw family.
	 * @return string
	 */
	public static function plain_family( $family ) {
		$family = trim( (string) $family );
		$family = preg_replace( '/[\x00-\x1F\x7F]/', '', $family );
		if ( ! is_string( $family ) ) {
			return '';
		}
		$family = trim( $family );
		if ( '' === $family || preg_match( '/[{}<>;\\\\]/', $family ) ) {
			return '';
		}
		$family = str_replace( array( '\\', '"', "'" ), '', $family );
		$family = trim( $family );
		if ( '' === $family ) {
			return '';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $family, 0, 80 );
		}
		if ( strlen( $family ) > 80 ) {
			return substr( $family, 0, 80 );
		}
		return $family;
	}

	/**
	 * Register one collection, or drop it when it has no families.
	 *
	 * @param string $slug Collection slug.
	 * @param array  $args Collection arguments.
	 * @return void
	 */
	private static function sync_collection( $slug, array $args ) {
		$families = ( isset( $args['font_families'] ) && is_array( $args['font_families'] ) ) ? $args['font_families'] : array();
		if ( array() === $families ) {
			self::drop_collection( $slug );
			return;
		}
		self::drop_collection( $slug );
		$result = wp_register_font_collection( $slug, $args );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) && function_exists( 'do_action' ) ) {
			do_action( 'wpfonts_collection_registration_error', $result, $slug );
		}
	}

	/**
	 * Unregister a collection that is already registered. A miss is ignored.
	 *
	 * @param string $slug Collection slug.
	 * @return void
	 */
	private static function drop_collection( $slug ) {
		if ( ! function_exists( 'wp_unregister_font_collection' ) || ! class_exists( 'WP_Font_Library', false ) ) {
			return;
		}
		$library = WP_Font_Library::get_instance();
		if ( ! is_object( $library ) || ! method_exists( $library, 'get_font_collection' ) ) {
			return;
		}
		if ( ! $library->get_font_collection( $slug ) ) {
			return;
		}
		wp_unregister_font_collection( $slug );
	}

	/**
	 * local.css text for each package id. Missing files are omitted.
	 *
	 * @param array $packages Package rows.
	 * @return array<string,string>
	 */
	private static function css_map( array $packages ) {
		$map = array();
		foreach ( $packages as $package ) {
			if ( ! is_array( $package ) || empty( $package['enabled'] ) ) {
				continue;
			}
			$id = isset( $package['id'] ) ? WPFonts_Packages::clean_id( $package['id'] ) : '';
			if ( '' === $id ) {
				continue;
			}
			$css = self::read_package_css( $id );
			if ( '' !== $css ) {
				$map[ $id ] = $css;
			}
		}
		return $map;
	}

	/**
	 * Read one package stylesheet from uploads. Paths outside the package are refused.
	 *
	 * @param string $id Package id.
	 * @return string
	 */
	private static function read_package_css( $id ) {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}
		$uploads = wp_upload_dir();
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		$root = rtrim( (string) $uploads['basedir'], '/\\' ) . '/wpfonts/packages';
		$path = $root . '/' . $id . '/local.css';
		if ( function_exists( 'wp_normalize_path' ) ) {
			$root = wp_normalize_path( $root );
			$path = wp_normalize_path( $path );
		}
		if ( 0 !== strpos( $path, $root . '/' ) || ! is_readable( $path ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local package CSS. WP_Filesystem may prompt for FTP.
		$raw = file_get_contents( $path );
		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * Whether a search string appears in the package name or family.
	 *
	 * @param array  $package Package row.
	 * @param string $search  Search text.
	 * @return bool
	 */
	private static function package_matches( array $package, $search ) {
		$name   = isset( $package['name'] ) ? (string) $package['name'] : '';
		$family = isset( $package['family'] ) ? (string) $package['family'] : '';
		$hay    = $name . ' ' . $family;
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $hay, $search );
		}
		return false !== stripos( $hay, $search );
	}
}

WPFonts_Library::init();
