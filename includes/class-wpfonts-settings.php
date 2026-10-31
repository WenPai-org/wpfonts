<?php
/**
 * Plugin settings. The API key lives in its own option and is not autoloaded.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and saves WPFonts options.
 */
class WPFonts_Settings {

	const OPTION = 'wpfonts_settings';

	const KEY_OPTION = 'wpfonts_api_key';

	/**
	 * True while an internal write is passing key_status through sanitize.
	 *
	 * A browser POST cannot set this flag.
	 *
	 * @var bool
	 */
	private static $writing = false;

	/**
	 * How many confirmed cloud faces one site can save.
	 *
	 * @var int
	 */
	const MAX_FACES = 12;

	/**
	 * System font collection codes. Each one can be turned off in settings.
	 *
	 * @return string[]
	 */
	public static function system_codes() {
		return array( 'cn', 'en', 'jp', 'kr' );
	}

	/**
	 * Default public settings. CDN is off. System collections stay on.
	 *
	 * `faces` is absent until a save writes it. An rc.6 row is still one
	 * family, css_status, and css_face.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'cdn_enabled'  => 0,
			'region'       => 'cn',
			'subset'       => 'zh-common',
			'project_slug' => '',
			'family'       => '',
			'key_status'   => '',
			'key_checked'  => 0,
			'css_status'   => '',
			'css_face'     => '',
			'system_cn'    => 1,
			'system_en'    => 1,
			'system_jp'    => 1,
			'system_kr'    => 1,
		);
	}

	/**
	 * Saved public settings, with defaults filled in.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$settings                 = array_merge( self::defaults(), $saved );
		$settings['cdn_enabled']  = empty( $settings['cdn_enabled'] ) ? 0 : 1;
		$settings['region']       = WPFonts_Link::region( $settings['region'] );
		$settings['subset']       = WPFonts_Link::subset( $settings['subset'] );
		$settings['project_slug'] = WPFonts_Link::token( $settings['project_slug'], 64 );
		$settings['family']       = WPFonts_Link::token( $settings['family'], 80 );
		if ( ! in_array( $settings['key_status'], array( '', 'valid', 'invalid', 'unreachable' ), true ) ) {
			$settings['key_status'] = '';
		}
		$settings['key_checked'] = (int) $settings['key_checked'];
		$css_status              = isset( $settings['css_status'] ) ? (string) $settings['css_status'] : '';
		if ( ! in_array( $css_status, array( '', 'ok', 'missing', 'unchecked' ), true ) ) {
			$css_status = '';
		}
		$settings['css_status'] = $css_status;
		$settings['css_face']   = WPFonts_Link::face_name( isset( $settings['css_face'] ) ? $settings['css_face'] : '' );
		foreach ( self::system_codes() as $code ) {
			$key              = 'system_' . $code;
			$settings[ $key ] = empty( $settings[ $key ] ) ? 0 : 1;
		}
		if ( array_key_exists( 'faces', $saved ) ) {
			$settings['faces'] = self::clean_stored_faces( $saved['faces'] );
		} else {
			unset( $settings['faces'] );
		}
		return $settings;
	}

	/**
	 * Whether a system collection should be registered. Missing keys stay on.
	 *
	 * @param string $code cn, en, jp, or kr.
	 * @return bool
	 */
	public static function system_enabled( $code ) {
		$code = (string) $code;
		if ( ! in_array( $code, self::system_codes(), true ) ) {
			return false;
		}
		$settings = self::get();
		$key      = 'system_' . $code;
		if ( ! array_key_exists( $key, $settings ) ) {
			return true;
		}
		return ! empty( $settings[ $key ] );
	}

	/**
	 * Stored cloud faces, including a single rc.6 face when `faces` was never written.
	 *
	 * An empty `faces` array is explicit: the legacy family is not put back.
	 * Rows without a CSS face are kept so a selection survives until a probe.
	 *
	 * @param array $settings Public settings.
	 * @return array<int,array{family:string,name:string,css_face:string,subset:string}>
	 */
	public static function saved_faces( array $settings ) {
		if ( array_key_exists( 'faces', $settings ) ) {
			return self::clean_stored_faces( $settings['faces'] );
		}
		$legacy = self::legacy_face( $settings );
		return ( null === $legacy ) ? array() : array( $legacy );
	}

