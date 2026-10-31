<?php
/**
 * Health-check interpretation. No network.
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/class-wpfonts-link.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-health.php';

$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$assert( 'valid' === WPFonts_Health::interpret( 200, '{"code":0,"data":{"ok":true}}' ), '200 should be valid' );
$assert( 'invalid' === WPFonts_Health::interpret( 401, '{"code":401,"errorCode":"invalid_api_key","message":"no"}' ), 'invalid_api_key should be invalid' );
$assert( 'invalid' === WPFonts_Health::interpret( 401, '{"code":401,"errorCode":"invalid_api_key_format","message":"no"}' ), 'bad format should be invalid' );
$assert( 'unreachable' === WPFonts_Health::interpret( 401, '{"code":401,"errorCode":"missing_api_key","message":"no"}' ), 'missing header is not an invalid key' );
$assert( 'invalid' === WPFonts_Health::interpret( 403, '' ), '403 should be invalid' );
$assert( 'unreachable' === WPFonts_Health::interpret( 404, '<Error>NoSuchKey</Error>' ), 'CDN 404 is not an invalid key' );
$assert( 'unreachable' === WPFonts_Health::interpret( 500, '' ), '500 should be unreachable' );
$assert( 'unreachable' === WPFonts_Health::interpret( 0, '' ), 'no response should be unreachable' );
$assert( 'unreachable' === WPFonts_Health::interpret( 429, '{"message":"slow down"}' ), '429 should be unreachable' );
$assert( WPFonts_Link::KEY_STATUS_URL === 'https://app.windfonts.com/api/key/status', 'health URL drifted' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: health results do not treat a missed connection as a bad key.\n";
