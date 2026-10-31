<?php
/**
 * Remove WPFonts options only.
 *
 * Global Styles and uploaded fonts belong to WordPress and are left in place.
 *
 * @package WPFonts
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpfonts_settings' );
delete_option( 'wpfonts_api_key' );