	/**
	 * Faces that may be listed and loaded.
	 *
	 * When cdn_enabled or key_status is missing, that gate is skipped so a
	 * partial row from rc.6 still counts. A present key must be valid, and a
	 * present switch must be on.
	 *
	 * @param array $settings Public settings.
	 * @return array<int,array{family:string,name:string,css_face:string,subset:string}>
	 */
	public static function confirmed_faces( array $settings ) {
		if ( array_key_exists( 'cdn_enabled', $settings ) && empty( $settings['cdn_enabled'] ) ) {
			return array();
		}
		if ( array_key_exists( 'key_status', $settings ) && 'valid' !== (string) $settings['key_status'] ) {
			return array();
		}
		$out = array();
		foreach ( self::saved_faces( $settings ) as $face ) {
			if ( '' !== $face['css_face'] ) {
				$out[] = $face;
			}
		}
		return $out;
	}

	/**
	 * Stable signature for Global Styles. Order does not matter.
	 *
	 * @param mixed $settings Settings row.
	 * @return string
	 */
	public static function faces_signature( $settings ) {
		if ( ! is_array( $settings ) || ! array_key_exists( 'faces', $settings ) ) {
			return '';
		}
		$parts = array();
		foreach ( self::clean_stored_faces( $settings['faces'] ) as $face ) {
			$parts[] = $face['family'] . '|' . $face['css_face'] . '|' . $face['subset'];
		}
		sort( $parts );
		return implode( "\n", $parts );
	}

	/**
	 * One stored face, or null when the family is not wenfeng-*.
	 *
	 * @param mixed $row         Raw row.
	 * @param bool  $require_css True when a CSS face is required.
	 * @return array{family:string,name:string,css_face:string,subset:string}|null
	 */
	public static function normalize_face( $row, $require_css = false ) {
		if ( ! is_array( $row ) || ! class_exists( 'WPFonts_Link', false ) ) {
			return null;
		}
		$family = WPFonts_Link::token( isset( $row['family'] ) ? $row['family'] : '', 80 );
		if ( '' === $family || 0 !== strpos( $family, 'wenfeng-' ) ) {
			return null;
		}
		$css = WPFonts_Link::face_name( isset( $row['css_face'] ) ? $row['css_face'] : '' );
		if ( $require_css && '' === $css ) {
			return null;
		}
		$name = isset( $row['name'] ) ? (string) $row['name'] : '';
		$name = preg_replace( '/[\x00-\x1F\x7F<>]/', '', $name );
		if ( ! is_string( $name ) ) {
			$name = '';
		}
		$name = trim( $name );
		if ( function_exists( 'mb_substr' ) ) {
			$name = mb_substr( $name, 0, 80 );
		} elseif ( strlen( $name ) > 80 ) {
			$name = substr( $name, 0, 80 );
		}
		$subset = isset( $row['subset'] ) ? $row['subset'] : 'zh-common';
		return array(
			'family'   => $family,
			'name'     => $name,
			'css_face' => $css,
			'subset'   => WPFonts_Link::subset( $subset ),
		);
	}

	/**
	 * Saved API key, or empty. Not used on the front.
	 *
	 * @return string
	 */
	public static function api_key() {
		$key = get_option( self::KEY_OPTION, '' );
		return is_string( $key ) ? $key : '';
	}

	/**
	 * Whether a key is stored.
	 *
	 * @return bool
	 */
	public static function has_key() {
		return '' !== self::api_key();
	}

	/**
	 * Short mask for the admin screen. Not reversible to the full key.
	 *
	 * @param string $key API key.
	 * @return string
	 */
	public static function mask( $key ) {
		$key = (string) $key;
		$len = strlen( $key );
		if ( $len < 8 ) {
			return '';
		}
		return substr( $key, 0, 3 ) . '••••••' . substr( $key, -4 );
	}

