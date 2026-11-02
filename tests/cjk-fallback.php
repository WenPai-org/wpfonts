<?php
/**
 * Chinese fallback: stack append, stylesheet rewrite, and option save.
 *
 * No WordPress and no network.
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['wpfonts_options']    = array();
$GLOBALS['wpfonts_hooks']      = array();
$GLOBALS['wpfonts_styles']     = array();
$GLOBALS['wpfonts_is_admin']   = false;
$GLOBALS['wpfonts_global_css'] = '';

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted_args Args.
 * @return void
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wpfonts_hooks'][] = array( 'action', $hook, $callback, $priority, $accepted_args );
}

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted_args Args.
 * @return void
 */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wpfonts_hooks'][] = array( 'filter', $hook, $callback, $priority, $accepted_args );
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
 * @param string $name Option.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	if ( array_key_exists( $name, $GLOBALS['wpfonts_options'] ) ) {
		return $GLOBALS['wpfonts_options'][ $name ];
	}
	return $default;
}

/**
 * @param string $name Option.
 * @param mixed  $value Value.
 * @param mixed  $autoload Autoload.
 * @return bool
 */
function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	if ( 'wpfonts_settings' === $name && class_exists( 'WPFonts_Settings', false ) ) {
		$value = WPFonts_Settings::sanitize( $value );
	}
	$GLOBALS['wpfonts_options'][ $name ] = $value;
	return true;
}

/**
 * @return bool
 */
function is_admin() {
	return ! empty( $GLOBALS['wpfonts_is_admin'] );
}

/**
 * @param mixed $types Stylesheet types.
 * @return string
 */
function wp_get_global_stylesheet( $types = array() ) {
	unset( $types );
	$css = isset( $GLOBALS['wpfonts_global_css'] ) ? (string) $GLOBALS['wpfonts_global_css'] : '';
	return WPFonts_CJK::filter_global_stylesheet( $css );
}

/**
 * @param string $handle Handle.
 * @param string $status Status.
 * @return bool
 */
function wp_style_is( $handle, $status = 'enqueued' ) {
	unset( $status );
	return isset( $GLOBALS['wpfonts_styles'][ $handle ] );
}

/**
 * @param string $handle Handle.
 * @param mixed  $src Source.
 * @param array  $deps Dependencies.
 * @param mixed  $ver Version.
 * @return bool
 */
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
	unset( $deps );
	$GLOBALS['wpfonts_styles'][ $handle ] = array(
		'src' => $src,
		'ver' => $ver,
		'css' => '',
	);
	return true;
}

/**
 * @param string $handle Handle.
 * @param string $src Source.
 * @param array  $deps Dependencies.
 * @param mixed  $ver Version.
 * @return void
 */
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	unset( $src, $deps, $ver );
	if ( ! isset( $GLOBALS['wpfonts_styles'][ $handle ] ) ) {
		$GLOBALS['wpfonts_styles'][ $handle ] = array(
			'src' => false,
			'ver' => false,
			'css' => '',
		);
	}
}

/**
 * @param string $handle Handle.
 * @param string $css CSS.
 * @return bool
 */
function wp_add_inline_style( $handle, $css ) {
	if ( ! isset( $GLOBALS['wpfonts_styles'][ $handle ] ) ) {
		$GLOBALS['wpfonts_styles'][ $handle ] = array(
			'src' => false,
			'ver' => false,
			'css' => '',
		);
	}
	$GLOBALS['wpfonts_styles'][ $handle ]['css'] .= $css;
	return true;
}

require dirname( __DIR__ ) . '/includes/class-wpfonts-link.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-packages.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-settings.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-library.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-cjk.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-global-styles.php';

$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$family = 'Source Han Sans SC';
$literal = '.has-expletus-sans-font-family{font-family:"Expletus Sans",system-ui !important;}';
$rewritten = WPFonts_CJK::rewrite_stylesheet( $literal, $family );
$assert(
	false !== strpos( $rewritten, '"Expletus Sans", "Source Han Sans SC", system-ui !important' ),
	'literal has-* stack did not gain the Chinese family before system-ui'
);
$assert(
	false !== strpos( $rewritten, '.has-expletus-sans-font-family' ),
	'has-* selector was dropped'
);
$again = WPFonts_CJK::rewrite_stylesheet( $rewritten, $family );
$assert( $again === $rewritten, 'rewriting the same sheet inserted the family twice' );

