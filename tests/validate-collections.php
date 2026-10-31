<?php
/**
 * Standalone collection contract checks.
 *
 * Family slugs use a language prefix: cn-, en-, jp-, kr-.
 * Japanese and Korean slugs that already ended in -jp or -kr drop that
 * suffix before the prefix is applied (system-ui-jp becomes jp-system-ui).
 */

$root       = dirname( __DIR__ );
$files      = glob( $root . '/collection/*.json' );
$all_slugs  = array();
$family_num = 0;
$failures   = array();
$prefixes   = array(
	'chinese.json'  => 'cn-',
	'english.json'  => 'en-',
	'japanese.json' => 'jp-',
	'korean.json'   => 'kr-',
);
$expected_counts = array(
	'chinese.json'  => 15,
	'english.json'  => 15,
	'japanese.json' => 5,
	'korean.json'   => 5,
);

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$assert( 4 === count( $files ), 'Expected exactly four collection files.' );

foreach ( $files as $file ) {
	$name  = basename( $file );
	$data  = json_decode( file_get_contents( $file ), true );
	$count = 0;
	$assert( JSON_ERROR_NONE === json_last_error(), $name . ': invalid JSON.' );
	$assert( isset( $prefixes[ $name ] ), $name . ': unexpected collection file.' );
	$assert( isset( $data['font_families'] ) && is_array( $data['font_families'] ), $name . ': missing font_families.' );

	foreach ( $data['font_families'] ?? array() as $index => $family ) {
		$context  = $name . '#' . $index;
		$settings = $family['font_family_settings'] ?? array();
		$slug     = $settings['slug'] ?? '';
		$stack    = $settings['fontFamily'] ?? '';
		$prefix   = $prefixes[ $name ] ?? '';

		++$family_num;
		++$count;
		$assert( '' !== ( $settings['name'] ?? '' ), $context . ': missing name.' );
		$assert( 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ), $context . ': invalid slug.' );
		$assert( '' !== $prefix && 0 === strpos( $slug, $prefix ), $context . ': slug is not language-prefixed.' );
		$assert( ! isset( $all_slugs[ $slug ] ), $context . ': duplicate global slug ' . $slug . '.' );
		$all_slugs[ $slug ] = true;
		$assert( '' !== $stack, $context . ': empty font stack.' );
		$assert( 0 === preg_match( '/(?:url\s*\(|@import|[{};<>])/i', $stack ), $context . ': unsafe CSS token in font stack.' );
		$assert( 1 === preg_match( '/(?:sans-serif|serif|monospace|cursive|fantasy)\s*$/i', $stack ), $context . ': no generic fallback.' );
		$assert( empty( $settings['fontFace'] ), $context . ': bundled fontFace would change the privacy model.' );
		$assert( empty( $settings['preview'] ), $context . ': external preview URL is not allowed.' );
		$assert( 1 === count( $family['categories'] ?? array() ), $context . ': expected one category.' );
	}

	$assert( ( $expected_counts[ $name ] ?? -1 ) === $count, $name . ': expected ' . ( $expected_counts[ $name ] ?? '?' ) . ' families, got ' . $count . '.' );
}

$assert( 40 === $family_num, 'Expected 40 non-zero font families.' );

$main = file_get_contents( $root . '/wpfonts.php' );
foreach ( array( 'wp-fonts-stacks-cn', 'wp-fonts-stacks-en', 'wp-fonts-stacks-jp', 'wp-fonts-stacks-kr' ) as $collection_slug ) {
	$assert( false !== strpos( $main, "'" . $collection_slug . "'" ), 'Main file does not register ' . $collection_slug . '.' );
}
$assert( false !== strpos( $main, 'Requires at least: 6.5' ), 'Requires at least 6.5.' );
$assert( false !== strpos( $main, 'Requires PHP: 7.4' ), 'Requires PHP 7.4.' );
$assert( false !== strpos( $main, 'Version: 2.0.0-rc.4' ), 'Version header must be 2.0.0-rc.4.' );
$assert( false !== strpos( $main, "define( 'WPFONTS_VERSION', '2.0.0-rc.4' )" ), 'Version constant must be 2.0.0-rc.4.' );
$assert( false === strpos( $main, 'Network:' ), 'Unjustified Network header must stay absent.' );
$assert( false !== strpos( $main, 'https://updates.wenpai.net' ), 'Update URI host must stay updates.wenpai.net.' );
$assert( false !== strpos( $main, 'https://updates.weixiaoduo.com/wpfonts.json' ), 'PUC metadata host must stay updates.weixiaoduo.com.' );
$assert( false !== strpos( $main, 'https://wpcy.com/wpfonts' ), 'Plugin URI must stay on wpcy.com.' );
$assert( false !== strpos( $main, 'function wp_fonts_initialize_update_checker' ), 'Updater gate missing.' );
$assert( false !== strpos( $main, '! is_admin()' ), 'Updater must skip non-admin requests.' );
$assert( false !== strpos( $main, '! wp_doing_cron()' ), 'Updater must allow WP-Cron.' );
$assert( false !== strpos( $main, 'WP_CLI' ), 'Updater must allow WP-CLI.' );
$assert( false === stripos( $main, 'updatepulse' ), 'UpdatePulse reference remains in the main file.' );
$assert( false !== strpos( $main, 'lib/wenpai-admin-ui/php/load.php' ), 'Main file must load the vendored kit.' );
$assert( false === strpos( $main, 'register_uninstall_hook' ), 'Uninstall must not register a data-deletion hook.' );
$assert( ! is_dir( $root . '/lib/updatepulse-updater' ), 'UpdatePulse directory must be removed.' );
$assert( is_dir( $root . '/lib/wenpai-admin-ui' ), 'wenpai-admin-ui must be vendored.' );
$assert( '0.3.1' === trim( (string) file_get_contents( $root . '/lib/wenpai-admin-ui/VERSION' ) ), 'Vendored kit VERSION must be 0.3.1 or newer and recorded.' );

