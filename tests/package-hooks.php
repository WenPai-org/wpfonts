<?php
/**
 * Package install, replace, and delete actions.
 *
 * No WordPress. unzip_file is stubbed. A normal filesystem path must install
 * without is_uploaded_file(), and the delete action must run while the
 * directory is still present.
 *
 * @package WPFonts
 */

if ( defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with PHP directly, not inside WordPress.\n" );
	exit( 1 );
}

$work = sys_get_temp_dir() . '/wpfonts-hooks-' . getmypid();
if ( ! mkdir( $work, 0777, true ) && ! is_dir( $work ) ) {
	fwrite( STDERR, "Could not create temp dir.\n" );
	exit( 1 );
}

define( 'ABSPATH', $work . '/wordpress/' );
define( 'FS_CHMOD_FILE', 0644 );

$upload  = $work . '/uploads';
$fixture = $work . '/fixture';
mkdir( ABSPATH . 'wp-admin/includes', 0777, true );
mkdir( $upload, 0777, true );
mkdir( $fixture, 0777, true );
file_put_contents( ABSPATH . 'wp-admin/includes/file.php', "<?php\n" );

$css = '@font-face{font-family:"WF-Fzss";src:url("WF-Fzss-1.woff2") format("woff2");}';
file_put_contents( $fixture . '/result.css', $css );
file_put_contents( $fixture . '/meta.json', '{"fontFamily":"WF-Fzss","family":"方正书宋"}' );
file_put_contents( $fixture . '/WF-Fzss-1.woff2', 'woff2' );
$zip = $work . '/demo.zip';
file_put_contents( $zip, 'zip-bytes' );

$GLOBALS['wpfonts_options']   = array();
$GLOBALS['wpfonts_hooks']     = array();
$GLOBALS['wpfonts_unzip_from'] = $fixture;
$GLOBALS['wpfonts_upload']    = array(
	'basedir' => $upload,
	'baseurl' => 'https://example.test/wp-content/uploads',
	'error'   => false,
);

/**
 * Test filesystem used by install and delete.
 */
class WPFonts_Hook_Filesystem {

	/**
	 * @param string $path      Path.
	 * @param bool   $recursive Recursive.
	 * @return bool
	 */
	public function delete( $path, $recursive = false ) {
		if ( is_file( $path ) || is_link( $path ) ) {
			return unlink( $path );
		}
		if ( ! is_dir( $path ) ) {
			return true;
		}
		if ( $recursive ) {
			$items = scandir( $path );
			if ( is_array( $items ) ) {
				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}
					$this->delete( $path . '/' . $item, true );
				}
			}
		}
		return rmdir( $path );
	}

	/**
	 * @param string $path Path.
	 * @return int
	 */
	public function size( $path ) {
		$size = filesize( $path );
		return false === $size ? 0 : (int) $size;
	}

	/**
	 * @param string $path Path.
	 * @return string|false
	 */
	public function get_contents( $path ) {
		return file_get_contents( $path );
	}

	/**
	 * @param string $path     Path.
	 * @param string $contents Contents.
	 * @param int    $mode     Mode.
	 * @return bool
	 */
	public function put_contents( $path, $contents, $mode = 0644 ) {
		unset( $mode );
		return false !== file_put_contents( $path, $contents );
	}

	/**
	 * @param string $source      Source.
	 * @param string $destination Destination.
	 * @param bool   $overwrite   Overwrite.
	 * @return bool
	 */
	public function move( $source, $destination, $overwrite = false ) {
		if ( file_exists( $destination ) ) {
			if ( ! $overwrite ) {
				return false;
			}
			if ( ! $this->delete( $destination, true ) ) {
				return false;
			}
		}
		return rename( $source, $destination );
	}
}

/**
 * @return bool
 */
function WP_Filesystem() {
	global $wp_filesystem;
	$wp_filesystem = new WPFonts_Hook_Filesystem();
	return true;
}

/**
 * @param string $file Zip path. Ignored.
 * @param string $to   Destination directory.
 * @return true
 */
