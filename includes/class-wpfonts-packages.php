<?php
/**
 * Local font packages. The plugin stores a zip the site owner already
 * downloaded. It does not slice fonts. On install, the package id follows
 * the catalog family wenfeng-{short} when the zip says what that short is.
 * A successful install or replace runs wpfonts_package_installed.
 * wpfonts_package_deleted runs while the package directory is still present.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Install, list, and delete on-site font packages.
 */
class WPFonts_Packages {

	const OPTION = 'wpfonts_packages';

	const MAX_BYTES = 83886080;

	const MAX_FILES = 4000;

	const MAX_DEPTH = 8;

	/**
	 * Saved packages. Paths inside the option are not trusted.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			return array();
		}
		$out = array();
		foreach ( $saved as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = self::clean_id( isset( $row['id'] ) ? $row['id'] : '' );
			if ( '' === $id ) {
				continue;
			}
			$name = self::plain_name( isset( $row['name'] ) ? $row['name'] : $id );
			if ( '' === $name ) {
				$name = $id;
			}
			$family = self::plain_name( isset( $row['family'] ) ? $row['family'] : '' );
			if ( '' === $family ) {
				$family = $name;
			}
			$out[] = array(
				'id'           => $id,
				'name'         => $name,
				'family'       => $family,
				'enabled'      => empty( $row['enabled'] ) ? 0 : 1,
				'installed_at' => isset( $row['installed_at'] ) ? (int) $row['installed_at'] : 0,
			);
		}
		return $out;
	}

	/**
	 * none, active, or inactive.
	 *
	 * @return string
	 */
	public static function presence() {
		$packages = self::all();
		if ( array() === $packages ) {
			return 'none';
		}
		foreach ( $packages as $package ) {
			if ( ! empty( $package['enabled'] ) ) {
				return 'active';
			}
		}
		return 'inactive';
	}

	/**
	 * Slug from a zip file name.
	 *
	 * @param string $filename Original file name.
	 * @return string
	 */
	public static function slug_from_filename( $filename ) {
		$base = pathinfo( (string) $filename, PATHINFO_FILENAME );
		$base = strtolower( $base );
		$base = preg_replace( '/[^a-z0-9]+/', '-', $base );
		if ( ! is_string( $base ) ) {
			return 'package';
		}
		$base = trim( $base, '-' );
		if ( '' === $base ) {
			return 'package';
		}
		if ( strlen( $base ) > 48 ) {
			$base = rtrim( substr( $base, 0, 48 ), '-' );
		}
		return '' === $base ? 'package' : $base;
	}

	/**
	 * Catalog family for a CSS or metadata token.
	 *
	 * WF-Xxx and windfonts-Xxx become wenfeng-{short}. A token that is already
	 * wenfeng-* stays, lowercased. The short is the same family the catalog
	 * stores. Any other name is empty, so the zip file name can still be used.
	 *
	 * @param string $token Raw family token.
	 * @return string
	 */
	public static function catalog_family( $token ) {
		$token = trim( (string) $token );
		$token = trim( $token, " \t\n\r\0\x0B\"'" );
		if ( '' === $token || ! preg_match( '/^(?:wenfeng|windfonts|WF)-(.+)$/i', $token, $matches ) ) {
			return '';
		}
		$short = self::fit_slug( $matches[1] );
		if ( '' === $short ) {
			return '';
		}
		$family = 'wenfeng-' . $short;
		if ( strlen( $family ) > 48 ) {
			$family = rtrim( substr( $family, 0, 48 ), '-' );
		}
		if ( '' === $family || 0 !== strpos( $family, 'wenfeng-' ) || 'wenfeng-' === $family ) {
			return '';
		}
		return $family;
	}

	/**
	 * Package id to try before a collision suffix.
	 *
	 * A metadata fontFamily wins when it is a catalog family. Otherwise the
	 * first @font-face font-family in the extracted stylesheet. Otherwise the
	 * zip file name, unchanged.
	 *
	 * @param string            $filename Zip file name.
	 * @param string            $css      Stylesheet text, usually result.css.
	 * @param array|string|null $metadata Decoded metadata, a JSON string, or a catalog token.
	 * @return string
	 */
	public static function package_slug_base( $filename, $css, $metadata = null ) {
		$from_meta = self::metadata_catalog_family( $metadata );
		if ( '' !== $from_meta ) {
			return $from_meta;
		}
		$from_css = self::catalog_family( self::family_from_css( (string) $css ) );
		if ( '' !== $from_css ) {
			return $from_css;
		}
		return self::slug_from_filename( $filename );
	}

