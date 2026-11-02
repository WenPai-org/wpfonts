<?php
/**
 * Font Library collection shape. No WordPress, no network, no theme.json writes.
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$catalog_dir = sys_get_temp_dir() . '/wpfonts-library-' . getmypid();
if ( ! is_dir( $catalog_dir ) && ! mkdir( $catalog_dir, 0777, true ) && ! is_dir( $catalog_dir ) ) {
	fwrite( STDERR, "Could not create library temp dir.\n" );
	exit( 1 );
}
define( 'WPFONTS_CATALOG_PATH', $catalog_dir . '/catalog.json' );

$upload_dir = $catalog_dir . '/uploads';
if ( ! is_dir( $upload_dir ) && ! mkdir( $upload_dir, 0777, true ) && ! is_dir( $upload_dir ) ) {
	fwrite( STDERR, "Could not create upload temp dir.\n" );
	exit( 1 );
}

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted_args Accepted args.
 * @return void
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wpfonts_actions'][] = array( $hook, $callback, $priority, $accepted_args );
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
 * @param mixed $thing Thing.
 * @return bool
 */
function is_wp_error( $thing ) {
	unset( $thing );
	return false;
}

/**
 * @param string $name Option name.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	if ( isset( $GLOBALS['wpfonts_options'][ $name ] ) ) {
		return $GLOBALS['wpfonts_options'][ $name ];
	}
	return $default;
}

/**
 * @return array
 */
function wp_upload_dir() {
	$basedir = isset( $GLOBALS['wpfonts_upload_dir'] ) ? $GLOBALS['wpfonts_upload_dir'] : '';
	return array(
		'basedir' => $basedir,
		'baseurl' => 'https://example.test/wp-content/uploads',
		'error'   => false,
	);
}

/**
 * @param string $path Path.
 * @return string
 */
function wp_normalize_path( $path ) {
	return str_replace( '\\', '/', (string) $path );
}

/**
 * @param mixed $data Data.
 * @param int   $options Options.
 * @param int   $depth Depth.
 * @return string|false
 */
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

$GLOBALS['wpfonts_actions'] = array();

/**
 * In-memory stand-in for the font library registry.
 */
class WP_Font_Library {

	/**
	 * @var self|null
	 */
	private static $instance;

	/**
	 * @var array<string,array>
	 */
	public $slugs = array();

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @param string $slug Slug.
	 * @return string|null
	 */
	public function get_font_collection( $slug ) {
		return isset( $this->slugs[ $slug ] ) ? $slug : null;
	}
}

/**
 * @param string $slug Slug.
 * @param array  $args Args.
 * @return bool
 */
function wp_register_font_collection( $slug, $args ) {
	WP_Font_Library::get_instance()->slugs[ $slug ] = $args;
	$GLOBALS['wpfonts_registered'][]                 = array( $slug, $args );
	return true;
}

/**
 * @param string $slug Slug.
 * @return bool
 */
function wp_unregister_font_collection( $slug ) {
	unset( WP_Font_Library::get_instance()->slugs[ $slug ] );
	$GLOBALS['wpfonts_unregistered'][] = $slug;
	return true;
}

require dirname( __DIR__ ) . '/includes/class-wpfonts-link.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-packages.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-catalog.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-settings.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-library.php';

$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$css = '@font-face{font-family:"IBM Plex Sans SC";font-style:normal;font-weight:400;font-display:swap;unicode-range:U+4E00-9FFF, U+3000-303F;src:url("https://example.test/wp-content/uploads/wpfonts/packages/demo/1.woff2") format("woff2");}'
	. '@font-face{font-family:"IBM Plex Sans SC";font-weight:700;src:url(https://evil.example/secret.woff) format("woff");}'
	. '@font-face{font-family:"IBM Plex Sans SC";src:url("../secret.woff2");}';

