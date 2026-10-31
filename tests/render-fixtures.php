<?php
/**
 * Render admin HTML fixtures without WordPress.
 *
 * Usage: php tests/render-fixtures.php
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPFONTS_FIXTURE', true );

$GLOBALS['fixture_options'] = array();

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted_args Accepted args.
 * @return void
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
}

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted_args Accepted args.
 * @return void
 */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
}

/**
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * @param string $text Text.
 * @return string
 */
function esc_attr( $text ) {
	return esc_html( $text );
}

/**
 * Fixture stand-in for WordPress esc_js().
 *
 * @param string $text Text.
 * @return string
 */
function esc_js( $text ) {
	return str_replace(
		array( '\\', "'", '"', "\n", "\r" ),
		array( '\\\\', "\\'", '\\"', '\\n', '\\r' ),
		(string) $text
	);
}

/**
 * @param string $url URL.
 * @return string
 */
function esc_url( $url ) {
	$url = trim( (string) $url );
	if ( preg_match( '#^(https?:|/)#i', $url ) ) {
		return esc_attr( $url );
	}
	return '';
}

/**
 * @param string $text Text.
 * @param string $domain Domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

/**
 * @param string $text Text.
 * @param string $domain Domain.
 * @return string
 */
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( __( $text, $domain ) );
}

/**
 * @param string $text Text.
 * @param string $domain Domain.
 * @return string
 */
function esc_attr__( $text, $domain = 'default' ) {
	return esc_attr( __( $text, $domain ) );
}

/**
 * @param string $text Text.
 * @param string $domain Domain.
 * @return void
 */
function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html__( $text, $domain );
}

/**
 * @param mixed  $helper Helper.
 * @param mixed  $current Current.
 * @param bool   $display Echo or return.
 * @param string $type Attribute name.
 * @return string
 */
function wpfonts_checked_selected_helper( $helper, $current, $display, $type ) {
	$result = ( (string) $helper === (string) $current ) ? " $type='$type'" : '';
	if ( $display ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixture helper, fixed attribute.
	}
	return $result;
}

/**
 * @param mixed $checked Checked.
 * @param mixed $current Current.
 * @param bool  $display Echo or return.
 * @return string
 */
function checked( $checked, $current = true, $display = true ) {
	return wpfonts_checked_selected_helper( $checked, $current, $display, 'checked' );
}

/**
 * @param mixed $selected Selected.
 * @param mixed $current Current.
 * @param bool  $display Echo or return.
 * @return string
 */
function selected( $selected, $current = true, $display = true ) {
	return wpfonts_checked_selected_helper( $selected, $current, $display, 'selected' );
}

/**
 * @param mixed $disabled Disabled.
 * @param mixed $current Current.
 * @param bool  $display Echo or return.
 * @return string
 */
function disabled( $disabled, $current = true, $display = true ) {
	return wpfonts_checked_selected_helper( $disabled, $current, $display, 'disabled' );
}

/**
 * @param mixed $args Args.
 * @param array $defaults Defaults.
 * @return array
 */
function wp_parse_args( $args, $defaults = array() ) {
	if ( is_object( $args ) ) {
		$args = get_object_vars( $args );
	}
	if ( ! is_array( $args ) ) {
		$args = array();
	}
	return array_merge( $defaults, $args );
}

/**
 * @param string $key Key.
 * @return string
 */
function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

/**
 * @param string $text Text.
 * @return string
 */
function sanitize_text_field( $text ) {
	return trim( (string) $text );
}

/**
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return $value;
}

/**
 * Fixture stand-in for wp_json_encode().
 *
 * @param mixed $data Data.
 * @param int   $options Options.
 * @param int   $depth Depth.
 * @return string|false
 */
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT, $depth );
}

/**
 * @param string $path Path.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

/**
 * @param array  $args Query args.
 * @param string $url Base URL.
 * @return string
 */
function add_query_arg( $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}

/**
 * @param string $cap Capability.
 * @return bool
 */
function current_user_can( $cap ) {
	unset( $cap );
	return true;
}

/**
 * @param string $message Message.
 * @return void
 */