	/**
	 * Keep a single-line secret, or return empty when it cannot be stored.
	 *
	 * @param mixed $raw Submitted key.
	 * @return string
	 */
	public static function clean_secret( $raw ) {
		$raw = trim( (string) $raw );
		$raw = preg_replace( '/\s+/', '', $raw );
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$len = strlen( $raw );
		if ( $len < 8 || $len > 256 ) {
			return '';
		}
		if ( ! preg_match( '/^[A-Za-z0-9._~+\/=-]+$/', $raw ) ) {
			return '';
		}
		return $raw;
	}

	/**
	 * Register the public option. The key option is written directly.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			'wpfonts',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize a settings form or an internal write.
	 *
	 * @param mixed $input Submitted or internally written value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		if ( self::$writing ) {
			return self::sanitize_internal( $input );
		}
		return self::sanitize_form( $input );
	}

	/**
	 * Save a public row from our own code, including a freshly checked status.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	public static function write( array $settings ) {
		self::$writing = true;
		update_option( self::OPTION, $settings );
		self::$writing = false;
	}

	/**
	 * Run a health check and store the status. Does nothing when no key is saved.
	 *
	 * @return string valid|invalid|unreachable|''
	 */
	public static function recheck() {
		$settings = self::get();
		$key      = self::api_key();
		if ( '' === $key ) {
			$settings['key_status']  = '';
			$settings['key_checked'] = 0;
			self::write( $settings );
			return '';
		}
		$status                  = WPFonts_Health::query( $key );
		$settings['key_status']  = $status;
		$settings['key_checked'] = time();
		$settings                = self::refresh_css( $settings );
		self::write( $settings );
		return $status;
	}

	/**
	 * Screen state for the admin pages.
	 *
	 * @param array $settings Public settings.
	 * @param bool  $has_key  Whether an API key is stored.
	 * @return string empty|nokey|unchecked|invalid|unreachable|incomplete|css_missing|ok
	 */
	public static function view_state( array $settings, $has_key ) {
		if ( empty( $settings['cdn_enabled'] ) ) {
			return 'empty';
		}
		if ( ! $has_key ) {
			return 'nokey';
		}
		$status = isset( $settings['key_status'] ) ? (string) $settings['key_status'] : '';
		if ( 'invalid' === $status ) {
			return 'invalid';
		}
		if ( 'unreachable' === $status ) {
			return 'unreachable';
		}
		if ( 'valid' !== $status ) {
			return 'unchecked';
		}
		if ( ! WPFonts_Link::has_target( $settings ) ) {
			return 'incomplete';
		}
		if ( isset( $settings['css_status'] ) && 'missing' === $settings['css_status'] ) {
			return 'css_missing';
		}
		return 'ok';
	}

	/**
	 * Check the saved stylesheet once. A miss is stored. A timeout keeps an
	 * earlier confirmation when the address did not change.
	 *
	 * @param array $settings Public settings. May include a previous css_status.
	 * @return array
	 */
	public static function refresh_css( array $settings ) {
		$had_faces = array_key_exists( 'faces', $settings );
		$faces     = ( $had_faces && is_array( $settings['faces'] ) ) ? $settings['faces'] : null;
		$settings  = array_merge( self::defaults(), $settings );
		if ( null === $faces ) {
			unset( $settings['faces'] );
		} else {
			$settings['faces'] = $faces;
		}
		$can_probe = ! empty( $settings['cdn_enabled'] ) && 'valid' === (string) $settings['key_status'];
		if ( ! $can_probe ) {
			$settings['css_status'] = '';
			$settings['css_face']   = '';
			return $settings;
		}
		if ( array_key_exists( 'faces', $settings ) ) {
			$settings['faces'] = self::probe_face_list( $settings );
		}
		if ( ! WPFonts_Link::has_target( $settings ) ) {
			$settings['css_status'] = '';
			$settings['css_face']   = '';
			return $settings;
		}
		$previous_status = (string) $settings['css_status'];
		$previous_face   = (string) $settings['css_face'];
		$probe           = WPFonts_Link::probe_stylesheet( $settings );
		if ( 'unchecked' === $probe['status'] && 'ok' === $previous_status && '' !== $previous_face ) {
			$settings['css_status'] = 'ok';
			$settings['css_face']   = WPFonts_Link::face_name( $previous_face );
			return $settings;
		}
		$settings['css_status'] = $probe['status'];
		$settings['css_face']   = $probe['face'];
		return $settings;
	}

