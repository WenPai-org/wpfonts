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
	 * Default public settings. CDN is off.
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
		return $settings;
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
		$settings = array_merge( self::defaults(), $settings );
		if ( empty( $settings['cdn_enabled'] ) || 'valid' !== (string) $settings['key_status'] || ! WPFonts_Link::has_target( $settings ) ) {
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
}
