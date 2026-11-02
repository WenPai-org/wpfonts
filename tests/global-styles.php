<?php
/**
 * User Global Styles sync. No WordPress, no theme.json writes.
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$catalog_dir = sys_get_temp_dir() . '/wpfonts-gs-' . getmypid();
if ( ! is_dir( $catalog_dir ) && ! mkdir( $catalog_dir, 0777, true ) && ! is_dir( $catalog_dir ) ) {
	fwrite( STDERR, "Could not create temp dir.\n" );
	exit( 1 );
}
define( 'WPFONTS_CATALOG_PATH', $catalog_dir . '/catalog.json' );

$GLOBALS['wpfonts_actions']  = array();
$GLOBALS['wpfonts_options']  = array();
$GLOBALS['wpfonts_updates']  = array();
$GLOBALS['wpfonts_inserts']  = array();
$GLOBALS['wpfonts_terms']    = array();
$GLOBALS['wpfonts_gs_post']  = null;
$GLOBALS['wpfonts_can_edit'] = false;
$GLOBALS['wpfonts_cron']     = false;
$GLOBALS['wpfonts_cleaned']  = 0;

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted_args Accepted args.
 * @return void
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wpfonts_actions'][] = array( $hook, $callback, $priority, $accepted_args );
}

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted_args Accepted args.
 * @return void
 */
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
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
 * @param mixed $thing Thing.
 * @return bool
 */
function is_wp_error( $thing ) {
	unset( $thing );
	return false;
}

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
	$GLOBALS['wpfonts_options'][ $name ] = $value;
	$GLOBALS['wpfonts_option_writes'][]  = $name;
	return true;
}

/**
 * @param mixed $data Data.
 * @param int   $options Options.
 * @param int   $depth Depth.
 * @return string|false
 */
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

/**
 * @param string $value Value.
 * @return string
 */
function wp_slash( $value ) {
	return addslashes( (string) $value );
}

/**
 * @param string $value Value.
 * @return string
 */
function wp_unslash( $value ) {
	return stripslashes( (string) $value );
}

/**
 * @return string
 */
function get_stylesheet() {
	return 'fixture-theme';
}

/**
 * @param int $post_id Post id.
 * @return object|null
 */
function get_post( $post_id ) {
	unset( $post_id );
	return $GLOBALS['wpfonts_gs_post'];
}

/**
 * @param array $post Post fields.
 * @param bool  $wp_error Return an error object.
 * @return int
 */
function wp_update_post( $post, $wp_error = false ) {
	unset( $wp_error );
	$GLOBALS['wpfonts_updates'][] = $post;
	if ( is_object( $GLOBALS['wpfonts_gs_post'] ) ) {
		$GLOBALS['wpfonts_gs_post']->post_content = wp_unslash( $post['post_content'] );
	}
	return (int) $post['ID'];
}

/**
 * @param array $post Post fields.
 * @param bool  $wp_error Return an error object.
 * @return int
 */
function wp_insert_post( $post, $wp_error = false ) {
	unset( $wp_error );
	$GLOBALS['wpfonts_inserts'][] = $post;
	$GLOBALS['wpfonts_gs_post']   = (object) array(
		'ID'           => 8,
		'post_content' => wp_unslash( $post['post_content'] ),
	);
	return 8;
}

/**
 * @param int          $post_id Post id.
 * @param string|array $terms Terms.
 * @param string       $taxonomy Taxonomy.
 * @return array
 */
function wp_set_object_terms( $post_id, $terms, $taxonomy ) {
	$GLOBALS['wpfonts_terms'][] = array( $post_id, $terms, $taxonomy );
	return array( 1 );
}

/**
 * @param string $capability Capability.
 * @return bool
 */
function current_user_can( $capability ) {
	unset( $capability );
	return ! empty( $GLOBALS['wpfonts_can_edit'] );
}

/**
 * @return bool
 */
function wp_doing_cron() {
	return ! empty( $GLOBALS['wpfonts_cron'] );
}

/**
 * Stand-in for the user global styles lookup.
 */
class WP_Theme_JSON_Resolver {

	/**
	 * @return int
	 */
	public static function get_user_global_styles_post_id() {
		if ( is_object( $GLOBALS['wpfonts_gs_post'] ) && ! empty( $GLOBALS['wpfonts_gs_post']->ID ) ) {
			return (int) $GLOBALS['wpfonts_gs_post']->ID;
		}
		return 0;
	}

