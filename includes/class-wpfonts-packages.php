<?php
/**
 * Local font packages. The plugin stores a zip the site owner already
 * downloaded. It does not slice fonts.
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
			$url = $base . $package['id'] . '/local.css';
			wp_enqueue_style( 'wpfonts-local-' . $package['id'], $url, array(), (string) $package['installed_at'] );
		}
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
			$url = $base . $package['id'] . '/local.css';
			wp_enqueue_style( 'wpfonts-preview-' . $package['id'], $url, array(), (string) $package['installed_at'] );
		}
	}

	/**
	 * Install an uploaded zip. Returns true, or a WP_Error on failure.
	 *
	 * Caller must verify the nonce and manage_options first. The admin screen
	 * shows one sentence for every failure.
	 *
	 * @param array $file A $_FILES item. Keys: name, tmp_name, size, error.
	 * @return true|WP_Error
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
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return new WP_Error( 'wpfonts_package', 'uploads' );
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
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

		$unzipped = unzip_file( $tmp, $incoming );
		if ( is_wp_error( $unzipped ) ) {
			$wp_filesystem->delete( $incoming, true );
			return new WP_Error( 'wpfonts_package', 'unzip' );
		}

		$rels = self::list_rels( $incoming );
		if ( count( $rels ) > self::MAX_FILES || ! self::rels_are_safe( $rels ) || ! self::has_woff2( $rels ) ) {
			$wp_filesystem->delete( $incoming, true );
			return new WP_Error( 'wpfonts_package', 'contents' );
		}
		$css_rel = self::locate_stylesheet( $rels );
		if ( '' === $css_rel ) {
			$wp_filesystem->delete( $incoming, true );
			return new WP_Error( 'wpfonts_package', 'css' );
		}
		$bytes = 0;
		foreach ( $rels as $rel ) {
			$bytes += (int) $wp_filesystem->size( $incoming . '/' . $rel );
			if ( $bytes > self::MAX_BYTES ) {
				$wp_filesystem->delete( $incoming, true );
				return new WP_Error( 'wpfonts_package', 'size' );
			}
		}

		$existing = array();
		foreach ( self::all() as $package ) {
			$existing[] = $package['id'];
		}
		$slug    = self::unique_slug( self::slug_from_filename( $name ), $existing );
		$baseurl = trailingslashit( $uploads['baseurl'] ) . 'wpfonts/packages/' . $slug;
		$raw     = $wp_filesystem->get_contents( $incoming . '/' . $css_rel );
		if ( ! is_string( $raw ) || '' === $raw ) {
			$wp_filesystem->delete( $incoming, true );
			return new WP_Error( 'wpfonts_package', 'read' );
		}
		$local = self::rewrite_css( $raw, $css_rel, $baseurl );
		if ( ! $wp_filesystem->put_contents( $incoming . '/local.css', $local, FS_CHMOD_FILE ) ) {
			$wp_filesystem->delete( $incoming, true );
			return new WP_Error( 'wpfonts_package', 'write' );
		}

		$final = $root . '/' . $slug;
		if ( ! $wp_filesystem->move( $incoming, $final, true ) ) {
			$wp_filesystem->delete( $incoming, true );
			return new WP_Error( 'wpfonts_package', 'move' );
		}

		$display = self::plain_name( pathinfo( $name, PATHINFO_FILENAME ) );
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
		$packages   = self::all();
		$packages[] = array(
			'id'           => $slug,
			'name'         => $display,
			'family'       => $family,
			'enabled'      => 1,
			'installed_at' => time(),
		);
		update_option( self::OPTION, $packages, true );
		return true;
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
	 * Remove one package directory and its registry row.
	 *
	 * @param string $id Package id.
	 * @return bool
	 */
	public static function delete( $id ) {
		$id = self::clean_id( $id );
		if ( '' === $id || ! function_exists( 'wp_upload_dir' ) ) {
			return false;
		}
		$packages = self::all();
		$next     = array();
		$found    = false;
		foreach ( $packages as $package ) {
			if ( $package['id'] === $id ) {
				$found = true;
				continue;
			}
			$next[] = $package;
		}
		if ( ! $found ) {
			return false;
		}
		$uploads = wp_upload_dir();
		if ( empty( $uploads['error'] ) ) {
			$root = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . 'wpfonts/packages' );
			$dir  = wp_normalize_path( $root . '/' . $id );
			if ( 0 === strpos( $dir, $root . '/' ) && is_dir( $dir ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
				global $wp_filesystem;
				if ( $wp_filesystem ) {
					$wp_filesystem->delete( $dir, true );
				}
			}
		}
		update_option( self::OPTION, $next, true );
		return true;
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
