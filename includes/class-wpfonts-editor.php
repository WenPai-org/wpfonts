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
		$cloud    = self::editor_cloud_list( $settings );
		wp_localize_script(
			'wpfonts-editor',
			'wpfontsEditor',
			array(
				'packages' => class_exists( 'WPFonts_Library', false ) ? WPFonts_Library::editor_packages( '' ) : array(),
				'cloud'    => $cloud,
				'i18n'     => array(
					'title'       => __( '文派字库', 'wpfonts' ),
					'intro'       => __( '侧栏里的选择只改当前区块。', 'wpfonts' ),
					'packages'    => __( 'Windfonts', 'wpfonts' ),
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
		$urls  = WPFonts_Link::front_stylesheets( WPFonts_Settings::get() );
		$total = count( $urls );
		for ( $index = 0; $index < $total; $index++ ) {
			$handle = ( 0 === $index ) ? 'wpfonts-cdn-editor' : 'wpfonts-cdn-editor-' . $index;
			wp_enqueue_style( $handle, $urls[ $index ], array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The allowlisted URL must stay free of a ver query arg.
		}
	}

	/**
	 * Confirmed 文风云 faces for the inspector.
	 *
	 * The list is the saved confirmation. Opening the panel does not search
	 * the catalog or call Windfonts.
	 *
	 * @param array $settings Public settings.
	 * @return array<int,array{name:string,stack:string,css:string,preconnect:string,licenseNote:string}>
	 */
	private static function editor_cloud_list( array $settings ) {
		if ( ! class_exists( 'WPFonts_Library', false ) || ! class_exists( 'WPFonts_Link', false ) || ! class_exists( 'WPFonts_Settings', false ) ) {
			return array();
		}
		$label    = WPFonts_Library::catalog_label( $settings );
		$families = WPFonts_Library::confirmed_cloud_families( $settings, $label );
		$faces    = WPFonts_Settings::confirmed_faces( $settings );
		$by       = array();
		foreach ( $faces as $face ) {
			$by[ $face['family'] ] = $face;
		}
		$out = array();
		foreach ( $families as $definition ) {
			$row  = $definition['font_family_settings'];
			$slug = isset( $row['slug'] ) ? (string) $row['slug'] : '';
			$url  = '';
			if ( isset( $by[ $slug ] ) ) {
				$built = WPFonts_Link::family_stylesheet_url( $settings, $slug, $by[ $slug ]['subset'] );
				$url   = WPFonts_Link::is_allowlisted( $built ) ? $built : '';
			}
			$out[] = array(
				'name'        => $row['name'],
				'stack'       => $row['fontFamily'],
				'css'         => $url,
				'preconnect'  => WPFonts_Link::preconnect_origin( $url ),
				'licenseNote' => WPFonts_Link::CDN_LICENSE_NOTE,
			);
		}
		return $out;
	}
}

WPFonts_Editor::init();