function wp_die( $message = '' ) {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

/**
 * @param string $group Settings group.
 * @return void
 */
function settings_fields( $group ) {
	echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '">';
}

/**
 * @param string $action Action.
 * @return void
 */
function wp_nonce_field( $action ) {
	echo '<input type="hidden" name="_wpnonce" value="fixture">';
	echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( $action ) . '">';
}

/**
 * @param string $name Option name.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	if ( array_key_exists( $name, $GLOBALS['fixture_options'] ) ) {
		return $GLOBALS['fixture_options'][ $name ];
	}
	return $default;
}

$root = dirname( __DIR__ );
define( 'WPFONTS_VERSION', '2.0.0-rc.3' );
define( 'WPFONTS_DIR_PATH', $root . '/' );
define( 'WPFONTS_DIR_URL', 'https://example.test/wp-content/plugins/wpfonts/' );
define( 'WPFONTS_BASENAME', 'wpfonts/wpfonts.php' );

require $root . '/lib/wenpai-admin-ui/php/load.php';
require $root . '/includes/class-wpfonts-link.php';
require $root . '/includes/class-wpfonts-health.php';
require $root . '/includes/class-wpfonts-settings.php';
require $root . '/includes/class-wpfonts-catalog.php';
require $root . '/includes/class-wpfonts-packages.php';
require $root . '/includes/class-wpfonts-embed.php';
require $root . '/includes/class-wpfonts-admin.php';

/**
 * Wrap a rendered screen in a standalone document.
 *
 * @param string $title Document title.
 * @param string $body  Screen HTML.
 * @return string
 */
function wpfonts_fixture_document( $title, $body ) {
	$css = '../../lib/wenpai-admin-ui/css/';
	$js  = '../../lib/wenpai-admin-ui/js/wenpai-admin.js';
	return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
		. esc_html( $title )
		. '</title><link rel="stylesheet" href="' . $css . 'wenpai-tokens.css"><link rel="stylesheet" href="' . $css . 'wenpai-admin.css"><link rel="stylesheet" href="' . $css . 'wenpai-admin-wp.css"><link rel="stylesheet" href="../../assets/css/wpfonts-admin.css"></head><body class="wenpai-ui-page wpfonts-admin"><div id="wpcontent"><div id="wpbody"><div id="wpbody-content">'
		. $body
		. '</div></div></div><script src="' . $js . '"></script><script src="../../assets/js/wpfonts-admin.js"></script></body></html>';
}

/**
 * A short directory page for settings fixtures.
 *
 * @return array
 */
function wpfonts_sample_catalog() {
	return array(
		'ok'        => true,
		'fonts'     => array(
			array(
				'name'       => '钟齐志莽行书',
				'fontFamily' => 'wenfeng-zhi-mang-xing',
			),
			array(
				'name'       => '寒蝉无机体',
				'fontFamily' => 'wenfeng-hcwjt',
			),
			array(
				'name'       => '975 圆体',
				'fontFamily' => 'wenfeng-975yt',
			),
			array(
				'name'       => 'Aleo',
				'fontFamily' => 'wenfeng-aleo',
			),
			array(
				'name'       => 'Alibaba Sans Thai',
				'fontFamily' => 'wenfeng-alibabasansthai',
			),
			array(
				'name'       => 'Alibaba Sans Viet',
				'fontFamily' => 'wenfeng-alibabasansviet',
			),
		),
		'page'      => 1,
		'pageTotal' => 43,
		'total'     => 853,
	);
}

/**
 * Render one screen.
 *
 * @param array      $options Options.
 * @param string     $tab Tab.
 * @param string     $mode Mode.
 * @param array|null $catalog Catalog payload, or null for the sample page.
 * @return string
 */
function wpfonts_render_screen( array $options, $tab, $mode, $catalog = null ) {
	$GLOBALS['fixture_options']         = $options;
	$GLOBALS['wpfonts_catalog_fixture'] = is_array( $catalog ) ? $catalog : wpfonts_sample_catalog();
	$_GET                               = array(
		'page' => 'wpfonts',
		'tab'  => $tab,
		'mode' => $mode,
	);
	ob_start();
	WPFonts_Admin::render();
	return (string) ob_get_clean();
}

$out = $root . '/fixtures/admin';
if ( ! is_dir( $out ) && ! mkdir( $out, 0777, true ) && ! is_dir( $out ) ) {
	fwrite( STDERR, "Could not create fixtures directory.\n" );
	exit( 1 );
}

$public = array(
	'cdn_enabled'  => 0,
	'region'       => 'cn',
	'subset'       => 'zh-common',
	'project_slug' => '',
	'family'       => '',
	'key_status'   => '',
	'key_checked'  => 0,
);

$screens = array(
	'overview-empty'  => array(
		'title'   => '概览 · 还没开',
		'tab'     => 'overview',
		'mode'    => 'simple',
		'options' => array(
			'wpfonts_settings' => $public,
			'wpfonts_api_key'  => '',
		),
	),
	'settings-ok'     => array(
		'title'   => '设置 · Key 能用',
		'tab'     => 'settings',
		'mode'    => 'advanced',
		'options' => array(
			'wpfonts_settings' => array_merge(
				$public,
				array(
					'cdn_enabled' => 1,
					'family'      => 'wenfeng-ibmps',
					'key_status'  => 'valid',
					'key_checked' => 1700000000,
				)
			),
			'wpfonts_api_key'  => 'wf_aaaaaaaaaaaak7Qm',
		),
	),
	'settings-error'  => array(
		'title'   => '设置 · Key 无效',
		'tab'     => 'settings',
		'mode'    => 'advanced',
		'options' => array(
			'wpfonts_settings' => array_merge(
				$public,
				array(
					'cdn_enabled' => 1,
					'family'      => 'wenfeng-ibmps',
					'key_status'  => 'invalid',
					'key_checked' => 1700000000,
				)
			),
			'wpfonts_api_key'  => 'wf_bbbbbbbbbbbbbb99',
		),
	),
	'tools-degraded'  => array(
		'title'   => '工具 · 没连上',
		'tab'     => 'tools',
		'mode'    => 'simple',
		'options' => array(
			'wpfonts_settings' => array_merge(
				$public,
				array(
					'cdn_enabled' => 1,
					'family'      => 'wenfeng-ibmps',
					'key_status'  => 'unreachable',
					'key_checked' => 1700000000,
				)
			),
			'wpfonts_api_key'  => 'wf_aaaaaaaaaaaak7Qm',
		),
	),
	'packages-empty'  => array(
		'title'   => '字体包 · 空',
		'tab'     => 'packages',
		'mode'    => 'simple',
		'options' => array(
			'wpfonts_settings' => $public,
			'wpfonts_api_key'  => '',
			'wpfonts_packages' => array(),
		),
	),
	'packages-one'    => array(
		'title'   => '字体包 · 已安装',
		'tab'     => 'packages',
		'mode'    => 'simple',
		'options' => array(
			'wpfonts_settings' => $public,
			'wpfonts_api_key'  => '',
			'wpfonts_packages' => array(
				array(
					'id'           => 'wenfeng-ibmps',
					'name'         => 'wenfeng-ibmps',
					'family'       => 'IBM Plex Sans SC',
					'enabled'      => 1,
					'installed_at' => 1700000000,
				),
			),
		),
	),
	'overview-with-local' => array(
		'title'   => '概览 · 文风字体包使用中',
		'tab'     => 'overview',
		'mode'    => 'simple',
		'options' => array(
			'wpfonts_settings' => $public,
			'wpfonts_api_key'  => '',
			'wpfonts_packages' => array(
				array(
					'id'           => 'wenfeng-ibmps',
					'name'         => 'wenfeng-ibmps',
					'family'       => 'IBM Plex Sans SC',
					'enabled'      => 1,
					'installed_at' => 1700000000,
				),
			),
		),
	),
	'settings-catalog' => array(
		'title'   => '设置 · 选用字体',
		'tab'     => 'settings',
		'mode'    => 'advanced',
		'options' => array(
			'wpfonts_settings' => array_merge(
				$public,
				array(
					'cdn_enabled' => 1,
					'family'      => 'wenfeng-zhi-mang-xing',
					'subset'      => 'zh-common',
					'key_status'  => 'valid',
					'key_checked' => 1700000000,
				)
			),
			'wpfonts_api_key'  => 'wf_aaaaaaaaaaaak7Qm',
		),
		'catalog' => wpfonts_sample_catalog(),
	),
	'settings-catalog-fail' => array(
		'title'   => '设置 · 目录读不到',
		'tab'     => 'settings',
		'mode'    => 'advanced',
		'options' => array(
			'wpfonts_settings' => array_merge(
				$public,
				array(
					'cdn_enabled' => 1,
					'family'      => 'wenfeng-ibmps',
					'subset'      => 'zh-common',
					'key_status'  => 'valid',
					'key_checked' => 1700000000,
				)
			),
			'wpfonts_api_key'  => 'wf_aaaaaaaaaaaak7Qm',
		),
		'catalog' => array(
			'ok'    => false,
			'fonts' => array(),
			'code'  => 0,
		),
	),
);

foreach ( $screens as $name => $screen ) {
	$html = wpfonts_fixture_document(
		$screen['title'],
		wpfonts_render_screen(
			$screen['options'],
			$screen['tab'],
			$screen['mode'],
			isset( $screen['catalog'] ) ? $screen['catalog'] : null
		)
	);
	$path = $out . '/' . $name . '.html';
	file_put_contents( $path, $html );
	echo $path . "\n";
}
