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
		$url = WPFonts_Link::embed_url( WPFonts_Settings::get() );
		if ( '' === $url ) {
			return;
		}
		// null avoids appending ?ver=, which would change the CDN request.
		wp_enqueue_style( 'wpfonts-cdn', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The allowlisted URL must stay free of a ver query arg.
	}
}

WPFonts_Embed::init();