	/**
	 * Normalize an internal write. Still drops anything that is not our shape.
	 *
	 * @param array $input Internal row.
	 * @return array
	 */
	private static function sanitize_internal( array $input ) {
		$settings                 = self::defaults();
		$settings['cdn_enabled']  = ( isset( $input['cdn_enabled'] ) && '1' === (string) $input['cdn_enabled'] ) ? 1 : 0;
		$settings['region']       = WPFonts_Link::region( isset( $input['region'] ) ? $input['region'] : 'cn' );
		$settings['subset']       = WPFonts_Link::subset( isset( $input['subset'] ) ? $input['subset'] : 'zh-common' );
		$settings['project_slug'] = WPFonts_Link::token( isset( $input['project_slug'] ) ? $input['project_slug'] : '', 64 );
		$settings['family']       = WPFonts_Link::token( isset( $input['family'] ) ? $input['family'] : '', 80 );
		$status                   = isset( $input['key_status'] ) ? (string) $input['key_status'] : '';
		$settings['key_status']   = in_array( $status, array( '', 'valid', 'invalid', 'unreachable' ), true ) ? $status : '';
		$settings['key_checked']  = isset( $input['key_checked'] ) ? (int) $input['key_checked'] : 0;
		$css_status               = isset( $input['css_status'] ) ? (string) $input['css_status'] : '';
		$settings['css_status']   = in_array( $css_status, array( '', 'ok', 'missing', 'unchecked' ), true ) ? $css_status : '';
		$settings['css_face']     = WPFonts_Link::face_name( isset( $input['css_face'] ) ? $input['css_face'] : '' );
		foreach ( self::system_codes() as $code ) {
			$key = 'system_' . $code;
			if ( array_key_exists( $key, $input ) ) {
				$settings[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}
		}
		if ( array_key_exists( 'faces', $input ) ) {
			$settings['faces'] = self::clean_stored_faces( $input['faces'] );
		}
		return $settings;
	}

	/**
	 * Sanitize the settings screen.
	 *
	 * @param array $input Form fields.
	 * @return array
	 */
	private static function sanitize_form( array $input ) {
		$prev    = self::get();
		$surface = isset( $input['surface'] ) ? (string) $input['surface'] : 'simple';
		$next    = WPFonts_Link::merge_public( $prev, $input, $surface );
		$key     = self::api_key();
		$changed = false;

		if ( 'advanced' === $surface ) {
			if ( ! empty( $input['clear_api_key'] ) ) {
				$key     = '';
				$changed = true;
				delete_option( self::KEY_OPTION );
			} elseif ( isset( $input['api_key'] ) && '' !== trim( (string) $input['api_key'] ) ) {
				$clean = self::clean_secret( $input['api_key'] );
				if ( '' === $clean ) {
					self::note( 'rejected' );
				} elseif ( ! hash_equals( $key, $clean ) ) {
					$key     = $clean;
					$changed = true;
					update_option( self::KEY_OPTION, $clean, false );
				}
			}
		}

		$next = self::apply_form_lists( $prev, $next, $input );

		if ( '' === $key ) {
			$next['key_status']  = '';
			$next['key_checked'] = 0;
			$next['css_status']  = '';
			$next['css_face']    = '';
			if ( $changed ) {
				delete_option( self::KEY_OPTION );
			}
			return $next;
		}

		$should_check = ! empty( $next['cdn_enabled'] ) && ( $changed || 'advanced' === $surface || '' === $prev['key_status'] );
		if ( $changed && empty( $next['cdn_enabled'] ) ) {
			$next['key_status']  = '';
			$next['key_checked'] = 0;
			$next['css_status']  = '';
			$next['css_face']    = '';
			return $next;
		}
		if ( $should_check ) {
			$next['key_status']  = WPFonts_Health::query( $key );
			$next['key_checked'] = time();
		}
		$next['css_status'] = isset( $prev['css_status'] ) ? (string) $prev['css_status'] : '';
		$next['css_face']   = isset( $prev['css_face'] ) ? (string) $prev['css_face'] : '';
		if ( self::stylesheet_changed( $prev, $next ) ) {
			$next['css_status'] = '';
			$next['css_face']   = '';
		}
		return self::refresh_css( $next );
	}

	/**
	 * Whether the public stylesheet address changed.
	 *
	 * @param array $prev Previous public settings.
	 * @param array $next Next public settings.
	 * @return bool
	 */
	private static function stylesheet_changed( array $prev, array $next ) {
		return WPFonts_Link::stylesheet_url( $prev ) !== WPFonts_Link::stylesheet_url( $next );
	}

	/**
	 * Remember a one-shot admin note for this user.
	 *
	 * @param string $code Note code.
	 * @return void
	 */
	public static function note( $code ) {
		if ( ! function_exists( 'get_current_user_id' ) || ! function_exists( 'set_transient' ) ) {
			return;
		}
		$user = (int) get_current_user_id();
		if ( $user < 1 ) {
			return;
		}
		set_transient( 'wpfonts_note_' . $user, (string) $code, 60 );
	}

	/**
	 * Take and clear the one-shot admin note.
	 *
	 * @return string
	 */
	public static function pull_note() {
		if ( ! function_exists( 'get_current_user_id' ) || ! function_exists( 'get_transient' ) ) {
			return '';
		}
		$user = (int) get_current_user_id();
		if ( $user < 1 ) {
			return '';
		}
		$key  = 'wpfonts_note_' . $user;
		$note = get_transient( $key );
		if ( ! is_string( $note ) || '' === $note ) {
			return '';
		}
		delete_transient( $key );
		return $note;
	}

	/**
	 * Copy system toggles and the requested cloud faces onto a form save.
	 *
	 * The API key is not read here. A form that does not include the face
	 * picker keeps the stored list, including an rc.6 row that has no `faces` key.
	 *
	 * @param array $prev  Saved public settings.
	 * @param array $next  Sanitized public fields.
	 * @param array $input Raw form input.
	 * @return array
	 */
	private static function apply_form_lists( array $prev, array $next, array $input ) {
		foreach ( self::system_codes() as $code ) {
			$key = 'system_' . $code;
			if ( ! empty( $input['system_form'] ) ) {
				$next[ $key ] = ( isset( $input[ $key ] ) && '1' === (string) $input[ $key ] ) ? 1 : 0;
			} else {
				$next[ $key ] = empty( $prev[ $key ] ) ? 0 : 1;
			}
		}
		if ( ! empty( $input['families_form'] ) ) {
			$tokens   = self::requested_tokens( $input, isset( $next['family'] ) ? $next['family'] : '' );
			$previous = self::saved_faces( $prev );
			$by       = array();
			foreach ( $previous as $face ) {
				$by[ $face['family'] ] = $face;
			}
			$faces = array();
			foreach ( $tokens as $token ) {
				if ( isset( $by[ $token ] ) ) {
					$row           = $by[ $token ];
					$row['subset'] = $next['subset'];
					if ( $by[ $token ]['subset'] !== $next['subset'] ) {
						$row['css_face'] = '';
					}
					$faces[] = $row;
					continue;
				}
				$faces[] = array(
					'family'   => $token,
					'name'     => self::catalog_name( $token ),
					'css_face' => '',
					'subset'   => $next['subset'],
				);
			}
			$next['faces'] = $faces;
		} elseif ( array_key_exists( 'faces', $prev ) ) {
			$next['faces'] = $prev['faces'];
		}
		return $next;
	}

	/**
	 * wenfeng-* tokens from the checkboxes and the manual field, in order.
	 *
	 * @param array  $input  Form input.
	 * @param string $family Manual family field.
	 * @return string[]
	 */
	private static function requested_tokens( array $input, $family ) {
		$raw = array();
		if ( isset( $input['families'] ) && is_array( $input['families'] ) ) {
			$raw = $input['families'];
		}
		$raw[] = $family;
		$out   = array();
		foreach ( $raw as $value ) {
			$token = WPFonts_Link::token( $value, 80 );
			if ( '' === $token || 0 !== strpos( $token, 'wenfeng-' ) || in_array( $token, $out, true ) ) {
				continue;
			}
			$out[] = $token;
			if ( count( $out ) >= self::MAX_FACES ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Probe each requested face. A miss is dropped. A timeout keeps a prior confirmation.
	 *
	 * @param array $settings Public settings. Must include faces.
	 * @return array<int,array{family:string,name:string,css_face:string,subset:string}>
	 */
	private static function probe_face_list( array $settings ) {
		$out   = array();
		$seen  = array();
		$rows  = is_array( $settings['faces'] ) ? $settings['faces'] : array();
		$count = 0;
		foreach ( $rows as $row ) {
			$face = self::normalize_face( $row, false );
			if ( null === $face || isset( $seen[ $face['family'] ] ) ) {
				continue;
			}
			$seen[ $face['family'] ] = true;
			if ( $count >= self::MAX_FACES ) {
				break;
			}
			++$count;
			$probe = WPFonts_Link::probe_family( $settings, $face['family'], $face['subset'] );
			if ( 'ok' === $probe['status'] && '' !== $probe['face'] ) {
				$face['css_face'] = $probe['face'];
				$named            = self::catalog_name( $face['family'] );
				if ( '' !== $named ) {
					$face['name'] = $named;
				}
				if ( '' === $face['name'] ) {
					$face['name'] = $face['css_face'];
				}
				$out[] = $face;
				continue;
			}
			if ( 'unchecked' === $probe['status'] && '' !== $face['css_face'] ) {
				if ( '' === $face['name'] ) {
					$face['name'] = $face['css_face'];
				}
				$out[] = $face;
			}
		}
		return $out;
	}

	/**
	 * Display name from the local catalog snapshot, or empty.
	 *
	 * @param string $token wenfeng-* family.
	 * @return string
	 */
	private static function catalog_name( $token ) {
		if ( ! class_exists( 'WPFonts_Catalog', false ) ) {
			return '';
		}
		foreach ( WPFonts_Catalog::stored_fonts() as $font ) {
			if ( isset( $font['fontFamily'] ) && $font['fontFamily'] === $token && isset( $font['name'] ) ) {
				$name = trim( (string) $font['name'] );
				return $name;
			}
		}
		return '';
	}

	/**
	 * rc.6 confirmation: one wenfeng-* family whose stylesheet opened.
	 *
	 * @param array $settings Public settings.
	 * @return array{family:string,name:string,css_face:string,subset:string}|null
	 */
	private static function legacy_face( array $settings ) {
		$status = isset( $settings['css_status'] ) ? (string) $settings['css_status'] : '';
		if ( 'ok' !== $status ) {
			return null;
		}
		$face = self::normalize_face(
			array(
				'family'   => isset( $settings['family'] ) ? $settings['family'] : '',
				'name'     => '',
				'css_face' => isset( $settings['css_face'] ) ? $settings['css_face'] : '',
				'subset'   => isset( $settings['subset'] ) ? $settings['subset'] : 'zh-common',
			),
			true
		);
		if ( null === $face ) {
			return null;
		}
		$named = self::catalog_name( $face['family'] );
		if ( '' !== $named ) {
			$face['name'] = $named;
		}
		if ( '' === $face['name'] ) {
			$face['name'] = $face['css_face'];
		}
		return $face;
	}

	/**
	 * Deduped wenfeng-* rows. A CSS face may be empty.
	 *
	 * @param mixed $rows Raw list.
	 * @return array<int,array{family:string,name:string,css_face:string,subset:string}>
	 */
	private static function clean_stored_faces( $rows ) {
		$out  = array();
		$seen = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$face = self::normalize_face( $row, false );
			if ( null === $face || isset( $seen[ $face['family'] ] ) ) {
				continue;
			}
			$seen[ $face['family'] ] = true;
			$out[]                   = $face;
			if ( count( $out ) >= self::MAX_FACES ) {
				break;
			}
		}
		return $out;
	}
}