	/**
	 * Catalog family stored on package metadata, or empty.
	 *
	 * Reads fontFamily, then font_family. Those are the catalog fields. A
	 * display name in family or name is not a slug.
	 *
	 * @param array|string|null $metadata Decoded metadata, a JSON string, or a catalog token.
	 * @return string
	 */
	public static function metadata_catalog_family( $metadata ) {
		if ( is_string( $metadata ) ) {
			$metadata = trim( $metadata );
			if ( '' === $metadata ) {
				return '';
			}
			$decoded = self::metadata_array( $metadata );
			if ( null === $decoded ) {
				return self::catalog_family( $metadata );
			}
			$metadata = $decoded;
		}
		$data = self::metadata_array( $metadata );
		if ( null === $data ) {
			return '';
		}
		foreach ( array( 'fontFamily', 'font_family' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_string( $data[ $key ] ) ) {
				continue;
			}
			$family = self::catalog_family( $data[ $key ] );
			if ( '' !== $family ) {
				return $family;
			}
		}
		return '';
	}

	/**
	 * Human package name from metadata, or empty.
	 *
	 * family, name, and the Chinese name fields are display text. A catalog
	 * token in one of those fields is skipped.
	 *
	 * @param array|string|null $metadata Decoded metadata or a JSON string.
	 * @return string
	 */
	public static function display_name_from_metadata( $metadata ) {
		$data = self::metadata_array( $metadata );
		if ( null === $data ) {
			return '';
		}
		foreach ( array( 'family', 'name', 'chineseName', 'chinese_name' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_string( $data[ $key ] ) ) {
				continue;
			}
			$name = self::plain_name( $data[ $key ] );
			if ( strlen( $name ) > 80 ) {
				$name = trim( substr( $name, 0, 80 ) );
			}
			if ( '' === $name || '' !== self::catalog_family( $name ) ) {
				continue;
			}
			return $name;
		}
		return '';
	}

	/**
	 * Metadata token and display name from an extracted package.
	 *
	 * Looks at meta.json, metadata.json, and font.json. The shallowest file
	 * wins. A file without a catalog family does not hide a later one.
	 *
	 * @param string   $root Extracted directory.
	 * @param string[] $rels Relative file paths.
	 * @return array{token:string,name:string}
	 */
	public static function metadata_from_directory( $root, array $rels ) {
		$root   = rtrim( (string) $root, '/\\' );
		$ranked = array(
			'meta.json'     => 0,
			'metadata.json' => 1,
			'font.json'     => 2,
		);
		$found  = array();
		foreach ( $rels as $rel ) {
			$rel = str_replace( '\\', '/', (string) $rel );
			if ( ! self::is_safe_rel( $rel ) ) {
				continue;
			}
			$base = strtolower( basename( $rel ) );
			if ( ! isset( $ranked[ $base ] ) ) {
				continue;
			}
			$found[] = array(
				'rel'   => $rel,
				'rank'  => $ranked[ $base ],
				'depth' => substr_count( $rel, '/' ),
			);
		}
		usort(
			$found,
			static function ( $left, $right ) {
				if ( $left['depth'] !== $right['depth'] ) {
					return $left['depth'] - $right['depth'];
				}
				return $left['rank'] - $right['rank'];
			}
		);
		$token = '';
		$name  = '';
		$reads = 0;
		foreach ( $found as $item ) {
			if ( 8 <= $reads || ( '' !== $token && '' !== $name ) ) {
				break;
			}
			++$reads;
			$path = $root . '/' . $item['rel'];
			$raw  = self::read_metadata_file( $root, $path );
			if ( '' === $raw ) {
				continue;
			}
			if ( '' === $token ) {
				$token = self::metadata_catalog_family( $raw );
			}
			if ( '' === $name ) {
				$name = self::display_name_from_metadata( $raw );
			}
		}
		return array(
			'token' => $token,
			'name'  => $name,
		);
	}

	/**
	 * Next free slug.
	 *
	 * @param string   $base     Preferred slug.
	 * @param string[] $existing Existing ids.
	 * @return string
	 */
	public static function unique_slug( $base, array $existing ) {
		$base = self::clean_id( $base );
		if ( '' === $base ) {
			$base = 'package';
		}
		if ( ! in_array( $base, $existing, true ) ) {
			return $base;
		}
		$n = 2;
		while ( $n < 1000 ) {
			$try = $base . '-' . $n;
			if ( ! in_array( $try, $existing, true ) ) {
				return $try;
			}
			++$n;
		}
		return $base . '-' . $n;
	}

