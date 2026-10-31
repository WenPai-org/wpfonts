<?php
/**
 * Copy enabled package families and the confirmed cloud face into user Global Styles.
 *
 * WordPress keeps those choices in the wp_global_styles post for the active
 * theme. This class updates that post only. It does not write files in the
 * theme directory, and it does not remove font families the site owner added.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps WPFonts font-family presets in the user theme JSON.
 */
class WPFonts_Global_Styles {

	/**
	 * Slugs written by the last successful sync. Used to drop a cloud face
	 * whose catalog token is not a wpfonts-pkg- id.
	 *
	 * @var string
	 */
	const OWNED_OPTION = 'wpfonts_gs_slugs';

	/**
	 * True while a sync is already running.
	 *
	 * @var bool
	 */
	private static $busy = false;

	/**
	 * Hook package, settings, and a guarded init reconcile.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'reconcile' ), 30 );
		add_action( 'add_option_wpfonts_packages', array( __CLASS__, 'on_store_change' ), 10, 3 );
		add_action( 'update_option_wpfonts_packages', array( __CLASS__, 'on_store_change' ), 10, 3 );
		add_action( 'delete_option_wpfonts_packages', array( __CLASS__, 'on_store_change' ), 10, 3 );
		add_action( 'add_option_wpfonts_settings', array( __CLASS__, 'on_store_change' ), 10, 3 );
		add_action( 'update_option_wpfonts_settings', array( __CLASS__, 'on_settings' ), 10, 3 );
		add_action( 'delete_option_wpfonts_settings', array( __CLASS__, 'on_store_change' ), 10, 3 );
	}

	/**
	 * Reconcile when an editor, cron, or WP-CLI can persist the post.
	 *
	 * Front-end visitors do not write. Package and settings saves call sync()
	 * directly, so a missed init still catches up on the next admin request.
	 *
	 * @return void
	 */
	public static function reconcile() {
		if ( ! self::can_write() ) {
			return;
		}
		self::sync();
	}

	/**
	 * Sync after a package list change. Extra hook arguments are ignored.
	 *
	 * @param mixed $old     Previous value, or the option name.
	 * @param mixed $updated New value.
	 * @param mixed $option  Option name.
	 * @return void
	 */
	public static function on_store_change( $old = null, $updated = null, $option = null ) {
		unset( $old, $updated, $option );
		self::sync();
	}

	/**
	 * Sync when the confirmed cloud face may have changed.
	 *
	 * @param mixed $old     Previous settings.
	 * @param mixed $updated New settings.
	 * @param mixed $option  Option name.
	 * @return void
	 */
	public static function on_settings( $old, $updated, $option = null ) {
		unset( $option );
		if ( ! self::settings_need_sync( $old, $updated ) ) {
			return;
		}
		self::sync();
	}

