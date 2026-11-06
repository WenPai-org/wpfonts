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
	 * Priority 20 is before the system collections at 25, so 文风字体 and 文风云
	 * are inserted ahead of 系统 · 中文.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_render_specimen' ), 0 );
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
		$css_map  = self::css_map( $packages );
		self::sync_collection(
			self::PACKAGES_SLUG,
			self::describe_packages( self::package_families( $packages, $css_map ), self::css_map_is_sliced( $css_map ) )
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
			'name'          => __( 'Windfonts', 'wpfonts' ),
			'description'   => __( '本站已启用的文风字体包。关闭或删除后，全站样式里也不再出现。', 'wpfonts' ),
			'font_families' => $families,
		);
	}

	/**
	 * Package collection arguments, plus the slice note when a package collapsed.
	 *
	 * The note is only for unicode-range packages. A single file per weight
	 * keeps the original description.
	 *
	 * @param array $families Family definitions.
	 * @param bool  $sliced   Whether any stylesheet collapsed to fewer faces.
	 * @return array{name:string,description:string,font_families:array}
	 */
	public static function describe_packages( array $families, $sliced ) {
		$args = self::packages_collection_args( $families );
		if ( $sliced ) {
			$args['description'] .= __( '本包按字符切片托管，前台会自动按需加载；字体库里每个字重只显示一条即可。', 'wpfonts' );
		}
		return $args;
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
			'description'   => __( '已确认能打开的文风字体。会出现在全站样式里。', 'wpfonts' ),
			'font_families' => $families,
		);
	}

	/**
	 * One Font Library family for each confirmed wenfeng-* face.
	 *
	 * An rc.6 row with no `faces` list still contributes its single confirmed
	 * face. The catalog token stays the slug. The CSS font-family is the face
	 * the stylesheet declares, which is not the same string.
	 *
	 * @param array  $settings Public settings.
	 * @param string $label    Optional display name.
	 * @return array<int,array{font_family_settings:array}>
	 */
	public static function confirmed_cloud_families( array $settings, $label = '' ) {
		if ( ! class_exists( 'WPFonts_Settings', false ) || ! class_exists( 'WPFonts_Link', false ) ) {
			return array();
		}
		$out   = array();
		$seen  = array();
		$label = trim( (string) $label );
		foreach ( WPFonts_Settings::confirmed_faces( $settings ) as $face ) {
			$token = isset( $face['family'] ) ? (string) $face['family'] : '';
			if ( '' === $token || isset( $seen[ $token ] ) || 0 !== strpos( $token, 'wenfeng-' ) ) {
				continue;
			}
			$seen[ $token ] = true;
			$css            = self::plain_family( isset( $face['css_face'] ) ? $face['css_face'] : '' );
			$stack          = self::css_stack( $css );
			if ( '' === $stack ) {
				continue;
			}
			$name    = isset( $face['name'] ) ? trim( (string) $face['name'] ) : '';
			$primary = WPFonts_Link::family( $settings );
			if ( ( '' === $name || $token === $name || $css === $name ) && '' !== $label && $token === $primary ) {
				$name = $label;
			}
			if ( '' === $name ) {
				$name = $css;
			}
			$row = array(
				'name'       => $name,
				'slug'       => $token,
				'fontFamily' => $stack,
			);
			if ( class_exists( 'WPFonts_Link', false ) ) {
				$region  = isset( $settings['region'] ) ? $settings['region'] : 'cn';
				$preview = WPFonts_Link::package_preview_url( $token, 'preview', 'webp', $region );
				if ( '' !== $preview ) {
					$row['preview'] = $preview;
				}
			}
			$out[] = array(
				'font_family_settings' => $row,
			);
		}
		return $out;
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
			'slug'       => self::package_family_slug( $id ),
			'fontFamily' => $stack,
		);
		$preview  = self::family_preview_url( $package );
		if ( '' !== $preview ) {
			$settings['preview'] = $preview;
		}
		$faces = self::faces_from_css( is_string( $css ) ? $css : '', $plain );
		if ( array() !== $faces ) {
			if ( '' !== $preview ) {
				foreach ( $faces as $index => $face ) {
					if ( empty( $face['preview'] ) ) {
						$faces[ $index ]['preview'] = $preview;
					}
				}
			}
			$settings['fontFace'] = $faces;
		}
		return array(
			'font_family_settings' => $settings,
		);
	}

	/**
	 * Font Library and Global Styles slug for one package.
	 *
	 * A package installed as wenfeng-{short} keeps that id, so the slug matches
	 * the catalog family. Packages that only have a zip-file name keep the
	 * wpfonts-pkg- prefix.
	 *
	 * @param string $id Package id.
	 * @return string
	 */
	public static function package_family_slug( $id ) {
		if ( ! class_exists( 'WPFonts_Packages', false ) ) {
			return '';
		}
		$id = WPFonts_Packages::clean_id( $id );
		if ( '' === $id ) {
			return '';
		}
		if ( 0 === strpos( $id, 'wenfeng-' ) ) {
			return $id;
		}
		return 'wpfonts-pkg-' . $id;
	}

	/**
	 * One Font Library face per font-weight and font-style.
	 *
	 * Unicode-range slices of the same weight stay in local.css. That file is
	 * what the front and the package screen load. The Font Library list is for
	 * choosing a weight, so it keeps a single representative file and does not
	 * copy every slice into the collection. A face without unicode-range wins.
	 * Otherwise the widest range wins, and a tie keeps the first file.
	 *
	 * @param string $css    CSS text.
	 * @param string $family Canonical family name, unquoted.
	 * @return array<int,array<string,string>>
	 */
	public static function faces_from_css( $css, $family ) {
		return self::fold_faces( self::parse_faces( $css, $family ) );
	}

	/**
	 * Whether this stylesheet has more than one file for the same weight and style.
	 *
	 * @param string $css    CSS text.
	 * @param string $family Family used when the stylesheet does not matter.
	 * @return bool
	 */
	public static function css_is_sliced( $css, $family = 'wpfonts' ) {
		$raw = self::parse_faces( $css, $family );
		return count( $raw ) > count( self::fold_faces( $raw ) );
	}

	/**
	 * Whether any package stylesheet in the map collapses.
	 *
	 * @param array<string,string> $css_by_id Package id => CSS text.
	 * @return bool
	 */
	public static function css_map_is_sliced( array $css_by_id ) {
		foreach ( $css_by_id as $css ) {
			if ( self::css_is_sliced( (string) $css ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether this installed package's local.css collapses in the Font Library.
	 *
	 * @param string $id Package id.
	 * @return bool
	 */
	public static function package_is_sliced( $id ) {
		$css = self::read_package_css( class_exists( 'WPFonts_Packages', false ) ? WPFonts_Packages::clean_id( $id ) : '' );
		if ( '' === $css ) {
			return false;
		}
		$family = 'wpfonts';
		if ( class_exists( 'WPFonts_Packages', false ) ) {
			$declared = WPFonts_Packages::family_from_css( $css );
			if ( '' !== self::plain_family( $declared ) ) {
				$family = $declared;
			}
		}
		return self::css_is_sliced( $css, $family );
	}

	/**
	 * @font-face rules whose src is an absolute woff2 URL, before folding.
	 *
	 * The fontFamily on each face is the package family, so an install uses the
	 * same name local.css already serves. `normal` and `bold` become 400 and
	 * 700 so they share a Font Library row with the numeric weight.
	 *
	 * @param string $css    CSS text.
	 * @param string $family Canonical family name, unquoted.
	 * @return array<int,array<string,string>>
	 */
	private static function parse_faces( $css, $family ) {
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
			if ( 'normal' === $weight ) {
				$weight = '400';
			} elseif ( 'bold' === $weight ) {
				$weight = '700';
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
	 * Keep one face for each weight and style.
	 *
	 * @param array<int,array<string,string>> $faces Parsed faces.
	 * @return array<int,array<string,string>>
	 */
	private static function fold_faces( array $faces ) {
		$groups = array();
		$order  = array();
		foreach ( $faces as $face ) {
			if ( ! is_array( $face ) ) {
				continue;
			}
			$weight = isset( $face['fontWeight'] ) ? (string) $face['fontWeight'] : '400';
			$style  = isset( $face['fontStyle'] ) ? (string) $face['fontStyle'] : 'normal';
			$key    = $weight . '|' . $style;
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array();
				$order[]        = $key;
			}
			$groups[ $key ][] = $face;
		}
		$out = array();
		foreach ( $order as $key ) {
			$best      = null;
			$best_span = -1;
			foreach ( $groups[ $key ] as $face ) {
				$span = self::range_span( isset( $face['unicodeRange'] ) ? (string) $face['unicodeRange'] : '' );
				if ( $span > $best_span ) {
					$best      = $face;
					$best_span = $span;
				}
			}
			if ( is_array( $best ) ) {
				$out[] = $best;
			}
		}
		return $out;
	}

	/**
	 * How many code points a unicode-range covers. Empty means the whole font.
	 *
	 * @param string $range unicode-range value, or empty.
	 * @return int
	 */
	private static function range_span( $range ) {
		$range = trim( (string) $range );
		if ( '' === $range ) {
			return 0x110000;
		}
		$span = 0;
		if ( ! preg_match_all( '/U\+([0-9A-Fa-f?]{1,6})(?:-([0-9A-Fa-f?]{1,6}))?/', $range, $parts, PREG_SET_ORDER ) ) {
			return 1;
		}
		foreach ( $parts as $part ) {
			$start_token = $part[1];
			$end_token   = ( isset( $part[2] ) && '' !== $part[2] ) ? $part[2] : $start_token;
			if ( false !== strpos( $start_token, '?' ) || false !== strpos( $end_token, '?' ) ) {
				$span += 128;
				continue;
			}
			$start = hexdec( $start_token );
			$end   = hexdec( $end_token );
			if ( $end < $start ) {
				$end = $start;
			}
			$span += ( $end - $start + 1 );
		}
		return $span > 0 ? $span : 1;
	}

	/**
	 * Family preview image.
	 *
	 * A pack https URL wins. Keys are preview, previewUrl, preview_url, and
	 * assets.previewUrl, in meta.json, metadata.json, or font.json. A
	 * wenfeng-* family with no pack image uses the CDN specimen
	 * /fonts-packages/{cdn norm}/preview/preview.webp on the same host as
	 * public CSS. A historical short uses the CDN folder alias. The package
	 * id and CSS family stay wenfeng-*. That image is the font, so the
	 * library does not download woff2
	 * just to paint a sample. Anything else is a same-site SVG of the
	 * display name. WordPress sanitizes preview with sanitize_url, which
	 * drops data URIs.
	 *
	 * @param array $package Package row.
	 * @return string
	 */
	private static function family_preview_url( array $package ) {
		$id = '';
		if ( class_exists( 'WPFonts_Packages', false ) && isset( $package['id'] ) ) {
			$id = WPFonts_Packages::clean_id( $package['id'] );
		}
		if ( '' !== $id ) {
			$packed = WPFonts_Packages::installed_preview_url( $id );
			if ( '' !== $packed ) {
				return $packed;
			}
		}
		if ( isset( $package['preview'] ) && class_exists( 'WPFonts_Packages', false ) ) {
			$given = WPFonts_Packages::clean_preview_url( $package['preview'] );
			if ( '' !== $given ) {
				return $given;
			}
		}
		if ( '' !== $id && class_exists( 'WPFonts_Link', false ) ) {
			$region = 'cn';
			if ( class_exists( 'WPFonts_Settings', false ) ) {
				$saved = WPFonts_Settings::get();
				if ( isset( $saved['region'] ) && '' !== (string) $saved['region'] ) {
					$region = $saved['region'];
				}
			}
			$cdn = WPFonts_Link::package_preview_url( $id, 'preview', 'webp', $region );
			if ( '' !== $cdn ) {
				return $cdn;
			}
		}
		return self::specimen_preview_url( $id );
	}

	/**
	 * Stable URL of the generated family specimen.
	 *
	 * @param string $id Package id.
	 * @return string
	 */
	public static function specimen_preview_url( $id ) {
		if ( ! class_exists( 'WPFonts_Packages', false ) ) {
			return '';
		}
		$id = WPFonts_Packages::clean_id( $id );
		if ( '' === $id ) {
			return '';
		}
		if ( function_exists( 'home_url' ) && function_exists( 'add_query_arg' ) ) {
			return add_query_arg( 'wpfonts_specimen', $id, home_url( '/' ) );
		}
		return 'https://example.test/?wpfonts_specimen=' . rawurlencode( $id );
	}

	/**
	 * Print an SVG specimen and stop. Missing packages use a generic label.
	 *
	 * The query value is only a package id. The painted text is the stored
	 * display name, not the raw query.
	 *
	 * @return void
	 */
	public static function maybe_render_specimen() {
		if ( ! isset( $_GET['wpfonts_specimen'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public specimen image. The id is cleaned and the text comes from the package list.
			return;
		}
		$raw = $_GET['wpfonts_specimen']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- clean_id() keeps letters, digits, and hyphens.
		if ( is_array( $raw ) ) {
			$raw = '';
		}
		if ( function_exists( 'wp_unslash' ) ) {
			$raw = wp_unslash( $raw );
		}
		$id    = class_exists( 'WPFonts_Packages', false ) ? WPFonts_Packages::clean_id( $raw ) : '';
		$label = '文风字体';
		if ( '' !== $id && class_exists( 'WPFonts_Packages', false ) ) {
			$package = WPFonts_Packages::find( $id );
			if ( is_array( $package ) && isset( $package['name'] ) ) {
				$name = trim( (string) $package['name'] );
				if ( '' !== $name ) {
					$label = $name;
				}
			}
		}
		if ( ! headers_sent() ) {
			if ( function_exists( 'status_header' ) ) {
				status_header( 200 );
			}
			header( 'Content-Type: image/svg+xml; charset=UTF-8' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Cache-Control: public, max-age=300' );
		}
		echo self::specimen_svg( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- specimen_svg() escapes the label.
		exit;
	}

	/**
	 * SVG of a display name. The browser paints it with a CJK system stack.
	 *
	 * This is the fallback when a pack does not ship a preview image. It is
	 * not the font file, and it does not load woff2.
	 *
	 * @param string $label Display name.
	 * @return string
	 */
	public static function specimen_svg( $label ) {
		$label = trim( (string) $label );
		$label = preg_replace( '/[\x00-\x1F\x7F]/', '', $label );
		if ( ! is_string( $label ) || '' === $label ) {
			$label = '文风字体';
		}
		if ( function_exists( 'mb_substr' ) ) {
			$label = mb_substr( $label, 0, 40 );
		} elseif ( strlen( $label ) > 120 ) {
			$label = substr( $label, 0, 120 );
		}
		$count = function_exists( 'mb_strlen' ) ? mb_strlen( $label ) : strlen( $label );
		if ( $count < 1 ) {
			$count = 1;
		}
		$width = $count * 36 + 8;
		if ( $width < 160 ) {
			$width = 160;
		}
		if ( $width > 720 ) {
			$width = 720;
		}
		$text = htmlspecialchars( $label, ENT_QUOTES | ENT_XML1, 'UTF-8' );
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="48" viewBox="0 0 ' . $width . ' 48" role="img">'
			. '<title>' . $text . '</title>'
			. '<text x="0" y="34" fill="#1d2327" font-size="32" font-family="\'PingFang SC\', \'Hiragino Sans GB\', \'Noto Sans CJK SC\', \'Noto Sans SC\', \'Microsoft YaHei\', \'Source Han Sans SC\', sans-serif">'
			. $text
			. '</text></svg>';
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