	/**
	 * Keep a package id.
	 *
	 * @param mixed $value Raw id.
	 * @return string
	 */
	public static function clean_id( $value ) {
		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9-]/', '', $value );
		if ( ! is_string( $value ) ) {
			return '';
		}
		return trim( $value, '-' );
	}

	/**
	 * Whether a zip entry path stays inside the package.
	 *
	 * @param string $rel Relative path.
	 * @return bool
	 */
	public static function is_safe_rel( $rel ) {
		$rel = str_replace( '\\', '/', (string) $rel );
		if ( '' === $rel || false !== strpos( $rel, "\0" ) ) {
			return false;
		}
		if ( '/' === $rel[0] || preg_match( '/^[a-z]:/i', $rel ) ) {
			return false;
		}
		if ( substr_count( $rel, '/' ) > self::MAX_DEPTH ) {
			return false;
		}
		foreach ( explode( '/', $rel ) as $part ) {
			if ( '' === $part || '.' === $part || '..' === $part ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Pick the stylesheet to publish.
	 *
	 * Prefer result.css. Otherwise the only .css in the root or one directory down.
	 *
	 * @param string[] $rels Relative file paths.
	 * @return string
	 */
	public static function locate_stylesheet( array $rels ) {
		$css = array();
		foreach ( $rels as $rel ) {
			$rel = str_replace( '\\', '/', (string) $rel );
			if ( preg_match( '/\.css$/i', $rel ) ) {
				$css[] = $rel;
			}
		}
		$results = array();
		foreach ( $css as $rel ) {
			if ( 'result.css' === strtolower( basename( $rel ) ) ) {
				$results[] = $rel;
			}
		}
		if ( array() !== $results ) {
			usort(
				$results,
				static function ( $a, $b ) {
					return substr_count( $a, '/' ) - substr_count( $b, '/' );
				}
			);
			return $results[0];
		}
		$shallow = array();
		foreach ( $css as $rel ) {
			if ( substr_count( $rel, '/' ) <= 1 ) {
				$shallow[] = $rel;
			}
		}
		if ( 1 === count( $shallow ) ) {
			return $shallow[0];
		}
		return '';
	}

	/**
	 * Whether the tree contains a woff2 file.
	 *
	 * @param string[] $rels Relative file paths.
	 * @return bool
	 */
	public static function has_woff2( array $rels ) {
		foreach ( $rels as $rel ) {
			if ( preg_match( '/\.woff2$/i', (string) $rel ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolve a CSS url() against the stylesheet directory.
	 *
	 * @param string $css_dir Directory of the CSS file inside the package.
	 * @param string $url     Raw url() value.
	 * @return string Relative path, or empty when it leaves the package.
	 */
	public static function resolve_rel( $css_dir, $url ) {
		$url = (string) $url;
		$url = preg_replace( '/[?#].*$/', '', $url );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		if ( preg_match( '#^(?:[a-z]+:)?//#i', $url ) || 'data:' === substr( $url, 0, 5 ) ) {
			return '';
		}
		$url = str_replace( '\\', '/', $url );
		if ( '/' === $url[0] ) {
			return '';
		}
		$parts = array();
		if ( '' !== $css_dir ) {
			$parts = explode( '/', $css_dir );
		}
		foreach ( explode( '/', $url ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				if ( array() === $parts ) {
					return '';
				}
				array_pop( $parts );
				continue;
			}
			$parts[] = $part;
		}
		$rel = implode( '/', $parts );
		return self::is_safe_rel( $rel ) ? $rel : '';
	}

	/**
	 * Rewrite relative url() values to absolute URLs under the package.
	 *
	 * @param string $css       CSS text.
	 * @param string $css_rel   Stylesheet path inside the package.
	 * @param string $base_url  Public URL of the package directory, no trailing slash.
	 * @return string
	 */
	public static function rewrite_css( $css, $css_rel, $base_url ) {
		$css_rel = str_replace( '\\', '/', (string) $css_rel );
		$css_dir = '';
		if ( false !== strrpos( $css_rel, '/' ) ) {
			$css_dir = substr( $css_rel, 0, strrpos( $css_rel, '/' ) );
		}
		$base_url  = rtrim( (string) $base_url, '/' );
		$rewritten = preg_replace_callback(
			'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
			static function ( $matches ) use ( $css_dir, $base_url ) {
				$raw = trim( $matches[2] );
				$rel = WPFonts_Packages::resolve_rel( $css_dir, $raw );
				if ( '' === $rel ) {
					return $matches[0];
				}
				return 'url("' . $base_url . '/' . $rel . '")';
			},
			(string) $css
		);
		return is_string( $rewritten ) ? $rewritten : (string) $css;
	}

	/**
	 * Point @font-face font-family at the catalog family.
	 *
	 * woff2 file names inside url() are left alone. The call is a no-op unless
	 * $family is already a wenfeng-* slug, including a collision suffix.
	 *
	 * @param string $css    CSS text.
	 * @param string $family Package slug, such as wenfeng-fzss.
	 * @return string
	 */
	public static function rewrite_family_css( $css, $family ) {
		$family = self::clean_id( $family );
		if ( 0 !== strpos( $family, 'wenfeng-' ) || 'wenfeng-' === $family || ! is_string( $css ) || '' === $css ) {
			return is_string( $css ) ? $css : '';
		}
		$rewritten = preg_replace_callback(
			'/(@font-face\s*\{[^}]*?font-family\s*:\s*)([^;}]+)/is',
			static function ( $matches ) use ( $family ) {
				$parts = explode( ',', $matches[2], 2 );
				$next  = '"' . $family . '"';
				if ( isset( $parts[1] ) ) {
					$next .= ',' . $parts[1];
				}
				return $matches[1] . $next;
			},
			$css
		);
		return is_string( $rewritten ) ? $rewritten : $css;
	}

	/**
	 * First font-family inside an @font-face rule, without quotes.
	 *
	 * @param string $css CSS text.
	 * @return string
	 */
	public static function family_from_css( $css ) {
		if ( ! is_string( $css ) || ! preg_match( '/@font-face\s*\{[^}]*font-family\s*:\s*([^;}]+)/is', $css, $matches ) ) {
			return '';
		}
		$value = trim( $matches[1] );
		$parts = explode( ',', $value );
		$value = trim( $parts[0] );
		$value = trim( $value, " \t\n\r\0\x0B\"'" );
		$value = preg_replace( '/[\x00-\x1F\x7F<>{};\\\\]/', '', $value );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Enqueue enabled local stylesheets. Independent of the CDN switch.
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		if ( ! function_exists( 'wp_upload_dir' ) || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}
		$base = trailingslashit( $uploads['baseurl'] ) . 'wpfonts/packages/';
		foreach ( self::all() as $package ) {
			if ( empty( $package['enabled'] ) ) {
				continue;
			}
			$health = self::health( $package );
			if ( 'missing_css' === $health['code'] || 'no_woff2' === $health['code'] ) {
				continue;
			}
			$url = $base . $package['id'] . '/local.css';
			wp_enqueue_style( 'wpfonts-local-' . $package['id'], $url, array(), (string) $package['installed_at'] );
		}
	}

	/**
	 * Why a package would not load, in one Chinese sentence.
	 *
	 * unknown means the uploads directory could not be read. That is not
	 * reported as a broken package. A healthy package returns an empty reason.
	 *
	 * @param array $package Package row.
	 * @return array{code:string,reason:string}
	 */
	public static function health( array $package ) {
		$enabled = ! empty( $package['enabled'] );
		$id      = isset( $package['id'] ) ? self::clean_id( $package['id'] ) : '';
		$unknown = array(
			'code'   => 'unknown',
			'reason' => '',
		);
		if ( '' === $id || ! function_exists( 'wp_upload_dir' ) ) {
			return $unknown;
		}
		$uploads = wp_upload_dir();
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return $unknown;
		}
		$root = rtrim( (string) $uploads['basedir'], '/\\' ) . '/wpfonts/packages';
		$dir  = $root . '/' . $id;
		$path = $dir . '/local.css';
		if ( function_exists( 'wp_normalize_path' ) ) {
			$root = wp_normalize_path( $root );
			$dir  = wp_normalize_path( $dir );
			$path = wp_normalize_path( $path );
		}
		if ( 0 !== strpos( $path, $root . '/' ) ) {
			return $unknown;
		}
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return self::health_result( 'missing_css', $enabled );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local package CSS. WP_Filesystem may prompt for FTP.
		$raw = file_get_contents( $path );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return self::health_result( 'missing_css', $enabled );
		}
		if ( ! self::css_has_usable_woff2( $raw, $dir, $uploads, $id ) ) {
			return self::health_result( 'no_woff2', $enabled );
		}
		return array(
			'code'   => 'ok',
			'reason' => '',
		);
	}

	/**
	 * Enqueue every package stylesheet on the packages screen, including disabled ones.
	 *
	 * @return void
	 */
	public static function enqueue_admin_previews() {
		if ( ! function_exists( 'wp_upload_dir' ) || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}
		$base = trailingslashit( $uploads['baseurl'] ) . 'wpfonts/packages/';
		foreach ( self::all() as $package ) {
			$health = self::health( $package );
			if ( 'missing_css' === $health['code'] || 'no_woff2' === $health['code'] ) {
				continue;
			}
			$url = $base . $package['id'] . '/local.css';
			wp_enqueue_style( 'wpfonts-preview-' . $package['id'], $url, array(), (string) $package['installed_at'] );
		}
	}

	/**
	 * Install an uploaded zip from the packages screen.
	 *
	 * Caller must verify the nonce and manage_options first. The admin screen
	 * shows one sentence for every failure. Browser uploads stay here and still
	 * require a real HTTP upload. Disk installs use install_local_zip().
	 *
	 * @param array $file A $_FILES item. Keys: name, tmp_name, size, error.
	 * @return array|WP_Error Location passed to wpfonts_package_installed, or an error.
	 */
	public static function install_uploaded_file( array $file ) {
		$name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
		$tmp  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$err  = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $err || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'wpfonts_package', 'upload' );
		}
		if ( $size < 1 || $size > self::MAX_BYTES ) {
			return new WP_Error( 'wpfonts_package', 'size' );
		}
		if ( ! preg_match( '/\.zip$/i', $name ) ) {
			return new WP_Error( 'wpfonts_package', 'type' );
		}
		return self::install_local_zip( $tmp, $name );
	}

	/**
	 * Install a zip that is already on disk.
	 *
	 * Same unpack, checks, and family normalize as a browser upload.
	 * is_uploaded_file() is not used. When that package id is already
	 * installed, the directory is replaced and the action is reinstall.
	 * The enabled flag is kept. wpfonts_package_installed runs once, after
	 * the directory and the package list both include the package.
	 *
	 * @param string $path Path to the zip. It does not have to be an HTTP upload.
	 * @param string $name File name used when the zip does not name a catalog family.
	 * @return array|WP_Error Location passed to wpfonts_package_installed, or an error.
	 */
	public static function install_local_zip( $path, $name ) {
		$path = (string) $path;
		$name = (string) $name;
		if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'wpfonts_package', 'missing' );
		}
		$size = filesize( $path );
		if ( false === $size || $size < 1 || $size > self::MAX_BYTES ) {
			return new WP_Error( 'wpfonts_package', 'size' );
		}
		if ( ! preg_match( '/\.zip$/i', $name ) ) {
			return new WP_Error( 'wpfonts_package', 'type' );
		}
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return new WP_Error( 'wpfonts_package', 'uploads' );
		}
		$uploads = wp_upload_dir();
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'wpfonts_package', 'uploads' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'wpfonts_package', 'filesystem' );
		}

		$root = trailingslashit( $uploads['basedir'] ) . 'wpfonts/packages';
		if ( ! wp_mkdir_p( $root ) ) {
			return new WP_Error( 'wpfonts_package', 'mkdir' );
		}
		$incoming = $root . '/.incoming-' . wp_generate_password( 12, false, false );
		if ( ! wp_mkdir_p( $incoming ) ) {
			return new WP_Error( 'wpfonts_package', 'mkdir' );
		}

		$unzipped = unzip_file( $path, $incoming );
		if ( is_wp_error( $unzipped ) ) {
			self::discard_incoming( $incoming );
			return new WP_Error( 'wpfonts_package', 'unzip' );
		}

		$rels = self::list_rels( $incoming );
		if ( count( $rels ) > self::MAX_FILES || ! self::rels_are_safe( $rels ) || ! self::has_woff2( $rels ) ) {
			self::discard_incoming( $incoming );
			return new WP_Error( 'wpfonts_package', 'contents' );
		}
		$css_rel = self::locate_stylesheet( $rels );
		if ( '' === $css_rel ) {
			self::discard_incoming( $incoming );
			return new WP_Error( 'wpfonts_package', 'css' );
		}
		$bytes = 0;
		foreach ( $rels as $rel ) {
			$bytes += (int) $wp_filesystem->size( $incoming . '/' . $rel );
			if ( $bytes > self::MAX_BYTES ) {
				self::discard_incoming( $incoming );
				return new WP_Error( 'wpfonts_package', 'size' );
			}
		}

		$existing = array();
		$previous = null;
		foreach ( self::all() as $package ) {
			$existing[] = $package['id'];
		}
		$raw = $wp_filesystem->get_contents( $incoming . '/' . $css_rel );
		if ( ! is_string( $raw ) || '' === $raw ) {
			self::discard_incoming( $incoming );
			return new WP_Error( 'wpfonts_package', 'read' );
		}
		$meta = self::metadata_from_directory( $incoming, $rels );
		$base = self::clean_id( self::package_slug_base( $name, $raw, $meta['token'] ) );
		if ( '' === $base ) {
			$base = 'package';
		}
		foreach ( self::all() as $package ) {
			if ( $package['id'] === $base ) {
				$previous = $package;
				break;
			}
		}
		if ( is_array( $previous ) ) {
			$slug   = $base;
			$action = 'reinstall';
		} else {
			$slug   = self::unique_slug( $base, $existing );
			$action = 'install';
		}
		$baseurl = trailingslashit( $uploads['baseurl'] ) . 'wpfonts/packages/' . $slug;
		if ( '' !== self::catalog_family( $base ) ) {
			$raw = self::rewrite_family_css( $raw, $slug );
		}
		$local = self::rewrite_css( $raw, $css_rel, $baseurl );
		if ( ! $wp_filesystem->put_contents( $incoming . '/local.css', $local, FS_CHMOD_FILE ) ) {
			self::discard_incoming( $incoming );
			return new WP_Error( 'wpfonts_package', 'write' );
		}

		$final = $root . '/' . $slug;
		if ( function_exists( 'wp_normalize_path' ) ) {
			$final = wp_normalize_path( $final );
		}
		if ( ! $wp_filesystem->move( $incoming, $final, true ) ) {
			self::discard_incoming( $incoming );
			return new WP_Error( 'wpfonts_package', 'move' );
		}

		$display = isset( $meta['name'] ) ? self::plain_name( $meta['name'] ) : '';
		if ( '' === $display ) {
			$display = self::plain_name( pathinfo( $name, PATHINFO_FILENAME ) );
		}
		if ( '' === $display ) {
			$display = $slug;
		}
		if ( strlen( $display ) > 80 ) {
			$display = substr( $display, 0, 80 );
		}
		$family = self::family_from_css( $local );
		if ( '' === $family ) {
			$family = $display;
		}
		$enabled = 1;
		if ( is_array( $previous ) ) {
			$enabled = empty( $previous['enabled'] ) ? 0 : 1;
		}
		$row      = array(
			'id'           => $slug,
			'name'         => $display,
			'family'       => $family,
			'enabled'      => $enabled,
			'installed_at' => time(),
		);
		$packages = self::all();
		$written  = false;
		if ( 'reinstall' === $action ) {
			foreach ( $packages as $index => $package ) {
				if ( $package['id'] === $slug ) {
					$packages[ $index ] = $row;
					$written            = true;
					break;
				}
			}
		}
		if ( ! $written ) {
			$packages[] = $row;
		}
		update_option( self::OPTION, $packages, true );

		$files   = self::list_rels( $final );
		$payload = self::package_location( $action, $slug, $final, $files );
		/**
		 * Fires after a font package is installed or replaced.
		 *
		 * The directory and the package list both include this package.
		 * `files` may be an empty list. Listeners can read `dir`.
		 *
		 * @param array $package Package location. Keys: action, slug, package_id, dir, uploads_rel, local_css, files.
		 */
		do_action( 'wpfonts_package_installed', $payload );
		return $payload;
	}

	/**
	 * Enable or disable one package.
	 *
	 * @param string $id      Package id.
	 * @param bool   $enabled Whether it should load on the front.
	 * @return bool
	 */
	public static function set_enabled( $id, $enabled ) {
		$id       = self::clean_id( $id );
		$packages = self::all();
		$found    = false;
		foreach ( $packages as $index => $package ) {
			if ( $package['id'] === $id ) {
				$packages[ $index ]['enabled'] = $enabled ? 1 : 0;
				$found                         = true;
			}
		}
		if ( ! $found ) {
			return false;
		}
		update_option( self::OPTION, $packages, true );
		return true;
	}

	/**
	 * Package row for a slug or id, or null.
	 *
	 * The package id is the directory name under wpfonts/packages.
	 * A display name is not an id.
	 *
	 * @param string $slug_or_id Slug or package id.
	 * @return array<string, mixed>|null
	 */
	public static function find( $slug_or_id ) {
		$want = self::clean_id( $slug_or_id );
		if ( '' === $want ) {
			return null;
		}
		foreach ( self::all() as $package ) {
			if ( $package['id'] === $want ) {
				return $package;
			}
		}
		return null;
	}

	/**
	 * Remove one package directory and its registry row.
	 *
	 * wpfonts_package_deleted runs first, while the directory is still on
	 * disk and the package list still includes this id. The directory and
	 * the registry row are removed after that action returns.
	 *
	 * @param string $id Package id.
	 * @return bool
	 */
	public static function delete( $id ) {
		$id = self::clean_id( $id );
		if ( '' === $id || ! function_exists( 'wp_upload_dir' ) ) {
			return false;
		}
		$found = false;
		foreach ( self::all() as $package ) {
			if ( $package['id'] === $id ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			return false;
		}

		$dir     = '';
		$files   = array();
		$uploads = wp_upload_dir();
		if ( is_array( $uploads ) && empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
			$root = trailingslashit( $uploads['basedir'] ) . 'wpfonts/packages';
			$try  = $root . '/' . $id;
			if ( function_exists( 'wp_normalize_path' ) ) {
				$root = wp_normalize_path( $root );
				$try  = wp_normalize_path( $try );
			}
			$root = rtrim( str_replace( '\\', '/', $root ), '/' );
			$try  = rtrim( str_replace( '\\', '/', $try ), '/' );
			if ( 0 === strpos( $try, $root . '/' ) && is_dir( $try ) ) {
				$dir   = $try;
				$files = self::list_rels( $dir );
			}
		}

		$payload = self::package_location( 'delete', $id, $dir, $files );
		/**
		 * Fires before a font package directory and its registry row are removed.
		 *
		 * When the directory exists, `dir` is still that absolute path and the
		 * package list still includes this id. Cleanup runs after this action.
		 * `files` may be an empty list. Listeners can read `dir`.
		 *
		 * @param array $package Package location. Keys: action, slug, package_id, dir, uploads_rel, local_css, files.
		 */
		do_action( 'wpfonts_package_deleted', $payload );

		if ( '' !== $dir && is_dir( $dir ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;
			if ( $wp_filesystem ) {
				$wp_filesystem->delete( $dir, true );
			}
		}

		$next = array();
		foreach ( self::all() as $package ) {
			if ( $package['id'] === $id ) {
				continue;
			}
			$next[] = $package;
		}
		update_option( self::OPTION, $next, true );
		return true;
	}

	/**
	 * Location of one package for the install and delete actions.
	 *
	 * `files` lists relative paths when the directory can be read. It is an
	 * empty list otherwise. Listeners can scan `dir` and must not require `files`.
	 *
	 * @param string   $action install, reinstall, or delete.
	 * @param string   $id     Package id.
	 * @param string   $dir    Absolute directory, or empty when it is already gone.
	 * @param string[] $files  Relative paths. May be empty.
	 * @return array{action:string,slug:string,package_id:string,dir:string,uploads_rel:string,local_css:string,files:array}
	 */
	private static function package_location( $action, $id, $dir, array $files ) {
		$dir = rtrim( str_replace( '\\', '/', (string) $dir ), '/' );
		if ( '' !== $dir && function_exists( 'wp_normalize_path' ) ) {
			$dir = rtrim( str_replace( '\\', '/', wp_normalize_path( $dir ) ), '/' );
		}
		$local_css = '';
		if ( '' !== $dir && is_file( $dir . '/local.css' ) ) {
			$local_css = $dir . '/local.css';
		}
		$clean = array();
		foreach ( $files as $rel ) {
			$rel = str_replace( '\\', '/', (string) $rel );
			if ( self::is_safe_rel( $rel ) ) {
				$clean[] = $rel;
			}
		}
		$clean = array_values( array_unique( $clean ) );
		sort( $clean, SORT_STRING );
		return array(
			'action'      => (string) $action,
			'slug'        => (string) $id,
			'package_id'  => (string) $id,
			'dir'         => $dir,
			'uploads_rel' => 'wpfonts/packages/' . $id,
			'local_css'   => $local_css,
			'files'       => $clean,
		);
	}

	/**
	 * Remove a temporary extract directory.
	 *
	 * @param string $dir Absolute directory.
	 * @return void
	 */
	private static function discard_incoming( $dir ) {
		global $wp_filesystem;
		if ( is_object( $wp_filesystem ) && '' !== (string) $dir ) {
			$wp_filesystem->delete( $dir, true );
		}
	}

	/**
	 * Chinese reason for a package that cannot load.
	 *
	 * @param string $code    missing_css or no_woff2.
	 * @param bool   $enabled Whether the package is switched on.
	 * @return array{code:string,reason:string}
	 */
	private static function health_result( $code, $enabled ) {
		if ( 'missing_css' === $code ) {
			$reason = $enabled
				? __( '已启用，但不会加载：找不到 local.css。', 'wpfonts' )
				: __( '找不到 local.css，启用后也不会加载。', 'wpfonts' );
		} else {
			$reason = $enabled
				? __( '已启用，但不会加载：样式里没有可用的 woff2。', 'wpfonts' )
				: __( '样式里没有可用的 woff2，启用后也不会加载。', 'wpfonts' );
		}
		return array(
			'code'   => $code,
			'reason' => $reason,
		);
	}

	/**
	 * Whether local.css names at least one woff2 the browser can request.
	 *
	 * An absolute woff2 outside this package counts, so a rewritten remote
	 * URL is not called missing. A URL inside this package counts only when
	 * the file is on disk.
	 *
	 * @param string $css     local.css text.
	 * @param string $dir     Package directory.
	 * @param array  $uploads wp_upload_dir() result.
	 * @param string $id      Package id.
	 * @return bool
	 */
	private static function css_has_usable_woff2( $css, $dir, array $uploads, $id ) {
		if ( ! preg_match_all( '/@font-face\s*\{([^}]*)\}/i', (string) $css, $blocks ) ) {
			return false;
		}
		$baseurl = '';
		if ( ! empty( $uploads['baseurl'] ) ) {
			$baseurl = rtrim( (string) $uploads['baseurl'], '/' ) . '/wpfonts/packages/' . $id;
		}
		foreach ( $blocks[1] as $body ) {
			if ( ! preg_match_all( '/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', $body, $urls ) ) {
				continue;
			}
			foreach ( $urls[2] as $url ) {
				$url = trim( (string) $url );
				if ( ! preg_match( '/\.woff2(?:$|[?#])/i', $url ) ) {
					continue;
				}
				if ( preg_match( '#^https?://#i', $url ) ) {
					if ( '' !== $baseurl && 0 === strpos( $url, $baseurl . '/' ) ) {
						$rel = substr( $url, strlen( $baseurl ) + 1 );
						$rel = preg_replace( '/[?#].*$/', '', (string) $rel );
						if ( self::is_safe_rel( (string) $rel ) && is_file( $dir . '/' . $rel ) ) {
							return true;
						}
						continue;
					}
					return true;
				}
				$rel = self::resolve_rel( '', $url );
				if ( '' !== $rel && is_file( $dir . '/' . $rel ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Visible package name with tags and control characters removed.
	 *
	 * @param mixed $name Raw name.
	 * @return string
	 */
	private static function plain_name( $name ) {
		$name = preg_replace( '/<[^>]*>/', '', (string) $name );
		if ( ! is_string( $name ) ) {
			return '';
		}
		$name = preg_replace( '/[\x00-\x1F\x7F]/', '', $name );
		return trim( is_string( $name ) ? $name : '' );
	}

	/**
	 * Short name capped so wenfeng-{short} fits the 48 character package id.
	 *
	 * @param string $slug Candidate short.
	 * @return string
	 */
	private static function fit_slug( $slug ) {
		$slug = self::clean_id( $slug );
		if ( '' === $slug ) {
			return '';
		}
		if ( strlen( $slug ) > 40 ) {
			$slug = rtrim( substr( $slug, 0, 40 ), '-' );
		}
		return $slug;
	}

	/**
	 * Decoded metadata object, or null.
	 *
	 * @param array|string|null $metadata Raw metadata.
	 * @return array|null
	 */
	private static function metadata_array( $metadata ) {
		if ( is_array( $metadata ) ) {
			return $metadata;
		}
		if ( ! is_string( $metadata ) ) {
			return null;
		}
		if ( 0 === strncmp( $metadata, "\xEF\xBB\xBF", 3 ) ) {
			$metadata = substr( $metadata, 3 );
		}
		$decoded = json_decode( $metadata, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Metadata JSON that stays inside the extracted package.
	 *
	 * @param string $root Extracted directory.
	 * @param string $path Absolute file path.
	 * @return string
	 */
	private static function read_metadata_file( $root, $path ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		$size = filesize( $path );
		if ( false === $size || $size < 1 || $size > 262144 ) {
			return '';
		}
		$real_root = realpath( $root );
		$real_file = realpath( $path );
		if ( false === $real_root || false === $real_file ) {
			return '';
		}
		$prefix = rtrim( str_replace( '\\', '/', $real_root ), '/' ) . '/';
		$file   = str_replace( '\\', '/', $real_file );
		if ( 0 !== strpos( $file, $prefix ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local package metadata. WP_Filesystem may prompt for FTP.
		$raw = file_get_contents( $path );
		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * Relative files under a directory.
	 *
	 * @param string $root Absolute directory.
	 * @return string[]
	 */
	private static function list_rels( $root ) {
		$root = rtrim( (string) $root, '/\\' );
		$out  = array();
		if ( ! is_dir( $root ) ) {
			return $out;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$rel   = substr( $file->getPathname(), strlen( $root ) + 1 );
			$out[] = str_replace( '\\', '/', $rel );
		}
		return $out;
	}

	/**
	 * Whether every relative path is safe and not a script.
	 *
	 * @param string[] $rels Relative paths.
	 * @return bool
	 */
	private static function rels_are_safe( array $rels ) {
		foreach ( $rels as $rel ) {
			if ( ! self::is_safe_rel( $rel ) ) {
				return false;
			}
			if ( preg_match( '/\.(?:php|phtml|phar|cgi|pl|exe|sh|htaccess)$/i', $rel ) ) {
				return false;
			}
		}
		return true;
	}
}
