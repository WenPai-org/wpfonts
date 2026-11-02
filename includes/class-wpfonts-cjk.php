<?php
/**
 * Site-level Chinese fallback. Plugin CSS and a stylesheet filter.
 *
 * Enabled packages and confirmed 文风云 faces can be assigned to body text,
 * headings, and a site fallback. The fallback is inserted into Latin stacks
 * before the first generic family, including `.has-*-font-family` rules that
 * use `!important`. Theme JSON and the user Global Styles post are not edited
 * here. WPFonts_Global_Styles still copies owned families on its own.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the Chinese assignment and prints it.
 */
class WPFonts_CJK {

	/**
	 * Settings keys on wpfonts_settings.
	 *
	 * Empty body and heading follow cjk_fallback. Values are `pkg:{id}` or
	 * `cloud:{wenfeng-token}`.
	 *
	 * @var string[]
	 */
	const KEYS = array( 'cjk_fallback', 'cjk_body', 'cjk_heading' );

	/**
	 * True after the inline stylesheet has been attached once this request.
	 *
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * Front stylesheet and the global-styles rewrite.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_get_global_stylesheet', array( __CLASS__, 'filter_global_stylesheet' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front' ), 20 );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'enqueue_editor' ) );
	}

	/**
	 * Keep a stored choice, or return empty when it is not one of ours.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function clean_choice( $value ) {
		if ( class_exists( 'WPFonts_Settings', false ) ) {
			return WPFonts_Settings::clean_cjk_choice( $value );
		}
		return '';
	}

	/**
	 * Copy the three choices onto a settings save.
	 *
	 * A form without `cjk_form` keeps the stored values. That includes the
	 * health check and the migration writer, which do not post these fields.
	 *
	 * @param array $prev  Saved public settings.
	 * @param array $next  Sanitized public fields.
	 * @param array $input Raw form input.
	 * @return array
	 */
	public static function apply_settings( array $prev, array $next, array $input ) {
		if ( ! empty( $input['cjk_form'] ) ) {
			foreach ( self::KEYS as $key ) {
				$next[ $key ] = self::clean_choice( isset( $input[ $key ] ) ? $input[ $key ] : '' );
			}
			return $next;
		}
		foreach ( self::KEYS as $key ) {
			$next[ $key ] = self::clean_choice( isset( $prev[ $key ] ) ? $prev[ $key ] : '' );
		}
		return $next;
	}

	/**
	 * Enabled packages and confirmed 文风云 faces a site owner can pick.
	 *
	 * The value is stored. The CSS name is the package family, or the cloud
	 * face declared in its stylesheet. A disabled package is omitted.
	 *
	 * @return array<int,array{value:string,label:string,group:string,css:string}>
	 */
	public static function choices() {
		$out  = array();
		$seen = array();
		if ( class_exists( 'WPFonts_Packages', false ) && class_exists( 'WPFonts_Library', false ) ) {
			foreach ( WPFonts_Packages::all() as $package ) {
				if ( empty( $package['enabled'] ) ) {
					continue;
				}
				$id  = isset( $package['id'] ) ? WPFonts_Packages::clean_id( $package['id'] ) : '';
				$css = WPFonts_Library::plain_family( isset( $package['family'] ) ? $package['family'] : '' );
				if ( '' === $id || '' === $css ) {
					continue;
				}
				$value = self::clean_choice( 'pkg:' . $id );
				if ( '' === $value || isset( $seen[ $value ] ) ) {
					continue;
				}
				$seen[ $value ] = true;
				$name           = isset( $package['name'] ) ? trim( (string) $package['name'] ) : '';
				if ( '' === $name ) {
					$name = $css;
				}
				$out[] = array(
					'value' => $value,
					'label' => $name,
					'group' => 'package',
					'css'   => $css,
				);
			}
		}
		if ( class_exists( 'WPFonts_Settings', false ) && class_exists( 'WPFonts_Library', false ) ) {
			$settings = WPFonts_Settings::get();
			foreach ( WPFonts_Settings::confirmed_faces( $settings ) as $face ) {
				$token = isset( $face['family'] ) ? (string) $face['family'] : '';
				$css   = WPFonts_Library::plain_family( isset( $face['css_face'] ) ? $face['css_face'] : '' );
				if ( '' === $token || '' === $css || 0 !== strpos( $token, 'wenfeng-' ) ) {
					continue;
				}
				$value = self::clean_choice( 'cloud:' . $token );
				if ( '' === $value || isset( $seen[ $value ] ) ) {
					continue;
				}
				$seen[ $value ] = true;
				$name           = isset( $face['name'] ) ? trim( (string) $face['name'] ) : '';
				if ( '' === $name ) {
					$name = $css;
				}
				$out[] = array(
					'value' => $value,
					'label' => $name,
					'group' => 'cloud',
					'css'   => $css,
				);
			}
		}
		return $out;
	}