$face = '@font-face{font-family:"Expletus Sans";src:url("expletus.woff2");}';
$with_face = $face . $literal;
$kept = WPFonts_CJK::rewrite_stylesheet( $with_face, $family );
$assert( false !== strpos( $kept, $face ), '@font-face family was rewritten' );
$assert( 1 === substr_count( $kept, 'Source Han Sans SC' ), '@font-face received the Chinese family' );

$variable = ':root{--wp--preset--font-family--expletus-sans:"Expletus Sans", system-ui;}'
	. '.has-expletus-sans-font-family{font-family:var(--wp--preset--font-family--expletus-sans) !important;}';
$var_out = WPFonts_CJK::rewrite_stylesheet( $variable, $family );
$assert(
	false !== strpos( $var_out, '--wp--preset--font-family--expletus-sans:"Expletus Sans", "Source Han Sans SC", system-ui;' ),
	'preset variable was not rewritten'
);
$assert(
	false !== strpos( $var_out, 'font-family:var(--wp--preset--font-family--expletus-sans) !important' ),
	'a preset variable declaration was expanded inside the global stylesheet'
);

$overrides = WPFonts_CJK::has_font_family_overrides( $variable, $family );
$assert(
	false !== strpos( $overrides, '.has-expletus-sans-font-family{font-family:"Expletus Sans", "Source Han Sans SC", system-ui !important}' ),
	'plugin copy of has-* did not append the Chinese family'
);

$body_sheet = 'body{font-family:"Libre Baskerville",serif;}h1,h2,h3,h4,h5,h6{font-family:Georgia,serif;}';
$body_out   = WPFonts_CJK::rewrite_stylesheet( $body_sheet, $family );
$assert(
	false !== strpos( $body_out, '"Libre Baskerville", "Source Han Sans SC", serif' ),
	'body stack was not appended'
);
$assert(
	false !== strpos( $body_out, 'Georgia, "Source Han Sans SC", serif' ),
	'heading stack was not appended'
);

$long = 'font-family:Inter, "Helvetica Neue", Arial, sans-serif;';
$long_out = WPFonts_CJK::append_family( 'Inter, "Helvetica Neue", Arial, sans-serif', $family );
$assert(
	'Inter, "Helvetica Neue", Arial, "Source Han Sans SC", sans-serif' === $long_out,
	'the family was not inserted before sans-serif: ' . $long_out
);
unset( $long );

$inherit = WPFonts_CJK::append_family( 'inherit', $family );
$assert( 'inherit' === $inherit, 'inherit was replaced' );

$present = WPFonts_CJK::append_family( '"Expletus Sans", "Source Han Sans SC", system-ui', $family );
$assert(
	'"Expletus Sans", "Source Han Sans SC", system-ui' === $present,
	'an existing family was inserted again'
);

$empty = WPFonts_CJK::rewrite_stylesheet( $literal, '' );
$assert( $literal === $empty, 'an empty family still rewrote the sheet' );

$roles = array(
	'fallback'         => $family,
	'body'             => $family,
	'heading'          => $family,
	'body_override'    => false,
	'heading_override' => false,
);
$css = WPFonts_CJK::compose_css( $roles, $variable );
$assert( false !== strpos( $css, ':where(body,' ), 'body assignment was omitted' );
$assert( false !== strpos( $css, ':where(h1,h2,h3,h4,h5,h6,' ), 'heading assignment was omitted' );
$assert( false !== strpos( $css, '.has-expletus-sans-font-family{font-family:' ), 'has-* override was omitted' );
$assert( false === strpos( $css, '[class*="-font-family"]' ), 'the shared fallback replaced heading classes' );
$assert( false !== strpos( $css, 'Source Han Sans SC' ), 'composed CSS dropped the family' );

$override_roles = $roles;
$override_roles['body']          = '正文宋';
$override_roles['body_override'] = true;
$override_css = WPFonts_CJK::compose_css( $override_roles, '' );
$assert( false !== strpos( $override_css, 'body,.wp-site-blocks' ), 'an explicit body family was not assigned' );
$assert( false !== strpos( $override_css, '"正文宋",sans-serif !important' ), 'explicit body assignment lost !important' );
$assert( false === strpos( $override_css, ':where(body,' ), 'explicit body assignment stayed at zero specificity' );