function unzip_file( $file, $to ) {
	unset( $file );
	$from = rtrim( (string) $GLOBALS['wpfonts_unzip_from'], '/' );
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $from, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ( $iterator as $item ) {
		$rel  = substr( $item->getPathname(), strlen( $from ) + 1 );
		$dest = $to . '/' . $rel;
		if ( $item->isDir() ) {
			if ( ! is_dir( $dest ) ) {
				mkdir( $dest, 0777, true );
			}
			continue;
		}
		$parent = dirname( $dest );
		if ( ! is_dir( $parent ) ) {
			mkdir( $parent, 0777, true );
		}
		copy( $item->getPathname(), $dest );
	}
	return true;
}

/**
 * @param string $name Name.
 * @return string
 */
function sanitize_file_name( $name ) {
	$name = preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name );
	return is_string( $name ) ? $name : '';
}

/**
 * @param string $path Path.
 * @return string
 */
function wp_normalize_path( $path ) {
	$path = str_replace( '\\', '/', (string) $path );
	return preg_replace( '|/+|', '/', $path );
}

/**
 * @param string $value Value.
 * @return string
 */
function trailingslashit( $value ) {
	return rtrim( (string) $value, "/\\" ) . '/';
}

/**
 * @param string $target Directory.
 * @return bool
 */
function wp_mkdir_p( $target ) {
	if ( is_dir( $target ) ) {
		return true;
	}
	return mkdir( $target, 0777, true );
}

/**
 * @param int  $length  Length.
 * @param bool $special Special.
 * @param bool $extra   Extra.
 * @return string
 */
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	unset( $special, $extra );
	return substr( bin2hex( random_bytes( 8 ) ), 0, (int) $length );
}

/**
 * @return array
 */
function wp_upload_dir() {
	return $GLOBALS['wpfonts_upload'];
}

/**
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}

/**
 * @param string $name    Option.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	if ( array_key_exists( $name, $GLOBALS['wpfonts_options'] ) ) {
		return $GLOBALS['wpfonts_options'][ $name ];
	}
	return $default;
}

/**
 * @param string $name     Option.
 * @param mixed  $value    Value.
 * @param mixed  $autoload Autoload.
 * @return bool
 */
function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	$GLOBALS['wpfonts_options'][ $name ] = $value;
	return true;
}

/**
 * @param mixed $thing Thing.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * Minimal error object.
 */
class WP_Error {

	/**
	 * @var string
	 */
	public $code = '';

	/**
	 * @var string
	 */
	public $message = '';

	/**
	 * @param string $code    Code.
	 * @param string $message Message.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = (string) $code;
		$this->message = (string) $message;
	}

	/**
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}

/**
 * @param string $hook Hook.
 * @param mixed  $arg  First argument.
 * @return void
 */
function do_action( $hook, $arg = null ) {
	$ids = array();
	$saved = get_option( 'wpfonts_packages', array() );
	if ( is_array( $saved ) ) {
		foreach ( $saved as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) ) {
				$ids[] = (string) $row['id'];
			}
		}
	}
	$dir = ( is_array( $arg ) && isset( $arg['dir'] ) ) ? (string) $arg['dir'] : '';
	$css = ( is_array( $arg ) && isset( $arg['local_css'] ) ) ? (string) $arg['local_css'] : '';
	$GLOBALS['wpfonts_hooks'][] = array(
		'hook'       => $hook,
		'arg'        => $arg,
		'dir_exists' => ( '' !== $dir && is_dir( $dir ) ),
		'css_read'   => ( '' !== $css && is_readable( $css ) && '' !== (string) file_get_contents( $css ) ),
		'option_ids' => $ids,
	);
}

require dirname( __DIR__ ) . '/includes/class-wpfonts-packages.php';

$failures = array();
$assert   = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$keys = array( 'action', 'slug', 'package_id', 'dir', 'uploads_rel', 'local_css', 'files' );

$uploaded = WPFonts_Packages::install_uploaded_file(
	array(
		'name'     => 'demo.zip',
		'tmp_name' => $zip,
		'size'     => filesize( $zip ),
		'error'    => UPLOAD_ERR_OK,
	)
);
$assert( is_wp_error( $uploaded ) && 'upload' === $uploaded->get_error_message(), 'browser path accepted a normal file' );
$assert( array() === $GLOBALS['wpfonts_hooks'], 'rejected upload fired a hook' );