	/**
	 * CSS family for a stored choice, or empty when it is not available.
	 *
	 * @param string $choice pkg:{id} or cloud:{token}.
	 * @return string
	 */
	public static function css_family( $choice ) {
		$choice = self::clean_choice( $choice );
		if ( '' === $choice ) {
			return '';
		}
		foreach ( self::choices() as $row ) {
			if ( $row['value'] === $choice ) {
				return $row['css'];
			}
		}
		return '';
	}

	/**
	 * Families to print. Empty body and heading follow the site fallback.
	 *
	 * An override is a body or heading choice whose CSS name differs from the
	 * fallback. Overrides replace that element's font. The fallback is appended.
	 *
	 * @param array|null $settings Public settings. Null reads the option.
	 * @return array{fallback:string,body:string,heading:string,body_override:bool,heading_override:bool}
	 */
	public static function resolved( $settings = null ) {
		if ( ! is_array( $settings ) ) {
			$settings = class_exists( 'WPFonts_Settings', false ) ? WPFonts_Settings::get() : array();
		}
		$fallback = self::css_family( isset( $settings['cjk_fallback'] ) ? $settings['cjk_fallback'] : '' );
		$body     = self::css_family( isset( $settings['cjk_body'] ) ? $settings['cjk_body'] : '' );
		$heading  = self::css_family( isset( $settings['cjk_heading'] ) ? $settings['cjk_heading'] : '' );
		if ( '' === $body ) {
			$body = $fallback;
		}
		if ( '' === $heading ) {
			$heading = $fallback;
		}
		return array(
			'fallback'         => $fallback,
			'body'             => $body,
			'heading'          => $heading,
			'body_override'    => ( '' !== $body && $body !== $fallback ),
			'heading_override' => ( '' !== $heading && $heading !== $fallback ),
		);
	}

	/**
	 * One line for the overview. Off until a choice still resolves.
	 *
	 * @return array{on:bool,text:string}
	 */
	public static function overview() {
		$roles = self::resolved();
		if ( '' === $roles['fallback'] && '' === $roles['body'] && '' === $roles['heading'] ) {
			return array(
				'on'   => false,
				'text' => __( '未指定。启用字体包或文风云后，可在设置里选。', 'wpfonts' ),
			);
		}
		return array(
			'on'   => true,
			'text' => __( '已指定。正文、标题，以及西文字体后面的中文，会用这套选择。', 'wpfonts' ),
		);
	}

	/**
	 * Append the Chinese family inside generated global styles.
	 *
	 * The string is the stylesheet WordPress already built. This does not read
	 * or write theme.json or the wp_global_styles post. `@font-face` names are
	 * left as they are. A declaration that is only a preset variable is left
	 * as well, because the variable itself is updated.
	 *
	 * @param mixed $stylesheet Generated CSS.
	 * @param mixed $types      Stylesheet types. Unused.
	 * @return mixed
	 */
	public static function filter_global_stylesheet( $stylesheet, $types = null ) {
		unset( $types );
		if ( ! is_string( $stylesheet ) || '' === $stylesheet ) {
			return $stylesheet;
		}
		$roles = self::resolved();
		if ( '' === $roles['fallback'] ) {
			return $stylesheet;
		}
		return self::rewrite_stylesheet( $stylesheet, $roles['fallback'] );
	}

