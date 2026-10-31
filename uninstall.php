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
delete_option( 'wpfonts_packages' );

/**
 * Drop the catalog snapshot and its cron event. Installed font packages stay.
 *
 * @return void
 */
function wpfonts_uninstall_catalog() {
	wp_clear_scheduled_hook( 'wpfonts_catalog_refresh' );
	wp_clear_scheduled_hook( 'wpfonts_catalog_bootstrap' );
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return;
	}
	$catalog = trailingslashit( $uploads['basedir'] ) . 'wpfonts/catalog.json';
	if ( is_file( $catalog ) ) {
		wp_delete_file( $catalog );
	}
	if ( is_file( $catalog . '.tmp' ) ) {
		wp_delete_file( $catalog . '.tmp' );
	}
}

wpfonts_uninstall_catalog();