$missing = WPFonts_Packages::install_local_zip( $work . '/missing.zip', 'missing.zip' );
$assert( is_wp_error( $missing ) && 'missing' === $missing->get_error_message(), 'missing zip did not fail closed' );
$assert( array() === $GLOBALS['wpfonts_hooks'], 'missing zip fired a hook' );

$installed = WPFonts_Packages::install_local_zip( $zip, 'demo.zip' );
$assert( is_array( $installed ), 'install did not return a location' );
if ( is_array( $installed ) ) {
	foreach ( $keys as $key ) {
		$assert( array_key_exists( $key, $installed ), 'install location missing ' . $key );
	}
	$assert( 'install' === $installed['action'], 'first install action drifted' );
	$assert( 'wenfeng-fzss' === $installed['slug'], 'slug did not follow WF- normalize' );
	$assert( $installed['slug'] === $installed['package_id'], 'slug and package_id diverged' );
	$assert( 'wpfonts/packages/wenfeng-fzss' === $installed['uploads_rel'], 'uploads_rel drifted' );
	$assert( true === $GLOBALS['wpfonts_hooks'][0]['dir_exists'], 'install hook ran before the directory existed' );
	$assert( array( 'wenfeng-fzss' ) === $GLOBALS['wpfonts_hooks'][0]['option_ids'], 'install hook ran before the list was saved' );
	$assert( is_array( $installed['files'] ) && in_array( 'local.css', $installed['files'], true ), 'files omitted local.css' );
	$assert( in_array( 'WF-Fzss-1.woff2', $installed['files'], true ), 'files omitted the woff2' );
	$css_text = (string) file_get_contents( (string) $installed['local_css'] );
	$assert( false !== strpos( $css_text, '"wenfeng-fzss"' ), 'family was not normalized' );
	$assert( false !== strpos( $css_text, 'WF-Fzss-1.woff2' ), 'woff2 file name was renamed' );
	$assert( false === strpos( $css_text, 'font-family:"WF-Fzss"' ), 'legacy font-family remained' );
}
$assert( 1 === count( WPFonts_Packages::all() ), 'install did not save one package' );
$assert( 1 === count( $GLOBALS['wpfonts_hooks'] ), 'install fired more than once' );
$assert( 'wpfonts_package_installed' === $GLOBALS['wpfonts_hooks'][0]['hook'], 'install hook name drifted' );

$assert( true === WPFonts_Packages::set_enabled( 'wenfeng-fzss', false ), 'could not disable the package' );
$replaced = WPFonts_Packages::install_local_zip( $zip, 'demo.zip' );
$assert( is_array( $replaced ) && 'reinstall' === $replaced['action'], 'second install did not replace' );
$assert( is_array( $replaced ) && 'wenfeng-fzss' === $replaced['package_id'], 'replace changed the id' );
$rows = WPFonts_Packages::all();
$assert( 1 === count( $rows ), 'replace stored a second package' );
$assert( 0 === (int) $rows[0]['enabled'], 'replace turned a disabled package back on' );
$assert( 2 === count( $GLOBALS['wpfonts_hooks'] ), 'replace fired an extra hook' );
$assert( 'wpfonts_package_installed' === $GLOBALS['wpfonts_hooks'][1]['hook'], 'replace used the delete hook' );

$removed = WPFonts_Packages::delete( 'wenfeng-fzss' );
$assert( true === $removed, 'delete did not report success' );
$delete_hook = $GLOBALS['wpfonts_hooks'][2];
$assert( 'wpfonts_package_deleted' === $delete_hook['hook'], 'delete hook name drifted' );
$assert( 'delete' === $delete_hook['arg']['action'], 'delete action drifted' );
$assert( true === $delete_hook['dir_exists'], 'delete hook ran after the directory was removed' );
$assert( true === $delete_hook['css_read'], 'listener could not read local.css during delete' );
$assert( array( 'wenfeng-fzss' ) === $delete_hook['option_ids'], 'package list was cleared before the delete hook' );
$assert( is_array( $delete_hook['arg']['files'] ) && in_array( 'local.css', $delete_hook['arg']['files'], true ), 'delete files were not listed' );
$assert( ! is_dir( (string) $delete_hook['arg']['dir'] ), 'directory remained after delete returned' );
$assert( array() === WPFonts_Packages::all(), 'package list still contains the deleted id' );

