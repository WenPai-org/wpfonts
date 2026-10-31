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

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: local package paths stay inside the package.\n";