$assert( '' === WPFonts_CJK::clean_choice( 'WF-Source' ), 'WF-* was accepted as a choice' );
$assert( '' === WPFonts_CJK::clean_choice( 'wenfeng-ibmps' ), 'a bare family token was stored' );
$assert( 'pkg:wenfeng-fzss' === WPFonts_CJK::clean_choice( 'pkg:wenfeng-fzss' ), 'package choice was dropped' );
$assert( 'cloud:wenfeng-ibmps' === WPFonts_CJK::clean_choice( 'CLOUD:wenfeng-ibmps' ), 'cloud choice was not normalized' );
$assert( '' === WPFonts_CJK::clean_choice( 'pkg:' ), 'an empty package id was stored' );

$applied = WPFonts_CJK::apply_settings(
	array(
		'cjk_fallback' => 'pkg:keep',
		'cjk_body'     => '',
		'cjk_heading'  => '',
	),
	array(),
	array(
		'cjk_form'     => '1',
		'cjk_fallback' => 'cloud:wenfeng-ibmps',
		'cjk_body'     => 'not a choice',
		'cjk_heading'  => 'pkg:wenfeng-fzss',
	)
);
$assert( 'cloud:wenfeng-ibmps' === $applied['cjk_fallback'], 'form save dropped the fallback' );
$assert( '' === $applied['cjk_body'], 'garbage body choice was stored' );
$assert( 'pkg:wenfeng-fzss' === $applied['cjk_heading'], 'heading choice was dropped' );

$kept_settings = WPFonts_CJK::apply_settings(
	array(
		'cjk_fallback' => 'pkg:wenfeng-fzss',
		'cjk_body'     => 'cloud:wenfeng-ibmps',
		'cjk_heading'  => '',
	),
	array(),
	array()
);
$assert( 'pkg:wenfeng-fzss' === $kept_settings['cjk_fallback'], 'a save without the Chinese form cleared the fallback' );
$assert( 'cloud:wenfeng-ibmps' === $kept_settings['cjk_body'], 'a save without the Chinese form cleared the body' );

$GLOBALS['wpfonts_options']['wpfonts_packages'] = array(
	array(
		'id'      => 'wenfeng-fzss',
		'name'    => '文风书宋',
		'family'  => 'wenfeng-fzss',
		'enabled' => 1,
	),
	array(
		'id'      => 'paused',
		'name'    => '停用',
		'family'  => '停用体',
		'enabled' => 0,
	),
);
$GLOBALS['wpfonts_options']['wpfonts_settings'] = array(
	'cdn_enabled'  => 1,
	'key_status'   => 'valid',
	'cjk_fallback' => 'pkg:wenfeng-fzss',
	'cjk_body'     => '',
	'cjk_heading'  => 'cloud:wenfeng-ibmps',
	'faces'        => array(
		array(
			'family'   => 'wenfeng-ibmps',
			'name'     => 'IBM',
			'css_face' => 'windfonts-ibmps',
			'subset'   => 'zh-common',
		),
		array(
			'family'   => 'wenfeng-missing',
			'name'     => '未确认',
			'css_face' => '',
			'subset'   => 'zh-common',
		),
	),
);

$choices = WPFonts_CJK::choices();
$values  = array();
foreach ( $choices as $row ) {
	$values[ $row['value'] ] = $row['css'];
}
$assert( isset( $values['pkg:wenfeng-fzss'] ) && 'wenfeng-fzss' === $values['pkg:wenfeng-fzss'], 'enabled package was not a choice' );
$assert( ! isset( $values['pkg:paused'] ), 'a disabled package was offered' );
$assert( isset( $values['cloud:wenfeng-ibmps'] ) && 'windfonts-ibmps' === $values['cloud:wenfeng-ibmps'], 'confirmed cloud face did not use css_face' );
$assert( ! isset( $values['cloud:wenfeng-missing'] ), 'an unconfirmed cloud face was offered' );

$resolved = WPFonts_CJK::resolved();
$assert( 'wenfeng-fzss' === $resolved['fallback'], 'fallback did not resolve to the package family' );
$assert( 'wenfeng-fzss' === $resolved['body'], 'empty body did not follow the fallback' );
$assert( 'windfonts-ibmps' === $resolved['heading'], 'heading did not resolve to the cloud css face' );
$assert( empty( $resolved['body_override'] ), 'inherited body was treated as its own font' );
$assert( ! empty( $resolved['heading_override'] ), 'a different heading was not an override' );

$saved = WPFonts_Settings::get();
$assert( 'pkg:wenfeng-fzss' === $saved['cjk_fallback'], 'get() dropped the fallback' );
$assert( 'cloud:wenfeng-ibmps' === $saved['cjk_heading'], 'get() dropped the heading' );
WPFonts_Settings::write( $saved );
$round = WPFonts_Settings::get();
$assert( 'pkg:wenfeng-fzss' === $round['cjk_fallback'], 'write() cleared the fallback' );
$assert( 'cloud:wenfeng-ibmps' === $round['cjk_heading'], 'write() cleared the heading' );
$assert( '' === $round['cjk_body'], 'write() invented a body choice' );