	/**
	 * @return void
	 */
	public static function clean_cached_data() {
		++$GLOBALS['wpfonts_cleaned'];
	}
}

require dirname( __DIR__ ) . '/includes/class-wpfonts-link.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-packages.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-catalog.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-settings.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-library.php';
require dirname( __DIR__ ) . '/includes/class-wpfonts-global-styles.php';

$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$package = WPFonts_Library::package_families(
	array(
		array(
			'id'      => 'demo',
			'name'    => '演示包',
			'family'  => 'IBM Plex Sans SC',
			'enabled' => 1,
		),
		array(
			'id'      => 'paused',
			'name'    => '停用包',
			'family'  => 'Paused Face',
			'enabled' => 0,
		),
	),
	array()
);
$cloud   = WPFonts_Library::confirmed_cloud_families(
	array(
		'cdn_enabled' => 1,
		'family'      => 'wenfeng-ibmps',
		'css_status'  => 'ok',
		'css_face'    => 'windfonts-ibmps',
	),
	'IBM Plex 无衬线简体中文'
);
$desired = array_merge( $package, $cloud );
$merged  = WPFonts_Global_Styles::merge_custom_families(
	array(
		array(
			'fontFamily' => 'Georgia, serif',
			'name'       => '用户字体',
			'slug'       => 'user-serif',
		),
		array(
			'fontFamily' => '"Old Face", sans-serif',
			'name'       => '旧名',
			'slug'       => 'wpfonts-pkg-demo',
			'fontFace'   => array(
				array(
					'src' => 'https://example.test/a.woff2',
				),
			),
		),
		array(
			'fontFamily' => '"Paused", sans-serif',
			'name'       => '停用包',
			'slug'       => 'wpfonts-pkg-paused',
		),
		array(
			'fontFamily' => '"Kept", sans-serif',
			'name'       => '别的文风',
			'slug'       => 'wenfeng-keep',
		),
		array(
			'fontFamily' => '"Gone", sans-serif',
			'name'       => '上次的云',
			'slug'       => 'wenfeng-old',
		),
		'leave-non-array',
	),
	$desired,
	array( 'wenfeng-old' )
);

$by_slug = array();
foreach ( $merged['custom'] as $item ) {
	if ( is_array( $item ) && isset( $item['slug'] ) ) {
		$by_slug[ $item['slug'] ] = $item;
	}
}
$assert( isset( $by_slug['user-serif'] ), 'unrelated family was removed' );
$assert( 'Georgia, serif' === $by_slug['user-serif']['fontFamily'], 'unrelated stack was rewritten' );
$assert( isset( $by_slug['wenfeng-keep'] ), 'a wenfeng family this plugin did not write was removed' );
$assert( ! isset( $by_slug['wpfonts-pkg-paused'] ), 'disabled package slug stayed' );
$assert( ! isset( $by_slug['wenfeng-old'] ), 'previously owned cloud slug stayed' );
$assert( isset( $by_slug['wpfonts-pkg-demo'] ), 'enabled package was not added' );
$assert( '"IBM Plex Sans SC", sans-serif' === $by_slug['wpfonts-pkg-demo']['fontFamily'], 'package stack was reinvented' );
$assert( '演示包' === $by_slug['wpfonts-pkg-demo']['name'], 'package name drifted' );
$assert( 'https://example.test/a.woff2' === $by_slug['wpfonts-pkg-demo']['fontFace'][0]['src'], 'existing fontFace was wiped' );
$assert( isset( $by_slug['wenfeng-ibmps'] ), 'confirmed cloud family was not added' );
$assert( '"windfonts-ibmps", sans-serif' === $by_slug['wenfeng-ibmps']['fontFamily'], 'cloud stack used the catalog token' );
$assert( 'IBM Plex 无衬线简体中文' === $by_slug['wenfeng-ibmps']['name'], 'cloud label drifted' );
$assert( ! isset( $by_slug['wenfeng-ibmps']['fontFace'] ), 'cloud preset invented a font file' );
$assert( in_array( 'leave-non-array', $merged['custom'], true ), 'non-array entry was dropped' );
$assert( array( 'wpfonts-pkg-demo', 'wenfeng-ibmps' ) === $merged['owned'], 'owned slug list drifted' );

