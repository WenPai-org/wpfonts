<?php
/**
 * Local package path and CSS checks. No WordPress, no zip install.
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/class-wpfonts-packages.php';

$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$assert( 'wenfeng-ibmps' === WPFonts_Packages::slug_from_filename( 'WenFeng-IBMPS.zip' ), 'slug from file name' );
$assert( 'package' === WPFonts_Packages::slug_from_filename( '....zip' ), 'empty slug fallback' );
$assert( 'pkg-2' === WPFonts_Packages::unique_slug( 'pkg', array( 'pkg' ) ), 'slug collision' );
$assert( ! WPFonts_Packages::is_safe_rel( '../etc/passwd' ), 'traversal rejected' );
$assert( ! WPFonts_Packages::is_safe_rel( '/tmp/x.css' ), 'absolute path rejected' );
$assert( WPFonts_Packages::is_safe_rel( 'split/1.woff2' ), 'nested woff2 allowed' );

$rels = array( 'readme.txt', 'split/result.css', 'split/1.woff2', 'other.css' );
$assert( 'split/result.css' === WPFonts_Packages::locate_stylesheet( $rels ), 'result.css wins' );
$assert( WPFonts_Packages::has_woff2( $rels ), 'woff2 detected' );
$assert( 'only.css' === WPFonts_Packages::locate_stylesheet( array( 'only.css', 'a/b/c.css' ) ), 'single shallow css' );
$assert( '' === WPFonts_Packages::locate_stylesheet( array( 'a.css', 'b.css' ) ), 'two shallow css files are ambiguous' );
$assert( ! WPFonts_Packages::has_woff2( array( 'only.css' ) ), 'css alone is not a package' );

$assert( '' === WPFonts_Packages::resolve_rel( 'split', '../../secret.woff2' ), 'url escape rejected' );
$assert( 'split/1.woff2' === WPFonts_Packages::resolve_rel( 'split', './1.woff2' ), 'relative woff2 resolved' );

$css = "/* demo */\n@font-face{src:url(./1.woff2)}";
$out = WPFonts_Packages::rewrite_css( $css, 'split/result.css', 'https://example.test/wp-content/uploads/wpfonts/packages/demo' );
$assert( false !== strpos( $out, 'https://example.test/wp-content/uploads/wpfonts/packages/demo/split/1.woff2' ), 'rewritten url: ' . $out );
$assert( false === strpos( $out, 'api_key' ), 'rewrite added a key' );

$remote = WPFonts_Packages::rewrite_css( 'src:url(https://cdn.example/a.woff2)', 'result.css', 'https://example.test/pkg' );
$assert( false !== strpos( $remote, 'https://cdn.example/a.woff2' ), 'absolute url left untouched' );

$css_family = "@font-face { font-family: \"IBM Plex Sans SC\"; src: url(1.woff2); }";
$assert( 'IBM Plex Sans SC' === WPFonts_Packages::family_from_css( $css_family ), 'font-family parsed' );
$assert( '' === WPFonts_Packages::family_from_css( 'body{color:red}' ), 'missing face is empty' );

$assert( 'wenfeng-fzss' === WPFonts_Packages::catalog_family( 'WF-Fzss' ), 'WF-Fzss maps to wenfeng-fzss' );
$assert( 'wenfeng-fzss' === WPFonts_Packages::catalog_family( '"wf-fzss"' ), 'quoted wf- prefix maps' );
$assert( 'wenfeng-ibmps' === WPFonts_Packages::catalog_family( 'windfonts-ibmps' ), 'windfonts- maps to wenfeng-' );
$assert( 'wenfeng-fzss' === WPFonts_Packages::catalog_family( 'wenfeng-fzss' ), 'wenfeng- passes through' );
$assert( 'wenfeng-fzss' === WPFonts_Packages::catalog_family( 'WenFeng-Fzss' ), 'WenFeng- passes through lowercased' );
$assert( '' === WPFonts_Packages::catalog_family( 'IBM Plex Sans SC' ), 'a human name is not a catalog family' );
$assert( '' === WPFonts_Packages::catalog_family( '方正书宋' ), 'a display name is not a catalog family' );

