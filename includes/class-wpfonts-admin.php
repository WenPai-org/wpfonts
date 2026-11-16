<?php
/**
 * Settings screens: 概览, 设置, 工具, and 帮助 in the header.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wenpai admin shell for WPFonts.
 */
class WPFonts_Admin {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_wpfonts_recheck', array( __CLASS__, 'recheck' ) );
		add_action( 'wp_ajax_wpfonts_catalog', array( __CLASS__, 'ajax_catalog' ) );
		add_action( 'admin_post_wpfonts_install_package', array( __CLASS__, 'install_package' ) );
		add_action( 'admin_post_wpfonts_package_toggle', array( __CLASS__, 'toggle_package' ) );
		add_action( 'admin_post_wpfonts_package_delete', array( __CLASS__, 'delete_package' ) );
		add_action( 'admin_post_wpfonts_export', array( __CLASS__, 'export_migration' ) );
		add_action( 'admin_post_wpfonts_import', array( __CLASS__, 'import_migration' ) );
		add_action( 'admin_notices', array( __CLASS__, 'cdn_notice' ) );
		add_filter( 'plugin_action_links_' . WPFONTS_BASENAME, array( __CLASS__, 'action_links' ) );
		add_filter( 'gettext', array( __CLASS__, 'saved_label' ), 10, 3 );
	}

	/**
	 * Settings submenu. The left menu uses the feature name; the browser title is the brand.
	 *
	 * @return void
	 */
	public static function register_menu() {
		self::boot();
		if ( ! class_exists( 'Wenpai_Admin_Menu', false ) ) {
			return;
		}
		Wenpai_Admin_Menu::register(
			array(
				'slug'   => 'wpfonts',
				'label'  => __( '字库', 'wpfonts' ),
				'title'  => __( '文派字库', 'wpfonts' ),
				'render' => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Register the public option group.
	 *
	 * @return void
	 */
	public static function register_settings() {
		WPFonts_Settings::register();
		WPFonts_Schema::register();
	}

	/**
	 * Load the kit on this screen only. Handle stays wenpai-admin-ui.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		$expected = class_exists( 'Wenpai_Admin_Menu', false ) ? Wenpai_Admin_Menu::hook( 'wpfonts' ) : 'settings_page_wpfonts';
		if ( $hook !== $expected ) {
			return;
		}
		self::boot();
		if ( class_exists( 'Wenpai_Admin_Loader', false ) ) {
			Wenpai_Admin_Loader::enqueue(
				array(
					'full' => true,
					'body' => 'wpfonts-admin',
				)
			);
		}
		wp_enqueue_style(
			'wpfonts-admin',
			WPFONTS_DIR_URL . 'assets/css/wpfonts-admin.css',
			array( 'wenpai-admin-ui' ),
			WPFONTS_VERSION
		);
		wp_enqueue_script(
			'wpfonts-admin',
			WPFONTS_DIR_URL . 'assets/js/wpfonts-admin.js',
			array(),
			WPFONTS_VERSION,
			true
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selector.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		if ( 'settings' === $tab ) {
			wp_localize_script(
				'wpfonts-admin',
				'wpfontsCatalog',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpfonts_catalog' ),
				)
			);
			self::enqueue_catalog_glyphs();
		}
		if ( 'tools' === $tab ) {
			WPFonts_Packages::enqueue_admin_previews();
		}
	}

	/**
	 * Plugins list: 设置 before Deactivate.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$url = self::page_url();
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( '设置', 'wpfonts' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Replace the core "Settings saved." line on this screen.
	 *
	 * @param string $translated Translated text.
	 * @param string $text       Original text.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public static function saved_label( $translated, $text, $domain ) {
		if ( 'default' !== $domain || 'Settings saved.' !== $text ) {
			return $translated;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
		if ( ! isset( $_GET['page'] ) || 'wpfonts' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return $translated;
		}
		return __( '已保存。', 'wpfonts' );
	}

	/**
	 * Check the key again from the tools screen.
	 *
	 * @return void
	 */
	public static function recheck() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '你没有权限做这件事。', 'wpfonts' ) );
		}
		check_admin_referer( 'wpfonts_recheck' );
		WPFonts_Settings::recheck();
		wp_safe_redirect( self::page_url( array( 'tab' => 'tools' ) ) );
		exit;
	}

	/**
	 * Catalog search or a manual snapshot refresh. Not registered for logged-out users.
	 *
	 * Search reads the local snapshot. Only the refresh flag calls the directory.
	 *
	 * @return void
	 */
	public static function ajax_catalog() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => self::catalog_messages()['fail'],
				),
				403
			);
		}
		check_ajax_referer( 'wpfonts_catalog' );
		$refresh = isset( $_POST['refresh'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['refresh'] ) );
		if ( $refresh ) {
			self::send_refresh_result( WPFonts_Catalog::refresh( true ) );
		}
		$page   = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$result = WPFonts_Catalog::read( $page, $search );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error(
				array(
					'message' => self::catalog_messages()['fail'],
					'fonts'   => array(),
				)
			);
		}
		wp_send_json_success(
			array(
				'fonts'     => self::annotate_catalog_fonts( $result['fonts'], WPFonts_Settings::get() ),
				'page'      => $result['page'],
				'pageTotal' => $result['pageTotal'],
				'total'     => $result['total'],
				'cached'    => true,
			)
		);
	}

	/**
	 * JSON for the settings button. A failed refresh still returns the old rows.
	 *
	 * @param array $result Refresh result.
	 * @return void
	 */
	private static function send_refresh_result( array $result ) {
		$messages = self::catalog_messages();
		$fonts    = ( isset( $result['fonts'] ) && is_array( $result['fonts'] ) ) ? $result['fonts'] : array();
		$fonts    = self::annotate_catalog_fonts( $fonts, WPFonts_Settings::get() );
		$fresh    = ! empty( $result['ok'] ) && empty( $result['stale'] ) && empty( $result['cooling'] );
		if ( $fresh ) {
			wp_send_json_success(
				array(
					'message'    => $messages['updated'],
					'fonts'      => $fonts,
					'updated_at' => isset( $result['updated_at'] ) ? (int) $result['updated_at'] : 0,
				)
			);
		}
		wp_send_json_error(
			array(
				'message' => array() === $fonts ? $messages['fail'] : $messages['stale'],
				'fonts'   => $fonts,
			)
		);
	}

	/**
	 * Render the current screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '你没有权限打开这一页。', 'wpfonts' ) );
		}
		self::boot();
		if ( ! class_exists( 'Wenpai_Admin_Shell', false ) ) {
			wp_die( esc_html__( '这一页暂时打不开。请重新安装插件后再试。', 'wpfonts' ) );
		}

		$tab     = self::query_key( 'tab', 'overview' );
		$section = self::query_key( 'section', '' );
		$mode    = self::query_key( 'mode', 'simple' );
		// Legacy ?tab=packages URLs land on 工具 → 字体包.
		if ( 'packages' === $tab ) {
			$tab     = 'tools';
			$section = 'packages';
		}
		if ( ! in_array( $tab, array( 'overview', 'settings', 'tools', 'help' ), true ) ) {
			$tab = 'overview';
		}
		if ( ! in_array( $section, array( 'packages', 'migration' ), true ) ) {
			$section = '';
		}
		if ( 'advanced' !== $mode ) {
			$mode = 'simple';
		}

		$settings = WPFonts_Settings::get();
		$has_key  = WPFonts_Settings::has_key();
		$state    = WPFonts_Settings::view_state( $settings, $has_key );
		$args     = self::shell_args( $tab );

		Wenpai_Admin_Shell::open( $args );
		if ( 'settings' === $tab ) {
			self::render_settings( $settings, $state, $has_key, $mode );
		} elseif ( 'tools' === $tab ) {
			self::render_tools( $settings, $state, $has_key, $section );
		} elseif ( 'help' === $tab ) {
			self::render_help();
		} else {
			self::render_overview( $settings, $state );
		}
		Wenpai_Admin_Shell::close( $args );
	}

	/**
	 * Boot the kit if the main file registered it.
	 *
	 * @return void
	 */
	private static function boot() {
		if ( function_exists( 'wenpai_admin_ui_boot' ) ) {
			wenpai_admin_ui_boot();
		}
	}

	/**
	 * Read a view query arg.
	 *
	 * @param string $key      Query key.
	 * @param string $fallback Default value.
	 * @return string
	 */
	private static function query_key( $key, $fallback ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selector, not a form action.
		if ( ! isset( $_GET[ $key ] ) ) {
			return $fallback;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only selector.
		return sanitize_key( wp_unslash( $_GET[ $key ] ) );
	}

	/**
	 * Admin URL for this plugin.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	private static function page_url( $args = array() ) {
		if ( class_exists( 'Wenpai_Admin_Menu', false ) ) {
			return Wenpai_Admin_Menu::url( 'wpfonts', $args );
		}
		$args = array_merge( array( 'page' => 'wpfonts' ), $args );
		return add_query_arg( $args, admin_url( 'options-general.php' ) );
	}

	/**
	 * Shell arguments shared by open and close.
	 *
	 * @param string $tab Current tab id.
	 * @return array
	 */
	private static function shell_args( $tab ) {
		$icon = static function ( $name ) {
			return class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( $name ) : '';
		};
		return array(
			'slug'               => 'wpfonts',
			'name'               => __( '文派字库', 'wpfonts' ),
			'mark_html'          => self::mark_html(),
			'current'            => $tab,
			'tabs'               => array(
				array(
					'id'        => 'overview',
					'label'     => __( '概览', 'wpfonts' ),
					'url'       => self::page_url(),
					'icon_html' => $icon( 'overview' ),
				),
				array(
					'id'        => 'settings',
					'label'     => __( '设置', 'wpfonts' ),
					'url'       => self::page_url( array( 'tab' => 'settings' ) ),
					'icon_html' => $icon( 'settings' ),
				),
				array(
					'id'        => 'tools',
					'label'     => __( '工具', 'wpfonts' ),
					'url'       => self::page_url( array( 'tab' => 'tools' ) ),
					'icon_html' => $icon( 'tools' ),
				),
			),
			'help_url'           => self::page_url( array( 'tab' => 'help' ) ),
			'help_label'         => __( '帮助', 'wpfonts' ),
			'help_icon_html'     => $icon( 'help' ),
			'feedback_url'       => 'https://wpcy.com/support',
			'feedback_label'     => __( '反馈', 'wpfonts' ),
			'feedback_icon_html' => $icon( 'feedback' ),
			'version'            => __( '文派字库（WPFonts）', 'wpfonts' ) . ' · ' . WPFONTS_VERSION,
			'new_tab_label'      => __( '（在新标签页打开）', 'wpfonts' ),
			'doc_url'            => 'https://wpcy.com/fonts',
			'doc_label'          => __( '文档', 'wpfonts' ),
		);
	}

	/**
	 * Blue mark with a T, matching the prototype.
	 *
	 * @return string
	 */
	private static function mark_html() {
		return '<svg viewBox="0 0 16 16" aria-hidden="true"><text x="8" y="12" text-anchor="middle" font-size="11" font-family="sans-serif" font-weight="600" fill="currentColor">T</text></svg>';
	}

	/**
	 * Echo a kit icon.
	 *
	 * @param string $name Icon name.
	 * @return void
	 */
	private static function icon( $name ) {
		if ( ! class_exists( 'Wenpai_Admin_Icons', false ) ) {
			return;
		}
		echo Wenpai_Admin_Icons::svg( $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by Wenpai_Admin_Icons::svg().
	}

	/**
	 * Hide the following block while CDN is off. Kit JS applies it.
	 *
	 * @return void
	 */
	private static function cdn_depends() {
		echo ' data-wenpai-depends-on="' . esc_attr( 'wpfonts_settings[cdn_enabled]' ) . '" data-wenpai-depends-value="1"';
	}

	/**
	 * field_html() for a schema-declared field. Empty string means the kit
	 * is not booted; callers keep their legacy markup as the fallback.
	 *
	 * @param string $id Schema field id.
	 * @return string
	 */
	private static function schema_field_html( $id ) {
		$fw = WPFonts_Schema::framework();
		if ( null === $fw ) {
			return '';
		}
		return (string) $fw->field_html( $id );
	}

	/**
	 * Overview.
	 *
	 * @param array  $settings Public settings.
	 * @param string $state    View state.
	 * @return void
	 */
	private static function render_overview( array $settings, $state ) {
		Wenpai_Admin_Shell::page_head(
			__( '概览', 'wpfonts' ),
			__( '系统字体开箱即用；需要文风字体时，开启文风云或安装字体包。', 'wpfonts' )
		);
		echo '<div class="wenpai-stack">';
		self::render_state_banner( $state );
		echo '<section class="card">';
		echo '<h2 class="card-title">' . esc_html__( '运行状态', 'wpfonts' ) . '</h2>';
		self::status_row( 'ok', 'info', __( '系统字体', 'wpfonts' ), __( '使用设备自带字体，前台不加载远程字体。', 'wpfonts' ), 'ok', __( '可用', 'wpfonts' ) );
		self::status_row(
			self::cdn_tile( $state ),
			'cloud',
			__( '文风云® CDN', 'wpfonts' ),
			self::cdn_desc( $state ),
			self::cdn_pill( $state ),
			self::cdn_pill_label( $state )
		);
		$embed_ok = ( 'ok' === $state );
		self::status_row(
			$embed_ok ? 'ok' : '',
			'external',
			__( '样式链接', 'wpfonts' ),
			$embed_ok ? __( '前台会加载文风服务器上的样式链接。', 'wpfonts' ) : __( '前台不加载远程样式。', 'wpfonts' ),
			$embed_ok ? 'ok' : 'mute',
			$embed_ok ? __( '会加载', 'wpfonts' ) : __( '未加载', 'wpfonts' )
		);
		self::render_local_row();
		self::render_cjk_row();
		echo '</section>';
		echo '</div>';
	}

	/**
	 * Settings, simple or advanced.
	 *
	 * @param array  $settings Public settings.
	 * @param string $state    View state.
	 * @param bool   $has_key  Whether a key is stored.
	 * @param string $mode     simple|advanced.
	 * @return void
	 */
	private static function render_settings( array $settings, $state, $has_key, $mode ) {
		$simple   = self::page_url(
			array(
				'tab'  => 'settings',
				'mode' => 'simple',
			)
		);
		$advanced = self::page_url(
			array(
				'tab'  => 'settings',
				'mode' => 'advanced',
			)
		);
		$aside    = '<nav class="seg wenpai-subnav" aria-label="' . esc_attr__( '简单或高级', 'wpfonts' ) . '">';
		$aside   .= '<a class="' . esc_attr( 'simple' === $mode ? 'on' : '' ) . '" href="' . esc_url( $simple ) . '">' . esc_html__( '简单', 'wpfonts' ) . '</a>';
		$aside   .= '<a class="' . esc_attr( 'advanced' === $mode ? 'on' : '' ) . '" href="' . esc_url( $advanced ) . '">' . esc_html__( '高级', 'wpfonts' ) . '</a>';
		$aside   .= '</nav>';

		Wenpai_Admin_Shell::page_head(
			__( '设置', 'wpfonts' ),
			__( '系统字体无需配置。文风云默认关闭，开启并保存后生效。', 'wpfonts' ),
			$aside
		);

		echo '<div class="wenpai-stack">';
		$note = WPFonts_Settings::pull_note();
		if ( 'rejected' === $note ) {
			self::notice( 'bad', __( '刚才填写的 Key 没有保存。请到文风重新复制 Key。', 'wpfonts' ) );
		}
		self::render_state_banner( $state, 'settings' );

		echo '<form method="post" action="options.php">';
		settings_fields( 'wpfonts' );
		echo '<input type="hidden" name="wpfonts_settings[surface]" value="' . esc_attr( $mode ) . '">';
		echo '<section class="card">';

		self::render_system_fonts( $settings );
		self::render_cjk_fields( $settings );

		ob_start();
		self::cdn_switch( ! empty( $settings['cdn_enabled'] ) );
		$wpfonts_cdn_control = (string) ob_get_clean();
		echo Wenpai_Admin_UI::row_html(
			array(
				'icon'         => 'cloud',
				'state'        => 'accent',
				'title'        => __( '开启文风云', 'wpfonts' ),
				'title_id'     => 'wpfonts-cdn-t',
				'desc'         => __( '默认关闭。开启并保存后，前台加载文风样式；关闭时只使用系统字体。', 'wpfonts' ),
				'desc_id'      => 'wpfonts-cdn-d',
				'control_html' => $wpfonts_cdn_control,
			)
		);

		self::render_key_field( $has_key, $state );

		if ( 'advanced' === $mode ) {
			self::render_region_field( $settings );
			echo '<div id="wpfonts-font-pick">';
			self::render_catalog_field( $settings );
			self::render_family_field( $settings );
			echo '</div>';
			self::render_subset_field( $settings );
			self::render_project_field( $settings );
		} else {
			echo '<div id="wpfonts-font-pick">';
			self::render_family_field( $settings );
			echo '</div>';
		}
		self::render_link_field( $settings, $state, $has_key );

		echo Wenpai_Admin_UI::card_foot_html(
			array(
				'actions' => array(
					array(
						'label' => __( '保存设置', 'wpfonts' ),
						'variant' => 'primary',
						'type' => 'submit',
					),
				),
			)
		);
		echo '</section>';
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Tools: health check and a reminder that this plugin does not upload.
	 *
	 * @param array  $settings Public settings.
	 * @param string $state    View state.
	 * @param bool   $has_key  Whether a key is stored.
	 * @return void
	 */
	private static function render_tools( array $settings, $state, $has_key, $section = '' ) {
		Wenpai_Admin_Shell::page_head(
			__( '工具', 'wpfonts' ),
			__( '安装字体包、检查文风 API Key、预览样式链接，并导出或导入字体记录（不含密钥）。', 'wpfonts' )
		);
		echo '<div class="wenpai-stack">';
		echo Wenpai_Admin_UI::subnav_html(
			array(
				'label'   => __( '工具分区', 'wpfonts' ),
				'current' => '' !== $section ? $section : 'tools',
				'slug'    => 'wpfonts',
				'query'   => array( 'tab' => 'tools' ),
				'items'   => array(
					array(
						'id'    => 'tools',
						'label' => __( '日常', 'wpfonts' ),
					),
					array(
						'id'    => 'packages',
						'label' => __( '字体包', 'wpfonts' ),
					),
					array(
						'id'    => 'migration',
						'label' => __( '迁移', 'wpfonts' ),
					),
				),
			)
		);
		if ( 'packages' === $section ) {
			self::render_packages_body();
			echo '</div>';
			return;
		}
		if ( 'migration' === $section ) {
			self::render_migration( $settings );
			echo '</div>';
			return;
		}
		self::render_state_banner( $state );
		self::render_selection_summary( $settings );

		echo Wenpai_Admin_UI::card_open_html( array( 'title' => __( '健康检查', 'wpfonts' ), 'sub' => __( '在服务器上向文风验证。页面上不会显示 Key。', 'wpfonts' ), 'icon' => 'info', 'state' => 'warn' ) );
		echo '<div class="field"><div class="field-label">' . esc_html__( '请求', 'wpfonts' ) . '</div><div class="field-ctl">';
		echo Wenpai_Admin_UI::code_inline_html( array( 'code' => 'GET /api/key/status', 'copy' => false ) );
		echo '<p class="hint">' . esc_html__( '验证请求发往 app.windfonts.com，不走字体 CDN。', 'wpfonts' ) . '</p>';
		echo '</div></div>';
		echo '<div class="field"><div class="field-label">' . esc_html__( '结果', 'wpfonts' ) . '</div><div class="field-ctl">';
		self::render_health_result( $settings['key_status'], $has_key );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wpfonts_recheck' );
		echo '<input type="hidden" name="action" value="wpfonts_recheck">';
		echo '<p><button type="submit" class="btn btn-secondary">' . esc_html__( '再检查一次', 'wpfonts' ) . '</button></p>';
		echo '</form>';
		echo '</div></div>';
		echo '</section>';

		echo Wenpai_Admin_UI::card_open_html( array( 'title' => __( '样式链接', 'wpfonts' ), 'sub' => __( '这里显示前台将加载的样式地址。', 'wpfonts' ), 'icon' => 'external' ) );
		self::render_link_preview( $settings, $state, $has_key, false );
		echo '</section>';

		echo '</div>';
	}

	/**
	 * Help. Not a fourth tab.
	 *
	 * @return void
	 */
	private static function render_help() {
		Wenpai_Admin_Shell::page_head(
			__( '帮助', 'wpfonts' ),
			__( '简要说明。', 'wpfonts' )
		);
		echo '<div class="wenpai-stack">';
		self::help_card(
			__( '编辑器与排版', 'wpfonts' ),
			__( '侧栏里的「文派字库」作用于当前区块。已启用的文风字体包和已确认的文风云会进入全站样式，可在外观 → 样式 → 排版里选用。', 'wpfonts' )
		);
		self::help_card(
			__( '中文', 'wpfonts' ),
			__( '未指定中文回退时，站点使用系统中文。安装文风字体包或开启文风云后，可在设置里指定正文、标题和中文回退。', 'wpfonts' )
		);
		self::help_card(
			__( '系统字体', 'wpfonts' ),
			__( '打开即用。字体库里的「系统 · 中文」「系统 · 英文」「系统 · 日文」「系统 · 韩文」默认可用，前台直接使用设备字体。', 'wpfonts' )
		);
		self::help_card(
			__( '文风云', 'wpfonts' ),
			__( '默认关闭。开启并保存后，每条已确认的文风云字体各加一条样式链接。服务器只有 cn.windfonts.com、hk.windfonts.com 和 en.windfonts.com。网页中不会出现 API Key。', 'wpfonts' )
		);
		self::help_card(
			__( '文风字体包', 'wpfonts' ),
			__( '安装文风已切好的字体包，文件保存在本站，由本站直接加载。', 'wpfonts' )
		);
		self::help_card(
			__( '字体目录', 'wpfonts' ),
			__( '目录保存在本站，每月自动更新一次；在设置里也可立即刷新。选中的字体会写入字体名。', 'wpfonts' )
		);
		echo '<section class="card">';
		echo '<h2 class="card-title">' . esc_html__( '使用范围', 'wpfonts' ) . '</h2>';
		echo '<p>' . esc_html__( '字体切分在文风侧完成，主题文件无需改动。已启用的文风字体包和确认能打开的文风云会出现在全站样式里；卸载插件时不删除全站样式。', 'wpfonts' ) . '</p>';
		echo '</section>';
		echo '</div>';
	}

	/**
	 * One short help card.
	 *
	 * @param string $title Title.
	 * @param string $body  Body.
	 * @return void
	 */
	private static function help_card( $title, $body ) {
		echo '<section class="card">';
		echo '<h2 class="card-title">' . esc_html( $title ) . '</h2>';
		echo '<p>' . esc_html( $body ) . '</p>';
		echo '</section>';
	}

	/**
	 * Top banner for the current state.
	 *
	 * @param string $state View state.
	 * @param string $tab   Current tab. The settings screen answers the empty
	 *                      state with its own switch, not a link to itself.
	 * @return void
	 */
	private static function render_state_banner( $state, $tab = 'overview' ) {
		$local = ( 'active' === WPFonts_Packages::presence() );
		if ( 'empty' === $state && $local ) {
			self::notice( 'ok', __( '文风字体包使用中。前台会加载本站这份字体样式。文风云仍可按需开启。', 'wpfonts' ) );
			return;
		}
		if ( 'empty' === $state && 'settings' === $tab ) {
			self::notice( 'info', __( '当前使用系统字体。要开启文风云，打开下方开关并保存；也可以先到字体包安装文风已切好的包。', 'wpfonts' ) );
			return;
		}
		if ( 'empty' === $state ) {
			Wenpai_Admin_UI::next(
				array(
					'icon'    => 'info',
					'title'   => __( '网页字体尚未开启', 'wpfonts' ),
					'desc'    => __( '系统字体开箱即用。需要文风字体时，可开启文风云，或到字体包安装。', 'wpfonts' ),
					'actions' => array(
						array(
							'label'   => __( '去设置', 'wpfonts' ),
							'variant' => 'primary',
							'href'    => self::page_url( array( 'tab' => 'settings' ) ),
						),
						array(
							'label'   => __( '字体包', 'wpfonts' ),
							'variant' => 'secondary',
							'href'    => self::page_url( array( 'tab' => 'tools', 'section' => 'packages' ) ),
						),
					),
				)
			);
			return;
		}
		$level = 'warn';
		$text  = __( '文风 API Key 尚未验证。请到工具中再检查一次。验证通过前，前台不加载样式。', 'wpfonts' );
		if ( 'nokey' === $state ) {
			$text = __( '文风云已开启，但尚未填写文风 API Key。请到设置页填写。前台暂不加载远程样式。', 'wpfonts' );
		} elseif ( 'invalid' === $state ) {
			$level = 'bad';
			$text  = __( '文风 API Key 无效，前台使用系统字体。', 'wpfonts' );
		} elseif ( 'unreachable' === $state ) {
			$text = __( '这次未能连接，暂不判定 Key 状态。前台使用系统字体，请稍后再检查。', 'wpfonts' );
		} elseif ( 'incomplete' === $state ) {
			$text = __( '文风 API Key 可用。还需要字体名或项目名，前台暂不加载样式。', 'wpfonts' );
		} elseif ( 'css_missing' === $state ) {
			$level = 'bad';
			$text  = __( '这条地址打不开，前台不会加载。换个字体，或改字符范围后再保存。', 'wpfonts' );
		} elseif ( 'ok' === $state ) {
			$level = 'ok';
			$text  = __( '文风云已开启，文风 API Key 可用。前台会加载文风服务器上的样式，网页中不会出现 Key。', 'wpfonts' );
			if ( $local ) {
				$text .= __( '文风字体包也在使用。', 'wpfonts' );
			}
		}
		self::notice( $level, $text );
	}

	/**
	 * A kit notice line.
	 *
	 * @param string $level ok|warn|bad|info.
	 * @param string $text  Already translated text.
	 * @return void
	 */
	private static function notice( $level, $text ) {
		echo Wenpai_Admin_UI::notice_html(
			array(
				'level' => $level,
				'text'  => $text,
				'wrap'  => false,
			)
		);
	}

	/**
	 * Site-wide notice when the enabled Windfonts stylesheet cannot be used.
	 *
	 * @return void
	 */
	public static function cdn_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = WPFonts_Settings::get();
		if ( empty( $settings['cdn_enabled'] ) || 'valid' !== (string) ( $settings['key_status'] ?? '' ) ) {
			return;
		}
		if ( ! WPFonts_Link::has_target( $settings ) ) {
			return;
		}
		$css_status = isset( $settings['css_status'] ) ? (string) $settings['css_status'] : '';
		if ( 'missing' === $css_status ) {
			$message = __( '文风样式表返回 404，前台不会加载远程字体。', 'wpfonts' );
			$kind    = 'notice-error';
		} elseif ( 'unchecked' === $css_status ) {
			$message = __( '文风样式表暂时无法验证，前台字体可能回退到系统字体。', 'wpfonts' );
			$kind    = 'notice-warning';
		} else {
			return;
		}
		printf(
			'<div class="notice %s"><p>%s <a href="%s">%s</a></p></div>',
			esc_attr( $kind ),
			esc_html( $message ),
			esc_url( self::page_url( array( 'tab' => 'tools' ) ) ),
			esc_html__( '打开工具页再检查一次', 'wpfonts' )
		);
	}

	/**
	 * One status row.
	 *
	 * @param string $tile  Tile modifier.
	 * @param string $icon  Icon name.
	 * @param string $title Title.
	 * @param string $desc  Description.
	 * @param string $pill  Pill modifier.
	 * @param string $label Pill label.
	 * @return void
	 */
	private static function status_row( $tile, $icon, $title, $desc, $pill, $label ) {
		echo Wenpai_Admin_UI::row_html(
			array(
				'icon'  => $icon,
				'state' => $tile,
				'title' => $title,
				'desc'  => $desc,
				'pill'  => array(
					'text'  => $label,
					'state' => $pill,
				),
			)
		);
	}

	/**
	 * CDN switch. Hidden 0 keeps a save while the box is off.
	 *
	 * @param bool $on Whether CDN is enabled.
	 * @return void
	 */
	/**
	 * System collection toggles. Default on. Unchecked languages are not registered.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	private static function render_system_fonts( array $settings ) {
		$choices = array(
			'cn' => __( '系统 · 中文', 'wpfonts' ),
			'en' => __( '系统 · 英文', 'wpfonts' ),
			'jp' => __( '系统 · 日文', 'wpfonts' ),
			'kr' => __( '系统 · 韩文', 'wpfonts' ),
		);
		echo '<div class="field">';
		echo '<div class="field-label">' . esc_html__( '系统字体', 'wpfonts' ) . '</div>';
		echo '<div class="field-ctl">';
		echo '<p class="field-help">' . esc_html__( '默认都开启。取消勾选后，这一组不再出现在字体库。', 'wpfonts' ) . '</p>';
		echo '<input type="hidden" name="wpfonts_settings[system_form]" value="1">';
		$on_count = 0;
		foreach ( $choices as $code => $label ) {
			$key = 'system_' . $code;
			if ( ! isset( $settings[ $key ] ) || ! empty( $settings[ $key ] ) ) {
				++$on_count;
			}
		}
		/* translators: 1: number of system font families enabled, 2: total number of choices. */
		$count_tpl = __( '已启用 %1$d / %2$d', 'wpfonts' );
		echo '<div class="wenpai-checkgroup" data-wenpai-checkgroup data-wenpai-count-total="' . esc_attr( (string) count( $choices ) ) . '">'
			. '<div class="wenpai-checkgroup__head">'
			. '<span class="wenpai-checkgroup__count" data-wenpai-count-template="' . esc_attr( $count_tpl ) . '">'
			. esc_html( sprintf( $count_tpl, $on_count, count( $choices ) ) )
			. '</span>'
			. '<span class="wenpai-checkgroup__acts">'
			. '<button type="button" data-wenpai-cg-all>' . esc_html__( '全选', 'wpfonts' ) . '</button>'
			. '<button type="button" data-wenpai-cg-none>' . esc_html__( '清空', 'wpfonts' ) . '</button>'
			. '</span></div>'
			. '<ul class="wenpai-checkgroup__list">';
		foreach ( $choices as $code => $label ) {
			$key = 'system_' . $code;
			$on  = ! isset( $settings[ $key ] ) || ! empty( $settings[ $key ] );
			echo '<li><label class="chk"><input type="checkbox" name="wpfonts_settings[' . esc_attr( $key ) . ']" value="1"' . checked( $on, true, false ) . '> '
				. '<span class="wenpai-checkgroup__text">' . esc_html( $label ) . '</span></label></li>';
		}
		echo '</ul></div></div></div>';
	}

	/**
	 * Overview line for the Chinese assignment.
	 *
	 * @return void
	 */
	private static function render_cjk_row() {
		$state = array(
			'on'   => false,
			'text' => __( '未指定，前台使用系统中文。安装字体包或开启文风云后，可在设置里选择。', 'wpfonts' ),
		);
		if ( class_exists( 'WPFonts_CJK', false ) ) {
			$state = WPFonts_CJK::overview();
		}
		self::status_row(
			! empty( $state['on'] ) ? 'ok' : '',
			'info',
			__( '中文回退', 'wpfonts' ),
			isset( $state['text'] ) ? (string) $state['text'] : '',
			! empty( $state['on'] ) ? 'ok' : 'mute',
			! empty( $state['on'] ) ? __( '使用中', 'wpfonts' ) : __( '未指定', 'wpfonts' )
		);
	}

	/**
	 * Body, heading, and site fallback. Choices are packages and 文风云.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	private static function render_cjk_fields( array $settings ) {
		$choices = class_exists( 'WPFonts_CJK', false ) ? WPFonts_CJK::choices() : array();
		echo '<div class="field wpfonts-cjk">';
		echo '<div class="field-label">' . esc_html__( '中文', 'wpfonts' ) . '</div>';
		echo '<div class="field-ctl">';
		echo '<input type="hidden" name="wpfonts_settings[cjk_form]" value="1">';
		echo '<p class="field-help">' . esc_html__( '从已启用的文风字体包，或已确认的文风云里选。留空则这一项不单独指定。', 'wpfonts' ) . '</p>';
		self::render_cjk_select(
			'cjk_body',
			__( '正文', 'wpfonts' ),
			__( '与中文回退相同', 'wpfonts' ),
			$settings,
			$choices
		);
		self::render_cjk_select(
			'cjk_heading',
			__( '标题', 'wpfonts' ),
			__( '与中文回退相同', 'wpfonts' ),
			$settings,
			$choices
		);
		self::render_cjk_select(
			'cjk_fallback',
			__( '中文回退', 'wpfonts' ),
			__( '不使用', 'wpfonts' ),
			$settings,
			$choices
		);
		echo '<p class="hint">' . esc_html__( '中文回退会接在现有西文字体后面，主题里的字体类也会自动带上。', 'wpfonts' ) . '</p>';
		if ( array() === $choices ) {
			echo '<p class="hint">' . esc_html__( '还没有可以选的中文。安装文风字体包或开启文风云并确认后，就能在这里指定中文回退。', 'wpfonts' ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * One Chinese assignment select.
	 *
	 * @param string $key      Settings key.
	 * @param string $label    Visible label.
	 * @param string $blank    Label for the blank option.
	 * @param array  $settings Public settings.
	 * @param array  $choices  Choice rows.
	 * @return void
	 */
	private static function render_cjk_select( $key, $label, $blank, array $settings, array $choices ) {
		$current = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
		$id      = 'wpfonts-' . $key;
		echo '<label class="field-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<select id="' . esc_attr( $id ) . '" name="wpfonts_settings[' . esc_attr( $key ) . ']">';
		echo '<option value=""' . selected( $current, '', false ) . '>' . esc_html( $blank ) . '</option>';
		$groups = array(
			'package' => __( '文风字体包', 'wpfonts' ),
			'cloud'   => __( '文风云', 'wpfonts' ),
		);
		$known  = array();
		foreach ( $groups as $group => $group_label ) {
			$rows = array();
			foreach ( $choices as $choice ) {
				if ( ! is_array( $choice ) || ( isset( $choice['group'] ) ? (string) $choice['group'] : '' ) !== $group ) {
					continue;
				}
				$rows[] = $choice;
				if ( isset( $choice['value'] ) ) {
					$known[ (string) $choice['value'] ] = true;
				}
			}
			if ( array() === $rows ) {
				continue;
			}
			echo '<optgroup label="' . esc_attr( $group_label ) . '">';
			foreach ( $rows as $choice ) {
				$value = isset( $choice['value'] ) ? (string) $choice['value'] : '';
				$text  = isset( $choice['label'] ) ? (string) $choice['label'] : $value;
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</optgroup>';
		}
		if ( '' !== $current && ! isset( $known[ $current ] ) ) {
			echo '<option value="' . esc_attr( $current ) . '" selected>' . esc_html__( '已保存，当前不可用', 'wpfonts' ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Tools export and import. Secrets stay out of the file.
	 *
	 * The cards follow the WPSSS tools export: admin-post, a JSON download,
	 * and a line that says the key is not in the file.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	private static function render_migration( array $settings ) {
		unset( $settings );
		$note   = self::query_key( 'wpfonts_xfer', '' );
		$detail = '';
		if ( function_exists( 'get_current_user_id' ) && function_exists( 'get_transient' ) ) {
			$user = (int) get_current_user_id();
			if ( $user > 0 ) {
				$stored = get_transient( 'wpfonts_xfer_' . $user );
				if ( is_string( $stored ) ) {
					$detail = $stored;
					delete_transient( 'wpfonts_xfer_' . $user );
				}
			}
		}
		if ( 'imported' === $note ) {
			self::notice( 'ok', '' !== $detail ? $detail : __( '已导入。密钥没有写入本站。', 'wpfonts' ) );
		} elseif ( 'refused' === $note ) {
			self::notice( 'bad', __( '文件里有密钥，已拒绝导入。密钥不会写入本站。', 'wpfonts' ) );
		} elseif ( 'invalid' === $note ) {
			self::notice( 'bad', __( '这份文件不能导入。请使用本页导出的 JSON。', 'wpfonts' ) );
		} elseif ( 'empty' === $note ) {
			self::notice( 'warn', __( '没有读到文件。', 'wpfonts' ) );
		}

		echo Wenpai_Admin_UI::card_open_html( array( 'title' => __( '导出', 'wpfonts' ), 'sub' => __( '下载不含 API Key 的 JSON，便于在另一站点恢复已启用的文风字体包和已确认的文风云。', 'wpfonts' ), 'icon' => 'download' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="wpfonts_export">';
		wp_nonce_field( 'wpfonts_export' );
		echo '<div class="card-foot start">';
		echo '<span class="meta">' . esc_html__( 'API Key 不会出现在文件中。', 'wpfonts' ) . '</span>';
		echo '<button class="btn btn-secondary" type="submit">' . esc_html__( '导出 JSON', 'wpfonts' ) . '</button>';
		echo '</div></form></section>';

		echo Wenpai_Admin_UI::card_open_html( array( 'title' => __( '导入', 'wpfonts' ), 'sub' => __( '写入已启用的字体包记录和已确认的文风云。本站没有对应文件的字体包会跳过。带密钥的文件会被拒绝。', 'wpfonts' ), 'icon' => 'info', 'state' => 'warn' ) );
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="wpfonts_import">';
		wp_nonce_field( 'wpfonts_import' );
		echo '<div class="wenpai-field"><label class="wenpai-field__label" for="wpfonts-import-file">' . esc_html__( 'JSON 文件', 'wpfonts' ) . '</label>';
		echo '<span class="wenpai-file" data-wenpai-file data-wenpai-file-local data-multiple="0" data-name="wpfonts_import" data-remove="' . esc_attr__( '移除', 'wpfonts' ) . '">';
		echo '<span class="wenpai-file__drop" role="button" tabindex="0" aria-label="' . esc_attr__( '拖入 JSON 文件，或点击选择', 'wpfonts' ) . '">';
		self::icon( 'upload' );
		echo '<span>' . esc_html__( '拖入 JSON 文件，或点击选择', 'wpfonts' ) . '</span>';
		echo '<input type="file" class="wenpai-file__input" id="wpfonts-import-file" name="wpfonts_import" accept="application/json,.json" hidden>';
		echo '</span>';
		echo '<span class="wenpai-file__list"></span>';
		echo '</span>';
		echo '<p class="wenpai-field__desc">' . esc_html__( '选择 .json 文件后随表单一起提交。', 'wpfonts' ) . '</p>';
		echo '</div>';
		echo Wenpai_Admin_UI::gate_html(
			array(
				'name'  => 'wpfonts_import_ack',
				'label' => __( '我确认用这份文件更新已启用的字体包，并覆盖已确认的文风云列表。', 'wpfonts' ),
			)
		);
		echo '<div class="card-foot start">';
		echo '<span class="meta">' . esc_html__( '未勾选不能点。密钥不会写入。', 'wpfonts' ) . '</span>';
		echo '<button type="submit" class="btn btn-secondary" data-wenpai-need-apply disabled>' . esc_html__( '导入 JSON', 'wpfonts' ) . '</button>';
		echo '</div></form></section>';
	}

	/**
	 * CDN switch. Hidden 0 keeps a save while the box is off.
	 *
	 * @param bool $on Whether CDN is enabled.
	 * @return void
	 */
	private static function cdn_switch( $on ) {
		if ( class_exists( 'Wenpai_Admin_Settings', false ) ) {
			$html = Wenpai_Admin_Settings::switch_html(
				array(
					'id'          => 'wpfonts-cdn',
					'name'        => 'wpfonts_settings[cdn_enabled]',
					'checked'     => (bool) $on,
					'on'          => __( '已开启', 'wpfonts' ),
					'off'         => __( '未开启', 'wpfonts' ),
					'labelledby'  => 'wpfonts-cdn-t',
					'describedby' => 'wpfonts-cdn-d',
				)
			);
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by Wenpai_Admin_Settings::switch_html().
			return;
		}
		echo '<input type="hidden" name="wpfonts_settings[cdn_enabled]" value="0">';
		echo '<input type="checkbox" id="wpfonts-cdn" name="wpfonts_settings[cdn_enabled]" value="1"' . checked( (bool) $on, true, false ) . '>';
	}

	/**
	 * Region cards.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	private static function render_region_field( array $settings ) {
		$html = self::schema_field_html( 'region' );
		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit field_html() escapes.
			return;
		}
		$regions = array(
			'cn' => array( __( '中国大陆', 'wpfonts' ), 'cn.windfonts.com' ),
			'hk' => array( __( '中国香港', 'wpfonts' ), 'hk.windfonts.com' ),
			'en' => array( __( '国际', 'wpfonts' ), 'en.windfonts.com' ),
		);
		echo '<div class="field"';
		self::cdn_depends();
		echo '>';
		echo '<div class="field-label">' . esc_html__( '区域', 'wpfonts' ) . '</div>';
		echo '<div class="field-ctl">';
		echo '<p class="field-help">' . esc_html__( '选择文风云字体的 CDN 加速服务器。可选中国大陆、中国香港、国际三区域，香港节点兼顾国内外访问。', 'wpfonts' ) . '</p>';
		echo '<div class="choice-cards">';
		foreach ( $regions as $id => $label ) {
			$on = $settings['region'] === $id;
			echo '<label class="choice' . ( $on ? ' is-on' : '' ) . '">';
			echo '<input type="radio" name="wpfonts_settings[region]" value="' . esc_attr( $id ) . '"' . checked( $on, true, false ) . '>';
			echo '<span><span class="t">' . esc_html( $label[0] ) . '</span><span class="d">' . esc_html( $label[1] ) . '</span></span>';
			echo '</label>';
		}
		echo '</div></div></div>';
	}

	/**
	 * Default subset.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	private static function render_subset_field( array $settings ) {
		$html = self::schema_field_html( 'subset' );
		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit field_html() escapes.
			return;
		}
		echo '<div class="field" id="wpfonts-subset-field"';
		self::cdn_depends();
		echo '>';
		echo '<div class="field-label" id="wpfonts-subset-label">' . esc_html__( '字符范围', 'wpfonts' ) . '</div>';
		echo '<div class="field-ctl">';
		echo '<div class="choice-cards wpfonts-subset-list" role="radiogroup" aria-labelledby="wpfonts-subset-label">';
		foreach ( self::subset_choices() as $id => $choice ) {
			$on = $settings['subset'] === $id;
			echo '<label class="choice' . ( $on ? ' is-on' : '' ) . '">';
			echo '<input type="radio" name="wpfonts_settings[subset]" value="' . esc_attr( $id ) . '"' . checked( $on, true, false ) . '>';
			echo '<span><span class="t">' . esc_html( $choice['label'] ) . '</span><span class="d">' . esc_html( $choice['hint'] ) . '</span></span>';
			echo '</label>';
		}
		echo '</div>';
		echo '<p class="hint">' . esc_html__( '字符范围决定远程字体包的大小，范围越小包越小。', 'wpfonts' ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * Four subset labels. The strings are the product copy for this screen.
	 *
	 * @return array<string, array{label:string, hint:string}>
	 */
	private static function subset_choices() {
		return array(
			'en'        => array(
				'label' => __( '西文', 'wpfonts' ),
				'hint'  => __( '体积最小，主要覆盖拉丁字符。', 'wpfonts' ),
			),
			'zh-common' => array(
				'label' => __( '常用中文（推荐）', 'wpfonts' ),
				'hint'  => __( '日常中文站点够用，体积适中。', 'wpfonts' ),
			),
			'zh'        => array(
				'label' => __( '较全中文', 'wpfonts' ),
				'hint'  => __( '生僻字更多，体积明显更大。', 'wpfonts' ),
			),
			'full'      => array(
				'label' => __( '全量', 'wpfonts' ),
				'hint'  => __( '最大包，仅在确实需要时使用。', 'wpfonts' ),
			),
		);
	}

	/**
	 * Catalog status lines. The new strings are the Foundry copy for this screen.
	 *
	 * @return array{loading:string,fail:string,empty:string,loaded:string,updating:string,updated:string,stale:string,note:string,refresh:string,missing:string}
	 */
	private static function catalog_messages() {
		return array(
			'loading'  => __( '正在加载字体目录…', 'wpfonts' ),
			'fail'     => __( '暂时读不到字体目录。你可以手动填写字体名，稍后再试。', 'wpfonts' ),
			'empty'    => __( '没有匹配的字体。改个词，或手动填写。', 'wpfonts' ),
			'loaded'   => __( '结果来自已加载页', 'wpfonts' ),
			'updating' => __( '正在更新字体目录…', 'wpfonts' ),
			'updated'  => __( '字体目录已更新。', 'wpfonts' ),
			'stale'    => __( '暂时更新不了，仍显示上次保存的目录。稍后再试。', 'wpfonts' ),
			'note'     => __( '目录保存在本站，每月自动更新一次；点「更新字体目录」可立即刷新。', 'wpfonts' ),
			'refresh'  => __( '更新字体目录', 'wpfonts' ),
			'missing'  => __( '还没有字体目录。可点「更新字体目录」再试，或先手填字体名。', 'wpfonts' ),
		);
	}

	/**
	 * Masked API key. Empty submit keeps the saved value.
	 *
	 * @param bool   $has_key Whether a key is stored.
	 * @param string $state   View state.
	 * @return void
	 */
	private static function render_key_field( $has_key, $state ) {
		echo '<div class="field">';
		echo '<label class="field-label" for="wpfonts-api-key">' . esc_html__( '文风 API Key', 'wpfonts' ) . '</label>';
		echo '<div class="field-ctl">';
		$placeholder = $has_key ? __( '留空表示不修改', 'wpfonts' ) : __( '粘贴文风 API Key', 'wpfonts' );
		echo '<input type="password" id="wpfonts-api-key" name="wpfonts_settings[api_key]" value="" autocomplete="new-password" spellcheck="false" placeholder="' . esc_attr( $placeholder ) . '">';
		if ( $has_key ) {
			$mask = WPFonts_Settings::mask( WPFonts_Settings::api_key() );
			if ( 'invalid' === $state ) {
				echo '<p class="hint is-bad">' . esc_html__( '文风 API Key 无效。已保存的 Key 不再完整显示，请到文风重新复制 Key。', 'wpfonts' ) . '</p>';
			} elseif ( 'ok' === $state || 'incomplete' === $state ) {
				echo '<p class="hint is-ok">';
				if ( '' !== $mask ) {
					echo esc_html(
						sprintf(
							/* translators: %s: masked API key, first and last characters only. */
							__( '已保存，这里显示 %s。验证通过。', 'wpfonts' ),
							$mask
						)
					);
				} else {
					esc_html_e( '已保存。页面上不再显示完整内容。', 'wpfonts' );
				}
				echo '</p>';
			} else {
				echo '<p class="hint">';
				esc_html_e( '已保存。留空表示不修改。', 'wpfonts' );
				echo '</p>';
			}
			echo '<label class="wenpai-field__clear"><input type="checkbox" name="wpfonts_settings[clear_api_key]" value="1"> ' . esc_html__( '清除已保存的 Key', 'wpfonts' ) . '</label>';
		} else {
			echo '<p class="hint">' . esc_html__( '留空表示不修改。', 'wpfonts' ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Optional font short name for public /api/css.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	private static function render_family_field( array $settings ) {
		$html = self::schema_field_html( 'family' );
		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit field_html() escapes.
			return;
		}
		echo '<div class="field"';
		self::cdn_depends();
		echo '>';
		echo '<label class="field-label" for="wpfonts-family">' . esc_html__( '字体名', 'wpfonts' ) . '</label>';
		echo '<div class="field-ctl">';
		echo '<input type="text" id="wpfonts-family" name="wpfonts_settings[family]" value="' . esc_attr( $settings['family'] ) . '" placeholder="wenfeng-ibmps" autocomplete="off" spellcheck="false">';
		echo '<p class="hint">' . esc_html__( '也可直接填写，例如 wenfeng-ibmps。填了项目名后，公开地址可以不填字体名。', 'wpfonts' ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * Directory picker. Selecting a row writes fontFamily into the font name field.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	private static function render_catalog_field( array $settings ) {
		$messages = self::catalog_messages();
		$result   = WPFonts_Catalog::read( 1, '' );
		$fonts    = ( ! empty( $result['ok'] ) && isset( $result['fonts'] ) && is_array( $result['fonts'] ) ) ? $result['fonts'] : array();
		$fonts    = self::annotate_catalog_fonts( $fonts, $settings );
		$failed   = empty( $result['ok'] );
		$family   = isset( $settings['family'] ) ? (string) $settings['family'] : '';
		$selected = array();
		foreach ( WPFonts_Settings::saved_faces( $settings ) as $face ) {
			$selected[] = $face['family'];
		}
		if ( '' !== $family && 0 === strpos( $family, 'wenfeng-' ) && ! in_array( $family, $selected, true ) ) {
			$selected[] = $family;
		}
		$visible = array();
		foreach ( $fonts as $font ) {
			if ( isset( $font['fontFamily'] ) ) {
				$visible[] = (string) $font['fontFamily'];
			}
		}

		echo '<div class="field" id="wpfonts-catalog-field"';
		self::cdn_depends();
		echo '>';
		echo '<div class="field-label" id="wpfonts-catalog-label">' . esc_html__( '选用字体', 'wpfonts' ) . '</div>';
		echo '<div class="field-ctl">';
		echo '<input type="hidden" name="wpfonts_settings[families_form]" value="1">';
		echo '<div id="wpfonts-family-holds">';
		foreach ( $selected as $token ) {
			if ( in_array( $token, $visible, true ) ) {
				continue;
			}
			echo '<input type="hidden" name="wpfonts_settings[families][]" value="' . esc_attr( $token ) . '">';
		}
		echo '</div>';
		echo '<p class="field-help">' . esc_html__( '从下方目录勾选字体，或在「字体名」里手动填写。选中的字体会写进前台加载的样式地址。', 'wpfonts' ) . '</p>';
		echo '<p class="hint">' . esc_html__( '可同时勾选多个。取消勾选并保存后，该字体不再加载。', 'wpfonts' ) . '</p>';
		echo '<p class="hint" id="wpfonts-catalog-note">' . esc_html( $messages['note'] ) . '</p>';
		echo '<p class="wpfonts-catalog-actions"><button type="button" class="btn btn-secondary" id="wpfonts-catalog-refresh">' . esc_html( $messages['refresh'] ) . '</button></p>';
		echo '<input type="search" id="wpfonts-catalog-search" placeholder="' . esc_attr__( '搜索字体名', 'wpfonts' ) . '" aria-label="' . esc_attr__( '搜索字体名', 'wpfonts' ) . '" autocomplete="off" spellcheck="false" aria-controls="wpfonts-catalog-list">';
		echo '<div id="wpfonts-catalog" data-preview-base="' . esc_url( home_url( '/' ) ) . '" data-preview-label="' . esc_attr__( '预览', 'wpfonts' ) . '" data-source-label="' . esc_attr__( '详情', 'wpfonts' ) . '" data-loading="' . esc_attr( $messages['loading'] ) . '" data-fail="' . esc_attr( $messages['fail'] ) . '" data-empty="' . esc_attr( $messages['empty'] ) . '" data-loaded="' . esc_attr( $messages['loaded'] ) . '" data-updating="' . esc_attr( $messages['updating'] ) . '" data-updated="' . esc_attr( $messages['updated'] ) . '" data-stale="' . esc_attr( $messages['stale'] ) . '" data-glyph="' . esc_attr__( '字形预览', 'wpfonts' ) . '" data-glyph-sample="' . esc_attr__( '永和九年', 'wpfonts' ) . '">';
		if ( $failed ) {
			echo '<p class="hint" id="wpfonts-catalog-status">' . esc_html( $messages['missing'] ) . '</p>';
		} elseif ( array() === $fonts ) {
			echo '<p class="hint" id="wpfonts-catalog-status">' . esc_html( $messages['empty'] ) . '</p>';
		} else {
			echo '<p class="hint" id="wpfonts-catalog-status" hidden>' . esc_html( $messages['loading'] ) . '</p>';
		}
		echo '<div id="wpfonts-catalog-list" class="choice-cards wpfonts-catalog-list" role="group" aria-labelledby="wpfonts-catalog-label"';
		if ( $failed || array() === $fonts ) {
			echo ' hidden';
		}
		echo '>';
		foreach ( $fonts as $font ) {
			$id = $font['fontFamily'];
			$on = in_array( $id, $selected, true );
			self::render_catalog_choice( $font, $on );
		}
		echo '</div>';
		echo '<p class="hint" id="wpfonts-catalog-scope" hidden>' . esc_html( $messages['loaded'] ) . '</p>';
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $fonts ) : false;
		if ( ! is_string( $json ) ) {
			$json = '[]';
		}
		echo '<script type="application/json" id="wpfonts-catalog-data">';
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() hex-encodes tags and quotes.
		echo '</script>';
		echo '</div></div></div>';
	}

	/**
	 * One catalog checkbox. A known CSS face paints a glyph sample.
	 *
	 * @param array $font Catalog row. May include cssFace and cssUrl.
	 * @param bool  $on   Whether the family is selected.
	 * @return void
	 */
	private static function render_catalog_choice( array $font, $on ) {
		$id      = isset( $font['fontFamily'] ) ? (string) $font['fontFamily'] : '';
		$name    = isset( $font['name'] ) ? (string) $font['name'] : $id;
		$css     = isset( $font['cssFace'] ) ? (string) $font['cssFace'] : '';
		$url     = isset( $font['cssUrl'] ) ? (string) $font['cssUrl'] : '';
		$card    = isset( $font['cardPreviewUrl'] ) ? (string) $font['cardPreviewUrl'] : '';
		$preview = isset( $font['previewUrl'] ) ? (string) $font['previewUrl'] : '';
		$style   = self::glyph_family_style( $css );
		$has_css = '' !== $style;
		$has_img = self::specimen_url_ok( $card );
		echo '<label class="choice' . ( $on ? ' is-on' : '' ) . '"';
		if ( $has_css ) {
			echo ' data-css-face="' . esc_attr( $css ) . '"';
		}
		if ( '' !== $url ) {
			echo ' data-css-url="' . esc_attr( $url ) . '"';
		}
		if ( $has_img ) {
			echo ' data-card-url="' . esc_attr( $card ) . '"';
		}
		if ( self::specimen_url_ok( $preview ) ) {
			echo ' data-preview-url="' . esc_attr( $preview ) . '"';
		}
		echo '>';
		echo '<input type="checkbox" name="wpfonts_settings[families][]" value="' . esc_attr( $id ) . '"' . checked( $on, true, false ) . '>';
		if ( $has_img ) {
			self::render_specimen_image( $card, $name );
		}
		echo '<span class="wpfonts-choice-text">';
		echo '<span class="t wpfonts-glyph"';
		if ( $has_css && ! $has_img ) {
			echo ' style="' . esc_attr( $style ) . '"';
		}
		echo '>' . esc_html( $name ) . '</span>';
		echo '<span class="d">' . esc_html( $id );
		echo '<a class="wpfonts-preview-link" href="' . esc_url( add_query_arg( array( 'wpfonts_preview' => $id ), home_url( '/' ) ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '预览', 'wpfonts' ) . '</a>';
		echo '<a class="wpfonts-source-link" href="' . esc_url( 'https://app.windfonts.com/fonts/' . rawurlencode( (string) preg_replace( '/^wenfeng-/', '', $id ) ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '详情', 'wpfonts' ) . '</a>';
		echo '</span>';
		if ( $has_css ) {
			echo '<span class="wpfonts-specimen-fallback"';
			if ( $has_img ) {
				echo ' hidden';
			}
			echo '>';
			echo '<span class="meta wpfonts-glyph-label">' . esc_html__( '字形预览', 'wpfonts' ) . '</span>';
			echo '<span class="wpfonts-glyph-sample wpfonts-glyph" style="' . esc_attr( $style ) . '">' . esc_html__( '永和九年', 'wpfonts' ) . '</span>';
			echo '</span>';
		}
		echo '</span></label>';
	}

	/**
	 * Attach a confirmed CSS face, and the public stylesheet URL, to catalog rows.
	 *
	 * cssFace is set only after this site has already read that stylesheet.
	 * cssUrl is the allowlisted public address. The browser may use it to
	 * learn a face that is not confirmed yet. The API key is not included.
	 * previewUrl, cardPreviewUrl, and fontPreviewUrl are derived from the slug.
	 * They are not stored in the catalog snapshot. A row that already has
	 * those fields, or the same keys under assets, keeps a matching webp.
	 * The checkbox list uses the card strip. fontPreviewUrl stays on the row
	 * and is not painted on that strip or on the installed package detail.
	 *
	 * @param array $fonts    Catalog rows.
	 * @param array $settings Public settings.
	 * @return array<int,array<string,string>>
	 */
	private static function annotate_catalog_fonts( array $fonts, array $settings ) {
		$map = array();
		if ( class_exists( 'WPFonts_Settings', false ) ) {
			foreach ( WPFonts_Settings::confirmed_faces( $settings ) as $face ) {
				if ( '' !== $face['css_face'] ) {
					$map[ $face['family'] ] = $face['css_face'];
				}
			}
		}
		$out = array();
		foreach ( $fonts as $font ) {
			if ( ! is_array( $font ) || empty( $font['fontFamily'] ) ) {
				continue;
			}
			$token = (string) $font['fontFamily'];
			$row   = array(
				'name'       => isset( $font['name'] ) ? (string) $font['name'] : $token,
				'fontFamily' => $token,
			);
			if ( isset( $map[ $token ] ) ) {
				$row['cssFace'] = $map[ $token ];
			}
			if ( class_exists( 'WPFonts_Link', false ) ) {
				$url = WPFonts_Link::family_stylesheet_url( $settings, $token, isset( $settings['subset'] ) ? $settings['subset'] : '' );
				if ( '' !== $url && WPFonts_Link::is_allowlisted( $url ) ) {
					$row['cssUrl'] = $url;
				}
				$region = isset( $settings['region'] ) ? $settings['region'] : 'cn';
				$spec   = WPFonts_Link::package_specimen_urls( $token, $region, $font );
				if ( '' !== $spec['previewUrl'] ) {
					$row['previewUrl'] = $spec['previewUrl'];
				}
				if ( '' !== $spec['cardPreviewUrl'] ) {
					$row['cardPreviewUrl'] = $spec['cardPreviewUrl'];
				}
				if ( '' !== $spec['fontPreviewUrl'] ) {
					$row['fontPreviewUrl'] = $spec['fontPreviewUrl'];
				}
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Whether this string is a CDN specimen image URL.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	private static function specimen_url_ok( $url ) {
		if ( ! class_exists( 'WPFonts_Link', false ) || ! is_string( $url ) || '' === $url ) {
			return false;
		}
		if ( ! preg_match( '#/fonts-packages/([a-z0-9-]+)/preview/(preview|card|og|fontpreview)\.(?:webp|png)$#', $url, $matches ) ) {
			return false;
		}
		return WPFonts_Link::is_package_specimen_url( $url, $matches[1], $matches[2] );
	}

	/**
	 * Print one specimen image. webp first; data-fallback is the png sibling.
	 *
	 * The admin script swaps to png, then reveals the text specimen, when
	 * the image is missing. Printing the URL does not download a font.
	 *
	 * @param string $url Image URL, already checked.
	 * @param string $alt Accessible name.
	 * @return void
	 */
	private static function render_specimen_image( $url, $alt ) {
		echo '<img class="wpfonts-specimen" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" decoding="async" loading="lazy"';
		if ( class_exists( 'WPFonts_Link', false ) ) {
			$png = WPFonts_Link::specimen_png_url( $url );
			if ( '' !== $png ) {
				echo ' data-fallback="' . esc_attr( $png ) . '"';
			}
		}
		echo '>';
	}

	/**
	 * Inline font-family for a confirmed CSS face, or empty.
	 *
	 * @param string $face CSS font-family name.
	 * @return string
	 */
	private static function glyph_family_style( $face ) {
		if ( ! class_exists( 'WPFonts_Library', false ) ) {
			return '';
		}
		$stack = WPFonts_Library::css_stack( $face );
		if ( '' === $stack ) {
			return '';
		}
		return 'font-family: ' . $stack;
	}

	/**
	 * Load confirmed 文风云 stylesheets so the catalog sample can use them.
	 *
	 * Only faces this site has already confirmed. Each URL is allowlisted.
	 *
	 * @return void
	 */
	private static function enqueue_catalog_glyphs() {
		if ( ! function_exists( 'wp_enqueue_style' ) || ! class_exists( 'WPFonts_Settings', false ) || ! class_exists( 'WPFonts_Link', false ) ) {
			return;
		}
		$settings = WPFonts_Settings::get();
		$seen     = array();
		foreach ( WPFonts_Settings::confirmed_faces( $settings ) as $face ) {
			$url = WPFonts_Link::family_stylesheet_url( $settings, $face['family'], $face['subset'] );
			if ( '' === $url || ! WPFonts_Link::is_allowlisted( $url ) || isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			wp_enqueue_style( 'wpfonts-catalog-' . $face['family'], $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The allowlisted URL must stay free of a ver query arg.
		}
	}

	/**
	 * Optional project slug.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	private static function render_project_field( array $settings ) {
		echo '<div id="wpfonts-project-wrap"';
		self::cdn_depends();
		echo '>';
		echo '<details id="wpfonts-project">';
		echo '<summary>' . esc_html__( '项目与白名单', 'wpfonts' ) . '</summary>';
		echo '<div class="field">';
		echo '<label class="field-label" for="wpfonts-slug">' . esc_html__( '项目名', 'wpfonts' ) . '</label>';
		echo '<div class="field-ctl">';
		echo '<input type="text" id="wpfonts-slug" name="wpfonts_settings[project_slug]" value="' . esc_attr( $settings['project_slug'] ) . '" autocomplete="off" spellcheck="false">';
		echo '<p class="hint">' . esc_html__( '选填。填写后前台改用项目样式。域名白名单在文风控制台配置，不在本插件里改。', 'wpfonts' ) . '</p>';
		echo '<p><a class="btn btn-secondary" href="' . esc_url( WPFonts_Link::CONSOLE_URL ) . '" target="_blank" rel="noopener noreferrer">';
		echo esc_html__( '打开文风控制台', 'wpfonts' );
		echo '<span class="screen-reader-text">' . esc_html__( '（在新标签页打开）', 'wpfonts' ) . '</span>';
		echo '</a></p>';
		echo '</div></div>';
		echo '</details></div>';
	}

	/**
	 * Read-only chosen font on the tools screen.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	private static function render_selection_summary( array $settings ) {
		$family  = WPFonts_Link::family( $settings );
		$subset  = WPFonts_Link::subset( isset( $settings['subset'] ) ? $settings['subset'] : '' );
		$choices = self::subset_choices();
		$choice  = isset( $choices[ $subset ] ) ? $choices[ $subset ] : $choices['zh-common'];
		echo '<section class="card" id="wpfonts-selection-summary">';
		echo '<h2 class="card-title">' . esc_html__( '选用字体', 'wpfonts' ) . '</h2>';
		echo Wenpai_Admin_UI::status_list_html(
			array(
				'items' => array(
					array(
						'label' => __( '字体名', 'wpfonts' ),
						'value' => '' !== $family ? $family : __( '未设置', 'wpfonts' ),
					),
					array(
						'label' => __( '字符范围', 'wpfonts' ),
						'value' => $choice['label'],
						'desc'  => $choice['hint'],
					),
				),
			)
		);
		if ( '' === $family ) {
			echo '<div class="card-foot start"><a class="btn btn-secondary" href="' . esc_url( self::page_url( array( 'tab' => 'settings' ) ) ) . '">' . esc_html__( '去设置', 'wpfonts' ) . '</a></div>';
		}
		echo '</section>';
	}

	/**
	 * Link field inside the settings card.
	 *
	 * @param array  $settings Public settings.
	 * @param string $state    View state.
	 * @param bool   $has_key  Whether a key is stored.
	 * @return void
	 */
	private static function render_link_field( array $settings, $state, $has_key ) {
		echo '<div class="field"';
		self::cdn_depends();
		echo '>';
		echo '<div class="field-label">' . esc_html__( '样式链接', 'wpfonts' ) . '</div>';
		echo '<div class="field-ctl">';
		self::render_link_preview( $settings, $state, $has_key, true );
		echo '</div></div>';
	}

	/**
	 * The link we would print, or a short reason we will not.
	 *
	 * @param array  $settings Public settings.
	 * @param string $state    View state.
	 * @param bool   $has_key  Whether a key is stored.
	 * @param bool   $in_form  True on the settings form.
	 * @return void
	 */
	private static function render_link_preview( array $settings, $state, $has_key, $in_form ) {
		if ( 'invalid' === $state ) {
			self::notice( 'bad', __( '不会加载远程样式。文风 API Key 无效。', 'wpfonts' ) );
			echo '<p class="hint">' . esc_html__( '更正 Key 并保存后，这里才会显示文风服务器上的样式链接。', 'wpfonts' ) . '</p>';
			return;
		}
		if ( ! $has_key && 'empty' !== $state ) {
			self::notice( 'warn', __( '尚未填写文风 API Key。前台暂不加载远程样式。', 'wpfonts' ) );
			return;
		}
		$faces = WPFonts_Settings::confirmed_faces( $settings );
		if ( ! WPFonts_Link::has_target( $settings ) ) {
			if ( array() === $faces ) {
				self::notice( 'warn', __( '还不能放到前台。请填写字体名，或填写项目名。', 'wpfonts' ) );
				echo '<p class="hint">' . esc_html__( '公开样式地址需要字体名或项目名。填写后这里显示前台加载的地址。', 'wpfonts' ) . '</p>';
				return;
			}
			self::render_face_links( $settings, $faces );
			return;
		}

		$url = WPFonts_Link::stylesheet_url( $settings );
		// Text preview only. esc_html() below keeps the browser from loading it.
		$css_status = isset( $settings['css_status'] ) ? (string) $settings['css_status'] : '';
		if ( 'css_missing' === $state || 'missing' === $css_status ) {
			self::notice( 'bad', __( '这条地址打不开，前台不会加载。换个字体，或改字符范围后再保存。', 'wpfonts' ) );
			self::echo_embed_code( $url );
			return;
		}
		self::echo_embed_code( $url );
		if ( 'ok' === $state && 'ok' === $css_status ) {
			echo '<p class="hint is-ok">' . esc_html__( '前台会加载这一条。链接中没有 API Key。', 'wpfonts' ) . '</p>';
		} elseif ( 'ok' === $state ) {
			echo '<p class="hint">' . esc_html__( '还没确认这条地址能打开。保存一次后再看。', 'wpfonts' ) . '</p>';
		} elseif ( 'unreachable' === $state || 'unchecked' === $state ) {
			echo '<p class="hint">' . esc_html__( '尚未验证通过，前台暂不使用。', 'wpfonts' ) . '</p>';
		} elseif ( $in_form && 'empty' === $state ) {
			echo '<p class="hint">' . esc_html__( '开启文风云并保存后，前台才会使用。', 'wpfonts' ) . '</p>';
		} else {
			echo '<p class="hint">' . esc_html__( '前台目前不会使用。', 'wpfonts' ) . '</p>';
		}
		if ( '' !== $settings['project_slug'] ) {
			echo '<p class="hint">' . esc_html__( '使用的是项目中已经准备好的样式。', 'wpfonts' ) . '</p>';
		} else {
			echo '<p class="hint">';
			echo esc_html(
				sprintf(
					/* translators: %s: subset id such as zh-common. */
					__( '公开字体地址，字符范围 %s 附在地址后面。', 'wpfonts' ),
					$settings['subset']
				)
			);
			echo '</p>';
		}
	}

	/**
	 * The copyable embed. License comment, preconnect, then the stylesheet.
	 *
	 * @param string $url Stylesheet URL.
	 * @return void
	 */
	private static function echo_embed_code( $url ) {
		$snippet = WPFonts_Link::embed_snippet( $url );
		if ( '' === $snippet ) {
			return;
		}
		echo '<p class="meta">' . esc_html__( '链接文字（不加载字体）', 'wpfonts' ) . '</p>';
		echo Wenpai_Admin_UI::code_block_html( array( 'code' => $snippet, 'lang' => 'html' ) );
	}

	/**
	 * Per-face stylesheet preview for catalog-only selections.
	 *
	 * @param array $settings Public settings.
	 * @param array $faces    Confirmed faces from WPFonts_Settings::confirmed_faces().
	 * @return void
	 */
	private static function render_face_links( array $settings, array $faces ) {
		echo '<p class="hint">';
		echo esc_html(
			sprintf(
				/* translators: %d: number of confirmed cloud fonts. */
				__( '前台按已确认字体各加载一条样式，共 %d 条。链接中没有 API Key。', 'wpfonts' ),
				count( $faces )
			)
		);
		echo '</p>';
		foreach ( $faces as $face ) {
			$furl = WPFonts_Link::family_stylesheet_url(
				$settings,
				isset( $face['family'] ) ? $face['family'] : '',
				isset( $face['subset'] ) ? $face['subset'] : ''
			);
			if ( '' === $furl || ! WPFonts_Link::is_allowlisted( $furl ) ) {
				continue;
			}
			$label = '' !== (string) ( $face['name'] ?? '' ) ? (string) $face['name'] : (string) $face['family'];
			echo '<p class="meta">' . esc_html( $label ) . '</p>';
			echo Wenpai_Admin_UI::code_inline_html( array( 'code' => $furl ) );
		}
	}

	/**
	 * Health result copy.
	 *
	 * @param string $status  Stored key status.
	 * @param bool   $has_key Whether a key is stored.
	 * @return void
	 */
	private static function render_health_result( $status, $has_key ) {
		if ( ! $has_key ) {
			self::notice( 'warn', __( '尚未填写文风 API Key。', 'wpfonts' ) );
			return;
		}
		if ( 'invalid' === $status ) {
			self::notice( 'bad', __( '文风 API Key 无效。', 'wpfonts' ) );
			return;
		}
		if ( 'unreachable' === $status ) {
			self::notice( 'warn', __( '这次未能连接。请稍后再试。', 'wpfonts' ) );
			echo '<p class="hint">' . esc_html__( '可能是超时或服务暂时不可用，并非 Key 无效。期间前台使用系统字体，请稍后再检查。', 'wpfonts' ) . '</p>';
			return;
		}
		if ( 'valid' === $status ) {
			self::notice( 'ok', __( '文风 API Key 可用。', 'wpfonts' ) );
			return;
		}
		self::notice( 'warn', __( '尚未检查。请点击下方再检查一次。', 'wpfonts' ) );
	}

	/**
	 * Tile color for the CDN row.
	 *
	 * @param string $state View state.
	 * @return string
	 */
	private static function cdn_tile( $state ) {
		if ( 'ok' === $state || 'incomplete' === $state ) {
			return 'ok';
		}
		if ( 'invalid' === $state || 'css_missing' === $state ) {
			return 'bad';
		}
		if ( 'unreachable' === $state || 'unchecked' === $state || 'nokey' === $state ) {
			return 'warn';
		}
		return '';
	}

	/**
	 * CDN row description.
	 *
	 * @param string $state View state.
	 * @return string
	 */
	private static function cdn_desc( $state ) {
		if ( 'empty' === $state ) {
			return __( '未开启。开启并保存后，前台加载文风样式。', 'wpfonts' );
		}
		if ( 'nokey' === $state ) {
			return __( '已开启，但尚未填写文风 API Key。', 'wpfonts' );
		}
		if ( 'invalid' === $state ) {
			return __( '文风 API Key 无效，前台使用系统字体。', 'wpfonts' );
		}
		if ( 'unreachable' === $state ) {
			return __( '这次未能连接。', 'wpfonts' );
		}
		if ( 'unchecked' === $state ) {
			return __( '文风 API Key 尚未验证。', 'wpfonts' );
		}
		if ( 'incomplete' === $state ) {
			return __( '文风 API Key 可用。还需要字体名或项目名。', 'wpfonts' );
		}
		if ( 'css_missing' === $state ) {
			return __( '这条地址打不开，前台不会加载。', 'wpfonts' );
		}
		return __( '已开启。前台会加载文风服务器上的样式。', 'wpfonts' );
	}

	/**
	 * CDN pill class.
	 *
	 * @param string $state View state.
	 * @return string
	 */
	private static function cdn_pill( $state ) {
		if ( 'ok' === $state ) {
			return 'ok';
		}
		if ( 'invalid' === $state || 'css_missing' === $state ) {
			return 'bad';
		}
		if ( 'empty' === $state ) {
			return 'mute';
		}
		return 'warn';
	}

	/**
	 * CDN pill label.
	 *
	 * @param string $state View state.
	 * @return string
	 */
	private static function cdn_pill_label( $state ) {
		if ( 'empty' === $state ) {
			return __( '未开启', 'wpfonts' );
		}
		if ( 'invalid' === $state ) {
			return __( '无效', 'wpfonts' );
		}
		if ( 'unreachable' === $state ) {
			return __( '未能连接', 'wpfonts' );
		}
		if ( 'nokey' === $state ) {
			return __( '未填写', 'wpfonts' );
		}
		if ( 'unchecked' === $state ) {
			return __( '未验证', 'wpfonts' );
		}
		if ( 'incomplete' === $state ) {
			return __( '未完成', 'wpfonts' );
		}
		if ( 'css_missing' === $state ) {
			return __( '打不开', 'wpfonts' );
		}
		return __( '已开启', 'wpfonts' );
	}

	/**
	 * Overview row for on-site packages.
	 *
	 * @return void
	 */
	private static function render_local_row() {
		$presence = WPFonts_Packages::presence();
		$pill     = 'mute';
		$label    = __( '未安装', 'wpfonts' );
		$desc     = __( '还没有文风字体包', 'wpfonts' );
		if ( 'active' === $presence ) {
			$pill  = 'ok';
			$label = __( '使用中', 'wpfonts' );
			$desc  = __( '前台会加载本站这份字体样式。', 'wpfonts' );
		} elseif ( 'inactive' === $presence ) {
			$label = __( '已停用', 'wpfonts' );
			$desc  = __( '文件保留在本站，启用后即可使用。', 'wpfonts' );
		}
		echo Wenpai_Admin_UI::row_html(
		array(
			'icon'       => 'download',
			'state'      => 'active' === $presence ? 'ok' : '',
			'title'      => __( '文风字体包', 'wpfonts' ),
			'desc'       => $desc,
			'pill'       => array(
				'text'  => $label,
				'state' => $pill,
			),
			'right_html' => '<a class="btn btn-secondary" href="' . esc_url( self::page_url( array( 'tab' => 'tools', 'section' => 'packages' ) ) ) . '">' . esc_html__( '字体包', 'wpfonts' ) . '</a>',
		)
	);
	}

	/**
	 * Packages body under 工具 → 字体包. Renders inside the tools stack.
	 *
	 * @return void
	 */
	private static function render_packages_body() {
		$note     = self::query_key( 'wpfonts_pkg', '' );
		$packages = WPFonts_Packages::all();
		echo '<div class="wenpai-stack">';
		if ( 'installed' === $note ) {
			self::notice( 'ok', __( '已安装。前台会加载本站这份字体样式，也会出现在全站样式里。', 'wpfonts' ) );
		} elseif ( 'reinstalled' === $note ) {
			self::notice( 'ok', __( '已替换本站这份字体。前台会加载新的样式。', 'wpfonts' ) );
		} elseif ( 'enabled' === $note ) {
			self::notice( 'ok', __( '前台会加载本站这份字体样式，也会出现在全站样式里。', 'wpfonts' ) );
		} elseif ( 'disabled' === $note ) {
			self::notice( 'info', __( '已停用。前台和全站样式不再使用这份字体，文件仍保留在本站。', 'wpfonts' ) );
		} elseif ( 'deleted' === $note ) {
			self::notice( 'ok', __( '已删除这份字体包。前台和全站样式不再使用它，本站文件也已移除。', 'wpfonts' ) );
		} elseif ( 'failed' === $note ) {
			self::notice( 'bad', __( '装不上。请用文风已切好的 zip（内含样式和 woff2）。', 'wpfonts' ) );
		}
		if ( array() === $packages ) {
			self::render_install_zone( true );
		} else {
			foreach ( $packages as $package ) {
				self::render_package_row( $package );
			}
			self::render_install_zone( false );
		}
		echo '</div>';
	}

	/**
	 * One drop zone. Choosing or dropping a zip submits immediately.
	 *
	 * @param bool $blank Empty state fills the zone. Otherwise a compact strip.
	 * @return void
	 */
	private static function render_install_zone( $blank ) {
		echo '<section class="card" id="wpfonts-install-zone">';
		echo '<form id="wpfonts-install-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wpfonts_install_package' );
		echo '<input type="hidden" name="action" value="wpfonts_install_package">';
		echo '<span class="wenpai-file" data-wenpai-file data-wenpai-file-local data-multiple="0" data-name="wpfonts_package" data-remove="' . esc_attr__( '移除', 'wpfonts' ) . '">';
		echo '<span class="wenpai-file__drop" role="button" tabindex="0" aria-label="' . esc_attr__( '拖入字体包 zip，或点击选择', 'wpfonts' ) . '">';
		self::icon( 'upload' );
		echo '<span>' . esc_html( $blank ? __( '把 zip 拖到这里，或点击选择文件', 'wpfonts' ) : __( '拖入 zip 安装，或点击选择', 'wpfonts' ) ) . '</span>';
		echo '<input type="file" class="wenpai-file__input" id="wpfonts-package-file" name="wpfonts_package" accept=".zip,application/zip" hidden>';
		echo '</span>';
		echo '<span class="wenpai-file__list"></span>';
		echo '</span>';
		if ( ! $blank ) {
			echo '<p class="meta" style="margin-top:var(--wenpai-space-3);">' . esc_html__( '安装后由本站托管、直接加载。', 'wpfonts' ) . '</p>';
		}
		echo '<p class="meta" id="wpfonts-install-status" data-busy="' . esc_attr__( '正在安装…', 'wpfonts' ) . '" hidden></p>';
		echo '</form>';
		echo '</section>';
	}

	/**
	 * One installed package.
	 *
	 * @param array $package Package row.
	 * @return void
	 */
	private static function render_package_row( array $package ) {
		$on = ! empty( $package['enabled'] );
		echo '<section class="card">';
		ob_start();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wpfonts_package_toggle' );
		echo '<input type="hidden" name="action" value="wpfonts_package_toggle">';
		echo '<input type="hidden" name="id" value="' . esc_attr( $package['id'] ) . '">';
		echo '<input type="hidden" name="enabled" value="' . esc_attr( $on ? '0' : '1' ) . '">';
		echo '<button type="submit" class="btn btn-secondary">' . esc_html( $on ? __( '停用', 'wpfonts' ) : __( '启用', 'wpfonts' ) ) . '</button>';
		echo '</form>';
		$wpfonts_toggle = (string) ob_get_clean();
		$health         = WPFonts_Packages::health( $package );
		echo Wenpai_Admin_UI::row_html(
			array(
				'icon'         => 'download',
				'state'        => $on ? 'ok' : '',
				'title'        => $package['name'],
				'desc'         => $health['reason'],
				'pill'         => array(
					'text'  => $on ? __( '使用中', 'wpfonts' ) : __( '已停用', 'wpfonts' ),
					'state' => $on ? 'ok' : 'mute',
				),
				'control_html' => $wpfonts_toggle,
			)
		);
		self::render_package_preview( $package );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wpfonts_package_delete' );
		echo '<input type="hidden" name="action" value="wpfonts_package_delete">';
		echo '<input type="hidden" name="id" value="' . esc_attr( $package['id'] ) . '">';
		echo Wenpai_Admin_UI::gate_html(
			array(
				'name'  => 'wpfonts_delete_ack',
				'label' => __( '删除后本站不再提供这份字体。我已核对。', 'wpfonts' ),
			)
		);
		echo '<div class="card-foot start">';
		echo '<span class="meta">' . esc_html__( '未勾选不能点。', 'wpfonts' ) . '</span>';
		echo '<button type="submit" class="btn btn-danger" data-wenpai-need-apply disabled>' . esc_html__( '删除', 'wpfonts' ) . '</button>';
		echo '</div>';
		echo '</form>';
		echo '</section>';
	}

	/**
	 * Card strip for one installed package.
	 *
	 * The detail screen shows cardPreviewUrl only (asset card, 1200×280).
	 * fontPreviewUrl stays on the specimen row and is not rendered here.
	 * Catalog choice cards also use the card strip. Font Library families
	 * stay on previewUrl. webp is printed first; the img carries the png
	 * sibling as data-fallback. A live pangram is only the fallback when
	 * the card image is missing. It is not painted on top of a CDN image.
	 *
	 * @param array $package Package row.
	 * @return void
	 */
	private static function render_package_preview( array $package ) {
		$family   = isset( $package['family'] ) ? (string) $package['family'] : '';
		$id       = isset( $package['id'] ) ? (string) $package['id'] : '';
		$name     = isset( $package['name'] ) ? (string) $package['name'] : $id;
		$declared = WPFonts_Packages::installed_css_family( $id );
		$plain    = WPFonts_Library::plain_family( '' !== $declared ? $declared : $family );
		$health   = WPFonts_Packages::health( $package );
		$ready    = '' !== $plain && 'ok' === $health['code'];
		$sliced   = class_exists( 'WPFonts_Library', false ) && WPFonts_Library::package_is_sliced( $id );
		$region   = 'cn';
		$image    = '';
		if ( class_exists( 'WPFonts_Settings', false ) ) {
			$saved  = WPFonts_Settings::get();
			$region = isset( $saved['region'] ) ? (string) $saved['region'] : 'cn';
		}
		if ( class_exists( 'WPFonts_Link', false ) ) {
			$spec  = WPFonts_Link::package_specimen_urls( $id, $region, $package );
			$image = isset( $spec['cardPreviewUrl'] ) ? (string) $spec['cardPreviewUrl'] : '';
		}
		$has_img = self::specimen_url_ok( $image );
		echo '<div class="wpfonts-preview"';
		if ( '' !== $plain ) {
			echo ' data-family="' . esc_attr( $plain ) . '"';
		}
		echo '>';
		if ( $sliced ) {
			echo '<p class="hint">' . esc_html__( '本包按字符切片托管，前台会自动按需加载；字体库里每个字重只显示一条即可。', 'wpfonts' ) . '</p>';
		}
		if ( 'unknown' === $health['code'] ) {
			$ready = '' !== $plain && self::package_css_ready( $id );
		}
		if ( $has_img ) {
			echo '<div class="wpfonts-specimen-wrap">';
			self::render_specimen_image( $image, $name );
		}
		if ( ! $ready ) {
			if ( ! $has_img ) {
				$reason = ( '' !== $health['reason'] ) ? $health['reason'] : __( '预览暂时不可用。', 'wpfonts' );
				echo '<p class="meta">' . esc_html( $reason ) . '</p>';
			}
			if ( $has_img ) {
				echo '</div>';
			}
			echo '</div>';
			return;
		}
		$style = self::glyph_family_style( $plain );
		echo '<div class="wpfonts-specimen-fallback"';
		if ( $has_img ) {
			echo ' hidden';
		}
		echo '>';
		echo '<p class="meta">' . esc_html__( '字形预览', 'wpfonts' ) . '</p>';
		echo '<div class="wpfonts-glyph" style="' . esc_attr( $style ) . '">';
		echo '<p class="wpfonts-preview-zh">' . esc_html__( '永和九年，岁在癸丑。暮春之初，会于会稽山阴之兰亭。', 'wpfonts' ) . '</p>';
		echo '<p class="wpfonts-preview-en">The quick brown fox jumps over the lazy dog.</p>';
		echo '<p class="wpfonts-preview-num">0123456789</p>';
		echo '</div></div>';
		if ( $has_img ) {
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Whether this package's local.css can be read. Unknown hosts still preview.
	 *
	 * @param string $id Package id.
	 * @return bool
	 */
	private static function package_css_ready( $id ) {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return true;
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return false;
		}
		$path = trailingslashit( $uploads['basedir'] ) . 'wpfonts/packages/' . $id . '/local.css';
		return is_readable( $path );
	}

	/**
	 * Install a zip from the packages screen.
	 *
	 * @return void
	 */
	public static function install_package() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '你没有权限做这件事。', 'wpfonts' ) );
		}
		check_admin_referer( 'wpfonts_install_package' );
		$file = array();
		if ( isset( $_FILES['wpfonts_package'] ) && is_array( $_FILES['wpfonts_package'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- install_uploaded_file() checks each upload field.
			$file = wp_unslash( $_FILES['wpfonts_package'] );
		}
		$result = WPFonts_Packages::install_uploaded_file( $file );
		if ( is_wp_error( $result ) ) {
			$code = 'failed';
		} elseif ( is_array( $result ) && isset( $result['action'] ) && 'reinstall' === $result['action'] ) {
			$code = 'reinstalled';
		} else {
			$code = 'installed';
		}
		wp_safe_redirect(
			self::page_url(
				array(
					'tab'         => 'tools',
					'section'     => 'packages',
					'wpfonts_pkg' => $code,
				)
			)
		);
		exit;
	}

	/**
	 * Enable or disable a package.
	 *
	 * @return void
	 */
	public static function toggle_package() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '你没有权限做这件事。', 'wpfonts' ) );
		}
		check_admin_referer( 'wpfonts_package_toggle' );
		$id      = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$enabled = isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );
		WPFonts_Packages::set_enabled( $id, $enabled );
		wp_safe_redirect(
			self::page_url(
				array(
					'tab'         => 'tools',
					'section'     => 'packages',
					'wpfonts_pkg' => $enabled ? 'enabled' : 'disabled',
				)
			)
		);
		exit;
	}

	/**
	 * Delete a package. The screen checkbox must be posted with the nonce.
	 *
	 * @return void
	 */
	public static function delete_package() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '你没有权限做这件事。', 'wpfonts' ) );
		}
		check_admin_referer( 'wpfonts_package_delete' );
		$ack = isset( $_POST['wpfonts_delete_ack'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['wpfonts_delete_ack'] ) );
		$code = 'noop';
		if ( $ack ) {
			$id   = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
			$gone = WPFonts_Packages::delete( $id );
			$code = $gone ? 'deleted' : 'noop';
		}
		wp_safe_redirect(
			self::page_url(
				array(
					'tab'         => 'tools',
					'section'     => 'packages',
					'wpfonts_pkg' => $code,
				)
			)
		);
		exit;
	}

	/**
	 * Download enabled package metadata and confirmed cloud faces.
	 *
	 * The API key is not part of the document.
	 *
	 * @return void
	 */
	public static function export_migration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '你没有权限做这件事。', 'wpfonts' ) );
		}
		check_admin_referer( 'wpfonts_export' );
		$payload = WPFonts_Transfer::export_json();
		if ( '' === $payload ) {
			wp_safe_redirect(
				self::page_url(
					array(
						'tab'          => 'tools',
						'section'      => 'migration',
						'wpfonts_xfer' => 'invalid',
					)
				)
			);
			exit;
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wpfonts-migration.json"' );
		echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download. Secret fields are stripped before encode.
		exit;
	}

	/**
	 * Import a migration file. A file that contains a key is refused.
	 *
	 * @return void
	 */
	public static function import_migration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '你没有权限做这件事。', 'wpfonts' ) );
		}
		check_admin_referer( 'wpfonts_import' );
		$ack = isset( $_POST['wpfonts_import_ack'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['wpfonts_import_ack'] ) );
		if ( ! $ack ) {
			wp_safe_redirect(
				self::page_url(
					array(
						'tab'          => 'tools',
						'section'      => 'migration',
						'wpfonts_xfer' => 'invalid',
					)
				)
			);
			exit;
		}
		$file = array();
		if ( isset( $_FILES['wpfonts_import'] ) && is_array( $_FILES['wpfonts_import'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The upload is read as JSON and then validated.
			$file = wp_unslash( $_FILES['wpfonts_import'] );
		}
		$tmp  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$err  = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$code = 'empty';
		if ( UPLOAD_ERR_OK === $err && '' !== $tmp && $size > 0 && $size <= 524288 && is_uploaded_file( $tmp ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- One uploaded JSON file, size-capped above.
			$raw    = file_get_contents( $tmp );
			$result = WPFonts_Transfer::import_json( is_string( $raw ) ? $raw : '' );
			$code   = isset( $result['code'] ) ? (string) $result['code'] : 'invalid';
			if ( 'imported' === $code && isset( $result['message'] ) && function_exists( 'get_current_user_id' ) && function_exists( 'set_transient' ) ) {
				$user = (int) get_current_user_id();
				if ( $user > 0 ) {
					set_transient( 'wpfonts_xfer_' . $user, (string) $result['message'], 60 );
				}
			}
		}
		if ( ! in_array( $code, array( 'imported', 'refused', 'invalid', 'empty' ), true ) ) {
			$code = 'invalid';
		}
		wp_safe_redirect(
			self::page_url(
				array(
					'tab'          => 'tools',
					'section'      => 'migration',
					'wpfonts_xfer' => $code,
				)
			)
		);
		exit;
	}
}

WPFonts_Admin::init();