$faces = WPFonts_Library::faces_from_css( $css, 'IBM Plex Sans SC' );
$assert( 1 === count( $faces ), 'expected one woff2 face, got ' . count( $faces ) );
$assert( 'https://example.test/wp-content/uploads/wpfonts/packages/demo/1.woff2' === $faces[0]['src'], 'face src drifted' );
$assert( 'IBM Plex Sans SC' === $faces[0]['fontFamily'], 'face family drifted' );
$assert( '400' === $faces[0]['fontWeight'], 'face weight drifted' );
$assert( 'swap' === $faces[0]['fontDisplay'], 'face display drifted' );
$assert( 'U+4E00-9FFF, U+3000-303F' === $faces[0]['unicodeRange'], 'unicode range drifted' );
$assert( '' === WPFonts_Library::css_stack( 'Bad{family}' ), 'unsafe family became a stack' );
$assert( '"IBM Plex Sans SC", sans-serif' === WPFonts_Library::css_stack( 'IBM Plex Sans SC' ), 'stack quoting drifted' );

$packages = array(
	array(
		'id'      => 'demo',
		'name'    => '演示包',
		'family'  => 'IBM Plex Sans SC',
		'enabled' => 1,
	),
	array(
		'id'      => 'paused',
		'name'    => '停用包',
		'family'  => 'Paused Face',
		'enabled' => 0,
	),
);
$built = WPFonts_Library::package_families(
	$packages,
	array(
		'demo' => $css,
	)
);
$assert( 1 === count( $built ), 'disabled package was registered' );
$assert( 'wpfonts-pkg-demo' === $built[0]['font_family_settings']['slug'], 'package slug drifted' );
$assert( 'wpfonts-pkg-demo' === WPFonts_Library::package_family_slug( 'demo' ), 'zip-name package slug was not prefixed' );
$aligned = WPFonts_Library::family_definition(
	array(
		'id'      => 'wenfeng-fzss',
		'name'    => '方正书宋',
		'family'  => 'wenfeng-fzss',
		'enabled' => 1,
	),
	''
);
$assert( is_array( $aligned ), 'wenfeng package produced no family' );
$assert( 'wenfeng-fzss' === $aligned['font_family_settings']['slug'], 'catalog package slug was prefixed' );
$assert( '方正书宋' === $aligned['font_family_settings']['name'], 'catalog package display name drifted' );
$assert( '"wenfeng-fzss", sans-serif' === $aligned['font_family_settings']['fontFamily'], 'catalog package stack drifted' );
$assert( '演示包' === $built[0]['font_family_settings']['name'], 'package name drifted' );
$assert( isset( $built[0]['font_family_settings']['fontFace'][0]['src'] ), 'enabled package lost its woff2' );
$assert( false === strpos( wp_json_encode( $built ), 'evil.example' ), 'non-woff2 url was kept' );
$assert( false === strpos( wp_json_encode( $built ), 'secret.woff2' ), 'relative url was kept' );

$deleted = WPFonts_Library::package_families(
	array(
		$packages[1],
	),
	array(
		'paused' => $css,
	)
);
$assert( array() === $deleted, 'deleted or disabled package still produced a family' );

$args = WPFonts_Library::packages_collection_args( $built );
$assert( 'Windfonts' === $args['name'], 'package collection name drifted' );
$assert( '本站已启用的文风字体包。关闭或删除后，全站样式里也不再出现。' === $args['description'], 'package collection description drifted' );

$cloud_settings = array(
	'cdn_enabled'  => 1,
	'region'       => 'cn',
	'subset'       => 'zh-common',
	'project_slug' => '',
	'family'       => 'wenfeng-ibmps',
	'key_status'   => 'valid',
	'css_status'   => 'ok',
	'css_face'     => 'windfonts-ibmps',
);
$cloud          = WPFonts_Library::confirmed_cloud_families( $cloud_settings, 'IBM Plex 无衬线简体中文' );
$assert( 1 === count( $cloud ), 'confirmed cloud family was dropped' );
$assert( 'wenfeng-ibmps' === $cloud[0]['font_family_settings']['slug'], 'cloud slug drifted' );
$assert( '"windfonts-ibmps", sans-serif' === $cloud[0]['font_family_settings']['fontFamily'], 'cloud stack used the catalog token' );
$assert( 'IBM Plex 无衬线简体中文' === $cloud[0]['font_family_settings']['name'], 'cloud label drifted' );
$assert( ! isset( $cloud[0]['font_family_settings']['fontFace'] ), 'cloud family attached a font file' );
$unconfirmed                   = $cloud_settings;
$unconfirmed['css_status']     = 'missing';
$unconfirmed['family']         = 'wenfeng-zhi-mang-xing';
$unconfirmed['css_face']       = 'WF-Zmxs';
$assert( array() === WPFonts_Library::confirmed_cloud_families( $unconfirmed, '钟齐志莽行书' ), 'a missing stylesheet was registered for Global Styles' );
$cloud_args = WPFonts_Library::cloud_collection_args( $cloud );
$assert( '文风云' === $cloud_args['name'], 'cloud collection name drifted' );