$admin = file_get_contents( $root . '/includes/class-wpfonts-admin.php' );
$embed = file_get_contents( $root . '/includes/class-wpfonts-embed.php' );
$assert( false !== strpos( $admin, "'admin_menu'" ), 'Admin screen must register under admin_menu.' );
$assert( false !== strpos( $admin, 'Wenpai_Admin_Shell::open' ), 'Admin screen must open Wenpai_Admin_Shell.' );
$assert( false !== strpos( $admin, 'wenpai-subnav' ), 'Settings must use the kit subnav.' );
$assert( false === strpos( $admin, '文派许可证' ), 'Admin UI must not mention the WenPai license.' );
$assert( false === strpos( $admin, 'updates.wenpai.net' ), 'Admin UI must not explain the update host.' );
$assert( false === strpos( $admin, 'render_license_card' ), 'License card must stay removed.' );
$assert( false === strpos( $admin, '去控制台上传' ), 'Admin UI must not offer a console upload button.' );
$assert( false === strpos( $admin, '文风 CDN' ), 'Admin UI must not say 文风 CDN.' );
$assert( false === strpos( $admin, '本站字体包' ), 'Admin UI must not say 本站字体包.' );
$assert( false !== strpos( $admin, '文风云' ), 'Admin UI lost 文风云.' );
$assert( false !== strpos( $admin, '文风字体包' ), 'Admin UI lost 文风字体包.' );
$assert( false !== strpos( $admin, '选用字体' ), 'Font picker title is missing.' );
$assert( false !== strpos( $admin, '从文风目录挑选，或手动填写字体名。选中的名字会写进公开样式地址。' ), 'Font picker description drifted.' );
$assert( false !== strpos( $admin, '搜索字体名' ), 'Catalog search placeholder drifted.' );
$assert( false !== strpos( $admin, '正在加载字体目录…' ), 'Catalog loading copy drifted.' );
$assert( false !== strpos( $admin, '暂时读不到字体目录。你可以手动填写字体名，稍后再试。' ), 'Catalog failure copy drifted.' );
$assert( false !== strpos( $admin, '没有匹配的字体。改个词，或手动填写。' ), 'Catalog empty copy drifted.' );
$assert( false !== strpos( $admin, '结果来自已加载页' ), 'Loaded-page note drifted.' );
$assert( false !== strpos( $admin, '也可直接填写，例如 wenfeng-ibmps。填了项目名后，公开地址可以不填字体名。' ), 'Manual family hint drifted.' );
$assert( false !== strpos( $admin, '体积最小，主要覆盖拉丁字符。' ), 'Latin subset copy drifted.' );
$assert( false !== strpos( $admin, '日常中文站点够用，体积适中。' ), 'Common Chinese subset copy drifted.' );
$assert( false !== strpos( $admin, '生僻字更多，体积明显更大。' ), 'Broader Chinese subset copy drifted.' );
$assert( false !== strpos( $admin, '最大包，仅在确实需要时使用。' ), 'Full subset copy drifted.' );
$assert( false !== strpos( $admin, '字符范围决定远程字体包大小。插件不在这里切字。' ), 'Subset hint drifted.' );
$assert( false !== strpos( $admin, '项目与白名单' ), 'Project disclosure title drifted.' );
$assert( false !== strpos( $admin, '选填。填写后前台改用项目样式。域名白名单在文风控制台配置，不在本插件里改。' ), 'Project hint drifted.' );
$assert( false !== strpos( $admin, '打开文风控制台' ), 'Console link label drifted.' );
$assert( false !== strpos( $admin, '字体包' ), 'Packages tab copy is missing.' );
$assert( false === strpos( $admin, 'form-table' ), 'Admin screen must not use form-table.' );
$assert( false === strpos( $admin, 'confirm(' ), 'Package delete must not use confirm().' );
$assert( false === strpos( $admin, 'onclick=' ), 'Package delete must not use an onclick confirm.' );
$assert( false !== strpos( $admin, 'wenpai-gate' ), 'Package delete must use the kit gate.' );
$assert( false !== strpos( $admin, 'data-wenpai-need-apply' ), 'Delete button must stay disabled until the gate is checked.' );
$assert( false !== strpos( $admin, '删除后本站不再提供这份字体。我已核对。' ), 'Delete gate copy drifted.' );
$assert( false !== strpos( $admin, '未勾选不能点。' ), 'Delete gate hint drifted.' );
$assert( false !== strpos( $admin, "check_admin_referer( 'wpfonts_package_delete' )" ), 'Delete handler lost its nonce check.' );
$assert( false !== strpos( $admin, 'wpfonts_delete_ack' ), 'Delete handler no longer requires the gate checkbox.' );
$assert( false === strpos( $embed, 'api_key' ), 'Front embed must not read the API key.' );
$uninstall = file_get_contents( $root . '/uninstall.php' );
$assert( false !== strpos( $uninstall, 'wpfonts_api_key' ), 'Uninstall must delete the API key option.' );
$assert( false === strpos( $uninstall, 'wp_global_styles' ), 'Uninstall must not touch Global Styles.' );
$assert( false === strpos( $admin, '块主题全局样式与 theme.json 写入，本版不做。' ), 'Help still says theme.json writes are out of scope.' );

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

printf( "PASS: %d collection files, %d unique language-prefixed system-font stacks.\n", count( $files ), $family_num );