	/**
	 * Insert one family into preset variables and font-family declarations.
	 *
	 * @param string $css    Stylesheet text.
	 * @param string $family CSS family name, unquoted.
	 * @return string
	 */
	public static function rewrite_stylesheet( $css, $family ) {
		if ( ! is_string( $css ) ) {
			return '';
		}
		$family = self::safe_family( $family );
		if ( '' === $family || '' === $css ) {
			return $css;
		}
		$blocks = array();
		$masked = preg_replace_callback(
			'/@font-face\s*\{[^}]*\}/i',
			static function ( $matches ) use ( &$blocks ) {
				$token    = '___WPFONTS_HOLD_' . count( $blocks ) . '___';
				$blocks[] = $matches[0];
				return $token;
			},
			$css
		);
		if ( ! is_string( $masked ) ) {
			return $css;
		}
		$masked = preg_replace_callback(
			'/(--wp--preset--font-family--[a-z0-9-]+)\s*:\s*([^;}{]+)/i',
			static function ( $matches ) use ( $family ) {
				return $matches[1] . ':' . self::append_family( $matches[2], $family );
			},
			$masked
		);
		if ( ! is_string( $masked ) ) {
			return $css;
		}
		$masked = preg_replace_callback(
			'/(?<!-)font-family\s*:\s*([^;}{]+)/i',
			static function ( $matches ) use ( $family ) {
				return 'font-family:' . self::append_family( $matches[1], $family );
			},
			$masked
		);
		if ( ! is_string( $masked ) ) {
			return $css;
		}
		foreach ( $blocks as $index => $block ) {
			$masked = str_replace( '___WPFONTS_HOLD_' . $index . '___', $block, $masked );
		}
		return $masked;
	}

	/**
	 * Insert a family before the first generic, or at the end when there is none.
	 *
	 * A stack that is only `var(...)` is returned unchanged so a preset variable
	 * rewrite stays the one edit. A family that is
	 * already present is left in place. `!important` stays at the end.
	 *
	 * @param string $value  Font stack, possibly with !important.
	 * @param string $family CSS family name, unquoted.
	 * @return string
	 */
	public static function append_family( $value, $family ) {
		$original = trim( (string) $value );
		$family   = self::safe_family( $family );
		if ( '' === $family || '' === $original ) {
			return $original;
		}
		$important = false;
		$stack     = $original;
		if ( preg_match( '/^(.*?)(\s*!important)\s*$/i', $stack, $matches ) ) {
			$stack     = trim( $matches[1] );
			$important = true;
		}
		$parts = self::split_stack( $stack );
		if ( array() === $parts ) {
			return $original;
		}
		if ( 1 === count( $parts ) && self::is_css_wide( $parts[0] ) ) {
			return $original;
		}
		$only_vars = true;
		foreach ( $parts as $part ) {
			if ( self::same_family( $part, $family ) ) {
				return $original;
			}
			if ( ! preg_match( '/^var\(/i', $part ) ) {
				$only_vars = false;
			}
		}
		if ( $only_vars ) {
			return $original;
		}
		$insert_at = count( $parts );
		foreach ( $parts as $index => $part ) {
			if ( self::is_generic( $part ) ) {
				$insert_at = $index;
				break;
			}
		}
		array_splice( $parts, $insert_at, 0, array( '"' . $family . '"' ) );
		$out = implode( ', ', $parts );
		if ( $important ) {
			$out .= ' !important';
		}
		return $out;
	}