$encoded = wp_json_encode( array( $args, $cloud_args ) );
$assert( false === strpos( (string) $encoded, 'theme.json' ), 'collection args mention theme.json' );

$package_dir = $upload_dir . '/wpfonts/packages/demo';
if ( ! is_dir( $package_dir ) && ! mkdir( $package_dir, 0777, true ) && ! is_dir( $package_dir ) ) {
	fwrite( STDERR, "Could not create package dir.\n" );
	exit( 1 );
}
file_put_contents( $package_dir . '/local.css', $css );
file_put_contents(
	WPFONTS_CATALOG_PATH,
	wp_json_encode(
		array(
			'updated_at' => 10,
			'fonts'      => array(
				array(
					'name'       => 'IBM Plex 无衬线简体中文',
					'fontFamily' => 'wenfeng-ibmps',
				),
			),
		)
	)
);

$GLOBALS['wpfonts_upload_dir'] = $upload_dir;
$GLOBALS['wpfonts_options']    = array(
	'wpfonts_packages' => $packages,
	'wpfonts_settings' => array(
		'cdn_enabled'  => 1,
		'region'       => 'cn',
		'subset'       => 'zh-common',
		'family'       => 'wenfeng-ibmps',
		'key_status'   => 'valid',
		'css_status'   => 'ok',
		'css_face'     => 'windfonts-ibmps',
	),
);
$GLOBALS['wpfonts_registered']   = array();
$GLOBALS['wpfonts_unregistered'] = array();
WPFonts_Library::register();
$slugs = array();
foreach ( $GLOBALS['wpfonts_registered'] as $call ) {
	$slugs[] = $call[0];
}
$assert( in_array( WPFonts_Library::PACKAGES_SLUG, $slugs, true ), 'enabled package collection was not registered' );
$assert( in_array( WPFonts_Library::CLOUD_SLUG, $slugs, true ), 'confirmed cloud collection was not registered' );
$cloud_registered = WP_Font_Library::get_instance()->slugs[ WPFonts_Library::CLOUD_SLUG ]['font_families'][0]['font_family_settings'];
$assert( '"windfonts-ibmps", sans-serif' === $cloud_registered['fontFamily'], 'registered cloud family used the catalog token' );
$assert( isset( WP_Font_Library::get_instance()->slugs[ WPFonts_Library::PACKAGES_SLUG ] ), 'package collection missing from the registry' );

$GLOBALS['wpfonts_options']['wpfonts_packages'] = array();
$GLOBALS['wpfonts_options']['wpfonts_settings'] = array(
	'css_status' => 'missing',
	'family'     => 'wenfeng-zhi-mang-xing',
	'subset'     => 'zh-common',
	'cdn_enabled' => 1,
	'key_status' => 'valid',
);
unlink( WPFONTS_CATALOG_PATH );
$GLOBALS['wpfonts_registered']   = array();
$GLOBALS['wpfonts_unregistered'] = array();
WPFonts_Library::register();
$assert( array() === $GLOBALS['wpfonts_registered'], 'empty lists were still registered' );
$assert( in_array( WPFonts_Library::PACKAGES_SLUG, $GLOBALS['wpfonts_unregistered'], true ), 'disabled packages were not deregistered' );
$assert( in_array( WPFonts_Library::CLOUD_SLUG, $GLOBALS['wpfonts_unregistered'], true ), 'missing catalog was not deregistered' );
$assert( ! isset( WP_Font_Library::get_instance()->slugs[ WPFonts_Library::PACKAGES_SLUG ] ), 'deleted package stayed in the registry' );