$fallback = WPFonts_Global_Styles::merge_custom_families(
	array(
		array(
			'slug'       => 'wpfonts-cloud',
			'name'       => '云',
			'fontFamily' => '"Face", sans-serif',
		),
		array(
			'slug'       => 'user-serif',
			'name'       => '用户字体',
			'fontFamily' => 'Georgia, serif',
		),
	),
	array(),
	array()
);
$fallback_slugs = array();
foreach ( $fallback['custom'] as $item ) {
	$fallback_slugs[] = $item['slug'];
}
$assert( array( 'user-serif' ) === $fallback_slugs, 'the cloud fallback slug stayed after the stylesheet was cleared' );

$again = WPFonts_Global_Styles::merge_custom_families( $merged['custom'], $desired, $merged['owned'] );
$assert( wp_json_encode( $merged['custom'] ) === wp_json_encode( $again['custom'] ), 'second merge changed the list' );

$cleared = WPFonts_Global_Styles::merge_custom_families( $merged['custom'], $package, array( 'wenfeng-ibmps', 'wpfonts-pkg-demo' ) );
$cleared_slugs = array();
foreach ( $cleared['custom'] as $item ) {
	if ( is_array( $item ) && isset( $item['slug'] ) ) {
		$cleared_slugs[] = $item['slug'];
	}
}
$assert( ! in_array( 'wenfeng-ibmps', $cleared_slugs, true ), 'cleared cloud face stayed' );
$assert( in_array( 'user-serif', $cleared_slugs, true ), 'clearing the cloud face removed an unrelated family' );
$assert( in_array( 'wenfeng-keep', $cleared_slugs, true ), 'clearing the cloud face removed another wenfeng family' );

$user = array(
	'version'  => 3,
	'settings' => array(
		'color'      => array(
			'palette' => array(
				'theme' => array(
					array(
						'slug'  => 'ink',
						'name'  => '墨',
						'color' => '#111111',
					),
				),
			),
		),
		'typography' => array(
			'fontFamilies' => array(
				'theme'  => array(
					array(
						'slug'       => 'system',
						'name'       => '系统',
						'fontFamily' => 'sans-serif',
					),
				),
				'custom' => array(
					array(
						'slug'       => 'user-serif',
						'name'       => '用户字体',
						'fontFamily' => 'Georgia, serif',
					),
				),
			),
		),
	),
	'styles'   => array(
		'typography' => array(
			'fontFamily' => 'var(--wp--preset--font-family--user-serif)',
		),
	),
);
$placed = WPFonts_Global_Styles::place_custom_families( $user, $merged['custom'] );
$assert( true === $placed['changed'], 'origin map was treated as unchanged' );
$assert( '墨' === $placed['data']['settings']['color']['palette']['theme'][0]['name'], 'color palette was rewritten' );
$assert( 'system' === $placed['data']['settings']['typography']['fontFamilies']['theme'][0]['slug'], 'theme font families were rewritten' );
$assert( 'var(--wp--preset--font-family--user-serif)' === $placed['data']['styles']['typography']['fontFamily'], 'styles were rewritten' );
$assert( isset( $placed['data']['settings']['typography']['fontFamilies']['custom'] ), 'custom list was not stored under custom' );

$same = WPFonts_Global_Styles::place_custom_families( $placed['data'], $merged['custom'] );
$assert( false === $same['changed'], 'placing the same list reported a change' );

$flat = array(
	'settings' => array(
		'typography' => array(
			'fontFamilies' => array(
				array(
					'slug'       => 'user-serif',
					'name'       => '用户字体',
					'fontFamily' => 'Georgia, serif',
				),
			),
		),
	),
);
$flat_placed = WPFonts_Global_Styles::place_custom_families(
	$flat,
	array(
		array(
			'slug'       => 'user-serif',
			'name'       => '用户字体',
			'fontFamily' => 'Georgia, serif',
		),
		array(
			'fontFamily' => '"IBM Plex Sans SC", sans-serif',
			'name'       => '演示包',
			'slug'       => 'wpfonts-pkg-demo',
		),
	)
);
$flat_families = $flat_placed['data']['settings']['typography']['fontFamilies'];
$assert( true === $flat_placed['changed'], 'flat list was treated as unchanged' );
$assert( array( 0, 1 ) === array_keys( $flat_families ), 'flat list was wrapped in an origin map' );
$assert( 'user-serif' === $flat_families[0]['slug'], 'flat list dropped the existing family' );

