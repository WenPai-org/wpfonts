<?php
/**
 * Settings submenu registration and page URLs.
 * Every WenPai settings plugin lives under Settings, never as a top-level menu.
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Menu', false ) ) {
	/**
	 * Thin wrapper over add_options_page so plugins cannot drift.
	 */
	class Wenpai_Admin_Menu {

		/**
		 * Register the settings page under Settings.
		 *
		 * @param array $args {
		 *     Page arguments.
		 *
		 *     @type string   $slug       Page slug (wpslug / wpsss).
		 *     @type string   $label      Feature name for the left menu, already translated.
		 *     @type string   $title      Optional browser title. Defaults to label.
		 *     @type string   $capability Defaults to manage_options.
		 *     @type callable $render     Page callback.
		 * }
		 * @return string|false Hook suffix.
		 */
		public static function register( $args ) {
			$slug  = isset( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
			$label = isset( $args['label'] ) ? $args['label'] : $slug;
			$title = isset( $args['title'] ) ? $args['title'] : $label;
			$cap   = isset( $args['capability'] ) ? $args['capability'] : 'manage_options';
			$cb    = isset( $args['render'] ) ? $args['render'] : '__return_null';
			if ( '' === $slug ) {
				return false;
			}
			return add_options_page( $title, $label, $cap, $slug, $cb );
		}

		/**
		 * Canonical admin URL for a settings page.
		 *
		 * @param string $slug Page slug.
		 * @param array  $args Extra query args (tab, etc).
		 * @return string
		 */
		public static function url( $slug, $args = array() ) {
			$args = array_merge( array( 'page' => sanitize_key( $slug ) ), (array) $args );
			return add_query_arg( $args, admin_url( 'options-general.php' ) );
		}

		/**
		 * Hook suffix WordPress assigns to the page.
		 *
		 * @param string $slug Page slug.
		 * @return string
		 */
		public static function hook( $slug ) {
			return 'settings_page_' . sanitize_key( $slug );
		}
	}
}
