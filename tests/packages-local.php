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

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: local package paths stay inside the package.\n";
