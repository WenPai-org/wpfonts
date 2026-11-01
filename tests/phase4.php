<?php
/**
 * Phase 4: multi-face cloud, system toggles, package health, migration.
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

$upload = sys_get_temp_dir() . '/wpfonts-phase4-' . getmypid();
if ( ! is_dir( $upload ) && ! mkdir( $upload, 0777, true ) && ! is_dir( $upload ) ) {
	fwrite( STDERR, "Could not create temp dir.\n" );
	exit( 1 );
}

$GLOBALS['wpfonts_options'] = array();
$GLOBALS['wpfonts_upload']  = array(
	'basedir' => $upload,
	'baseurl' => 'https://example.test/wp-content/uploads',
	'error'   => false,
);

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted_args Args.
 * @return void
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
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
	$GLOBALS['wpfonts_options'][ $name ] = $value;
	$GLOBALS['wpfonts_writes'][]         = $name;
	return true;
}

/**
 * @param string $name Option.
 * @return bool
 */
function delete_option( $name ) {
	unset( $GLOBALS['wpfonts_options'][ $name ] );
	return true;
}

/**
 * @return array
 */
function wp_upload_dir() {
	return $GLOBALS['wpfonts_upload'];
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
 * @param int   $flags Flags.
 * @return string|false
 */
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}

require dirname( __DIR__ ) . '/includes/class-wpfonts-link.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-packages.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-settings.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-library.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-global-styles.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-transfer.php';

$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$legacy = array(
	'cdn_enabled' => 1,
	'key_status'  => 'valid',
	'region'      => 'cn',
	'subset'      => 'zh-common',
	'family'      => 'wenfeng-ibmps',
	'css_status'  => 'ok',
	'css_face'    => 'windfonts-ibmps',
);
$migrated = WPFonts_Settings::saved_faces( $legacy );
$assert( 1 === count( $migrated ), 'rc.6 row did not migrate to one face' );
$assert( 'wenfeng-ibmps' === $migrated[0]['family'], 'migrated family drifted' );
$assert( 'windfonts-ibmps' === $migrated[0]['css_face'], 'migrated css face drifted' );
$listed = WPFonts_Library::confirmed_cloud_families( $legacy, 'IBM Plex' );
$assert( 1 === count( $listed ) && 'wenfeng-ibmps' === $listed[0]['font_family_settings']['slug'], 'library dropped the migrated face' );

$explicit = $legacy;
$explicit['faces'] = array();
$assert( array() === WPFonts_Settings::saved_faces( $explicit ), 'an empty faces list resurrected the rc.6 face' );

$two = $legacy;
$two['faces'] = array(
	array(
		'family'   => 'wenfeng-ibmps',
		'name'     => '甲',
		'css_face' => 'windfonts-ibmps',
		'subset'   => 'zh-common',
	),
	array(
		'family'   => 'wenfeng-hcwjt',
		'name'     => '乙',
		'css_face' => 'windfonts-hcwjt',
		'subset'   => 'zh',
	),
	array(
		'family'   => 'not-a-cloud-face',
		'name'     => '坏',
		'css_face' => 'bad',
		'subset'   => 'zh-common',
	),
);
$clean = WPFonts_Settings::confirmed_faces( $two );
$assert( 2 === count( $clean ), 'non-wenfeng face was kept' );
$assert( 'wenfeng-hcwjt' === $clean[1]['family'], 'second face was dropped' );

