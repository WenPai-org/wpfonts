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
$assert( false !== strpos( $main, 'Version: 2.0.0-rc.1' ), 'Version header must be 2.0.0-rc.1.' );
$assert( false !== strpos( $main, "define( 'WPFONTS_VERSION', '2.0.0-rc.1' )" ), 'Version constant must be 2.0.0-rc.1.' );
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
$assert( false === strpos( $admin, 'form-table' ), 'Admin screen must not use form-table.' );
$assert( false === strpos( $embed, 'api_key' ), 'Front embed must not read the API key.' );
$uninstall = file_get_contents( $root . '/uninstall.php' );
$assert( false !== strpos( $uninstall, 'wpfonts_api_key' ), 'Uninstall must delete the API key option.' );
$assert( false === strpos( $uninstall, 'wp_global_styles' ), 'Uninstall must not touch Global Styles.' );

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

printf( "PASS: %d collection files, %d unique language-prefixed system-font stacks.\n", count( $files ), $family_num );