$GLOBALS['wpfonts_options']['wpfonts_settings']['cjk_fallback'] = 'pkg:not-installed';
$assert( '' === WPFonts_CJK::css_family( 'pkg:not-installed' ), 'a missing package still produced CSS' );
$assert( '' === WPFonts_CJK::resolved()['fallback'], 'an unavailable fallback was still appended' );
$GLOBALS['wpfonts_options']['wpfonts_settings']['cjk_body']     = '';
$GLOBALS['wpfonts_options']['wpfonts_settings']['cjk_heading']  = '';
$GLOBALS['wpfonts_options']['wpfonts_settings']['cjk_fallback'] = 'pkg:not-installed';
$assert( '' === WPFonts_CJK::frontend_css( $literal ), 'unavailable choices still emitted CSS' );

$assert(
	false === WPFonts_Global_Styles::settings_need_sync(
		array( 'cjk_fallback' => '' ),
		array( 'cjk_fallback' => 'pkg:wenfeng-fzss' )
	),
	'changing the Chinese fallback asked Global Styles to sync'
);

$filtered = WPFonts_CJK::filter_global_stylesheet( $literal );
$assert( $literal === $filtered, 'filter rewrote a sheet when the fallback was unavailable' );

$GLOBALS['wpfonts_options']['wpfonts_settings']['cjk_fallback'] = 'pkg:wenfeng-fzss';
$GLOBALS['wpfonts_options']['wpfonts_settings']['cjk_heading']  = '';
$GLOBALS['wpfonts_global_css'] = $literal;
$live = WPFonts_CJK::frontend_css();
$assert( false !== strpos( $live, '"Expletus Sans", "wenfeng-fzss", system-ui !important' ), 'live stylesheet did not append the package family' );
$assert( false !== strpos( $live, ':where(body,' ), 'live stylesheet omitted body' );
$assert( false !== strpos( $live, ':where(h1,h2,h3,h4,h5,h6,' ), 'live stylesheet omitted headings' );

$filtered_live = WPFonts_CJK::filter_global_stylesheet( $literal );
$assert( false !== strpos( $filtered_live, '"wenfeng-fzss"' ), 'filter ignored an available fallback' );
$assert( false === strpos( $filtered_live, 'theme.json' ), 'filter mentioned theme.json' );

$hooked = false;
foreach ( $GLOBALS['wpfonts_hooks'] as $hook ) {
	if ( 'filter' === $hook[0] && 'wp_get_global_stylesheet' === $hook[1] && 20 === $hook[3] && 2 === $hook[4] ) {
		$hooked = true;
	}
}
$assert( $hooked, 'global stylesheet filter was not registered' );

$GLOBALS['wpfonts_is_admin'] = false;
WPFonts_CJK::enqueue_front();
$assert( isset( $GLOBALS['wpfonts_styles']['wpfonts-cjk'] ), 'front enqueue skipped wpfonts-cjk' );
$assert( false !== strpos( $GLOBALS['wpfonts_styles']['wpfonts-cjk']['css'], 'wenfeng-fzss' ), 'front inline CSS missed the family' );

$reset = new ReflectionProperty( 'WPFonts_CJK', 'enqueued' );
$reset->setAccessible( true );
$reset->setValue( null, false );
$GLOBALS['wpfonts_styles']['wpfonts-cjk']['css'] = '';
$GLOBALS['wpfonts_is_admin'] = true;
WPFonts_CJK::enqueue_editor();
$assert( false !== strpos( $GLOBALS['wpfonts_styles']['wpfonts-cjk']['css'], 'wenfeng-fzss' ), 'editor canvas missed the Chinese family' );
$before = $GLOBALS['wpfonts_styles']['wpfonts-cjk']['css'];
WPFonts_CJK::enqueue_front();
$assert( $before === $GLOBALS['wpfonts_styles']['wpfonts-cjk']['css'], 'front enqueue ran inside the editor and duplicated the CSS' );

$overview = WPFonts_CJK::overview();
$assert( ! empty( $overview['on'] ), 'overview stayed off after a real choice' );
$assert( false !== strpos( $overview['text'], '已指定' ), 'overview copy drifted' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: Chinese fallback appends to Latin stacks and survives an option save.\n";