$merged = WPFonts_Global_Styles::merge_custom_families(
	array(
		array(
			'slug'       => 'wenfeng-ibmps',
			'name'       => '甲',
			'fontFamily' => '"windfonts-ibmps", sans-serif',
		),
		array(
			'slug'       => 'wenfeng-hcwjt',
			'name'       => '乙',
			'fontFamily' => '"windfonts-hcwjt", sans-serif',
		),
		array(
			'slug'       => 'wenfeng-keep',
			'name'       => '别人的',
			'fontFamily' => '"Kept", sans-serif',
		),
		array(
			'slug'       => 'user-serif',
			'name'       => '用户',
			'fontFamily' => 'Georgia, serif',
		),
	),
	array(
		array(
			'slug'       => 'wenfeng-ibmps',
			'name'       => '甲',
			'fontFamily' => '"windfonts-ibmps", sans-serif',
		),
	),
	array( 'wenfeng-ibmps', 'wenfeng-hcwjt' )
);
$slugs = array();
foreach ( $merged['custom'] as $item ) {
	if ( is_array( $item ) && isset( $item['slug'] ) ) {
		$slugs[] = $item['slug'];
	}
}
$assert( in_array( 'wenfeng-ibmps', $slugs, true ), 'the face that stayed was removed' );
$assert( ! in_array( 'wenfeng-hcwjt', $slugs, true ), 'removing one face left its Global Styles entry' );
$assert( in_array( 'wenfeng-keep', $slugs, true ), 'a wenfeng face this plugin did not write was removed' );
$assert( in_array( 'user-serif', $slugs, true ), 'a user family was removed with the cloud face' );
$assert( array( 'wenfeng-ibmps' ) === $merged['owned'], 'owned slugs still include the removed face' );

$assert(
	true === WPFonts_Global_Styles::settings_need_sync(
		array( 'faces' => $two['faces'] ),
		array( 'faces' => array( $two['faces'][0] ) )
	),
	'removing a face did not ask Global Styles to sync'
);
$assert(
	false === WPFonts_Global_Styles::settings_need_sync(
		array(
			'css_status' => 'ok',
			'css_face'   => 'windfonts-ibmps',
			'family'     => 'wenfeng-ibmps',
		),
		array(
			'css_status' => 'ok',
			'css_face'   => 'windfonts-ibmps',
			'family'     => 'wenfeng-ibmps',
			'key_checked' => 9,
		)
	),
	'a settings save without face changes asked for a sync'
);

$GLOBALS['wpfonts_options'] = array();
$assert( WPFonts_Settings::system_enabled( 'cn' ), 'Chinese system fonts defaulted off' );
$assert( WPFonts_Settings::system_enabled( 'kr' ), 'Korean system fonts defaulted off' );
$assert( ! WPFonts_Settings::system_enabled( 'xx' ), 'an unknown language was registered' );
$GLOBALS['wpfonts_options']['wpfonts_settings'] = array(
	'system_jp' => 0,
	'system_en' => 1,
);
$assert( ! WPFonts_Settings::system_enabled( 'jp' ), 'Japanese stayed registered after it was unchecked' );
$assert( WPFonts_Settings::system_enabled( 'en' ), 'English was dropped while still checked' );
$assert( WPFonts_Settings::system_enabled( 'cn' ), 'an omitted language was treated as off' );

$package = array(
	'id'      => 'demo',
	'name'    => '演示',
	'family'  => '演示',
	'enabled' => 1,
);
$dir = $upload . '/wpfonts/packages/demo';
if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) && ! is_dir( $dir ) ) {
	fwrite( STDERR, "Could not create package dir.\n" );
	exit( 1 );
}
$missing = WPFonts_Packages::health( $package );
$assert( 'missing_css' === $missing['code'], 'missing local.css was not reported' );
$assert( false !== strpos( $missing['reason'], '找不到 local.css' ), 'missing css reason was not plain Chinese' );
$assert( false !== strpos( $missing['reason'], '已启用，但不会加载' ), 'an enabled package hid that it would not load' );

file_put_contents( $dir . '/local.css', '@font-face{font-family:"演示";src:url(demo.woff);}' );
$woff = WPFonts_Packages::health( $package );
$assert( 'no_woff2' === $woff['code'], 'a woff file was treated as a usable woff2' );
$assert( false !== strpos( $woff['reason'], '没有可用的 woff2' ), 'missing woff2 reason drifted' );