	/**
	 * Plugin CSS for the front and the editor.
	 *
	 * Pass global CSS to also emit `.has-*-font-family` copies with the
	 * fallback inserted and `!important` kept. Null reads the live stylesheet
	 * when WordPress provides it.
	 *
	 * @param string|null    $global_css Global stylesheet, or null to read it.
	 * @param array|null     $roles      Resolved families. Null reads settings.
	 * @return string
	 */
	public static function frontend_css( $global_css = null, $roles = null ) {
		if ( ! is_array( $roles ) ) {
			$roles = self::resolved();
		}
		if ( null === $global_css ) {
			$global_css = '';
			if ( function_exists( 'wp_get_global_stylesheet' ) ) {
				$got = wp_get_global_stylesheet();
				if ( is_string( $got ) ) {
					$global_css = $got;
				}
			}
		}
		return self::compose_css( $roles, is_string( $global_css ) ? $global_css : '' );
	}

	/**
	 * Body, heading, and `.has-*-font-family` rules for one assignment.
	 *
	 * @param array  $roles      Resolved families.
	 * @param string $global_css Global stylesheet text. May be empty.
	 * @return string
	 */
	public static function compose_css( array $roles, $global_css ) {
		$body             = isset( $roles['body'] ) ? self::safe_family( $roles['body'] ) : '';
		$heading          = isset( $roles['heading'] ) ? self::safe_family( $roles['heading'] ) : '';
		$fallback         = isset( $roles['fallback'] ) ? self::safe_family( $roles['fallback'] ) : '';
		$body_override    = ! empty( $roles['body_override'] ) && '' !== $body && $body !== $fallback;
		$heading_override = ! empty( $roles['heading_override'] ) && '' !== $heading && $heading !== $fallback;
		$chunks           = array();
		if ( '' !== $body && ! $body_override ) {
			$chunks[] = ':where(body,.wp-site-blocks,.entry-content,.wp-block-post-content,.editor-styles-wrapper){font-family:' . self::quote_family( $body ) . ',sans-serif;}';
		}
		if ( '' !== $heading && ! $heading_override ) {
			$chunks[] = ':where(h1,h2,h3,h4,h5,h6,.wp-block-heading,.wp-block-post-title,.entry-title,.wp-block-site-title){font-family:' . self::quote_family( $heading ) . ',sans-serif;}';
		}
		if ( '' !== $fallback && is_string( $global_css ) && '' !== $global_css ) {
			$overrides = self::has_font_family_overrides( $global_css, $fallback );
			if ( '' !== $overrides ) {
				$chunks[] = $overrides;
			}
		}
		if ( $body_override ) {
			$chunks[] = 'body,.wp-site-blocks,.entry-content,.wp-block-post-content,.editor-styles-wrapper{font-family:' . self::quote_family( $body ) . ',sans-serif !important;}';
		}
		if ( $heading_override ) {
			$quoted   = self::quote_family( $heading );
			$chunks[] = 'h1,h2,h3,h4,h5,h6,.wp-block-heading,.wp-block-post-title,.entry-title,.wp-block-site-title{font-family:' . $quoted . ',sans-serif !important;}';
			$chunks[] = ':is(h1,h2,h3,h4,h5,h6,.wp-block-heading,.wp-block-post-title,.entry-title,.wp-block-site-title)[class*="-font-family"]{font-family:' . $quoted . ',sans-serif !important;}';
		}
		if ( array() === $chunks ) {
			return '';
		}
		return implode( "\n", $chunks );
	}