$library_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-library.php' );
$editor_src  = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-editor.php' );
$editor_js   = file_get_contents( dirname( __DIR__ ) . '/assets/js/wpfonts-editor.js' );
$assert( false === strpos( $library_src, 'wp_global_styles' ), 'library writes global styles' );
$assert( false === strpos( $library_src, 'WP_Theme_JSON' ), 'library touches WP_Theme_JSON' );
$assert( false === strpos( $editor_src, 'admin_bar_menu' ), 'editor adds an admin bar item' );
$assert( false === strpos( $editor_src, 'wp_global_styles' ), 'editor writes global styles' );
$assert( false === strpos( $editor_js, 'PluginSidebar' ), 'editor adds header chrome' );
$assert( false === strpos( $editor_js, 'admin-bar' ), 'editor script targets the admin bar' );
$assert( false !== strpos( $editor_js, 'InspectorControls' ), 'editor panel is not in the inspector' );
$assert( false !== strpos( $editor_js, 'editor.BlockEdit' ), 'editor panel is not inside the block edit context' );
$assert( false === strpos( $editor_js, 'registerPlugin' ), 'editor panel is registered from the editor root' );
$assert( false !== strpos( $editor_src, "'wp-hooks'" ), 'editor script does not depend on wp-hooks' );
$assert( false === strpos( $editor_src, "'wp-plugins'" ), 'editor script still depends on the plugin area' );
$assert( false !== strpos( $editor_src, 'edit_posts' ), 'editor panel is not limited to people who can edit' );
$assert( false !== strpos( $editor_src, '设置里还没有确认能打开的文风字体。' ), 'editor cloud empty copy drifted' );
$assert( false !== strpos( $editor_src, "'packages'    => __( 'Windfonts', 'wpfonts' )" ), 'inspector package group is not labeled Windfonts' );
$assert( false !== strpos( $editor_src, '还没有启用的文风字体包。' ), 'inspector empty package copy drifted' );
$assert( false !== strpos( $editor_src, 'confirmed_cloud_families' ), 'editor still applies raw catalog tokens' );
$assert( false === strpos( $editor_js, 'wenfeng-' ), 'editor script builds a CSS stack from the catalog token' );
$assert( false !== strpos( $editor_src, '侧栏里的选择只改当前区块。' ), 'editor intro drifted' );

$two = $cloud_settings;
$two['faces'] = array(
	array(
		'family'   => 'wenfeng-ibmps',
		'name'     => 'IBM Plex 无衬线简体中文',
		'css_face' => 'windfonts-ibmps',
		'subset'   => 'zh-common',
	),
	array(
		'family'   => 'wenfeng-hcwjt',
		'name'     => '汇文楷体',
		'css_face' => 'windfonts-hcwjt',
		'subset'   => 'zh-common',
	),
);
$many = WPFonts_Library::confirmed_cloud_families( $two, 'IBM Plex 无衬线简体中文' );
$assert( 2 === count( $many ), 'confirmed cloud list dropped a second face' );
$assert( 'wenfeng-ibmps' === $many[0]['font_family_settings']['slug'], 'first cloud slug drifted' );
$assert( 'wenfeng-hcwjt' === $many[1]['font_family_settings']['slug'], 'second cloud slug drifted' );
$assert( '"windfonts-hcwjt", sans-serif' === $many[1]['font_family_settings']['fontFamily'], 'second cloud stack used the catalog token' );
$kept_only = $two;
$kept_only['faces'] = array( $two['faces'][0] );
$kept_only['css_face'] = 'windfonts-ibmps';
$kept_only['family'] = 'wenfeng-ibmps';
$after_remove = WPFonts_Library::confirmed_cloud_families( $kept_only, '' );
$assert( 1 === count( $after_remove ), 'removing a face left it in the cloud list' );
$assert( 'wenfeng-ibmps' === $after_remove[0]['font_family_settings']['slug'], 'the remaining face was not the one kept' );

$slice = '@font-face{font-family:"wenfeng-xxzt";font-weight:400;src:url("https://example.test/wp-content/uploads/wpfonts/packages/wenfeng-xxzt/0.woff2") format("woff2");unicode-range:U+4E00;}'
	. '@font-face{font-family:"wenfeng-xxzt";font-weight:400;src:url("https://example.test/wp-content/uploads/wpfonts/packages/wenfeng-xxzt/1.woff2") format("woff2");unicode-range:U+4E01;}';