file_put_contents( $dir . '/local.css', "/* url(ghost.woff2) */\nbody{color:#111}" );
$comment = WPFonts_Packages::health( $package );
$assert( 'no_woff2' === $comment['code'], 'a woff2 mentioned outside @font-face counted as usable' );

file_put_contents( $dir . '/one.woff2', 'woff2' );
file_put_contents(
	$dir . '/local.css',
	'@font-face{font-family:"演示";src:url("https://example.test/wp-content/uploads/wpfonts/packages/demo/one.woff2");}'
);
$ok = WPFonts_Packages::health( $package );
$assert( 'ok' === $ok['code'] && '' === $ok['reason'], 'a real woff2 was reported as broken' );

$package['enabled'] = 0;
file_put_contents( $dir . '/local.css', '@font-face{src:url("https://cdn.example/a.woff2");}' );
$external = WPFonts_Packages::health( $package );
$assert( 'ok' === $external['code'] && '' === $external['reason'], 'an external woff2 was called missing' );

$GLOBALS['wpfonts_upload']['error'] = 'unavailable';
$unknown = WPFonts_Packages::health( $package );
$assert( 'unknown' === $unknown['code'] && '' === $unknown['reason'], 'an unreadable uploads directory was reported as a broken package' );
$GLOBALS['wpfonts_upload']['error'] = false;

$slug_pack = array(
	'id'      => 'wenfeng-xxzt',
	'name'    => 'wenfeng-xxzt',
	'family'  => 'wenfeng-xxzt',
	'enabled' => 1,
);
$xx_dir    = $upload . '/wpfonts/packages/wenfeng-xxzt';
if ( ! is_dir( $xx_dir ) && ! mkdir( $xx_dir, 0777, true ) && ! is_dir( $xx_dir ) ) {
	fwrite( STDERR, "Could not create preview package dir.\n" );
	exit( 1 );
}
file_put_contents(
	$xx_dir . '/local.css',
	'@font-face{font-family:"霞鹜新致宋";src:url("face.woff2") format("woff2");}'
);
$assert( '霞鹜新致宋' === WPFonts_Packages::preview_family( $slug_pack ), 'preview used the package slug instead of local.css' );
$front_url = WPFonts_Packages::front_stylesheet_url( $slug_pack );
$assert( false !== strpos( $front_url, '/wpfonts/packages/wenfeng-xxzt/local.css' ), 'front stylesheet URL drifted: ' . $front_url );
$gone = array(
	'id'      => 'wenfeng-missing',
	'name'    => 'wenfeng-missing',
	'family'  => 'wenfeng-missing',
	'enabled' => 1,
);
$assert( '' === WPFonts_Packages::preview_family( $gone ), 'a missing stylesheet still previewed the slug' );

$secret = 'wf_phase4_secret_key_value';
$GLOBALS['wpfonts_options'] = array(
	'wpfonts_api_key'  => $secret,
	'wpfonts_settings' => $legacy,
	'wpfonts_packages' => array(
		array(
			'id'      => 'demo',
			'name'    => '演示',
			'family'  => '演示体',
			'enabled' => 1,
		),
		array(
			'id'      => 'paused',
			'name'    => '停用',
			'family'  => '停用体',
			'enabled' => 0,
		),
	),
);
$GLOBALS['wpfonts_writes'] = array();
$json = WPFonts_Transfer::export_json();
$assert( false === strpos( $json, $secret ), 'export contained the API key' );
$assert( false === strpos( $json, 'api_key' ), 'export contained an api_key field' );
$assert( false !== strpos( $json, 'wenfeng-ibmps' ), 'export dropped the confirmed face' );
$assert( false !== strpos( $json, '"id": "demo"' ) || false !== strpos( $json, '"id":"demo"' ), 'export dropped the enabled package' );
$assert( false === strpos( $json, 'paused' ), 'export included a disabled package' );

