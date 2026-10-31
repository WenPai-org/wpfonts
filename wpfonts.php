<?php
/**
 * Plugin Name: WPFonts
 * Plugin URI: https://wpcy.com/wpfonts
 * Description: 中文、英文、日文、韩文系统字体。文风云默认关闭，开启后每条已确认的文风云字体各加载一条样式。
 * Version: 2.0.0-rc.8
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: WPFonts.com
 * Author URI: https://wpcy.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpfonts
 * Domain Path: /languages
 * Update URI: https://updates.wenpai.net
 *
 * System font stacks stay on when the CDN is off. The CDN client is a
 * release candidate: it prints an allowlisted stylesheet link and does not
 * delete Global Styles or uploaded fonts on uninstall. Enabled packages and
 * confirmed cloud faces are copied into the user Global Styles post. Theme
 * files on disk are left alone. Unchecked system-font languages are not
 * registered.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPFONTS_VERSION', '2.0.0-rc.8' );
define( 'WPFONTS_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'WPFONTS_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPFONTS_BASENAME', plugin_basename( __FILE__ ) );

require_once WPFONTS_DIR_PATH . 'lib/wenpai-admin-ui/php/load.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-link.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-health.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-settings.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-catalog.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-packages.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-embed.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-library.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-global-styles.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-editor.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-transfer.php';
require_once WPFONTS_DIR_PATH . 'includes/class-wpfonts-admin.php';

register_activation_hook( __FILE__, array( 'WPFonts_Catalog', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPFonts_Catalog', 'deactivate' ) );
add_filter( 'cron_schedules', array( 'WPFonts_Catalog', 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Interval is WPFonts_Catalog::MONTH_INTERVAL (2592000 seconds, about 30 days) in cron_schedules().
add_action( WPFonts_Catalog::CRON_HOOK, array( 'WPFonts_Catalog', 'run_cron' ) );
add_action( WPFonts_Catalog::BOOTSTRAP_HOOK, array( 'WPFonts_Catalog', 'run_bootstrap' ) );
add_action( 'init', array( 'WPFonts_Catalog', 'ensure_schedule' ) );

/**
 * Load bundled translations.
 *
 * @return void
 */
function wp_fonts_load_textdomain() {
	load_plugin_textdomain( 'wpfonts', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wp_fonts_load_textdomain' );

/**
 * Register local system-font collections.
 *
 * Collection JSON contains font-family stacks only. It does not load remote
 * stylesheets or font binaries. A registration failure is reported through
 * wpfonts_collection_registration_error and does not alter Global Styles.
 *
 * Priority 25 runs after WPFonts_Library (priority 20) so 文风字体 and 文风云
 * appear before these collections in the Font Library.
 *
 * @return void
 */
function wp_fonts_register_collections() {
	if ( ! function_exists( 'wp_register_font_collection' ) ) {
		return;
	}

	$categories = array(
		array(
			'slug' => 'sans-serif',
			'name' => __( 'Sans Serif', 'wpfonts' ),
		),
		array(
			'slug' => 'serif',
			'name' => __( 'Serif', 'wpfonts' ),
		),
		array(
			'slug' => 'monospace',
			'name' => __( 'Monospace', 'wpfonts' ),
		),
		array(
			'slug' => 'handwriting',
			'name' => __( 'Handwriting', 'wpfonts' ),
		),
	);

	$collections = array(
		'wp-fonts-stacks-cn' => array(
			'name'        => __( '系统 · 中文', 'wpfonts' ),
			'description' => __( '设备上已有的中文字体。不下载字体文件。', 'wpfonts' ),
			'file'        => 'chinese.json',
			'system'      => 'cn',
		),
		'wp-fonts-stacks-en' => array(
			'name'        => __( '系统 · 英文', 'wpfonts' ),
			'description' => __( '设备上已有的西文字体。不下载字体文件。', 'wpfonts' ),
			'file'        => 'english.json',
			'system'      => 'en',
		),
		'wp-fonts-stacks-jp' => array(
			'name'        => __( '系统 · 日文', 'wpfonts' ),
			'description' => __( '设备上已有的日文字体。不下载字体文件。', 'wpfonts' ),
			'file'        => 'japanese.json',
			'system'      => 'jp',
		),
		'wp-fonts-stacks-kr' => array(
			'name'        => __( '系统 · 韩文', 'wpfonts' ),
			'description' => __( '设备上已有的韩文字体。不下载字体文件。', 'wpfonts' ),
			'file'        => 'korean.json',
			'system'      => 'kr',
		),
	);

	foreach ( $collections as $slug => $collection ) {
		$system = isset( $collection['system'] ) ? (string) $collection['system'] : '';
		if ( '' !== $system && class_exists( 'WPFonts_Settings', false ) && ! WPFonts_Settings::system_enabled( $system ) ) {
			continue;
		}
		$result = wp_register_font_collection(
			$slug,
			array(
				'name'          => $collection['name'],
				'description'   => $collection['description'],
				'categories'    => $categories,
				'font_families' => path_join( WPFONTS_DIR_PATH, 'collection/' . $collection['file'] ),
			)
		);

		if ( is_wp_error( $result ) ) {
			/**
			 * Fires when a WPFonts collection cannot be registered.
			 *
			 * @param WP_Error $result Registration error.
			 * @param string   $slug   Collection slug.
			 */
			do_action( 'wpfonts_collection_registration_error', $result, $slug );
		}
	}
}
add_action( 'init', 'wp_fonts_register_collections', 25 );

/**
 * Load Plugin Update Checker only for wp-admin, WP-Cron, and WP-CLI.
 *
 * Front-end page views skip the library. Metadata stays on
 * updates.weixiaoduo.com; the plugin header Update URI stays on
 * updates.wenpai.net. If the library file is absent, return quietly.
 *
 * @return void
 */
function wp_fonts_initialize_update_checker() {
	$is_wp_cli = defined( 'WP_CLI' ) && WP_CLI;

	if ( ! is_admin() && ! wp_doing_cron() && ! $is_wp_cli ) {
		return;
	}

	$checker = WPFONTS_DIR_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
	if ( ! is_readable( $checker ) ) {
		return;
	}

	require_once $checker;

	\YahnisElsts\PluginUpdateChecker\v5p3\PucFactory::buildUpdateChecker(
		'https://updates.weixiaoduo.com/wpfonts.json',
		__FILE__,
		'wpfonts'
	);
}
add_action( 'plugins_loaded', 'wp_fonts_initialize_update_checker', 20 );