$hook_count = count( $GLOBALS['wpfonts_hooks'] );
$assert( false === WPFonts_Packages::delete( 'missing' ), 'unknown id reported success' );
$assert( $hook_count === count( $GLOBALS['wpfonts_hooks'] ), 'unknown id fired a hook' );

$again = WPFonts_Packages::install_local_zip( $zip, 'demo.zip' );
$assert( is_array( $again ), 'reinstall after delete failed' );
if ( is_array( $again ) ) {
	$fs = new WPFonts_Hook_Filesystem();
	$fs->delete( (string) $again['dir'], true );
}
$empty = count( $GLOBALS['wpfonts_hooks'] );
$assert( true === WPFonts_Packages::delete( 'wenfeng-fzss' ), 'delete of a missing directory failed' );
$early = $GLOBALS['wpfonts_hooks'][ $empty ];
$assert( 'wpfonts_package_deleted' === $early['hook'], 'missing directory skipped the delete hook' );
$assert( '' === $early['arg']['dir'], 'missing directory still reported a path' );
$assert( array() === $early['arg']['files'], 'missing directory invented a file list' );
$assert( array( 'wenfeng-fzss' ) === $early['option_ids'], 'list was cleared before the empty delete hook' );
$assert( array() === WPFonts_Packages::all(), 'list kept the package after the empty delete' );

$root     = dirname( __DIR__ );
$packages = (string) file_get_contents( $root . '/includes/class-wpfonts-packages.php' );
$cli      = (string) file_get_contents( $root . '/includes/class-wpfonts-cli.php' );
$main     = (string) file_get_contents( $root . '/wpfonts.php' );
$upload_at = strpos( $packages, 'function install_uploaded_file' );
$local_at  = strpos( $packages, 'function install_local_zip' );
$enable_at = strpos( $packages, 'function set_enabled' );
$delete_at = strpos( $packages, 'function delete' );
$next_at   = strpos( $packages, 'function package_location' );
$assert( false !== $upload_at && false !== $local_at && $upload_at < $local_at, 'install methods moved' );
$upload_body = substr( $packages, $upload_at, $local_at - $upload_at );
$local_body  = substr( $packages, $local_at, $enable_at - $local_at );
$delete_body = substr( $packages, $delete_at, $next_at - $delete_at );
$assert( false !== strpos( $upload_body, 'is_uploaded_file' ), 'browser install dropped is_uploaded_file' );
$assert( false === strpos( $local_body, 'is_uploaded_file' ), 'disk install requires is_uploaded_file' );
$installed_hook = strpos( $local_body, "do_action( 'wpfonts_package_installed'" );
$installed_save = strpos( $local_body, 'update_option( self::OPTION, $packages, true )' );
$assert( false !== $installed_save && false !== $installed_hook && $installed_save < $installed_hook, 'install hook runs before the list is saved' );
$deleted_hook = strpos( $delete_body, "do_action( 'wpfonts_package_deleted'" );
$deleted_fs   = strpos( $delete_body, '$wp_filesystem->delete' );
$deleted_save = strpos( $delete_body, 'update_option( self::OPTION, $next, true )' );
$assert( false !== $deleted_hook && false !== $deleted_fs && false !== $deleted_save, 'delete hook markers missing' );
$assert( $deleted_hook < $deleted_fs && $deleted_hook < $deleted_save, 'delete hook runs after cleanup' );
$assert( false !== strpos( $cli, "WP_CLI::add_command( 'fonts package', 'WPFonts_CLI' )" ), 'CLI command group drifted' );
$assert( false === strpos( $cli, 'wpfonts package' ), 'CLI still registers wpfonts package' );
$assert( false !== strpos( $main, 'includes/class-wpfonts-cli.php' ), 'main file does not load the CLI class' );
$assert( false !== strpos( $main, "defined( 'WP_CLI' ) && WP_CLI" ), 'CLI class loads on a front-end request' );

if ( array() !== $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: package hooks fire around install, replace, and delete.\n";
