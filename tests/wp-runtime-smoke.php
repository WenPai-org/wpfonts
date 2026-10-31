<?php
/**
 * WordPress runtime smoke test for wp eval-file.
 *
 * Requires WordPress 6.5+ with WPFonts active. Asserts four collections,
 * 48 language-prefixed families, the authenticated Font Collections REST
 * response, and a theme.json font-family round trip.
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_Font_Library' ) ) {
	fwrite( STDERR, "WordPress 6.5+ runtime is required.\n" );
	exit( 1 );
}

$failures  = array();
$slugs     = array( 'wp-fonts-stacks-cn', 'wp-fonts-stacks-en', 'wp-fonts-stacks-jp', 'wp-fonts-stacks-kr' );
$prefixes  = array(
	'wp-fonts-stacks-cn' => 'cn-',
	'wp-fonts-stacks-en' => 'en-',
	'wp-fonts-stacks-jp' => 'jp-',
	'wp-fonts-stacks-kr' => 'kr-',
);
$all_slugs = array();
$total     = 0;
$library   = WP_Font_Library::get_instance();

foreach ( $slugs as $slug ) {
	$collection = $library->get_font_collection( $slug );
	if ( ! $collection ) {
		$failures[] = 'Missing collection: ' . $slug;
		continue;
	}
	$data = $collection->get_data();
	if ( is_wp_error( $data ) ) {
		$failures[] = $slug . ': ' . $data->get_error_message();
		continue;
	}
	foreach ( $data['font_families'] as $family ) {
		$settings = $family['font_family_settings'];
		++$total;
		if ( isset( $all_slugs[ $settings['slug'] ] ) ) {
			$failures[] = 'Duplicate runtime font slug: ' . $settings['slug'];
		}
		if ( 0 !== strpos( $settings['slug'], $prefixes[ $slug ] ) ) {
			$failures[] = 'Slug is not language-prefixed: ' . $settings['slug'];
		}
		$all_slugs[ $settings['slug'] ] = true;
	}
}

wp_set_current_user( 1 );
$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/font-collections' ) );
if ( $response->is_error() ) {
	$failures[] = 'Font Collections REST request failed: ' . $response->as_error()->get_error_message();
} else {
	$rest_slugs = wp_list_pluck( $response->get_data(), 'slug' );
	foreach ( $slugs as $slug ) {
		if ( ! in_array( $slug, $rest_slugs, true ) ) {
			$failures[] = 'REST response missing collection: ' . $slug;
		}
	}
}

$sample     = $library->get_font_collection( 'wp-fonts-stacks-cn' )->get_data()['font_families'][0]['font_family_settings'];
$theme      = new WP_Theme_JSON(
	array(
		'version'  => 3,
		'settings' => array(
			'typography' => array(
				'fontFamilies' => array(
					'custom' => array( $sample ),
				),
			),
		),
	)
);
$round_trip = $theme->get_settings()['typography']['fontFamilies']['custom'][0] ?? array();
if ( ( $round_trip['fontFamily'] ?? '' ) !== $sample['fontFamily'] ) {
	$failures[] = 'theme.json font-family round trip failed.';
}
if ( 48 !== $total ) {
	$failures[] = 'Expected 48 runtime families, got ' . $total . '.';
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

printf( "PASS: 4 collections, %d families, authenticated REST, and theme.json round trip.\n", $total );