$weird = array(
	'settings' => array(
		'typography' => array(
			'fontFamilies' => array(
				'custom' => array(
					'slug' => 'weird',
				),
			),
		),
	),
);
$weird_placed = WPFonts_Global_Styles::place_custom_families( $weird, array() );
$assert( false === $weird_placed['changed'], 'unrecognized custom shape was rewritten' );
$assert( 'weird' === $weird_placed['data']['settings']['typography']['fontFamilies']['custom']['slug'], 'unrecognized custom entry was wiped' );

$empty_user = array( 'styles' => array( 'color' => array( 'background' => '#fff' ) ) );
$empty_same = WPFonts_Global_Styles::place_custom_families( $empty_user, array() );
$assert( false === $empty_same['changed'], 'empty desired created a fontFamilies key' );
$assert( ! isset( $empty_same['data']['settings'] ), 'empty desired invented settings' );

$assert( false === WPFonts_Global_Styles::settings_need_sync(
	array(
		'css_status'  => 'ok',
		'css_face'    => 'windfonts-ibmps',
		'family'      => 'wenfeng-ibmps',
		'cdn_enabled' => 1,
		'key_checked' => 1,
	),
	array(
		'css_status'  => 'ok',
		'css_face'    => 'windfonts-ibmps',
		'family'      => 'wenfeng-ibmps',
		'cdn_enabled' => 1,
		'key_checked' => 9,
	)
), 'an unrelated settings save asked for a sync' );
$assert( true === WPFonts_Global_Styles::settings_need_sync(
	array( 'css_status' => 'ok', 'css_face' => 'windfonts-ibmps' ),
	array( 'css_status' => '', 'css_face' => '' )
), 'clearing a confirmed stylesheet did not ask for a sync' );
$assert( true === WPFonts_Global_Styles::settings_need_sync(
	array( 'css_status' => 'ok', 'css_face' => 'windfonts-ibmps', 'family' => 'wenfeng-ibmps' ),
	array( 'css_status' => 'ok', 'css_face' => 'windfonts-other', 'family' => 'wenfeng-ibmps' )
), 'a new face on an ok stylesheet did not ask for a sync' );

file_put_contents(
	WPFONTS_CATALOG_PATH,
	wp_json_encode(
		array(
			'updated_at' => 10,
			'fonts'      => array(
				array(
					'name'       => 'IBM Plex 无衬线简体中文',
					'fontFamily' => 'wenfeng-ibmps',
				),
			),
		)
	)
);