$wf_css   = '@font-face{font-family:"WF-Fzss";src:url("WF-Fzss-1.woff2") format("woff2");}@font-face{font-family:"WF-Fzss";src:url("WF-Fzss-2.woff2");}';
$pass_css = '@font-face{font-family:"wenfeng-fzss";src:url("fzss-1.woff2");}';
$zip_css  = '@font-face{font-family:"方正书宋";src:url("a.woff2");}';

$assert( 'wenfeng-fzss' === WPFonts_Packages::package_slug_base( 'fzss-zhcommon.zip', $wf_css ), 'WF-Fzss in CSS beats the zip name' );
$assert( 'wenfeng-fzss' === WPFonts_Packages::package_slug_base( 'other-name.zip', $pass_css ), 'an existing wenfeng- family passes through' );
$assert( 'wenfeng-ibmps' === WPFonts_Packages::package_slug_base( 'ibm.zip', '@font-face{font-family:windfonts-ibmps;src:url(1.woff2);}' ), 'windfonts- in CSS becomes wenfeng-' );
$assert( 'fzss-zhcommon' === WPFonts_Packages::package_slug_base( 'fzss-zhcommon.zip', $zip_css ), 'zip name is the fallback' );
$assert( 'fzss-zhcommon' === WPFonts_Packages::package_slug_base( 'fzss-zhcommon.zip', 'body{color:red}' ), 'css without a face falls back to the zip name' );

$meta = '{"fontFamily":"WF-Fzss","family":"方正书宋"}';
$assert( 'wenfeng-fzss' === WPFonts_Packages::package_slug_base( 'fzss-zhcommon.zip', $zip_css, $meta ), 'metadata fontFamily wins over a display name in CSS' );
$assert( 'wenfeng-fzss' === WPFonts_Packages::metadata_catalog_family( array( 'font_family' => 'windfonts-fzss' ) ), 'font_family metadata maps' );
$assert( 'wenfeng-albbpht' === WPFonts_Packages::package_slug_base( 'fzss-zhcommon.zip', $wf_css, '{"fontFamily":"wenfeng-albbpht"}' ), 'metadata wins over a different CSS family' );
$assert( '方正书宋' === WPFonts_Packages::display_name_from_metadata( $meta ), 'display name stays human' );
$assert( '' === WPFonts_Packages::display_name_from_metadata( '{"family":"WF-Fzss"}' ), 'a token is not used as the display name' );

$rewritten = WPFonts_Packages::rewrite_family_css( $wf_css, 'wenfeng-fzss' );
$assert( false !== strpos( $rewritten, '"wenfeng-fzss"' ), 'legacy family rewritten: ' . $rewritten );
$assert( false !== strpos( $rewritten, 'WF-Fzss-1.woff2' ), 'first woff2 name stays' );
$assert( false !== strpos( $rewritten, 'WF-Fzss-2.woff2' ), 'second woff2 name stays' );
$assert( false === strpos( $rewritten, 'font-family:"WF-Fzss"' ), 'legacy font-family remained' );
$kept = WPFonts_Packages::rewrite_family_css( $pass_css, 'wenfeng-fzss' );
$assert( false !== strpos( $kept, '"wenfeng-fzss"' ), 'pass-through rewrite keeps wenfeng-fzss' );
$assert( false !== strpos( $kept, 'fzss-1.woff2' ), 'pass-through keeps the woff2 name' );
$assert( false !== strpos( WPFonts_Packages::rewrite_family_css( $zip_css, '' ), '方正书宋' ), 'an empty target does not rewrite CSS' );

