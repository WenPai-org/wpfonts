<?php
/**
 * Thin block-editor panel. It lives in the block inspector, not the admin bar.
 *
 * Picking a family writes that family's CSS stack onto the selected block.
 * Site-wide Global Styles stay on the Font Library install path.
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
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Inspector script. Users who cannot edit posts do not receive it.
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
			array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-block-editor', 'wp-api-fetch' ),
			WPFONTS_VERSION,
			true
		);

		$settings = class_exists( 'WPFonts_Settings', false ) ? WPFonts_Settings::get() : array();
		$ready    = class_exists( 'WPFonts_Link', false ) && '' !== WPFonts_Link::embed_url( $settings );
		wp_localize_script(
			'wpfonts-editor',
			'wpfontsEditor',
			array(
				'packages' => class_exists( 'WPFonts_Library', false ) ? WPFonts_Library::editor_packages( '' ) : array(),
				'cdn'      => array(
					'ready'  => $ready,
					'host'   => class_exists( 'WPFonts_Link', false ) ? WPFonts_Link::host( isset( $settings['region'] ) ? $settings['region'] : 'cn' ) : '',
					'subset' => isset( $settings['subset'] ) ? (string) $settings['subset'] : 'zh-common',
				),
				'i18n'     => array(
					'title'       => __( '文派字库', 'wpfonts' ),
					'intro'       => __( '选一个字体，用在当前区块。全站安装请用字体库。', 'wpfonts' ),
					'packages'    => __( '文风字体包', 'wpfonts' ),
					'cloud'       => __( '文风云', 'wpfonts' ),
					'search'      => __( '搜索字体名', 'wpfonts' ),
					'noPackages'  => __( '还没有启用的文风字体包。', 'wpfonts' ),
					'noCatalog'   => __( '还没有字体目录。可点「更新字体目录」再试，或先手填字体名。', 'wpfonts' ),
					'more'        => __( '还有更多，请搜索字体名。', 'wpfonts' ),
					'unsupported' => __( '这个区块不能单独设字体。', 'wpfonts' ),
					'cdnOff'      => __( '文风云未开启，前台还不会加载远程字体。', 'wpfonts' ),
					'applied'     => __( '已用在这段。', 'wpfonts' ),
					'emptySearch' => __( '没有匹配的字体。', 'wpfonts' ),
					'fail'        => __( '暂时读不到字体目录。', 'wpfonts' ),
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
	 * Read-only family search for the inspector.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'wpfonts/v1',
			'/families',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_families' ),
				'permission_callback' => array( __CLASS__, 'rest_permission' ),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Editors and above. The payload is names only.
	 *
	 * @return bool
	 */
	public static function rest_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Packages and a short page of catalog rows.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_families( WP_REST_Request $request ) {
		$search = $request->get_param( 'search' );
		$search = is_string( $search ) ? $search : '';
		$list   = class_exists( 'WPFonts_Catalog', false ) ? WPFonts_Catalog::editor_list( $search ) : array(
			'fonts'     => array(),
			'total'     => 0,
			'truncated' => false,
		);
		return rest_ensure_response(
			array(
				'packages'  => class_exists( 'WPFonts_Library', false ) ? WPFonts_Library::editor_packages( $search ) : array(),
				'fonts'     => $list['fonts'],
				'total'     => $list['total'],
				'truncated' => ! empty( $list['truncated'] ),
			)
		);
	}
}

WPFonts_Editor::init();
