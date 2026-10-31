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
		WPFonts_Packages::enqueue_styles();
	}
}

WPFonts_Embed::init();