$fixture = sys_get_temp_dir() . '/wpfonts-pkg-fixture-' . uniqid( '', true );
if ( ! mkdir( $fixture, 0777, true ) && ! is_dir( $fixture ) ) {
	fwrite( STDERR, "Could not create package fixture.\n" );
	exit( 1 );
}
file_put_contents( $fixture . '/result.css', $wf_css );
file_put_contents( $fixture . '/meta.json', $meta );
file_put_contents( $fixture . '/WF-Fzss-1.woff2', 'not-a-font' );
$from_tree = WPFonts_Packages::metadata_from_directory(
	$fixture,
	array( 'result.css', 'meta.json', 'WF-Fzss-1.woff2', '../meta.json' )
);
$assert( 'wenfeng-fzss' === $from_tree['token'], 'fixture metadata token drifted' );
$assert( '方正书宋' === $from_tree['name'], 'fixture display name drifted' );
$assert(
	'wenfeng-fzss' === WPFonts_Packages::package_slug_base( 'fzss-zhcommon.zip', (string) file_get_contents( $fixture . '/result.css' ), $from_tree['token'] ),
	'fixture slug did not follow metadata'
);
$zip_only = sys_get_temp_dir() . '/wpfonts-pkg-zip-' . uniqid( '', true );
if ( ! mkdir( $zip_only, 0777, true ) && ! is_dir( $zip_only ) ) {
	fwrite( STDERR, "Could not create zip-only fixture.\n" );
	exit( 1 );
}
file_put_contents( $zip_only . '/result.css', $zip_css );
$zip_tree = WPFonts_Packages::metadata_from_directory( $zip_only, array( 'result.css' ) );
$assert( '' === $zip_tree['token'], 'zip-only fixture invented a token' );
$assert(
	'fzss-zhcommon' === WPFonts_Packages::package_slug_base( 'fzss-zhcommon.zip', (string) file_get_contents( $zip_only . '/result.css' ), $zip_tree['token'] ),
	'zip-only fixture did not keep the file name'
);

$xxzt_css = "/* 玄宗体 - Regular (full) */\n@font-face{font-family:\"WF-Xxzt\";src:url('0.woff2') format(\"woff2\");}";
$assert( '玄宗体' === WPFonts_Packages::title_from_css( $xxzt_css ), 'css comment title drifted' );
$syht_css = "/* 思源黑体 Regular - Regular (zh-common) */\n@font-face{font-family:\"wenfeng-syhtcjk\";src:url('0.woff2');}";
$assert( '思源黑体' === WPFonts_Packages::title_from_css( $syht_css ), 'syhtcjk style word stayed on the title' );
$assert( '文风·思源黑体' === WPFonts_Packages::resolve_display_name( '', 'wenfeng-syhtcjk', $syht_css, 'wenfeng-syhtcjk' ), 'syhtcjk comment did not become 文风·思源黑体' );
$assert( '思源黑体' === WPFonts_Packages::title_from_css( "/* 思源黑体  Regular   Italic - Regular */\n" ), 'repeated style words stayed' );
$assert( '思源黑体' === WPFonts_Packages::title_from_css( "/* 思源黑体 常规 - Regular */\n" ), 'Chinese style word stayed' );
$assert( '方正书宋' === WPFonts_Packages::title_from_css( "/* 方正书宋 Bold - Bold */\n" ), 'Bold stayed on the family title' );
$assert( 'Blackletter' === WPFonts_Packages::title_from_css( "/* Blackletter - Regular */\n" ), 'Blackletter was trimmed as a style word' );
$assert( '细体' === WPFonts_Packages::title_from_css( "/* 细体 - Regular */\n" ), 'a one-word title was stripped' );
$assert( '鸿雷小纸条青春体' === WPFonts_Packages::title_from_css( "/* 鸿雷小纸条青春体 - Regular (full) */\n" ), '体 was treated as a style word' );
$assert( '' === WPFonts_Packages::title_from_css( "/* Generated By cn-font-split@7.6.8 */\n@font-face{font-family:\"WF-Xxzt\";}" ), 'generator comment became a title' );
$assert( '' === WPFonts_Packages::title_from_css( "/* WF-Xxzt - Regular */\n" ), 'a token in the css comment became a title' );
$assert( '文风·玄宗体' === WPFonts_Packages::resolve_display_name( '', 'wenfeng-xxzt', $xxzt_css, 'wenfeng-xxzt' ), 'css comment did not become 文风·玄宗体' );
$assert( '文风·方正书宋' === WPFonts_Packages::resolve_display_name( '方正书宋', 'wenfeng-xxzt', $xxzt_css, 'wenfeng-xxzt' ), 'metadata did not win' );
$assert( '文风·玄宗体' === WPFonts_Packages::resolve_display_name( '文风·玄宗体', 'wenfeng-xxzt', '', 'wenfeng-xxzt' ), 'prefix was applied twice' );
$assert( '文风·玄宗体' === WPFonts_Packages::resolve_display_name( 'WF-Xxzt', 'wenfeng-xxzt', $xxzt_css, 'wenfeng-xxzt' ), 'token metadata was used as the title' );
$assert( 'custom-pack' === WPFonts_Packages::resolve_display_name( '', 'custom-pack', "/* Generated By cn-font-split */\n", 'custom-pack' ), 'slug fallback was prefixed' );
$assert( 'wenfeng-xxzt' === WPFonts_Packages::resolve_display_name( 'wenfeng-xxzt', 'wenfeng-xxzt', '@font-face{font-family:"WF-Xxzt";}', 'wenfeng-xxzt' ), 'token-only name was prefixed' );
$assert( '玄宗体' === WPFonts_Packages::display_name_from_metadata( array( 'name' => 'wenfeng-xxzt', 'chinese' => '玄宗体' ) ), 'chinese metadata was skipped' );