$xxzt_family = WPFonts_Library::family_definition(
	array(
		'id'      => 'wenfeng-xxzt',
		'name'    => '文风·玄宗体',
		'family'  => 'wenfeng-xxzt',
		'enabled' => 1,
	),
	$slice
);
$assert( is_array( $xxzt_family ), 'xxzt package produced no family' );
$assert( '文风·玄宗体' === $xxzt_family['font_family_settings']['name'], 'font library did not show the healed name' );
$assert( 'wenfeng-xxzt' === $xxzt_family['font_family_settings']['slug'], 'font library changed the package slug' );
$assert( '"wenfeng-xxzt", sans-serif' === $xxzt_family['font_family_settings']['fontFamily'], 'font library changed the css family' );
$assert( 1 === count( $xxzt_family['font_family_settings']['fontFace'] ), 'unicode-range slices were listed once per file' );
$assert( 'wenfeng-xxzt' === $xxzt_family['font_family_settings']['fontFace'][0]['fontFamily'], 'folded slice used another family' );
$assert( '400' === $xxzt_family['font_family_settings']['fontFace'][0]['fontWeight'], 'folded slice changed weight' );
$assert( 'normal' === $xxzt_family['font_family_settings']['fontFace'][0]['fontStyle'], 'folded slice changed style' );
$assert( 'https://example.test/wp-content/uploads/wpfonts/packages/wenfeng-xxzt/0.woff2' === $xxzt_family['font_family_settings']['fontFace'][0]['src'], 'folded slice did not keep one woff2' );
$assert( 'U+4E00' === $xxzt_family['font_family_settings']['fontFace'][0]['unicodeRange'], 'folded slice dropped its own range' );
$assert( false === strpos( wp_json_encode( $xxzt_family ), '1.woff2' ), 'the second slice was copied into the font library' );
$assert( isset( $xxzt_family['font_family_settings']['preview'] ) && false !== strpos( $xxzt_family['font_family_settings']['preview'], 'wpfonts_specimen=wenfeng-xxzt' ), 'family specimen URL missing' );
$svg = WPFonts_Library::specimen_svg( '文风·玄宗体</text><script>' );
$assert( false !== strpos( $svg, '文风·玄宗体' ), 'specimen dropped the display name' );
$assert( false === strpos( $svg, '<script>' ), 'specimen svg was not escaped' );
$assert( $xxzt_family['font_family_settings']['preview'] === $xxzt_family['font_family_settings']['fontFace'][0]['preview'], 'face preview was not the family specimen' );
$assert( true === WPFonts_Library::css_is_sliced( $slice, 'wenfeng-xxzt' ), 'two slices of one weight were not treated as sliced' );
$weights = $slice . '@font-face{font-family:"wenfeng-xxzt";font-weight:700;font-style:italic;src:url("https://example.test/wp-content/uploads/wpfonts/packages/wenfeng-xxzt/bold.woff2") format("woff2");unicode-range:U+4E00;}';
$weight_faces = WPFonts_Library::faces_from_css( $weights, 'wenfeng-xxzt' );
$assert( 2 === count( $weight_faces ), 'a different weight was folded into Normal' );
$assert( '700' === $weight_faces[1]['fontWeight'] && 'italic' === $weight_faces[1]['fontStyle'], 'italic bold was rewritten' );
$wide = '@font-face{font-family:"wenfeng-xxzt";font-weight:400;src:url("https://example.test/wp-content/uploads/wpfonts/packages/wenfeng-xxzt/0.woff2") format("woff2");unicode-range:U+4E00;}'
	. '@font-face{font-family:"wenfeng-xxzt";font-weight:400;src:url("https://example.test/wp-content/uploads/wpfonts/packages/wenfeng-xxzt/wide.woff2") format("woff2");unicode-range:U+4E00-9FFF;}';
