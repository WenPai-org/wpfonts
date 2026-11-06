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
$confirmed_url = WPFonts_Link::embed_url( $confirmed );
$assert( 'https://cn.windfonts.com/api/css?family=wenfeng-ibmps&subset=zh-common' === $confirmed_url, 'confirmed ibmps embed drifted' );
$license = '<!-- 此中文网页字体由文风字体（Windfonts）免费提供，您可以自由引用，请务必保留此授权许可标注 https://wenfeng.org/license -->';
$assert( WPFonts_Link::CDN_LICENSE_NOTE === $license, 'license note drifted' );
$snippet = WPFonts_Link::embed_snippet( $confirmed_url );
$note_at = strpos( $snippet, $license );
$pre_at  = strpos( $snippet, '<link rel="preconnect" href="https://cn.windfonts.com" crossorigin>' );
$css_at  = strpos( $snippet, '<link rel="stylesheet" href="' . $confirmed_url . '">' );
$assert( 0 === $note_at, 'healthy snippet does not start with the license note' );
$assert( false !== $pre_at && false !== $css_at && $note_at < $pre_at && $pre_at < $css_at, 'embed order is not note, preconnect, stylesheet' );
$hk_snip = WPFonts_Link::embed_snippet( 'https://hk.windfonts.com/api/css?family=wenfeng-ibmps&subset=zh-common' );
$assert( false !== strpos( $hk_snip, 'href="https://hk.windfonts.com" crossorigin' ), 'preconnect left the stylesheet host' );
$assert( '' === WPFonts_Link::embed_snippet( 'https://fonts.example/api/css?family=wenfeng-ibmps' ), 'a foreign host received an embed snippet' );
$assert( 'xzt' === WPFonts_Link::package_norm( 'wenfeng-xzt' ), 'xzt norm drifted' );
$assert( 'xzt' === WPFonts_Link::package_norm( 'Wenfeng-XZT' ), 'norm was not lowercased' );
$assert( 'syhtsc' === WPFonts_Link::package_norm( 'wenfeng-syhtsc' ), 'syhtsc norm drifted' );
$assert( 'xxzt' === WPFonts_Link::package_norm( 'wenfeng-xxzt' ), 'xxzt short was aliased inside package_norm' );
$assert( 'syhtcjk' === WPFonts_Link::package_norm( 'wenfeng-syhtcjk' ), 'syhtcjk short was aliased inside package_norm' );
$assert( '' === WPFonts_Link::package_norm( 'demo' ), 'a zip id invented a norm' );
$assert( '' === WPFonts_Link::package_norm( 'wenfeng-' ), 'an empty short invented a norm' );
$assert( 'xzt' === WPFonts_Link::package_cdn_norm( 'wenfeng-xzt' ), 'xzt CDN folder was rewritten' );
$assert( 'xzt' === WPFonts_Link::package_cdn_norm( 'wenfeng-xxzt' ), 'xxzt CDN folder drifted' );
$assert( 'xzt' === WPFonts_Link::package_cdn_norm( 'Wenfeng-XXZT' ), 'xxzt CDN folder was not lowercased' );
$assert( 'syhtsc' === WPFonts_Link::package_cdn_norm( 'wenfeng-syhtsc' ), 'syhtsc CDN folder was rewritten' );
$assert( 'syhtsc' === WPFonts_Link::package_cdn_norm( 'wenfeng-syhtcjk' ), 'syhtcjk CDN folder drifted' );
$assert( '' === WPFonts_Link::package_cdn_norm( 'demo' ), 'a zip id invented a CDN folder' );
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.webp' === WPFonts_Link::package_preview_url( 'wenfeng-xzt', 'preview', 'webp', 'cn' ), 'xzt preview URL drifted' );
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.webp' === WPFonts_Link::package_preview_url( 'wenfeng-xxzt', 'preview', 'webp', 'cn' ), 'xxzt specimen still 404s' );
$assert( 'https://cn.windfonts.com/fonts-packages/syhtsc/preview/preview.webp' === WPFonts_Link::package_preview_url( 'wenfeng-syhtcjk', 'preview', 'webp', 'cn' ), 'syhtcjk specimen still 404s' );
$assert( 'https://hk.windfonts.com/fonts-packages/xzt/preview/card.png' === WPFonts_Link::package_preview_url( 'wenfeng-xxzt', 'card', 'png', 'hk' ), 'xxzt card left the aliased folder' );
$assert( true === WPFonts_Link::is_package_specimen_url( 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.webp', WPFonts_Link::package_cdn_norm( 'wenfeng-xxzt' ), 'preview' ), 'aliased specimen was rejected' );
$assert( false === WPFonts_Link::is_package_specimen_url( 'https://cn.windfonts.com/fonts-packages/xxzt/preview/preview.webp', WPFonts_Link::package_cdn_norm( 'wenfeng-xxzt' ), 'preview' ), 'raw short path passed the aliased allowlist' );
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/card.webp' === WPFonts_Link::package_preview_url( 'wenfeng-xzt', 'card', 'webp', 'cn' ), 'xzt card URL drifted' );
$xzt_font = WPFonts_Link::package_preview_url( 'wenfeng-xzt', 'fontPreview', 'webp', 'cn' );
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.webp' === $xzt_font, 'xzt fontPreview URL drifted' );
$assert( false !== strpos( $xzt_font, '/preview/fontpreview.webp' ), 'derived fontPreview path is not /preview/fontpreview.webp' );
$assert( false === strpos( $xzt_font, 'fontPreview.webp' ), 'derived fontPreview URL kept camelCase' );
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.webp' === WPFonts_Link::package_preview_url( 'wenfeng-xxzt', 'fontpreview', 'webp', 'cn' ), 'xxzt fontPreview left the aliased folder' );
$assert( 'https://en.windfonts.com/fonts-packages/syhtsc/preview/fontpreview.png' === WPFonts_Link::package_preview_url( 'wenfeng-syhtcjk', 'fontPreview', 'png', 'en' ), 'syhtcjk fontPreview left the aliased folder' );
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.png' === WPFonts_Link::specimen_png_url( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.webp' ), 'fontpreview webp did not fall back to png' );
$assert( '' === WPFonts_Link::specimen_png_url( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontPreview.webp' ), 'camelCase fontPreview still produced a png sibling' );
$assert( false === WPFonts_Link::is_package_specimen_url( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontPreview.webp', 'xzt', 'fontpreview' ), 'camelCase fontPreview was accepted' );
$assert( true === WPFonts_Link::is_package_specimen_url( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.webp', 'xzt', 'fontPreview' ), 'lowercase fontpreview was rejected' );
$assert( true === WPFonts_Link::is_package_specimen_url( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.png', 'xzt', 'fontPreview' ), 'lowercase fontpreview png was rejected' );
$assert( 'https://cn.windfonts.com/fonts-packages/syhtsc/preview/preview.webp' === WPFonts_Link::package_preview_url( 'wenfeng-syhtsc', 'preview', 'webp', 'cn' ), 'syhtsc preview URL drifted' );
$assert( 'https://hk.windfonts.com/fonts-packages/xzt/preview/preview.png' === WPFonts_Link::package_preview_url( 'wenfeng-xzt', 'preview', 'png', 'hk' ), 'png specimen left the site host' );
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.png' === WPFonts_Link::specimen_png_url( 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.webp' ), 'webp did not fall back to png' );
$assert( '' === WPFonts_Link::specimen_png_url( 'https://cdn.example.test/fonts-packages/xzt/preview/preview.webp' ), 'a second CDN host was accepted as a specimen' );
$assert( '' === WPFonts_Link::package_preview_url( 'not-wenfeng', 'preview', 'webp', 'cn' ), 'a non-wenfeng slug invented a specimen' );
$assert( '' === WPFonts_Link::package_preview_url( 'wenfeng-xzt', 'script', 'webp', 'cn' ), 'an unknown specimen asset was built' );
$xzt_row = WPFonts_Link::package_specimen_urls(
	'wenfeng-xzt',
	'cn',
	array(
		'assets' => array(
			'previewUrl'    => 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.webp',
			'cardPreviewUrl' => 'https://evil.example/card.webp',
		),
	)
);
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.webp' === $xzt_row['previewUrl'], 'supplied preview webp was dropped' );
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/card.webp' === $xzt_row['cardPreviewUrl'], 'a foreign card URL replaced the derived specimen' );
$png_row = WPFonts_Link::package_specimen_urls(
	'wenfeng-xzt',
	'cn',
	array(
		'previewUrl' => 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.png',
	)
);
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.webp' === $png_row['previewUrl'], 'a png specimen replaced webp' );
$alias_row = WPFonts_Link::package_specimen_urls(
	'wenfeng-xxzt',
	'cn',
	array(
		'previewUrl'     => 'https://cn.windfonts.com/fonts-packages/xxzt/preview/preview.webp',
		'cardPreviewUrl' => 'https://cn.windfonts.com/fonts-packages/xzt/preview/card.webp',
	)
);
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.webp' === $alias_row['previewUrl'], 'raw short specimen was kept' );
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/card.webp' === $alias_row['cardPreviewUrl'], 'aliased card specimen was dropped' );
$syht_row = WPFonts_Link::package_specimen_urls( 'wenfeng-syhtcjk', 'en' );
$assert( 'https://en.windfonts.com/fonts-packages/syhtsc/preview/preview.webp' === $syht_row['previewUrl'], 'syhtcjk preview left the aliased folder' );
$assert( 'https://en.windfonts.com/fonts-packages/syhtsc/preview/card.webp' === $syht_row['cardPreviewUrl'], 'syhtcjk card left the aliased folder' );
$assert( 'https://en.windfonts.com/fonts-packages/syhtsc/preview/fontpreview.webp' === $syht_row['fontPreviewUrl'], 'syhtcjk fontPreview left the aliased folder' );
$assert( false !== strpos( $syht_row['fontPreviewUrl'], '/preview/fontpreview.webp' ), 'derived fontPreviewUrl path is not /preview/fontpreview.webp' );
$font_row = WPFonts_Link::package_specimen_urls(
	'wenfeng-xxzt',
	'cn',
	array(
		'assets' => array(
			'fontPreviewUrl' => 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.webp',
			'previewUrl'     => 'https://evil.example/fontpreview.webp',
		),
	)
);
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.webp' === $font_row['fontPreviewUrl'], 'aliased fontpreview was dropped' );
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/preview.webp' === $font_row['previewUrl'], 'a foreign preview replaced the derived specimen' );
$font_camel = WPFonts_Link::package_specimen_urls(
	'wenfeng-xzt',
	'cn',
	array(
		'fontPreviewUrl' => 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontPreview.webp',
	)
);
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.webp' === $font_camel['fontPreviewUrl'], 'camelCase fontPreview replaced the derived specimen' );
$font_png = WPFonts_Link::package_specimen_urls(
	'wenfeng-xzt',
	'cn',
	array(
		'fontPreviewUrl' => 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.png',
	)
);
$assert( 'https://cn.windfonts.com/fonts-packages/xzt/preview/fontpreview.webp' === $font_png['fontPreviewUrl'], 'a png fontpreview replaced webp' );
$assert( '' === WPFonts_Link::preconnect_origin( 'https://cn.windfonts.com/api/css?family=wenfeng-zhi-mang-xing&subset=zh-common' ) || false !== strpos( WPFonts_Link::embed_snippet( 'https://cn.windfonts.com/api/css?family=wenfeng-zhi-mang-xing&subset=zh-common' ), $license ), 'a shown link dropped the license note' );
$embed_src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-embed.php' );
$assert( false !== strpos( $embed_src, 'print_license_note' ), 'front head does not print the license note' );
$assert( false !== strpos( $embed_src, 'CDN_LICENSE_NOTE' ), 'front embed does not use the license constant' );

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
