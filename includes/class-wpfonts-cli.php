<?php
/**
 * WP-CLI commands for on-site font packages.
 *
 * Command group is `fonts`, so the commands are `wp fonts package …`.
 * Install uses the same unpack path as the packages screen and does not
 * require a browser upload. Hooks fire from that shared path.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Install, delete, and list font packages from WP-CLI.
 */
class WPFonts_CLI {

	/**
	 * Install a font package from a zip on disk.
	 *
	 * The zip is unpacked and checked the same way as a packages-screen upload.
	 * A browser upload is not required. Installing an id that is already
	 * present replaces that package.
	 *
	 * ## OPTIONS
	 *
	 * <zip>
	 * : Path to the zip file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp fonts package install /path/to/package.zip
	 *
	 * @param string[] $args       Positional arguments. First is the zip path.
	 * @param array    $assoc_args Associative arguments. Unused.
	 * @return void
	 */
	public function install( $args, $assoc_args ) {
		unset( $assoc_args );
		$path = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			WP_CLI::error( __( '找不到这个 zip。', 'wpfonts' ) );
		}
		$name   = function_exists( 'sanitize_file_name' ) ? sanitize_file_name( basename( $path ) ) : basename( $path );
		$result = WPFonts_Packages::install_local_zip( $path, $name );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( self::failure_message( $result->get_error_message() ) );
		}
		$id = '';
		if ( is_array( $result ) && isset( $result['package_id'] ) ) {
			$id = (string) $result['package_id'];
		}
		if ( is_array( $result ) && isset( $result['action'] ) && 'reinstall' === $result['action'] ) {
			/* translators: %s: package id */
			WP_CLI::success( sprintf( __( '已替换 %s。', 'wpfonts' ), $id ) );
			return;
		}
		/* translators: %s: package id */
		WP_CLI::success( sprintf( __( '已安装 %s。', 'wpfonts' ), $id ) );
	}

	/**
	 * Delete one font package by its slug or id.
	 *
	 * The slug and the id are the directory name under wpfonts/packages.
	 * The delete action runs while that directory is still on disk.
	 *
	 * ## OPTIONS
	 *
	 * <slug-or-id>
	 * : Package slug or id.
	 *
	 * ## EXAMPLES
	 *
	 *     wp fonts package delete <slug-or-id>
	 *
	 * @param string[] $args       Positional arguments. First is the slug or id.
	 * @param array    $assoc_args Associative arguments. Unused.
	 * @return void
	 */
	public function delete( $args, $assoc_args ) {
		unset( $assoc_args );
		$slug    = isset( $args[0] ) ? (string) $args[0] : '';
		$package = WPFonts_Packages::find( $slug );
		if ( ! is_array( $package ) ) {
			WP_CLI::error( __( '没有这个字体包。', 'wpfonts' ) );
		}
		$id = isset( $package['id'] ) ? (string) $package['id'] : '';
		if ( ! WPFonts_Packages::delete( $id ) ) {
			WP_CLI::error( __( '没能删除这个字体包。', 'wpfonts' ) );
		}
		/* translators: %s: package id */
		WP_CLI::success( sprintf( __( '已删除 %s。', 'wpfonts' ), $id ) );
	}

	/**
	 * List installed font packages.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - ids
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp fonts package list
	 *
	 * @subcommand list
	 *
	 * @param string[] $args       Positional arguments. Unused.
	 * @param array    $assoc_args Associative arguments. Accepts --format.
	 * @return void
	 */
	public function list_packages( $args, $assoc_args ) {
		unset( $args );
		$items = array();
		foreach ( WPFonts_Packages::all() as $package ) {
			$items[] = array(
				'id'           => $package['id'],
				'name'         => $package['name'],
				'family'       => $package['family'],
				'enabled'      => empty( $package['enabled'] ) ? '0' : '1',
				'installed_at' => (string) $package['installed_at'],
			);
		}
		$format = 'table';
		if ( isset( $assoc_args['format'] ) && is_string( $assoc_args['format'] ) && '' !== $assoc_args['format'] ) {
			$format = $assoc_args['format'];
		}
		if ( array() === $items && 'table' === $format ) {
			WP_CLI::log( __( '还没有字体包。', 'wpfonts' ) );
			return;
		}
		\WP_CLI\Utils\format_items( $format, $items, array( 'id', 'name', 'family', 'enabled', 'installed_at' ) );
	}

	/**
	 * One sentence for a package install failure code.
	 *
	 * @param string $code Machine code from WP_Error.
	 * @return string
	 */
	private static function failure_message( $code ) {
		switch ( (string) $code ) {
			case 'size':
				return __( '这个 zip 太大，或是空的。', 'wpfonts' );
			case 'type':
				return __( '请提供 .zip 文件。', 'wpfonts' );
			case 'uploads':
				return __( '读不到上传目录。', 'wpfonts' );
			case 'filesystem':
				return __( '现在写不了文件。', 'wpfonts' );
			case 'mkdir':
				return __( '建不了字体包目录。', 'wpfonts' );
			case 'unzip':
				return __( '解不开这个 zip。', 'wpfonts' );
			case 'contents':
				return __( '包里需要有 woff2，并且不能有脚本文件。', 'wpfonts' );
			case 'css':
				return __( '包里需要一份样式。', 'wpfonts' );
			case 'missing':
			case 'read':
				return __( '找不到这个 zip，或读不到包里的样式。', 'wpfonts' );
			case 'write':
				return __( '写不了本站样式。', 'wpfonts' );
			case 'move':
				return __( '没能把字体包放到上传目录。', 'wpfonts' );
			default:
				return __( '装不上。请用文风已切好的 zip（内含样式和 woff2）。', 'wpfonts' );
		}
	}
}

WP_CLI::add_command( 'fonts package', 'WPFonts_CLI' );
