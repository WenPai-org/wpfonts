<?php
/**
 * Front stylesheet tag. Prints an allowlisted link, or nothing.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the CDN stylesheet on public views.
 */
class WPFonts_Embed {

	/**
	 * Hook the front enqueue.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_license_note' ), 7 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'preview_admin_bar' ), 100 );
	}

	/**
	 * Admin-only preview token from ?wpfonts_preview=wenfeng-*, or empty.
	 *
	 * Read-only selector for the front-end preview. It never writes options
	 * and never changes which faces are confirmed.
	 *
	 * @return string
	 */
	private static function preview_request() {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview selector, gated by manage_options.
		if ( empty( $_GET['wpfonts_preview'] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only selector.
		$token = WPFonts_Link::token( sanitize_text_field( wp_unslash( $_GET['wpfonts_preview'] ) ), 80 );
		if ( '' === $token || 0 !== strpos( $token, 'wenfeng-' ) ) {
			return '';
		}
		return $token;
	}

	/**
	 * Allowlisted stylesheet URL for the preview token, or empty.
	 *
	 * @param array  $settings Public settings.
	 * @param string $token    wenfeng-* token.
	 * @return string
	 */
	private static function preview_url( array $settings, $token ) {
		$subset = isset( $settings['subset'] ) ? (string) $settings['subset'] : 'zh-common';
		$url    = WPFonts_Link::family_stylesheet_url( $settings, $token, $subset );
		if ( '' === $url || ! WPFonts_Link::is_allowlisted( $url ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * CSS that applies the preview face to body text for this request only.
	 *
	 * The name must be the @font-face name from the stylesheet, which is not
	 * always the wenfeng-* token (e.g. PangZhenGui-PMZD).
	 *
	 * @param string $face CSS font-family name.
	 * @return string
	 */
	private static function preview_css( $face ) {
		$family = '"' . $face . '"';
		return 'body{font-family:' . $family . ',-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif!important}'
			. 'h1,h2,h3,h4,h5,h6{font-family:inherit!important}';
	}

	/**
	 * Resolve the @font-face name for a preview token.
	 *
	 * Confirmed faces already store the probed name. Anything else is probed
	 * once per URL per day through the allowlisted stylesheet; failures are
	 * cached briefly so a dead CDN is not hit on every request.
	 *
	 * @param array  $settings Public settings.
	 * @param string $token    wenfeng-* token.
	 * @return string CSS font-family name, or empty.
	 */
	private static function preview_face( array $settings, $token ) {
		if ( class_exists( 'WPFonts_Settings', false ) ) {
			foreach ( WPFonts_Settings::confirmed_faces( $settings ) as $face ) {
				if ( isset( $face['family'], $face['css_face'] ) && $face['family'] === $token && '' !== $face['css_face'] ) {
					return $face['css_face'];
				}
			}
		}
		$url = self::preview_url( $settings, $token );
		if ( '' === $url ) {
			return '';
		}
		$key    = 'wpfonts_preview_face_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_string( $cached ) && '' !== $cached ) {
			$parts = explode( '|', $cached, 2 );
			return 'ok' === $parts[0] ? $parts[1] : '';
		}
		$probe = WPFonts_Link::probe_family( $settings, $token );
		$ttl   = 'ok' === $probe['status'] ? DAY_IN_SECONDS : 10 * MINUTE_IN_SECONDS;
		set_transient( $key, $probe['status'] . '|' . $probe['face'], $ttl );
		return 'ok' === $probe['status'] ? $probe['face'] : '';
	}

	/**
	 * License comment and preconnect. Printed before wp_print_styles.
	 *
	 * wp_enqueue_style() cannot carry an HTML comment. The comment is the
	 * fixed storefront note. Preconnect uses only the stylesheet's own origin.
	 *
	 * @return void
	 */
	public static function print_license_note() {
		if ( is_admin() ) {
			return;
		}
		$settings = WPFonts_Settings::get();
		$urls     = WPFonts_Link::front_stylesheets( $settings );
		$token    = self::preview_request();
		if ( '' !== $token ) {
			$preview = self::preview_url( $settings, $token );
			if ( '' !== $preview && ! in_array( $preview, $urls, true ) ) {
				$urls[] = $preview;
			}
		}
		if ( array() === $urls ) {
			return;
		}
		$origin = WPFonts_Link::preconnect_origin( $urls[0] );
		if ( '' === $origin ) {
			return;
		}
		echo "\n" . WPFonts_Link::CDN_LICENSE_NOTE . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed license comment. It contains no request data.
		echo '<link rel="preconnect" href="' . esc_url( $origin ) . '" crossorigin>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Resource hint for the allowlisted CDN origin, printed with the license comment.
	}

	/**
	 * Print the stylesheet link when CDN is on and the last check passed.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( is_admin() ) {
			return;
		}
		$settings = WPFonts_Settings::get();
		$html     = '';
		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$post = get_post();
			if ( $post instanceof WP_Post ) {
				$html = (string) $post->post_content;
			}
		}
		$globals = array();
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$globals = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		}
		$urls  = WPFonts_Link::front_stylesheets( $settings, $html, $globals );
		$total = count( $urls );
		for ( $index = 0; $index < $total; $index++ ) {
			$handle = ( 0 === $index ) ? 'wpfonts-cdn' : 'wpfonts-cdn-' . $index;
			// null avoids appending ?ver=, which would change the CDN request.
			wp_enqueue_style( $handle, $urls[ $index ], array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The allowlisted URL must stay free of a ver query arg.
		}
		$token = self::preview_request();
		if ( '' !== $token ) {
			$preview = self::preview_url( $settings, $token );
			$face    = '' !== $preview ? self::preview_face( $settings, $token ) : '';
			if ( '' !== $preview && '' !== $face ) {
				$existing = array_search( $preview, $urls, true );
				if ( false === $existing ) {
					wp_enqueue_style( 'wpfonts-preview', $preview, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Same allowlisted URL rule as above.
					wp_add_inline_style( 'wpfonts-preview', self::preview_css( $face ) );
				} else {
					$handle = 0 === $existing ? 'wpfonts-cdn' : 'wpfonts-cdn-' . $existing;
					wp_add_inline_style( $handle, self::preview_css( $face ) );
				}
			}
		}
		WPFonts_Packages::enqueue_styles();
	}

	/**
	 * Admin bar node marking an active font preview; click exits the preview.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public static function preview_admin_bar( $wp_admin_bar ) {
		$token = self::preview_request();
		if ( '' === $token ) {
			return;
		}
		$settings = WPFonts_Settings::get();
		if ( '' === self::preview_url( $settings, $token ) || '' === self::preview_face( $settings, $token ) ) {
			return;
		}
		$wp_admin_bar->add_node(
			array(
				'id'    => 'wpfonts-preview',
				'title' => esc_html(
					sprintf(
						/* translators: %s: previewed font family token. */
						__( '字体预览：%s', 'wpfonts' ),
						$token
					)
				),
				'href'  => esc_url( remove_query_arg( 'wpfonts_preview' ) ),
				'meta'  => array( 'title' => esc_attr__( '退出预览', 'wpfonts' ) ),
			)
		);
	}
}

WPFonts_Embed::init();