$wide_faces = WPFonts_Library::faces_from_css( $wide, 'wenfeng-xxzt' );
$assert( 1 === count( $wide_faces ), 'a wider slice did not collapse' );
$assert( false !== strpos( $wide_faces[0]['src'], 'wide.woff2' ), 'the wider unicode-range was not the representative' );
$assert( 'U+4E00-9FFF' === $wide_faces[0]['unicodeRange'], 'the representative lost its range' );
$full = '@font-face{font-family:"wenfeng-xxzt";font-weight:normal;src:url("https://example.test/wp-content/uploads/wpfonts/packages/wenfeng-xxzt/full.woff2") format("woff2");}'
	. '@font-face{font-family:"wenfeng-xxzt";font-weight:400;src:url("https://example.test/wp-content/uploads/wpfonts/packages/wenfeng-xxzt/0.woff2") format("woff2");unicode-range:U+4E00;}';
$full_faces = WPFonts_Library::faces_from_css( $full, 'wenfeng-xxzt' );
$assert( 1 === count( $full_faces ), 'normal and 400 stayed as two rows' );
$assert( false !== strpos( $full_faces[0]['src'], 'full.woff2' ), 'a file without unicode-range lost to a slice' );
$assert( ! isset( $full_faces[0]['unicodeRange'] ), 'the full file picked up a slice range' );
$packed = WPFonts_Library::family_definition(
	array(
		'id'      => 'wenfeng-xxzt',
		'name'    => '文风·玄宗体',
		'family'  => 'wenfeng-xxzt',
		'enabled' => 1,
		'preview' => 'https://cdn.example.test/previews/xxzt.svg',
	),
	$slice
);
$assert( is_array( $packed ) && 'https://cdn.example.test/previews/xxzt.svg' === $packed['font_family_settings']['preview'], 'pack preview URL was ignored' );
$noted = WPFonts_Library::describe_packages( array( $xxzt_family ), true );
$assert( false !== strpos( $noted['description'], '本包按字符切片托管，前台会自动按需加载；字体库里每个字重只显示一条即可。' ), 'sliced collection lost the install note' );
$plain_args = WPFonts_Library::describe_packages( array( $xxzt_family ), false );
$assert( '本站已启用的文风字体包。关闭或删除后，全站样式里也不再出现。' === $plain_args['description'], 'unsliced collection description changed' );
$packages_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-packages.php' );
$assert( false !== strpos( $packages_src, "\$url = \$base . \$package['id'] . '/local.css';" ), 'package runtime stopped loading local.css' );
$assert( false === strpos( $packages_src, 'faces_from_css' ), 'package runtime started copying font library faces' );
$xxzt_list = WPFonts_Library::package_families(
	array(
		array(
			'id'      => 'wenfeng-xxzt',
			'name'    => '文风·玄宗体',
			'family'  => 'wenfeng-xxzt',
			'enabled' => 1,
		),
	),
	array(
		'wenfeng-xxzt' => $slice,
	)
);
$assert( 1 === count( $xxzt_list ), 'one package registered more than one family' );

$previous_packages = isset( $GLOBALS['wpfonts_options']['wpfonts_packages'] ) ? $GLOBALS['wpfonts_options']['wpfonts_packages'] : null;
$GLOBALS['wpfonts_options']['wpfonts_packages'] = array(
	array(
		'id'      => 'wenfeng-xxzt',
		'name'    => '文风·玄宗体',
		'family'  => 'wenfeng-xxzt',
		'enabled' => 1,
	),
);
$picked = WPFonts_Library::editor_packages();
$assert( 1 === count( $picked ), 'editor package picker dropped the healed package' );
$assert( '文风·玄宗体' === $picked[0]['name'], 'editor package picker did not show the healed name' );
$assert( 'wenfeng-xxzt' === $picked[0]['family'], 'editor package picker changed the family' );
if ( null === $previous_packages ) {
	unset( $GLOBALS['wpfonts_options']['wpfonts_packages'] );
} else {
	$GLOBALS['wpfonts_options']['wpfonts_packages'] = $previous_packages;
}

$hooked = false;
foreach ( $GLOBALS['wpfonts_actions'] as $action ) {
	if ( 'init' === $action[0] ) {
		$hooked = true;
	}
}
$assert( $hooked, 'library did not register on init' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: enabled packages and catalog names register as Font Library families, and disabled packages drop out.\n";