	/**
	 * Copies of `.has-*-font-family` rules with the fallback inserted.
	 *
	 * Preset variables in the same stylesheet are expanded first, so a rule
	 * that only points at `var(--wp--preset--font-family--*)` still receives
	 * a literal stack. The copy uses the original selector and `!important`.
	 *
	 * @param string $css    Stylesheet text.
	 * @param string $family CSS family name.
	 * @return string
	 */
	public static function has_font_family_overrides( $css, $family ) {
		$family = self::safe_family( $family );
		if ( '' === $family || ! is_string( $css ) || '' === $css ) {
			return '';
		}
		$vars = array();
		if ( preg_match_all( '/(--wp--preset--font-family--[a-z0-9-]+)\s*:\s*([^;}{]+)/i', $css, $found, PREG_SET_ORDER ) ) {
			foreach ( $found as $match ) {
				$vars[ strtolower( $match[1] ) ] = trim( $match[2] );
			}
		}
		if ( ! preg_match_all( '/([^{}@]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER ) ) {
			return '';
		}
		$out   = '';
		$count = 0;
		foreach ( $rules as $rule ) {
			if ( $count >= 80 ) {
				break;
			}
			$selector = trim( $rule[1] );
			if ( ! preg_match( '/\.has-[a-z0-9-]+-font-family(?![a-z0-9-])/i', $selector ) ) {
				continue;
			}
			if ( preg_match( '/[<>{}\\\\]|expression\s*\(|url\s*\(/i', $selector ) ) {
				continue;
			}
			if ( ! preg_match( '/(?<!-)font-family\s*:\s*([^;}{]+)/i', $rule[2], $decl ) ) {
				continue;
			}
			$expanded = self::expand_preset_vars( $decl[1], $vars );
			$next     = self::append_family( $expanded, $family );
			if ( ! self::stack_has_family( $next, $family ) ) {
				continue;
			}
			if ( ! preg_match( '/!important\s*$/i', $next ) ) {
				$next .= ' !important';
			}
			if ( preg_match( '/[<>{}]|expression\s*\(|url\s*\(/i', $next ) ) {
				continue;
			}
			$out .= $selector . '{font-family:' . $next . '}';
			++$count;
		}
		return $out;
	}

	/**
	 * Front styles. The editor has its own call.
	 *
	 * @return void
	 */
	public static function enqueue_front() {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}
		self::enqueue_style();
	}