$initial = array(
	'version'                      => 3,
	'isGlobalStylesUserThemeJSON'  => true,
	'settings'                     => array(
		'color'      => array(
			'palette' => array(
				'theme' => array(
					array(
						'slug'  => 'ink',
						'name'  => '墨',
						'color' => '#111111',
					),
				),
			),
		),
		'typography' => array(
			'fontFamilies' => array(
				'theme'  => array(
					array(
						'slug'       => 'system',
						'name'       => '系统',
						'fontFamily' => 'sans-serif',
					),
				),
				'custom' => array(
					array(
						'slug'       => 'user-serif',
						'name'       => '用户字体',
						'fontFamily' => 'Georgia, serif',
					),
					array(
						'fontFamily' => '"Old Face", sans-serif',
						'name'       => '旧名',
						'slug'       => 'wpfonts-pkg-demo',
						'fontFace'   => array(
							array(
								'src' => 'https://example.test/a.woff2',
							),
						),
					),
					array(
						'fontFamily' => '"Paused", sans-serif',
						'name'       => '停用包',
						'slug'       => 'wpfonts-pkg-paused',
					),
					array(
						'fontFamily' => '"Kept", sans-serif',
						'name'       => '别的文风',
						'slug'       => 'wenfeng-keep',
						'fontFace'   => array(
							array(
								'src' => 'https://example.test/keep.woff2',
							),
						),
					),
					array(
						'fontFamily' => '"Gone", sans-serif',
						'name'       => '上次的云',
						'slug'       => 'wenfeng-old',
					),
				),
			),
		),
	),
	'styles'                       => array(
		'typography' => array(
			'fontFamily' => 'var(--wp--preset--font-family--user-serif)',
		),
	),
);
$GLOBALS['wpfonts_gs_post'] = (object) array(
	'ID'           => 5,
	'post_content' => wp_json_encode( $initial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
);
$GLOBALS['wpfonts_options'] = array(
	'wpfonts_gs_slugs' => array( 'wenfeng-old' ),
	'wpfonts_packages' => array(
		array(
			'id'      => 'demo',
			'name'    => '演示包',
			'family'  => 'IBM Plex Sans SC',
			'enabled' => 1,
		),
	),
	'wpfonts_settings' => array(
		'cdn_enabled'  => 1,
		'region'       => 'cn',
		'subset'       => 'zh-common',
		'family'       => 'wenfeng-ibmps',
		'key_status'   => 'valid',
		'css_status'   => 'ok',
		'css_face'     => 'windfonts-ibmps',
	),
);
$GLOBALS['wpfonts_updates']       = array();
$GLOBALS['wpfonts_option_writes'] = array();
$GLOBALS['wpfonts_can_edit']      = false;

WPFonts_Global_Styles::reconcile();
$assert( array() === $GLOBALS['wpfonts_updates'], 'a visitor reconciled Global Styles' );

$GLOBALS['wpfonts_can_edit'] = true;
WPFonts_Global_Styles::sync();
$stored = json_decode( $GLOBALS['wpfonts_gs_post']->post_content, true );
$assert( is_array( $stored ), 'stored global styles were not JSON' );
$custom = $stored['settings']['typography']['fontFamilies']['custom'];
$found  = array();
foreach ( $custom as $item ) {
	$found[ $item['slug'] ] = $item;
}
$assert( isset( $found['user-serif'] ) && isset( $found['wenfeng-keep'] ) && isset( $found['wpfonts-pkg-demo'] ) && isset( $found['wenfeng-ibmps'] ), 'sync dropped a family that should remain' );
$assert( ! isset( $found['wpfonts-pkg-paused'] ) && ! isset( $found['wenfeng-old'] ), 'sync left a removed family in place' );
$assert( '"IBM Plex Sans SC", sans-serif' === $found['wpfonts-pkg-demo']['fontFamily'], 'persisted package stack drifted' );
$assert( 'https://example.test/a.woff2' === $found['wpfonts-pkg-demo']['fontFace'][0]['src'], 'persisted fontFace was wiped' );
$assert( '"windfonts-ibmps", sans-serif' === $found['wenfeng-ibmps']['fontFamily'], 'persisted cloud stack used the catalog token' );
$assert( 'IBM Plex 无衬线简体中文' === $found['wenfeng-ibmps']['name'], 'persisted cloud label drifted' );
$assert( 'https://example.test/keep.woff2' === $found['wenfeng-keep']['fontFace'][0]['src'], 'an unrelated wenfeng face was rewritten' );
$assert( 'system' === $stored['settings']['typography']['fontFamilies']['theme'][0]['slug'], 'persisted theme families changed' );
$assert( '#111111' === $stored['settings']['color']['palette']['theme'][0]['color'], 'persisted colors changed' );
$assert( 'var(--wp--preset--font-family--user-serif)' === $stored['styles']['typography']['fontFamily'], 'persisted styles changed' );
$assert( false === strpos( $GLOBALS['wpfonts_gs_post']->post_content, 'theme.json' ), 'global styles JSON mentions theme.json' );
$assert( 1 === count( $GLOBALS['wpfonts_updates'] ), 'first sync did not write the post' );
$assert( $GLOBALS['wpfonts_cleaned'] > 0, 'resolver cache was not cleared' );
$assert( array( 'wpfonts-pkg-demo', 'wenfeng-ibmps' ) === $GLOBALS['wpfonts_options']['wpfonts_gs_slugs'], 'owned slugs were not recorded' );

$writes_after_first = count( $GLOBALS['wpfonts_updates'] );
WPFonts_Global_Styles::sync();
$assert( $writes_after_first === count( $GLOBALS['wpfonts_updates'] ), 'an in-sync list was written again' );

WPFonts_Global_Styles::on_settings(
	array(
		'css_status' => 'ok',
		'css_face'   => 'windfonts-ibmps',
		'family'     => 'wenfeng-ibmps',
	),
	array(
		'css_status'  => 'ok',
		'css_face'    => 'windfonts-ibmps',
		'family'      => 'wenfeng-ibmps',
		'key_checked' => 3,
	)
);
$assert( $writes_after_first === count( $GLOBALS['wpfonts_updates'] ), 'settings hook synced when the cloud face did not change' );

$GLOBALS['wpfonts_options']['wpfonts_settings']['css_status'] = '';
$GLOBALS['wpfonts_options']['wpfonts_settings']['css_face']   = '';
WPFonts_Global_Styles::on_settings(
	array(
		'css_status' => 'ok',
		'css_face'   => 'windfonts-ibmps',
		'family'     => 'wenfeng-ibmps',
	),
	array(
		'css_status' => '',
		'css_face'   => '',
		'family'     => 'wenfeng-ibmps',
	)
);
$stored = json_decode( $GLOBALS['wpfonts_gs_post']->post_content, true );
$slugs  = array();
foreach ( $stored['settings']['typography']['fontFamilies']['custom'] as $item ) {
	$slugs[] = $item['slug'];
}
$assert( ! in_array( 'wenfeng-ibmps', $slugs, true ), 'clearing css_status left the cloud family' );
$assert( in_array( 'wpfonts-pkg-demo', $slugs, true ), 'clearing css_status removed the package' );
$assert( in_array( 'wenfeng-keep', $slugs, true ), 'clearing css_status removed an unrelated family' );
$assert( in_array( 'user-serif', $slugs, true ), 'clearing css_status removed the user family' );

$GLOBALS['wpfonts_options']['wpfonts_packages'][0]['enabled'] = 0;
WPFonts_Global_Styles::on_store_change( array(), array(), 'wpfonts_packages' );
$stored = json_decode( $GLOBALS['wpfonts_gs_post']->post_content, true );
$slugs  = array();
foreach ( $stored['settings']['typography']['fontFamilies']['custom'] as $item ) {
	$slugs[] = $item['slug'];
}
$assert( ! in_array( 'wpfonts-pkg-demo', $slugs, true ), 'disabling a package left its family' );
$assert( array( 'user-serif', 'wenfeng-keep' ) === $slugs, 'disabling a package changed the remaining families' );

$corrupt                         = $GLOBALS['wpfonts_gs_post']->post_content;
$GLOBALS['wpfonts_gs_post']->post_content = '{';
$before_corrupt                  = count( $GLOBALS['wpfonts_updates'] );
WPFonts_Global_Styles::sync();
$assert( $before_corrupt === count( $GLOBALS['wpfonts_updates'] ), 'corrupt JSON was overwritten' );
$GLOBALS['wpfonts_gs_post']->post_content = $corrupt;

$flat_raw = wp_json_encode(
	array(
		'version'  => 2,
		'settings' => array(
			'typography' => array(
				'fontFamilies' => array(
					array(
						'slug'       => 'user-serif',
						'name'       => '用户字体',
						'fontFamily' => 'Georgia, serif',
					),
				),
			),
		),
		'styles'   => new stdClass(),
	),
	JSON_UNESCAPED_UNICODE
);
$GLOBALS['wpfonts_gs_post'] = (object) array(
	'ID'           => 6,
	'post_content' => $flat_raw,
);
$GLOBALS['wpfonts_options']['wpfonts_packages'][0]['enabled'] = 1;
$GLOBALS['wpfonts_options']['wpfonts_gs_slugs']               = array();
$GLOBALS['wpfonts_updates']                                   = array();
WPFonts_Global_Styles::sync();
$flat_stored = json_decode( $GLOBALS['wpfonts_gs_post']->post_content, true );
$flat_keys   = array_keys( $flat_stored['settings']['typography']['fontFamilies'] );
$assert( array( 0, 1 ) === $flat_keys, 'a flat fontFamilies list was converted to an origin map' );
$assert( 'user-serif' === $flat_stored['settings']['typography']['fontFamilies'][0]['slug'], 'flat list lost the user family' );
$assert( 'wpfonts-pkg-demo' === $flat_stored['settings']['typography']['fontFamilies'][1]['slug'], 'flat list did not gain the package' );
$assert( false !== strpos( $GLOBALS['wpfonts_gs_post']->post_content, '"styles":{}' ), 'styles object was rewritten as a list' );

$GLOBALS['wpfonts_gs_post']     = null;
$GLOBALS['wpfonts_inserts']     = array();
$GLOBALS['wpfonts_terms']       = array();
$GLOBALS['wpfonts_options']['wpfonts_gs_slugs'] = array();
WPFonts_Global_Styles::sync();
$assert( 1 === count( $GLOBALS['wpfonts_inserts'] ), 'missing user styles post was not created' );
$inserted = $GLOBALS['wpfonts_inserts'][0];
$assert( 'wp_global_styles' === $inserted['post_type'], 'created post is not wp_global_styles' );
$assert( 'wp-global-styles-fixture-theme' === $inserted['post_name'], 'created post name drifted' );
$assert( isset( $GLOBALS['wpfonts_terms'][0] ) && 'wp_theme' === $GLOBALS['wpfonts_terms'][0][2], 'created post was not tagged with the theme' );
$assert( 'fixture-theme' === $GLOBALS['wpfonts_terms'][0][1], 'created post used the wrong stylesheet' );
$created = json_decode( wp_unslash( $inserted['post_content'] ), true );
$assert( ! empty( $created['isGlobalStylesUserThemeJSON'] ), 'created post is not user theme JSON' );
$assert( isset( $created['settings']['typography']['fontFamilies']['custom'] ), 'created post has no custom families' );
$assert( false !== strpos( wp_unslash( $inserted['post_content'] ), '"styles":{}' ), 'created styles were not an empty object' );
$created_slugs = array();
foreach ( $created['settings']['typography']['fontFamilies']['custom'] as $item ) {
	$created_slugs[] = $item['slug'];
}
$assert( in_array( 'wpfonts-pkg-demo', $created_slugs, true ), 'created post missed the enabled package' );
$assert( false === strpos( wp_unslash( $inserted['post_content'] ), 'theme.json' ), 'created post mentions a theme file' );

$gs_src      = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-global-styles.php' );
$library_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-library.php' );
$main_src    = file_get_contents( dirname( __DIR__ ) . '/wpfonts.php' );
$admin_src   = file_get_contents( dirname( __DIR__ ) . '/includes/class-wpfonts-admin.php' );
$uninstall   = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );
$assert( false === strpos( $gs_src, 'file_put_contents' ), 'global styles helper writes a file' );
$assert( false === strpos( $gs_src, 'theme.json' ), 'global styles helper mentions theme.json' );
$assert( false === strpos( $gs_src, 'get_stylesheet_directory' ), 'global styles helper touches the theme directory' );
$assert( false !== strpos( $gs_src, 'wp_global_styles' ), 'global styles helper does not use the user post type' );
$assert( false !== strpos( $gs_src, 'WP_Theme_JSON_Resolver' ), 'global styles helper does not use the resolver' );
$assert( false === strpos( $library_src, 'wp_global_styles' ), 'library started writing global styles' );
$assert( false !== strpos( $main_src, 'class-wpfonts-global-styles.php' ), 'main file does not load the sync helper' );
$assert( false !== strpos( $admin_src, '已启用的文风字体包和确认能打开的文风云会出现在全站样式里。' ), 'help copy drifted' );
$assert( false === strpos( $admin_src, '全站样式由字体库安装写入' ), 'help still says Font Library install is required' );
$assert( false === strpos( $admin_src, '全站安装请用字体库' ), 'admin still sends people to install for site-wide type' );
$assert( false !== strpos( $uninstall, 'wpfonts_gs_slugs' ), 'uninstall leaves the owned-slug option' );
$assert( false === strpos( $uninstall, 'wp_global_styles' ), 'uninstall touches Global Styles' );

$hooked = array();
foreach ( $GLOBALS['wpfonts_actions'] as $action ) {
	$hooked[] = $action[0];
}
foreach ( array( 'init', 'update_option_wpfonts_packages', 'update_option_wpfonts_settings', 'add_option_wpfonts_packages', 'delete_option_wpfonts_settings' ) as $hook ) {
	$assert( in_array( $hook, $hooked, true ), 'missing hook ' . $hook );
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: enabled packages and a confirmed cloud face sync into user Global Styles, and unrelated entries stay.\n";
