<?php
/**
 * Thin block-editor panel. It lives in the block inspector, not the admin bar.
 *
 * Picking a family writes that family's CSS stack onto the selected block.
 * Enabled packages and a confirmed cloud face are also listed in Global Styles.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor assets and a read-only family search.
 */
class WPFonts_Editor {

	/**
	 * Hook the editor and the REST search.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'enqueue_canvas' ) );
	}

	/**
	 * Inspector script. Users who cannot edit posts do not receive it.
	 *
	 * The script filters editor.BlockEdit. Inspector controls only render
	 * inside that context.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_enqueue_style(
			'wpfonts-editor',
			WPFONTS_DIR_URL . 'assets/css/wpfonts-editor.css',
			array(),
			WPFONTS_VERSION
		);
		wp_enqueue_script(
			'wpfonts-editor',
			WPFONTS_DIR_URL . 'assets/js/wpfonts-editor.js',
			array( 'wp-hooks', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-block-editor' ),
			WPFONTS_VERSION,
			true
		);

		$settings = class_exists( 'WPFonts_Settings', false ) ? WPFonts_Settings::get() : array();
		$cloud    = self::editor_cloud( $settings );
		wp_localize_script(
			'wpfonts-editor',
			'wpfontsEditor',
			array(
				'packages' => class_exists( 'WPFonts_Library', false ) ? WPFonts_Library::editor_packages( '' ) : array(),
				'cloud'    => $cloud,
				'i18n'     => array(
					'title'       => __( '文派字库', 'wpfonts' ),
					'intro'       => __( '选一个字体，用在当前区块。已启用的字体会出现在全站样式里。', 'wpfonts' ),
					'packages'    => __( '文风字体包', 'wpfonts' ),
					'cloud'       => __( '文风云', 'wpfonts' ),
					'noPackages'  => __( '还没有启用的文风字体包。', 'wpfonts' ),
					'noCloud'     => __( '设置里还没有确认能打开的文风字体。', 'wpfonts' ),
					'unsupported' => __( '这个区块不能单独设字体。', 'wpfonts' ),
					'applied'     => __( '已用在这段。', 'wpfonts' ),
				),
			)
		);
	}

	/**
	 * Load package CSS and the saved CDN link inside the editor canvas.
	 *
	 * The front has its own enqueue. This returns immediately there.
	 *
	 * @return void
	 */
	public static function enqueue_canvas() {
		if ( ! is_admin() ) {
			return;
		}
		if ( class_exists( 'WPFonts_Packages', false ) ) {
			WPFonts_Packages::enqueue_styles();
		}
		if ( ! class_exists( 'WPFonts_Settings', false ) || ! class_exists( 'WPFonts_Link', false ) ) {
			return;
		}
		$url = WPFonts_Link::embed_url( WPFonts_Settings::get() );
		if ( '' === $url ) {
			return;
		}
		wp_enqueue_style( 'wpfonts-cdn-editor', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The allowlisted URL must stay free of a ver query arg.
	}

	/**
	 * The one confirmed 文风云 face, or null when the stylesheet has not opened.
	 *
	 * @param array $settings Public settings.
	 * @return array{name:string,stack:string,css:string,preconnect:string,licenseNote:string}|null
	 */
	private static function editor_cloud( array $settings ) {
		if ( ! class_exists( 'WPFonts_Library', false ) || ! class_exists( 'WPFonts_Link', false ) ) {
			return null;
		}
		$label    = WPFonts_Library::catalog_label( $settings );
		$families = WPFonts_Library::confirmed_cloud_families( $settings, $label );
		if ( array() === $families ) {
			return null;
		}
		$row = $families[0]['font_family_settings'];
		$url = '';
		if ( isset( $settings['css_status'] ) && 'ok' === $settings['css_status'] ) {
			$built = WPFonts_Link::stylesheet_url( $settings );
			$url   = WPFonts_Link::is_allowlisted( $built ) ? $built : '';
		}
		return array(
			'name'        => $row['name'],
			'stack'       => $row['fontFamily'],
			'css'         => $url,
			'preconnect'  => WPFonts_Link::preconnect_origin( $url ),
			'licenseNote' => WPFonts_Link::CDN_LICENSE_NOTE,
		);
	}
}

WPFonts_Editor::init();
