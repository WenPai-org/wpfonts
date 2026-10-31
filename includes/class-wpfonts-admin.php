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
		add_filter( 'plugin_action_links_' . WPFONTS_BASENAME, array( __CLASS__, 'action_links' ) );
		add_filter( 'gettext', array( __CLASS__, 'saved_label' ), 10, 3 );
	}

	/**
	 * Settings submenu. The brand name stays in the page, not the left menu.
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
				'label'  => __( '文派字库', 'wpfonts' ),
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

		$tab  = self::query_key( 'tab', 'overview' );
		$mode = self::query_key( 'mode', 'simple' );
		if ( ! in_array( $tab, array( 'overview', 'settings', 'tools', 'help' ), true ) ) {
			$tab = 'overview';
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
			self::render_tools( $settings, $state, $has_key );
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
			'feedback_url'       => 'https://github.com/WenPai-org/wpfonts/issues',
			'feedback_label'     => __( '反馈', 'wpfonts' ),
			'feedback_icon_html' => $icon( 'feedback' ),
			'version'            => __( '文派字库（WPFonts）', 'wpfonts' ) . ' · ' . WPFONTS_VERSION,
			'new_tab_label'      => __( '（在新标签页打开）', 'wpfonts' ),
			'links'              => array(
				array(
					'url'   => WPFonts_Link::CONSOLE_URL,
					'label' => __( '控制台', 'wpfonts' ),
				),
			),
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
	 * Link that opens the Windfonts console.
	 *
	 * @param string $css_class Button classes.
	 * @return void
	 */
	private static function console_link( $css_class ) {
		echo '<a class="' . esc_attr( $css_class ) . '" href="' . esc_url( WPFonts_Link::CONSOLE_URL ) . '" target="_blank" rel="noopener noreferrer">';
		esc_html_e( '去控制台上传', 'wpfonts' );
		echo '<span class="screen-reader-text">' . esc_html__( '（在新标签页打开）', 'wpfonts' ) . '</span>';
		echo '</a>';
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
			__( '默认使用系统字体。需要网页字体时再开启文风 CDN。插件不内置字体文件。', 'wpfonts' )
		);
		echo '<div class="wenpai-stack">';
		self::render_state_banner( $state );
		echo '<section class="card">';
		echo '<h2 class="card-title">' . esc_html__( '运行状态', 'wpfonts' ) . '</h2>';
		self::status_row( 'ok', 'info', __( '系统字体', 'wpfonts' ), __( '使用站点已有的字体。前台不请求远程字体。', 'wpfonts' ), 'ok', __( '可用', 'wpfonts' ) );
		self::status_row(
			self::cdn_tile( $state ),
			'cloud',
			__( '文风 CDN', 'wpfonts' ),
			self::cdn_desc( $state ),
			self::cdn_pill( $state ),
			self::cdn_pill_label( $state )
		);
		$embed_ok = ( 'ok' === $state );
		self::status_row(
			$embed_ok ? 'ok' : '',
			'external',
			__( '样式链接', 'wpfonts' ),
			$embed_ok ? __( '前台会加载文风服务器上的样式链接。', 'wpfonts' ) : __( '前台不会加载远程样式链接。', 'wpfonts' ),
			$embed_ok ? 'ok' : 'mute',
			$embed_ok ? __( '会加载', 'wpfonts' ) : __( '未加载', 'wpfonts' )
		);
		echo '<p class="meta">' . esc_html__( '字体上传在文风控制台完成，插件不处理上传。', 'wpfonts' ) . '</p>';
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
			__( '系统字体无需配置。CDN 默认关闭，开启并保存后生效。', 'wpfonts' ),
			$aside
		);

		echo '<div class="wenpai-stack">';
		$note = WPFonts_Settings::pull_note();
		if ( 'rejected' === $note ) {
			self::notice( 'bad', __( '刚才填写的 Key 没有保存。请到控制台重新复制。', 'wpfonts' ) );
		}
		self::render_state_banner( $state );

		echo '<form method="post" action="options.php">';
		settings_fields( 'wpfonts' );
		echo '<input type="hidden" name="wpfonts_settings[surface]" value="' . esc_attr( $mode ) . '">';
		echo '<section class="card">';

		self::status_row( 'ok', 'info', __( '系统字体', 'wpfonts' ), __( '默认使用这项，不依赖 CDN。', 'wpfonts' ), 'ok', __( '可用', 'wpfonts' ) );

		echo '<div class="simple-row">';
		echo '<span class="tile accent">';
		self::icon( 'cloud' );
		echo '</span>';
		echo '<div>';
		echo '<div class="t" id="wpfonts-cdn-t">' . esc_html__( '开启 CDN', 'wpfonts' ) . '</div>';
		echo '<div class="d" id="wpfonts-cdn-d">' . esc_html__( '默认关闭。开启后，前台才会请求文风样式。关闭后不再请求远程字体，系统字体仍然可用。', 'wpfonts' ) . '</div>';
		echo '</div>';
		echo '<div class="r">';
		self::cdn_switch( ! empty( $settings['cdn_enabled'] ) );
		echo '</div></div>';

		self::render_region_field( $settings );
		self::render_subset_field( $settings );
		if ( 'advanced' === $mode ) {
			self::render_key_field( $has_key, $state );
			self::render_family_field( $settings );
			self::render_slug_field( $settings );
		}
		self::render_link_field( $settings, $state, $has_key );

		echo '<div class="simple-row"><div></div><div></div><div class="r">';
		echo '<button type="submit" class="btn btn-primary">' . esc_html__( '保存设置', 'wpfonts' ) . '</button>';
		self::console_link( 'btn btn-secondary' );
		echo '</div></div>';
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
	private static function render_tools( array $settings, $state, $has_key ) {
		Wenpai_Admin_Shell::page_head(
			__( '工具', 'wpfonts' ),
			__( '检查文风 API Key 是否可用，并预览将加载的样式链接。插件不负责上传或切分字体。', 'wpfonts' )
		);
		echo '<div class="wenpai-stack">';
		self::render_state_banner( $state );

		echo '<section class="card">';
		echo '<div class="card-head"><span class="tile warn">';
		self::icon( 'info' );
		echo '</span><div>';
		echo '<h2 class="card-title">' . esc_html__( '健康检查', 'wpfonts' ) . '</h2>';
		echo '<p class="card-sub">' . esc_html__( '在服务器上向文风验证。页面上不会显示 Key。', 'wpfonts' ) . '</p>';
		echo '</div></div>';
		echo '<div class="field"><div class="field-label">' . esc_html__( '请求', 'wpfonts' ) . '</div><div class="field-ctl">';
		echo '<div class="wenpai-code"><code>GET /api/key/status</code></div>';
		echo '<p class="hint">' . esc_html__( '请求发往 app.windfonts.com。三台字体服务器不提供这个地址。', 'wpfonts' ) . '</p>';
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

		echo '<section class="card">';
		echo '<div class="card-head"><span class="tile">';
		self::icon( 'external' );
		echo '</span><div>';
		echo '<h2 class="card-title">' . esc_html__( '样式链接', 'wpfonts' ) . '</h2>';
		echo '<p class="card-sub">' . esc_html__( '只显示计算得到的地址。插件不下载 CSS 正文。', 'wpfonts' ) . '</p>';
		echo '</div></div>';
		self::render_link_preview( $settings, $state, $has_key, false );
		echo '</section>';

		echo '<section class="card">';
		echo '<h2 class="card-title">' . esc_html__( '插件不上传、不切分字体', 'wpfonts' ) . '</h2>';
		echo '<p class="card-sub">' . esc_html__( '字体文件的切分、上传和审核都在文风控制台完成。', 'wpfonts' ) . '</p>';
		self::status_row( '', 'cloud', __( '上传', 'wpfonts' ), __( '插件不提供上传，也不代为传输字体文件。', 'wpfonts' ), 'mute', __( '不做', 'wpfonts' ) );
		self::status_row( '', 'info', __( '字体名单', 'wpfonts' ), __( '名称在控制台查看。插件不拉取目录。', 'wpfonts' ), 'mute', __( '不列出', 'wpfonts' ) );
		self::status_row( 'ok', 'info', __( '系统字体', 'wpfonts' ), __( '无论远程状态如何，系统字体仍然可用。', 'wpfonts' ), 'ok', __( '可用', 'wpfonts' ) );
		echo '<div class="empty">';
		echo '<span class="tile">';
		self::icon( 'info' );
		echo '</span>';
		echo '<p>' . esc_html__( '字体名单不在插件中列出。', 'wpfonts' ) . '</p>';
		echo '<p class="meta">' . esc_html__( '请到文风控制台查看已发布的字体。不要把任意 CSS 地址交给插件下载正文。', 'wpfonts' ) . '</p>';
		self::console_link( 'btn btn-secondary' );
		echo '</div>';
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
			__( '系统字体', 'wpfonts' ),
			__( '无需填写。字体库中的中文、英文、日文和韩文系统字体仍然可用，前台不会因此请求远程字体。', 'wpfonts' )
		);
		self::help_card(
			__( '文风 CDN', 'wpfonts' ),
			__( '默认关闭。开启并保存后，前台只增加一条样式链接。服务器只有 cn.windfonts.com、hk.windfonts.com 和 en.windfonts.com。网页中不会出现 API Key。', 'wpfonts' )
		);
		echo '<section class="card">';
		echo '<h2 class="card-title">' . esc_html__( '本版不做', 'wpfonts' ) . '</h2>';
		echo '<p>' . esc_html__( '插件不上传、不切分字体。块主题的全局样式，以及把字体名写入 theme.json，本版也不做。', 'wpfonts' ) . '</p>';
		echo '<p>';
		self::console_link( 'btn btn-secondary' );
		echo '</p>';
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
	 * @return void
	 */
	private static function render_state_banner( $state ) {
		if ( 'empty' === $state ) {
			echo '<div class="next"><span class="tile">';
			self::icon( 'info' );
			echo '</span><div><strong>' . esc_html__( '网页字体尚未开启', 'wpfonts' ) . '</strong>';
			echo '<div class="d">' . esc_html__( '当前使用系统字体，前台不会请求远程字体。需要文风字体时，请开启 CDN 并填写文风 API Key。', 'wpfonts' ) . '</div></div>';
			echo '<div class="next-actions">';
			echo '<a class="btn btn-primary" href="' . esc_url( self::page_url( array( 'tab' => 'settings' ) ) ) . '">' . esc_html__( '去设置', 'wpfonts' ) . '</a>';
			self::console_link( 'btn btn-ghost' );
			echo '</div></div>';
			return;
		}
		$level = 'warn';
		$text  = __( '文风 API Key 尚未验证。请到工具中再检查一次。验证通过前，前台不加载样式。', 'wpfonts' );
		if ( 'nokey' === $state ) {
			$text = __( 'CDN 已开启，但尚未填写文风 API Key。请到高级中填写。前台暂不加载远程样式。', 'wpfonts' );
		} elseif ( 'invalid' === $state ) {
			$level = 'bad';
			$text  = __( '文风 API Key 无效。前台不会加载网页字体。系统字体仍然可用。', 'wpfonts' );
		} elseif ( 'unreachable' === $state ) {
			$text = __( '这次未能连接。暂不判定 Key 无效。系统字体仍然可用，请稍后再检查。', 'wpfonts' );
		} elseif ( 'incomplete' === $state ) {
			$text = __( '文风 API Key 可用。还需要字体名或项目名，前台暂不加载样式。', 'wpfonts' );
		} elseif ( 'ok' === $state ) {
			$level = 'ok';
			$text  = __( 'CDN 已开启，文风 API Key 可用。前台会加载文风服务器上的样式，网页中不会出现 Key。', 'wpfonts' );
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
		echo '<div class="notice ' . esc_attr( $level ) . '">';
		self::icon( 'ok' === $level ? 'info' : 'info' );
		echo '<span>' . esc_html( $text ) . '</span>';
		echo '</div>';
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
		echo '<div class="simple-row is-static">';
		echo '<span class="tile ' . esc_attr( $tile ) . '">';
		self::icon( $icon );
		echo '</span><div>';
		echo '<div class="t">' . esc_html( $title ) . '</div>';
		echo '<div class="d">' . esc_html( $desc ) . '</div>';
		echo '</div><div class="r"><span class="pill ' . esc_attr( $pill ) . '">' . esc_html( $label ) . '</span></div>';
		echo '</div>';
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
		echo '<p class="field-help">' . esc_html__( '选择文风服务器。只有这三台。', 'wpfonts' ) . '</p>';
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
		$choices = array(
			'zh-common' => __( 'zh-common · 常用汉字（建议）', 'wpfonts' ),
			'zh'        => __( 'zh · 更多汉字', 'wpfonts' ),
			'en'        => __( 'en · 英文', 'wpfonts' ),
			'full'      => __( 'full · 整套', 'wpfonts' ),
		);
		echo '<div class="field"';
		self::cdn_depends();
		echo '>';
		echo '<label class="field-label" for="wpfonts-subset">' . esc_html__( '字符范围', 'wpfonts' ) . '</label>';
		echo '<div class="field-ctl">';
		echo '<select id="wpfonts-subset" name="wpfonts_settings[subset]">';
		foreach ( $choices as $id => $label ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $settings['subset'], $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="hint">' . esc_html__( '建议使用 zh-common。插件不在这里切分字体，名称以控制台已发布的为准。', 'wpfonts' ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * Masked API key. Empty submit keeps the saved value.
	 *
	 * @param bool   $has_key Whether a key is stored.
	 * @param string $state   View state.
	 * @return void
	 */
	private static function render_key_field( $has_key, $state ) {
		echo '<div class="field"';
		self::cdn_depends();
		echo '>';
		echo '<label class="field-label" for="wpfonts-api-key">' . esc_html__( '文风 API Key', 'wpfonts' ) . '</label>';
		echo '<div class="field-ctl">';
		$placeholder = $has_key ? __( '留空表示不修改', 'wpfonts' ) : __( '粘贴文风 API Key', 'wpfonts' );
		echo '<input type="password" id="wpfonts-api-key" name="wpfonts_settings[api_key]" value="" autocomplete="new-password" spellcheck="false" placeholder="' . esc_attr( $placeholder ) . '">';
		if ( $has_key ) {
			$mask = WPFonts_Settings::mask( WPFonts_Settings::api_key() );
			if ( 'invalid' === $state ) {
				echo '<p class="hint is-bad">' . esc_html__( '文风 API Key 无效。已保存的 Key 不再完整显示，请到控制台确认后重新粘贴。', 'wpfonts' ) . '</p>';
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
		echo '<div class="field"';
		self::cdn_depends();
		echo '>';
		echo '<label class="field-label" for="wpfonts-family">' . esc_html__( '字体名', 'wpfonts' ) . '</label>';
		echo '<div class="field-ctl">';
		echo '<input type="text" id="wpfonts-family" name="wpfonts_settings[family]" value="' . esc_attr( $settings['family'] ) . '" placeholder="wenfeng-ibmps" autocomplete="off" spellcheck="false">';
		echo '<p class="hint">' . esc_html__( '公开字体地址需要填写。例如 wenfeng-ibmps。填写了项目名后可以留空。', 'wpfonts' ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * Optional project slug.
	 *
	 * @param array $settings Public settings.
	 * @return void
	 */
	private static function render_slug_field( array $settings ) {
		echo '<div class="field"';
		self::cdn_depends();
		echo '>';
		echo '<label class="field-label" for="wpfonts-slug">' . esc_html__( '项目名', 'wpfonts' ) . '</label>';
		echo '<div class="field-ctl">';
		echo '<input type="text" id="wpfonts-slug" name="wpfonts_settings[project_slug]" value="' . esc_attr( $settings['project_slug'] ) . '" placeholder="studio" autocomplete="off" spellcheck="false">';
		echo '<p class="hint">' . esc_html__( '可以留空。填写后使用该区域的 /p/项目名/index.css。可使用的网站在控制台设置。', 'wpfonts' ) . '</p>';
		echo '</div></div>';
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
		if ( ! WPFonts_Link::has_target( $settings ) ) {
			self::notice( 'warn', __( '还不能放到前台。请填写字体名，或填写项目名。', 'wpfonts' ) );
			echo '<p class="hint">' . esc_html__( '公开字体地址需要字体名。未填写前，不会把无法打开的地址放到网页上。', 'wpfonts' ) . '</p>';
			return;
		}

		$url = WPFonts_Link::stylesheet_url( $settings );
		// Text preview only. esc_html() below keeps the browser from loading it.
		$tag = '<link rel="stylesheet" href="' . $url . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Not a live tag.
		echo '<div class="wenpai-code"><code>' . esc_html( $tag ) . '</code></div>';
		if ( 'ok' === $state ) {
			echo '<p class="hint is-ok">' . esc_html__( '前台会加载这一条。链接中没有 API Key。', 'wpfonts' ) . '</p>';
		} elseif ( 'unreachable' === $state || 'unchecked' === $state ) {
			echo '<p class="hint">' . esc_html__( '尚未验证通过，前台暂不使用。', 'wpfonts' ) . '</p>';
		} elseif ( $in_form && 'empty' === $state ) {
			echo '<p class="hint">' . esc_html__( '开启 CDN 并保存后，前台才会使用。', 'wpfonts' ) . '</p>';
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
			echo '<p class="hint">' . esc_html__( '可能是超时，或服务暂时不可用。这不是 Key 无效。请稍后再检查。系统字体目前仍然可用。', 'wpfonts' ) . '</p>';
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
		if ( 'invalid' === $state ) {
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
			return __( '尚未开启，前台也不会加载样式。', 'wpfonts' );
		}
		if ( 'nokey' === $state ) {
			return __( '已开启，但尚未填写文风 API Key。', 'wpfonts' );
		}
		if ( 'invalid' === $state ) {
			return __( '文风 API Key 无效，前台不加载远程样式。', 'wpfonts' );
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
		if ( 'invalid' === $state ) {
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
		return __( '已开启', 'wpfonts' );
	}
}

WPFonts_Admin::init();