/**
 * Catalog stand-in. This file does not load the real catalog class.
 */
class WPFonts_Catalog {

	/**
	 * @return array<int,array{name:string,fontFamily:string}>
	 */
	public static function stored_fonts() {
		return ( isset( $GLOBALS['wpfonts_catalog_rows'] ) && is_array( $GLOBALS['wpfonts_catalog_rows'] ) ) ? $GLOBALS['wpfonts_catalog_rows'] : array();
	}
}

$GLOBALS['wpfonts_catalog_rows'] = array(
	array(
		'name'       => '玄宗体',
		'fontFamily' => 'wenfeng-xzt',
	),
	array(
		'name'       => 'wenfeng-syhtsc',
		'fontFamily' => 'wenfeng-syhtsc',
	),
);
$assert( '文风·玄宗体' === WPFonts_Packages::resolve_display_name( '', 'wenfeng-xzt', "/* 注释里的别名 - Regular */\n", 'wenfeng-xzt' ), 'catalog name lost to the css comment' );
$assert( '文风·玄宗体' === WPFonts_Packages::resolve_display_name( '', 'wenfeng-xxzt', $xxzt_css, 'wenfeng-xxzt' ), 'xxzt used the xzt catalog row' );
$assert( '' === WPFonts_Packages::catalog_display_name( 'wenfeng-syhtsc' ), 'a catalog token was returned as a name' );
$assert( '文风·方正书宋' === WPFonts_Packages::resolve_display_name( '方正书宋', 'wenfeng-xzt', "/* 注释里的别名 - Regular */\n", 'wenfeng-xzt' ), 'metadata lost to the catalog' );
$GLOBALS['wpfonts_catalog_rows'][] = array(
	'name'       => '思源黑体 Regular',
	'fontFamily' => 'wenfeng-syhtcjk',
);
$assert( '文风·思源黑体 Regular' === WPFonts_Packages::resolve_display_name( '', 'wenfeng-syhtcjk', $syht_css, 'wenfeng-syhtcjk' ), 'catalog name was suffix-stripped' );

$assert( '文风·玄宗体' === WPFonts_Packages::healed_display_name( 'wenfeng-xxzt', 'wenfeng-xxzt', $xxzt_css ), 'token name was not healed' );
$assert( '文风·玄宗体' === WPFonts_Packages::healed_display_name( 'wenfeng-xxzt', 'WF-Xxzt', $xxzt_css ), 'WF-* name was not healed' );
$assert( '文风·玄宗体' === WPFonts_Packages::healed_display_name( 'wenfeng-xxzt', 'windfonts-xxzt', $xxzt_css ), 'windfonts-* name was not healed' );
$assert( '文风·玄宗体' === WPFonts_Packages::healed_display_name( 'wenfeng-xxzt', '', $xxzt_css ), 'empty name was not healed' );
$assert( '方正书宋' === WPFonts_Packages::healed_display_name( 'wenfeng-fzss', '方正书宋', $xxzt_css ), 'an existing human name was replaced' );
$assert( 'custom-pack' === WPFonts_Packages::healed_display_name( 'custom-pack', 'custom-pack', 'body{color:red}' ), 'slug-only heal was prefixed' );
$assert( true === WPFonts_Packages::display_name_needs_heal( 'wenfeng-xxzt', 'wenfeng-xxzt' ), 'token name was not marked for heal' );
$assert( false === WPFonts_Packages::display_name_needs_heal( '方正书宋', 'wenfeng-fzss' ), 'human name was marked for heal' );

