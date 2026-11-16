<?php
/**
 * Monthly unique-visitor count for CDN font loads, with a soft reminder.
 *
 * The browser fetches the stylesheet straight from the Windfonts CDN, so
 * the server cannot see real CDN traffic. What it can see is one front-end
 * page render that printed a font stylesheet. A month-scoped cookie marks
 * each visitor once, so the option only grows about once per visitor per
 * month. Counts are a reminder signal, not billing data.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts and reports front-end font usage.
 */
class WPFonts_Usage {

	const OPTION  = 'wpfonts_usage';
	const COOKIE  = 'wpfonts_uv';
	const LIMIT   = 50000;
	const MONTHLY = 40 * DAY_IN_SECONDS;

	/**
	 * Hooks: admin notice plus the dismiss handler.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss' ) );
		add_action( 'wp', array( __CLASS__, 'record_front' ) );
	}

	/**
	 * Count one front-end visit when a CDN stylesheet will print.
	 *
	 * Runs on `wp`, before any theme output, so headers_sent() is still
	 * false and the month cookie can be written. Mirrors the context
	 * WPFonts_Embed::enqueue() uses for front_stylesheets().
	 *
	 * @return void
	 */
	public static function record_front() {
		if ( isset( $_GET['wpfonts_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preview requests are not counted.
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
		if ( array() === WPFonts_Link::front_stylesheets( $settings, $html, $globals ) ) {
			return;
		}
		self::record();
	}

	/**
	 * Record one front-end visit for the current month.
	 *
	 * Called from the embed enqueue only when a CDN stylesheet will print.
	 * The cookie short-circuits repeat page views; full-page caches may
	 * undercount, which is acceptable for a reminder.
	 *
	 * @return void
	 */
	public static function record() {
		if ( is_admin() || headers_sent() || ! function_exists( 'get_option' ) ) {
			return;
		}
		$month  = gmdate( 'Y-m' );
		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only visit marker.
		if ( $cookie === $month ) {
			return;
		}
		$row = get_option( self::OPTION );
		if ( ! is_array( $row ) || ! isset( $row['month'] ) || $row['month'] !== $month ) {
			$row = array(
				'month'    => $month,
				'visitors' => 0,
			);
		}
		$row['visitors'] = (int) $row['visitors'] + 1;
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, $row, '', 'no' );
		} else {
			update_option( self::OPTION, $row );
		}
		$secure = is_ssl();
		$domain = defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
		setcookie( self::COOKIE, $month, time() + self::MONTHLY, '/', $domain, $secure, true ); // phpcs:ignore WordPress.WP.Capabilities -- Public visit marker, no capability needed.
	}

	/**
	 * Usage row for the current month.
	 *
	 * @return array{month:string,visitors:int}
	 */
	public static function get() {
		$month = gmdate( 'Y-m' );
		$row   = get_option( self::OPTION );
		if ( is_array( $row ) && isset( $row['month'] ) && $row['month'] === $month && isset( $row['visitors'] ) ) {
			return array(
				'month'    => $month,
				'visitors' => (int) $row['visitors'],
			);
		}
		return array(
			'month'    => $month,
			'visitors' => 0,
		);
	}

	/**
	 * Whether this month passed the reminder line.
	 *
	 * @return bool
	 */
	public static function over_limit() {
		return self::get()['visitors'] > self::LIMIT;
	}

	/**
	 * Dismiss link handler. One dismissal covers the current month only.
	 *
	 * @return void
	 */
	public static function maybe_dismiss() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only dismiss flag behind a nonce below.
		if ( empty( $_GET['wpfonts_usage_dismiss'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'wpfonts_usage_dismiss' );
		update_user_meta( get_current_user_id(), 'wpfonts_usage_dismiss', gmdate( 'Y-m' ) );
		wp_safe_redirect( remove_query_arg( 'wpfonts_usage_dismiss' ) );
		exit;
	}

	/**
	 * Site-wide reminder once monthly unique visitors pass the line.
	 *
	 * Only nags while no valid API Key is stored: a keyed site is on the
	 * platform quota path already.
	 *
	 * @return void
	 */
	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! self::over_limit() ) {
			return;
		}
		$settings = WPFonts_Settings::get();
		if ( empty( $settings['cdn_enabled'] ) || 'valid' === (string) ( $settings['key_status'] ?? '' ) ) {
			return;
		}
		$month = gmdate( 'Y-m' );
		if ( get_user_meta( get_current_user_id(), 'wpfonts_usage_dismiss', true ) === $month ) {
			return;
		}
		$count    = self::get()['visitors'];
		$packages = admin_url( 'options-general.php?page=wpfonts&tab=tools&section=packages' );
		$notice   = sprintf(
			/* translators: 1: estimated monthly visitor count, 2: reminder line. */
			__( '本月约有 %1$s 位访客加载了文风字体，超过 %2$s/月 的提醒参考线。', 'wpfonts' ),
			number_format_i18n( $count ),
			number_format_i18n( self::LIMIT )
		);
		$notice .= ' ' . __( '建议到「工具 → 字体包」下载安装本地字体包（自托管，不限量）；用量大时也可以联系文风平台，把本站加入域名白名单。', 'wpfonts' );
		$dismiss = wp_nonce_url( add_query_arg( 'wpfonts_usage_dismiss', '1' ), 'wpfonts_usage_dismiss' );
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a> · <a href="%s" target="_blank" rel="noopener noreferrer">%s</a> · <a href="%s">%s</a></p></div>',
			esc_html( $notice ),
			esc_url( $packages ),
			esc_html__( '查看字体包', 'wpfonts' ),
			esc_url( 'https://app.windfonts.com/' ),
			esc_html__( '文风平台', 'wpfonts' ),
			esc_url( $dismiss ),
			esc_html__( '本月不再提醒', 'wpfonts' )
		);
	}

	/**
	 * One-line usage summary for the tools page.
	 *
	 * @return string Already translated HTML-free text.
	 */
	public static function summary_text() {
		$usage = self::get();
		return sprintf(
			/* translators: 1: estimated monthly visitor count, 2: reminder line. */
			__( '本月约有 %1$s 位访客加载了文风字体（按访客 Cookie 估算，提醒线 %2$s/月）。', 'wpfonts' ),
			number_format_i18n( $usage['visitors'] ),
			number_format_i18n( self::LIMIT )
		);
	}
}

WPFonts_Usage::init();
