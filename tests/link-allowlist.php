<?php
/**
 * Allowlist and settings-merge checks. No WordPress, no network.
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/class-wpfonts-link.php';

$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$secret = 'wf_super_secret_key_value';

$base = array(
	'cdn_enabled'  => 1,
	'key_status'   => 'valid',
	'region'       => 'cn',
	'subset'       => 'zh-common',
	'family'       => 'wenfeng-ibmps',
	'project_slug' => '',
	'api_key'      => $secret,
);

$url = WPFonts_Link::embed_url( $base );
$assert( 'https://cn.windfonts.com/api/css?family=wenfeng-ibmps&subset=zh-common' === $url, 'public URL mismatch: ' . $url );
$assert( false === strpos( $url, $secret ), 'public URL contains the API key' );
$assert( false === strpos( $url, 'key=' ), 'public URL has a key query arg' );
$assert( WPFonts_Link::is_allowlisted( $url ), 'public URL is not allowlisted' );

$hk = $base;
$hk['region'] = 'hk';
$assert( 0 === strpos( WPFonts_Link::embed_url( $hk ), 'https://hk.windfonts.com/api/css?' ), 'hk host mismatch' );

$en = $base;
$en['region'] = 'en';
$assert( 0 === strpos( WPFonts_Link::embed_url( $en ), 'https://en.windfonts.com/api/css?' ), 'en host mismatch' );

$evil = $base;
$evil['region'] = 'https://evil.example/api/css';
$evil_url = WPFonts_Link::embed_url( $evil );
$assert( 0 === strpos( $evil_url, 'https://cn.windfonts.com/' ), 'hostile region was not forced back to cn: ' . $evil_url );

$project = $base;
$project['project_slug'] = '../studio';
$project['family']       = 'ignored-family';
$project_url             = WPFonts_Link::embed_url( $project );
$assert( 'https://cn.windfonts.com/p/studio/index.css' === $project_url, 'project URL mismatch: ' . $project_url );
$assert( false === strpos( $project_url, 'ignored-family' ), 'project URL kept the family' );
$assert( false === strpos( $project_url, '..' ), 'project URL kept traversal' );

$off = $base;
$off['cdn_enabled'] = 0;
$assert( '' === WPFonts_Link::embed_url( $off ), 'CDN off still embedded' );

foreach ( array( 'invalid', 'unreachable', '', 'valid-looking' ) as $status ) {
	$row               = $base;
	$row['key_status'] = $status;
	$assert( '' === WPFonts_Link::embed_url( $row ), 'status ' . $status . ' still embedded' );
}

$nofamily = $base;
$nofamily['family'] = '';
$assert( '' === WPFonts_Link::embed_url( $nofamily ), 'family-less public CSS was embedded' );
$draft = WPFonts_Link::stylesheet_url( $nofamily );
$assert( 'https://cn.windfonts.com/api/css?subset=zh-common' === $draft, 'draft public URL mismatch: ' . $draft );
$assert( ! WPFonts_Link::is_allowlisted( 'http://cn.windfonts.com/api/css' ), 'http was allowed' );
$assert( ! WPFonts_Link::is_allowlisted( 'https://user:pass@cn.windfonts.com/api/css' ), 'userinfo was allowed' );
$assert( ! WPFonts_Link::is_allowlisted( 'https://cn.windfonts.com/api/css?key=secret' ), 'key query was allowed' );
$assert( ! WPFonts_Link::is_allowlisted( 'https://fonts.example/api/css?family=wenfeng-ibmps' ), 'foreign host was allowed' );
$assert( ! WPFonts_Link::is_allowlisted( 'https://cn.windfonts.com/api/css/../../etc' ), 'path traversal was allowed' );

$prev  = array(
	'cdn_enabled'  => 0,
	'region'       => 'hk',
	'subset'       => 'zh',
	'project_slug' => 'studio',
	'family'       => 'wenfeng-ibmps',
	'key_status'   => 'valid',
	'key_checked'  => 12,
);
$input = array(
	'cdn_enabled'  => '1',
	'region'       => 'en',
	'subset'       => 'full',
	'project_slug' => 'hacked',
	'family'       => 'other-font',
	'key_status'   => 'invalid',
	'api_key'      => $secret,
);
$simple = WPFonts_Link::merge_public( $prev, $input, 'simple' );
$assert( 1 === $simple['cdn_enabled'], 'simple save did not enable CDN' );
$assert( 'en' === $simple['region'], 'simple save dropped region' );
$assert( 'full' === $simple['subset'], 'simple save dropped subset' );
$assert( 'studio' === $simple['project_slug'], 'simple save overwrote the project' );
$assert( 'other-font' === $simple['family'], 'simple save ignored the font name field' );
$assert( 'valid' === $simple['key_status'], 'posted key_status was trusted' );

$without_family = $input;
unset( $without_family['family'] );
$kept = WPFonts_Link::merge_public( $prev, $without_family, 'simple' );
$assert( 'wenfeng-ibmps' === $kept['family'], 'simple save without a family field cleared it' );
$assert( 'studio' === $kept['project_slug'], 'simple save without a family field overwrote the project' );

$broken                 = $base;
$broken['family']       = 'wenfeng-zhi-mang-xing';
$broken['subset']       = 'zh-common';
$broken['css_status']   = 'missing';
$broken_url             = WPFonts_Link::stylesheet_url( $broken );
$assert( 'https://cn.windfonts.com/api/css?family=wenfeng-zhi-mang-xing&subset=zh-common' === $broken_url, 'broken preview URL drifted: ' . $broken_url );
$assert( '' === WPFonts_Link::embed_url( $broken ), 'a 404 family and subset was treated as a healthy embed' );
$assert( array() === WPFonts_Link::front_stylesheets( $broken, 'wenfeng-zhi-mang-xing', array() ), 'a missing stylesheet was still printed' );

$ibmps_css = '/* IBM */ @font-face { font-family: "windfonts-ibmps"; src: url(https://cn.windfonts.com/a.woff2); }';
$probed    = WPFonts_Link::interpret_stylesheet( 200, $ibmps_css );
$assert( 'ok' === $probed['status'], 'ibmps CSS was not accepted' );
$assert( 'windfonts-ibmps' === $probed['face'], 'ibmps face name drifted: ' . $probed['face'] );
$miss = WPFonts_Link::interpret_stylesheet( 404, '{"code":404,"message":"字体 wenfeng-zhi-mang-xing 的 regular 字重 zh-common 版本不存在"}' );
$assert( 'missing' === $miss['status'] && '' === $miss['face'], '404 stylesheet was treated as healthy' );
$quiet = WPFonts_Link::interpret_stylesheet( 0, '' );
$assert( 'unchecked' === $quiet['status'], 'a timeout was stored as a miss' );

$confirmed               = $base;
$confirmed['css_status'] = 'ok';
$confirmed['css_face']   = 'windfonts-ibmps';
$assert( 'https://cn.windfonts.com/api/css?family=wenfeng-ibmps&subset=zh-common' === WPFonts_Link::embed_url( $confirmed ), 'confirmed ibmps embed drifted' );

$off_sheets = WPFonts_Link::front_stylesheets( $off, '', array() );
$assert( array() === $off_sheets, 'CDN off still printed a stylesheet' );

$advanced = WPFonts_Link::merge_public( $prev, $input, 'advanced' );
$assert( 'hacked' === $advanced['project_slug'], 'advanced save kept the old project' );
$assert( 'other-font' === $advanced['family'], 'advanced save kept the old family' );
$assert( 'valid' === $advanced['key_status'], 'advanced save trusted posted key_status' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: allowlist keeps the key out of the stylesheet URL.\n";