	/**
	 * Whether a settings save can add or clear the cloud family.
	 *
	 * @param mixed $old     Previous settings.
	 * @param mixed $updated New settings.
	 * @return bool
	 */
	public static function settings_need_sync( $old, $updated ) {
		if ( ! is_array( $old ) ) {
			$old = array();
		}
		if ( ! is_array( $updated ) ) {
			$updated = array();
		}
		$keys = array( 'css_status', 'css_face', 'family', 'cdn_enabled', 'project_slug' );
		foreach ( $keys as $key ) {
			$before = isset( $old[ $key ] ) ? (string) $old[ $key ] : '';
			$after  = isset( $updated[ $key ] ) ? (string) $updated[ $key ] : '';
			if ( $before !== $after ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Write the current families into user Global Styles when they differ.
	 *
	 * @return void
	 */
	public static function sync() {
		if ( self::$busy ) {
			return;
		}
		self::$busy = true;
		self::persist();
		self::$busy = false;
	}

	/**
	 * One font-family preset from a library definition, or null.
	 *
	 * The CSS stack and slug are the ones WPFonts_Library already computed.
	 * fontFace is not copied: package CSS and the cloud stylesheet already
	 * define the faces.
	 *
	 * @param array $definition font_family_settings, or a wrapped library row.
	 * @return array{slug:string,name:string,fontFamily:string}|null
	 */
	public static function preset_from_definition( array $definition ) {
		$settings = $definition;
		if ( isset( $definition['font_family_settings'] ) && is_array( $definition['font_family_settings'] ) ) {
			$settings = $definition['font_family_settings'];
		}
		$slug  = isset( $settings['slug'] ) ? self::clean_slug( $settings['slug'] ) : '';
		$stack = isset( $settings['fontFamily'] ) ? trim( (string) $settings['fontFamily'] ) : '';
		$name  = isset( $settings['name'] ) ? trim( (string) $settings['name'] ) : '';
		if ( '' === $slug || '' === $stack ) {
			return null;
		}
		if ( '' === $name ) {
			$name = $slug;
		}
		return array(
			'slug'       => $slug,
			'name'       => $name,
			'fontFamily' => $stack,
		);
	}

	/**
	 * Merge desired presets into a custom font-family list.
	 *
	 * Entries we own drop out when they are no longer desired. Every other
	 * entry stays, including a wenfeng-* family this plugin did not write.
	 *
	 * @param array $custom  Existing custom families.
	 * @param array $desired Desired presets, or wrapped library rows.
	 * @param array $owned   Slugs recorded by the previous sync.
	 * @return array{custom:array,owned:array}
	 */
	public static function merge_custom_families( array $custom, array $desired, array $owned ) {
		$by_slug = array();
		$order   = array();
		foreach ( $desired as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$preset = self::preset_from_definition( $row );
			if ( null === $preset ) {
				continue;
			}
			$by_slug[ $preset['slug'] ] = $preset;
			$order[]                    = $preset['slug'];
		}
		$order = array_values( array_unique( $order ) );

		$owned_lookup = array();
		foreach ( $owned as $slug ) {
			$slug = self::clean_slug( $slug );
			if ( '' !== $slug ) {
				$owned_lookup[ $slug ] = true;
			}
		}

		$next = array();
		$seen = array();
		foreach ( $custom as $item ) {
			if ( ! is_array( $item ) ) {
				$next[] = $item;
				continue;
			}
			$slug = isset( $item['slug'] ) ? self::clean_slug( $item['slug'] ) : '';
			if ( '' !== $slug && isset( $by_slug[ $slug ] ) ) {
				$item['slug']       = $slug;
				$item['name']       = $by_slug[ $slug ]['name'];
				$item['fontFamily'] = $by_slug[ $slug ]['fontFamily'];
				$next[]             = $item;
				$seen[ $slug ]      = true;
				continue;
			}
			if ( '' !== $slug && self::slug_is_owned( $slug, $owned_lookup ) ) {
				continue;
			}
			$next[] = $item;
		}
		foreach ( $order as $slug ) {
			if ( isset( $seen[ $slug ] ) ) {
				continue;
			}
			$next[] = array(
				'fontFamily' => $by_slug[ $slug ]['fontFamily'],
				'name'       => $by_slug[ $slug ]['name'],
				'slug'       => $slug,
			);
		}
		return array(
			'custom' => $next,
			'owned'  => $order,
		);
	}

	/**
	 * Read the custom font-family list, or null when the shape is not one we can edit safely.
	 *
	 * A numeric list is the whole set. An origin map contributes only its custom key.
	 *
	 * @param array $data Decoded user theme JSON.
	 * @return array|null
	 */
	public static function read_custom_families( array $data ) {
		if ( ! isset( $data['settings']['typography']['fontFamilies'] ) ) {
			return array();
		}
		$families = $data['settings']['typography']['fontFamilies'];
		if ( ! is_array( $families ) ) {
			return null;
		}
		if ( self::is_list( $families ) ) {
			return $families;
		}
		if ( ! array_key_exists( 'custom', $families ) ) {
			return array();
		}
		if ( ! is_array( $families['custom'] ) || ! self::is_list( $families['custom'] ) ) {
			return null;
		}
		return $families['custom'];
	}

	/**
	 * Put a custom list back without touching theme families, styles, or other settings.
	 *
	 * @param array $data   Decoded user theme JSON.
	 * @param array $custom Replacement custom families.
	 * @return array{data:array,changed:bool}
	 */
	public static function place_custom_families( array $data, array $custom ) {
		$current = self::read_custom_families( $data );
		if ( null === $current ) {
			return array(
				'data'    => $data,
				'changed' => false,
			);
		}
		if ( self::same_json( $current, $custom ) ) {
			return array(
				'data'    => $data,
				'changed' => false,
			);
		}
		if ( array() === $custom && ! self::has_family_slot( $data ) ) {
			return array(
				'data'    => $data,
				'changed' => false,
			);
		}
		return array(
			'data'    => self::write_custom_families( $data, $custom ),
			'changed' => true,
		);
	}

	/**
	 * Read, merge, and update the user global styles post when the list changed.
	 *
	 * @return void
	 */
	private static function persist() {
		if ( function_exists( 'wp_installing' ) && wp_installing() ) {
			return;
		}
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'wp_json_encode' ) || ! function_exists( 'get_stylesheet' ) ) {
			return;
		}
		$stylesheet = (string) get_stylesheet();
		if ( '' === $stylesheet ) {
			return;
		}

		$post_id = self::user_post_id();
		$raw     = '';
		$data    = array();
		if ( $post_id ) {
			$decoded = self::decode_post( $post_id, $raw );
			if ( null === $decoded ) {
				return;
			}
			$data = $decoded;
		}

		$current = self::read_custom_families( $data );
		if ( null === $current ) {
			return;
		}
		$merged = self::merge_custom_families( $current, self::desired_presets(), self::recorded_slugs() );
		$placed = self::place_custom_families( $data, $merged['custom'] );
		if ( $placed['changed'] ) {
			if ( ! $post_id && array() === $merged['custom'] ) {
				self::record_slugs( $merged['owned'] );
				return;
			}
			$json = self::encode_updated( $raw, $merged['custom'], ! $post_id );
			if ( '' === $json ) {
				return;
			}
			$saved = $post_id ? self::write_post( $post_id, $json ) : self::create_post( $stylesheet, $json );
			if ( ! $saved ) {
				return;
			}
		}
		self::record_slugs( $merged['owned'] );
	}

	/**
	 * Families to show now: enabled packages, then the confirmed cloud face.
	 *
	 * @return array<int,array{slug:string,name:string,fontFamily:string}>
	 */
	private static function desired_presets() {
		$out = array();
		if ( ! class_exists( 'WPFonts_Library', false ) ) {
			return $out;
		}
		$packages = class_exists( 'WPFonts_Packages', false ) ? WPFonts_Packages::all() : array();
		foreach ( WPFonts_Library::package_families( $packages, array() ) as $definition ) {
			$preset = self::preset_from_definition( $definition );
			if ( null !== $preset ) {
				$out[] = $preset;
			}
		}
		$settings = class_exists( 'WPFonts_Settings', false ) ? WPFonts_Settings::get() : array();
		$label    = WPFonts_Library::catalog_label( $settings );
		foreach ( WPFonts_Library::confirmed_cloud_families( $settings, $label ) as $definition ) {
			$preset = self::preset_from_definition( $definition );
			if ( null !== $preset ) {
				$out[] = $preset;
			}
		}
		return $out;
	}

	/**
	 * Decode a stored post. Null means the JSON is corrupt and must be left alone.
	 *
	 * @param int    $post_id Post id.
	 * @param string $raw     Raw post_content, filled for the caller.
	 * @return array|null
	 */
	private static function decode_post( $post_id, &$raw ) {
		$raw  = '';
		$post = get_post( $post_id );
		if ( ! $post || ! isset( $post->post_content ) ) {
			return array();
		}
		$raw = (string) $post->post_content;
		if ( '' === trim( $raw ) ) {
			return array();
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		return $data;
	}

	/**
	 * JSON for the updated document. Objects in the original post stay objects.
	 *
	 * @param string $raw    Original post_content.
	 * @param array  $custom New custom families.
	 * @param bool   $create True when there is no document yet.
	 * @return string
	 */
	private static function encode_updated( $raw, array $custom, $create ) {
		if ( $create || '' === trim( (string) $raw ) ) {
			$data = self::fresh_document( $custom );
		} else {
			$data = json_decode( (string) $raw );
			if ( ! is_object( $data ) ) {
				return '';
			}
			self::assign_custom_object( $data, $custom );
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? $json : '';
	}

	/**
	 * Assign a theme JSON field. WordPress fixes the name, so it is not snake_case.
	 *
	 * @param object $target Object being built.
	 * @param string $name   Field name.
	 * @param mixed  $value  Field value.
	 * @return void
	 */
	private static function put_field( $target, $name, $value ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Theme JSON field.
		$target->$name = $value;
	}

	/**
	 * Minimal user theme JSON containing only the custom families.
	 *
	 * @param array $custom Custom families.
	 * @return stdClass
	 */
	private static function fresh_document( array $custom ) {
		$families         = new stdClass();
		$families->custom = self::custom_json_list( $custom );
		$typography       = new stdClass();
		self::put_field( $typography, 'fontFamilies', $families );
		$settings             = new stdClass();
		$settings->typography = $typography;
		$data                 = new stdClass();
		$data->version        = self::schema_version();
		$data->settings       = $settings;
		$data->styles         = new stdClass();
		self::put_field( $data, 'isGlobalStylesUserThemeJSON', true );
		return $data;
	}

	/**
	 * Replace only fontFamilies.custom on an existing document.
	 *
	 * @param stdClass $data   Decoded post, as objects.
	 * @param array    $custom New custom families.
	 * @return void
	 */
	private static function assign_custom_object( $data, array $custom ) {
		if ( ! isset( $data->settings ) || ! is_object( $data->settings ) ) {
			$data->settings = new stdClass();
		}
		if ( ! isset( $data->settings->typography ) || ! is_object( $data->settings->typography ) ) {
			$data->settings->typography = new stdClass();
		}
		$families = isset( $data->settings->typography->fontFamilies ) ? $data->settings->typography->fontFamilies : null;
		$list     = self::custom_json_list( $custom );
		if ( is_array( $families ) ) {
			$data->settings->typography->fontFamilies = $list;
			return;
		}
		if ( ! is_object( $families ) ) {
			$families = new stdClass();
		}
		$families->custom                         = $list;
		$data->settings->typography->fontFamilies = $families;
	}

	/**
	 * Turn family arrays into JSON objects while keeping lists as lists.
	 *
	 * @param array $custom Custom families.
	 * @return array
	 */
	private static function custom_json_list( array $custom ) {
		$list = array();
		foreach ( $custom as $item ) {
			$list[] = is_array( $item ) ? self::value_for_json( $item ) : $item;
		}
		return $list;
	}

	/**
	 * @param array $value List or map.
	 * @return array|stdClass
	 */
	private static function value_for_json( array $value ) {
		if ( self::is_list( $value ) ) {
			$list = array();
			foreach ( $value as $item ) {
				$list[] = is_array( $item ) ? self::value_for_json( $item ) : $item;
			}
			return $list;
		}
		$object = new stdClass();
		foreach ( $value as $key => $item ) {
			$key          = (string) $key;
			$object->$key = is_array( $item ) ? self::value_for_json( $item ) : $item;
		}
		return $object;
	}

	/**
	 * Write the custom list into the associative user JSON used by tests and previews.
	 *
	 * A non-empty numeric list stays a list. An origin map keeps its other keys.
	 *
	 * @param array $data   User theme JSON.
	 * @param array $custom Replacement custom families.
	 * @return array
	 */
	private static function write_custom_families( array $data, array $custom ) {
		if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			$data['settings'] = array();
		}
		if ( ! isset( $data['settings']['typography'] ) || ! is_array( $data['settings']['typography'] ) ) {
			$data['settings']['typography'] = array();
		}
		$families = array();
		if ( isset( $data['settings']['typography']['fontFamilies'] ) && is_array( $data['settings']['typography']['fontFamilies'] ) ) {
			$families = $data['settings']['typography']['fontFamilies'];
		}
		if ( self::is_list( $families ) && array() !== $families ) {
			$data['settings']['typography']['fontFamilies'] = $custom;
			return $data;
		}
		if ( self::is_list( $families ) ) {
			$families = array();
		}
		$families['custom']                             = $custom;
		$data['settings']['typography']['fontFamilies'] = $families;
		return $data;
	}

	/**
	 * Whether fontFamilies is already present.
	 *
	 * @param array $data User theme JSON.
	 * @return bool
	 */
	private static function has_family_slot( array $data ) {
		return isset( $data['settings']['typography']['fontFamilies'] ) && is_array( $data['settings']['typography']['fontFamilies'] );
	}

	/**
	 * User global styles post id, or 0 when WordPress has not created one.
	 *
	 * @return int
	 */
	private static function user_post_id() {
		if ( ! class_exists( 'WP_Theme_JSON_Resolver', false ) || ! method_exists( 'WP_Theme_JSON_Resolver', 'get_user_global_styles_post_id' ) ) {
			return 0;
		}
		$id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		return $id ? (int) $id : 0;
	}

	/**
	 * Insert the user styles post and attach it to the active theme.
	 *
	 * @param string $stylesheet Theme stylesheet slug.
	 * @param string $json       Post content.
	 * @return bool
	 */
	private static function create_post( $stylesheet, $json ) {
		if ( ! function_exists( 'wp_insert_post' ) || ! function_exists( 'wp_set_object_terms' ) || ! function_exists( 'wp_slash' ) ) {
			return false;
		}
		$inserted = wp_insert_post(
			array(
				'post_content' => wp_slash( $json ),
				'post_status'  => 'publish',
				'post_title'   => 'Custom Styles',
				'post_type'    => 'wp_global_styles',
				'post_name'    => 'wp-global-styles-' . $stylesheet,
			),
			true
		);
		if ( is_wp_error( $inserted ) || ! $inserted ) {
			return false;
		}
		$post_id = (int) $inserted;
		$terms   = wp_set_object_terms( $post_id, $stylesheet, 'wp_theme' );
		if ( is_wp_error( $terms ) ) {
			if ( function_exists( 'wp_delete_post' ) ) {
				wp_delete_post( $post_id, true );
			}
			return false;
		}
		self::flush_resolver();
		return true;
	}

	/**
	 * Replace post_content. kses is lifted so the JSON is stored intact.
	 *
	 * @param int    $post_id Post id.
	 * @param string $json    Post content.
	 * @return bool
	 */
	private static function write_post( $post_id, $json ) {
		if ( ! function_exists( 'wp_update_post' ) || ! function_exists( 'wp_slash' ) ) {
			return false;
		}
		$removed = false;
		if ( function_exists( 'has_filter' ) && has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
			remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
			$removed = true;
		}
		$result = wp_update_post(
			array(
				'ID'           => (int) $post_id,
				'post_content' => wp_slash( $json ),
			),
			true
		);
		if ( $removed ) {
			add_filter( 'content_save_pre', 'wp_filter_post_kses' );
		}
		if ( is_wp_error( $result ) || ! $result ) {
			return false;
		}
		self::flush_resolver();
		return true;
	}

	/**
	 * Drop the resolver cache so this request sees the post we just wrote.
	 *
	 * @return void
	 */
	private static function flush_resolver() {
		if ( class_exists( 'WP_Theme_JSON_Resolver', false ) && method_exists( 'WP_Theme_JSON_Resolver', 'clean_cached_data' ) ) {
			WP_Theme_JSON_Resolver::clean_cached_data();
		}
	}

	/**
	 * Theme JSON schema version for a post we create.
	 *
	 * @return int
	 */
	private static function schema_version() {
		if ( class_exists( 'WP_Theme_JSON', false ) ) {
			return (int) WP_Theme_JSON::LATEST_SCHEMA;
		}
		return 3;
	}

	/**
	 * Slugs remembered from the previous sync.
	 *
	 * @return string[]
	 */
	private static function recorded_slugs() {
		$saved = get_option( self::OWNED_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			return array();
		}
		$out = array();
		foreach ( $saved as $slug ) {
			$slug = self::clean_slug( $slug );
			if ( '' !== $slug ) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	/**
	 * Remember the slugs this sync owns. The option is not autoloaded.
	 *
	 * @param string[] $slugs Owned slugs.
	 * @return void
	 */
	private static function record_slugs( array $slugs ) {
		$current = self::recorded_slugs();
		if ( $current === $slugs ) {
			return;
		}
		update_option( self::OWNED_OPTION, $slugs, false );
	}

	/**
	 * Whether this request may create or update the user styles post.
	 *
	 * @return bool
	 */
	private static function can_write() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'edit_theme_options' );
	}

	/**
	 * A slug this plugin may remove.
	 *
	 * Package ids and the cloud fallback are ours even if the option was lost.
	 * A wenfeng-* slug is ours only after we recorded it.
	 *
	 * @param string $slug         Family slug.
	 * @param array  $owned_lookup Recorded slugs as keys.
	 * @return bool
	 */
	private static function slug_is_owned( $slug, array $owned_lookup ) {
		if ( isset( $owned_lookup[ $slug ] ) ) {
			return true;
		}
		if ( 'wpfonts-cloud' === $slug ) {
			return true;
		}
		return 0 === strpos( $slug, 'wpfonts-pkg-' );
	}

	/**
	 * Slug safe to store. Empty when it is not ours or the user's to compare.
	 *
	 * @param mixed $value Raw slug.
	 * @return string
	 */
	private static function clean_slug( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( ! preg_match( '/^[a-z0-9-]{1,120}$/', $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Numeric array, including an empty one. PHP 7.4 has no array_is_list().
	 *
	 * @param array $value Value.
	 * @return bool
	 */
	private static function is_list( array $value ) {
		$index = 0;
		foreach ( $value as $key => $unused ) {
			unset( $unused );
			if ( $key !== $index ) {
				return false;
			}
			++$index;
		}
		return true;
	}

	/**
	 * JSON equality that ignores nothing: key order is part of the value.
	 *
	 * @param mixed $left  Left.
	 * @param mixed $right Right.
	 * @return bool
	 */
	private static function same_json( $left, $right ) {
		return wp_json_encode( $left ) === wp_json_encode( $right );
	}
}

WPFonts_Global_Styles::init();