$heal_root = sys_get_temp_dir() . '/wpfonts-heal-' . uniqid( '', true );
$heal_dir  = $heal_root . '/wpfonts/packages/wenfeng-xxzt';
if ( ! mkdir( $heal_dir, 0777, true ) && ! is_dir( $heal_dir ) ) {
	fwrite( STDERR, "Could not create heal fixture.\n" );
	exit( 1 );
}
file_put_contents( $heal_dir . '/local.css', $xxzt_css );
$GLOBALS['wpfonts_heal_upload']  = $heal_root;
$GLOBALS['wpfonts_option_writes'] = array();
$GLOBALS['wpfonts_options']       = array(
	'wpfonts_packages' => array(
		array(
			'id'           => 'wenfeng-xxzt',
			'name'         => 'wenfeng-xxzt',
			'family'       => 'wenfeng-xxzt',
			'enabled'      => 1,
			'installed_at' => 1793495730,
		),
		array(
			'id'           => 'wenfeng-fzss',
			'name'         => '方正书宋',
			'family'       => 'wenfeng-fzss',
			'enabled'      => 1,
			'installed_at' => 10,
		),
	),
);

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
 * @param string $name Option name.
 * @param mixed  $value Value.
 * @param mixed  $autoload Autoload.
 * @return bool
 */
function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	$GLOBALS['wpfonts_options'][ $name ]  = $value;
	$GLOBALS['wpfonts_option_writes'][]   = $name;
	return true;
}

/**
 * @return array{basedir:string,baseurl:string,error:bool}
 */
function wp_upload_dir() {
	return array(
		'basedir' => $GLOBALS['wpfonts_heal_upload'],
		'baseurl' => 'https://example.test/wp-content/uploads',
		'error'   => false,
	);
}

$healed_rows = WPFonts_Packages::all();
$assert( 2 === count( $healed_rows ), 'heal dropped a package' );
$assert( 'wenfeng-xxzt' === $healed_rows[0]['id'], 'heal changed the package id' );
$assert( 'wenfeng-xxzt' === $healed_rows[0]['family'], 'heal changed the css family' );
$assert( '文风·玄宗体' === $healed_rows[0]['name'], 'saved wenfeng-xxzt name was not healed' );
$assert( 'wenfeng-fzss' === $healed_rows[1]['id'], 'heal changed the untouched id' );
$assert( 'wenfeng-fzss' === $healed_rows[1]['family'], 'heal changed the untouched family' );
$assert( '方正书宋' === $healed_rows[1]['name'], 'heal prefixed an existing human name' );
$assert( array( 'wpfonts_packages' ) === $GLOBALS['wpfonts_option_writes'], 'heal did not save the list' );
$saved_rows = get_option( 'wpfonts_packages' );
$assert( is_array( $saved_rows ) && '文风·玄宗体' === $saved_rows[0]['name'], 'option still stores wenfeng-xxzt' );
$assert( is_array( $saved_rows ) && 'wenfeng-xxzt' === $saved_rows[0]['family'], 'option family was rewritten' );
$assert( false !== strpos( (string) file_get_contents( $heal_dir . '/local.css' ), 'WF-Xxzt' ), 'heal rewrote local.css' );
$again = WPFonts_Packages::all();
$assert( '文风·玄宗体' === $again[0]['name'] && 'wenfeng-xxzt' === $again[0]['family'], 'second read changed the healed row' );
$assert( 1 === count( $GLOBALS['wpfonts_option_writes'] ), 'second read saved the list again' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: local package paths stay inside the package.\n";