$refused = WPFonts_Transfer::import_json(
	wp_json_encode(
		array(
			'plugin'  => 'wpfonts',
			'api_key' => $secret,
			'faces'   => array(
				array(
					'family'   => 'wenfeng-hcwjt',
					'css_face' => 'windfonts-hcwjt',
					'subset'   => 'zh-common',
				),
			),
		)
	)
);
$assert( false === $refused['ok'] && 'refused' === $refused['code'], 'a file with an API key was imported' );
$assert( $secret === get_option( 'wpfonts_api_key' ), 'refusing an import changed the stored key' );
$assert( ! in_array( 'wpfonts_api_key', $GLOBALS['wpfonts_writes'], true ), 'refusing an import wrote the key option' );

$before_faces = get_option( 'wpfonts_settings' );
$imported = WPFonts_Transfer::import_json(
	wp_json_encode(
		array(
			'plugin'   => 'wpfonts',
			'packages' => array(
				array(
					'id'      => 'demo',
					'name'    => '演示',
					'family'  => '演示体',
					'enabled' => 1,
				),
				array(
					'id'      => 'missing-zip',
					'name'    => '没有文件',
					'enabled' => 1,
				),
			),
			'faces'    => array(
				array(
					'family'   => 'wenfeng-ibmps',
					'name'     => '甲',
					'css_face' => 'windfonts-ibmps',
					'subset'   => 'zh-common',
				),
				array(
					'family'   => 'wenfeng-hcwjt',
					'name'     => '乙',
					'css_face' => 'windfonts-hcwjt',
					'subset'   => 'zh',
				),
			),
		)
	)
);
$assert( true === $imported['ok'], 'a clean file was refused: ' . $imported['message'] );
$assert( false === strpos( wp_json_encode( get_option( 'wpfonts_settings' ) ), $secret ), 'import stored the API key in settings' );
$assert( $secret === get_option( 'wpfonts_api_key' ), 'import replaced the API key' );
$stored_faces = WPFonts_Settings::saved_faces( get_option( 'wpfonts_settings' ) );
$assert( 2 === count( $stored_faces ), 'import did not store both cloud faces' );
$assert( 'wenfeng-hcwjt' === $stored_faces[1]['family'], 'imported second face drifted' );
$packages = get_option( 'wpfonts_packages' );
$by_id    = array();
foreach ( $packages as $row ) {
	$by_id[ $row['id'] ] = $row;
}
$assert( ! isset( $by_id['missing-zip'] ), 'import created a package that has no files' );
$assert( ! empty( $by_id['demo']['enabled'] ), 'import did not keep the installed package enabled' );
unset( $before_faces );

$root = dirname( __DIR__ );
$admin = (string) file_get_contents( $root . '/includes/class-wpfonts-admin.php' );
$main  = (string) file_get_contents( $root . '/wpfonts.php' );
$editor_js = (string) file_get_contents( $root . '/assets/js/wpfonts-editor.js' );
$assert( false !== strpos( $admin, '侧栏里的「文派字库」只改当前区块。已启用的文风字体包和已确认的文风云会进入全站样式，可在外观 → 样式 → 排版里选用。' ), 'help lost the two sentences' );
$assert( false !== strpos( $main, 'WPFonts_Settings::system_enabled' ), 'unchecked system languages are still registered' );
$assert( false === strpos( $editor_js, 'app.windfonts.com' ), 'the inspector calls the Windfonts catalog' );
$assert( false === strpos( $editor_js, 'admin-ajax.php' ), 'the inspector searches on open' );
$assert( false === strpos( $admin, '去控制台上传' ), 'admin grew a console upload button' );
$assert( false !== strpos( $admin, '安装本站要用的字体包。启用后可在全站样式里选用。' ), 'packages lede drifted' );
$assert( false === strpos( $admin, '插件不切字' ), 'slicing lecture returned' );
$assert( false !== strpos( $admin, '这些项用于文风云' ), 'cloud tools lost the secondary label' );
$assert( false !== strpos( $admin, '预览暂时不可用。' ), 'missing preview copy drifted' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: multi-face cloud, system toggles, package health, and secret-free migration.\n";