	/**
	 * Editor canvas. enqueue_block_assets also runs on the front.
	 *
	 * @return void
	 */
	public static function enqueue_editor() {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}
		self::enqueue_style();
	}

	/**
	 * Attach the inline stylesheet once.
	 *
	 * @return void
	 */
	private static function enqueue_style() {
		if ( self::$enqueued || ! function_exists( 'wp_register_style' ) || ! function_exists( 'wp_add_inline_style' ) ) {
			return;
		}
		$css = self::frontend_css();
		if ( '' === $css ) {
			return;
		}
		$version = defined( 'WPFONTS_VERSION' ) ? WPFONTS_VERSION : false;
		if ( ! function_exists( 'wp_style_is' ) || ! wp_style_is( 'wpfonts-cjk', 'registered' ) ) {
			wp_register_style( 'wpfonts-cjk', false, array(), $version );
		}
		wp_enqueue_style( 'wpfonts-cjk' );
		wp_add_inline_style( 'wpfonts-cjk', $css );
		self::$enqueued = true;
	}

	/**
	 * Replace preset variables in a stack with the values from the same sheet.
	 *
	 * @param string $value Stack, possibly with !important.
	 * @param array  $vars  Custom property name => value.
	 * @return string
	 */
	private static function expand_preset_vars( $value, array $vars ) {
		$value     = trim( (string) $value );
		$important = false;
		if ( preg_match( '/^(.*?)(\s*!important)\s*$/i', $value, $matches ) ) {
			$value     = trim( $matches[1] );
			$important = true;
		}
		$parts = self::split_stack( $value );
		foreach ( $parts as $index => $part ) {
			if ( ! preg_match( '/^var\(\s*(--wp--preset--font-family--[a-z0-9-]+)/i', $part, $matches ) ) {
				continue;
			}
			$key = strtolower( $matches[1] );
			if ( ! isset( $vars[ $key ] ) ) {
				continue;
			}
			$replacement = trim( (string) $vars[ $key ] );
			$replacement = preg_replace( '/\s*!important\s*$/i', '', $replacement );
			if ( is_string( $replacement ) ) {
				$parts[ $index ] = trim( $replacement );
			}
		}
		$out = implode( ', ', $parts );
		if ( $important ) {
			$out .= ' !important';
		}
		return $out;
	}

	/**
	 * Whether the stack already names this family.
	 *
	 * @param string $value  Stack.
	 * @param string $family CSS family name.
	 * @return bool
	 */
	private static function stack_has_family( $value, $family ) {
		$stack = trim( (string) $value );
		if ( preg_match( '/^(.*?)(\s*!important)\s*$/i', $stack, $matches ) ) {
			$stack = trim( $matches[1] );
		}
		foreach ( self::split_stack( $stack ) as $part ) {
			if ( self::same_family( $part, $family ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Split a font stack on commas that are not inside quotes or parentheses.
	 *
	 * @param string $value Stack without !important.
	 * @return string[]
	 */
	private static function split_stack( $value ) {
		$parts = array();
		$buf   = '';
		$quote = '';
		$depth = 0;
		$len   = strlen( (string) $value );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $value[ $i ];
			if ( '' !== $quote ) {
				$buf .= $ch;
				if ( $ch === $quote && ( 0 === $i || '\\' !== $value[ $i - 1 ] ) ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $ch || "'" === $ch ) {
				$quote = $ch;
				$buf  .= $ch;
				continue;
			}
			if ( '(' === $ch ) {
				++$depth;
				$buf .= $ch;
				continue;
			}
			if ( ')' === $ch && $depth > 0 ) {
				--$depth;
				$buf .= $ch;
				continue;
			}
			if ( ',' === $ch && 0 === $depth ) {
				$part = trim( $buf );
				if ( '' !== $part ) {
					$parts[] = $part;
				}
				$buf = '';
				continue;
			}
			$buf .= $ch;
		}
		$part = trim( $buf );
		if ( '' !== $part ) {
			$parts[] = $part;
		}
		return $parts;
	}

	/**
	 * Whether this token is a generic family or a CSS-wide keyword.
	 *
	 * @param string $part One stack token.
	 * @return bool
	 */
	private static function is_generic( $part ) {
		$part = self::unquote( $part );
		$part = strtolower( $part );
		$list = array(
			'serif',
			'sans-serif',
			'monospace',
			'cursive',
			'fantasy',
			'system-ui',
			'ui-serif',
			'ui-sans-serif',
			'ui-monospace',
			'ui-rounded',
			'emoji',
			'math',
			'fangsong',
			'inherit',
			'initial',
			'unset',
			'revert',
			'revert-layer',
		);
		return in_array( $part, $list, true );
	}

	/**
	 * Whether this token is inherit, initial, unset, or revert.
	 *
	 * @param string $part One stack token.
	 * @return bool
	 */
	private static function is_css_wide( $part ) {
		$part = strtolower( self::unquote( $part ) );
		return in_array( $part, array( 'inherit', 'initial', 'unset', 'revert', 'revert-layer' ), true );
	}

	/**
	 * Whether two tokens name the same family.
	 *
	 * @param string $part   One stack token.
	 * @param string $family CSS family name, unquoted.
	 * @return bool
	 */
	private static function same_family( $part, $family ) {
		return 0 === strcasecmp( self::unquote( $part ), (string) $family );
	}

	/**
	 * Drop one pair of matching quotes.
	 *
	 * @param string $part Token.
	 * @return string
	 */
	private static function unquote( $part ) {
		$part = trim( (string) $part );
		$len  = strlen( $part );
		if ( $len >= 2 ) {
			$quote = $part[0];
			if ( ( '"' === $quote || "'" === $quote ) && $part[ $len - 1 ] === $quote ) {
				return substr( $part, 1, -1 );
			}
		}
		return $part;
	}

	/**
	 * Family name safe to place in a stylesheet, or empty.
	 *
	 * @param string $family Raw family.
	 * @return string
	 */
	private static function safe_family( $family ) {
		if ( ! class_exists( 'WPFonts_Library', false ) ) {
			return '';
		}
		$family = WPFonts_Library::plain_family( $family );
		if ( '' === $family || false !== strpos( $family, ',' ) ) {
			return '';
		}
		return $family;
	}

	/**
	 * Double-quoted family.
	 *
	 * @param string $family Safe family name.
	 * @return string
	 */
	private static function quote_family( $family ) {
		return '"' . $family . '"';
	}
}

WPFonts_CJK::init();
