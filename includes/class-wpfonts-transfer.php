<?php
/**
 * Export and import enabled package metadata and confirmed cloud faces.
 *
 * The file never contains the API key. A file that still carries a secret
 * is refused and nothing is written. The shape follows WPSSS export_public()
 * (plugin, version, exported_at, public options only) and WPSlug importOptions(),
 * which drops credential fields instead of storing them.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JSON migration for packages and 文风云 faces.
 */
class WPFonts_Transfer {

	/**
	 * Field names that must not be stored from an import file.
	 *
	 * @var string[]
	 */
	const SECRET_FIELDS = array(
		'api_key',
		'apikey',
		'wpfonts_api_key',
		'secret',
		'token',
		'authorization',
		'password',
		'access_key',
		'access_key_id',
		'access_key_secret',
		'google_api_key',
		'baidu_secret_key',
	);

	/**
	 * Document for download. Enabled packages and confirmed faces only.
	 *
	 * @return array<string,mixed>
	 */
	public static function export_document() {
		$packages = array();
		if ( class_exists( 'WPFonts_Packages', false ) ) {
			foreach ( WPFonts_Packages::all() as $package ) {
				if ( empty( $package['enabled'] ) ) {
					continue;
				}
				$packages[] = array(
					'id'      => $package['id'],
					'name'    => $package['name'],
					'family'  => $package['family'],
					'enabled' => 1,
				);
			}
		}
		$faces = array();
		if ( class_exists( 'WPFonts_Settings', false ) ) {
			foreach ( WPFonts_Settings::saved_faces( WPFonts_Settings::get() ) as $face ) {
				if ( '' === $face['css_face'] ) {
					continue;
				}
				$faces[] = array(
					'family'   => $face['family'],
					'name'     => $face['name'],
					'css_face' => $face['css_face'],
					'subset'   => $face['subset'],
				);
			}
		}
		return array(
			'plugin'      => 'wpfonts',
			'version'     => defined( 'WPFONTS_VERSION' ) ? WPFONTS_VERSION : '',
			'exported_at' => gmdate( 'c' ),
			'packages'    => $packages,
			'faces'       => $faces,
		);
	}

	/**
	 * Pretty JSON. Secret field names are removed even if a caller added them.
	 *
	 * @return string
	 */
	public static function export_json() {
		if ( ! function_exists( 'wp_json_encode' ) ) {
			return '';
		}
		$document = self::strip_secrets( self::export_document() );
		$json     = wp_json_encode( $document, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		return is_string( $json ) ? $json : '';
	}

	/**
	 * Apply a migration file.
	 *
	 * Packages that are not already installed on this site are skipped. The
	 * API key option is never written. A non-empty secret field refuses the
	 * whole file.
	 *
	 * @param string $raw JSON text.
	 * @return array{ok:bool,code:string,message:string}
	 */
	public static function import_json( $raw ) {
		$raw = (string) $raw;
		if ( strlen( $raw ) > 524288 ) {
			return self::result( false, 'invalid', __( '文件太大，没有导入。', 'wpfonts' ) );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return self::result( false, 'invalid', __( '这不是可读取的 JSON，没有导入。', 'wpfonts' ) );
		}
		if ( self::has_plaintext_secret( $data ) ) {
			return self::result( false, 'refused', __( '文件里有 API Key 或其他密钥，已拒绝导入。密钥不会写入本站。', 'wpfonts' ) );
		}
		$data = self::strip_secrets( $data );
		if ( isset( $data['plugin'] ) && 'wpfonts' !== (string) $data['plugin'] ) {
			return self::result( false, 'invalid', __( '这不是文派字库的导出文件，没有导入。', 'wpfonts' ) );
		}
		if ( ! array_key_exists( 'packages', $data ) && ! array_key_exists( 'faces', $data ) ) {
			return self::result( false, 'invalid', __( '文件里没有字体包或文风云字体，没有导入。', 'wpfonts' ) );
		}

		$enabled = 0;
		$skipped = 0;
		if ( isset( $data['packages'] ) && is_array( $data['packages'] ) && class_exists( 'WPFonts_Packages', false ) ) {
			$applied = self::apply_packages( $data['packages'] );
			$enabled = $applied['enabled'];
			$skipped = $applied['skipped'];
		}
		$faces = 0;
		if ( isset( $data['faces'] ) && is_array( $data['faces'] ) && class_exists( 'WPFonts_Settings', false ) ) {
			$faces = self::apply_faces( $data['faces'] );
		}
		if ( 0 === $enabled && 0 === $faces && 0 === $skipped ) {
			return self::result( false, 'invalid', __( '文件里没有可导入的字体包或文风云字体。', 'wpfonts' ) );
		}

		$message = sprintf(
			/* translators: 1: enabled package count, 2: skipped package count, 3: cloud face count. */
			__( '已启用 %1$d 个本站已有的文风字体包，跳过 %2$d 个本站没有文件的包，写入 %3$d 条已确认的文风云。密钥没有写入。', 'wpfonts' ),
			$enabled,
			$skipped,
			$faces
		);
		return self::result( true, 'imported', $message );
	}

	/**
	 * Whether a decoded document carries a credential.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return bool
	 */
	public static function has_plaintext_secret( $data ) {
		$stored = '';
		if ( class_exists( 'WPFonts_Settings', false ) ) {
			$stored = WPFonts_Settings::api_key();
		}
		return self::walk_secrets( $data, $stored );
	}

	/**
	 * Remove secret fields. Values that are not secrets stay.
	 *
	 * @param mixed $data Decoded value.
	 * @return mixed
	 */
	public static function strip_secrets( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( $data as $key => $value ) {
			if ( self::is_secret_field( $key ) ) {
				continue;
			}
			$out[ $key ] = is_array( $value ) ? self::strip_secrets( $value ) : $value;
		}
		return $out;
	}

	/**
	 * Whether this field name is a credential.
	 *
	 * @param mixed $key Field name.
	 * @return bool
	 */
	public static function is_secret_field( $key ) {
		$key = strtolower( str_replace( '-', '_', (string) $key ) );
		if ( in_array( $key, self::SECRET_FIELDS, true ) ) {
			return true;
		}
		return (bool) preg_match( '/(^|_)(api_key|secret|password|access_key)($|_)/', $key );
	}

	/**
	 * Enable installed packages named in the file. Unknown ids are skipped.
	 *
	 * @param array $rows Package rows.
	 * @return array{enabled:int,skipped:int}
	 */
	private static function apply_packages( array $rows ) {
		$packages = WPFonts_Packages::all();
		$index    = array();
		foreach ( $packages as $i => $package ) {
			$index[ $package['id'] ] = $i;
		}
		$enabled = 0;
		$skipped = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['enabled'] ) && empty( $row['enabled'] ) ) {
				continue;
			}
			$id = WPFonts_Packages::clean_id( isset( $row['id'] ) ? $row['id'] : '' );
			if ( '' === $id || ! isset( $index[ $id ] ) ) {
				if ( '' !== $id ) {
					++$skipped;
				}
				continue;
			}
			$at                         = $index[ $id ];
			$packages[ $at ]['enabled'] = 1;
			if ( isset( $row['name'] ) ) {
				$name = trim( (string) $row['name'] );
				if ( '' !== $name ) {
					$packages[ $at ]['name'] = $name;
				}
			}
			if ( isset( $row['family'] ) ) {
				$family = trim( (string) $row['family'] );
				if ( '' !== $family ) {
					$packages[ $at ]['family'] = $family;
				}
			}
			++$enabled;
		}
		if ( $enabled > 0 ) {
			update_option( WPFonts_Packages::OPTION, $packages, true );
		}
		return array(
			'enabled' => $enabled,
			'skipped' => $skipped,
		);
	}

	/**
	 * Replace the confirmed cloud list. The API key and region stay.
	 *
	 * @param array $rows Face rows.
	 * @return int
	 */
	private static function apply_faces( array $rows ) {
		$clean = array();
		foreach ( $rows as $row ) {
			$face = WPFonts_Settings::normalize_face( $row, true );
			if ( null === $face ) {
				continue;
			}
			if ( '' === $face['name'] ) {
				$face['name'] = $face['css_face'];
			}
			$clean[] = $face;
			if ( count( $clean ) >= WPFonts_Settings::MAX_FACES ) {
				break;
			}
		}
		if ( array() === $clean ) {
			return 0;
		}
		$settings               = WPFonts_Settings::get();
		$settings['faces']      = $clean;
		$settings['family']     = $clean[0]['family'];
		$settings['css_status'] = 'ok';
		$settings['css_face']   = $clean[0]['css_face'];
		WPFonts_Settings::write( $settings );
		return count( $clean );
	}

	/**
	 * @param bool   $ok      Success.
	 * @param string $code    Machine code.
	 * @param string $message Already translated sentence.
	 * @return array{ok:bool,code:string,message:string}
	 */
	private static function result( $ok, $code, $message ) {
		return array(
			'ok'      => (bool) $ok,
			'code'    => (string) $code,
			'message' => (string) $message,
		);
	}

	/**
	 * @param mixed  $data   Value.
	 * @param string $stored Current API key.
	 * @return bool
	 */
	private static function walk_secrets( $data, $stored ) {
		if ( ! is_array( $data ) ) {
			return false;
		}
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( self::walk_secrets( $value, $stored ) ) {
					return true;
				}
				continue;
			}
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}
			if ( self::is_secret_field( $key ) ) {
				return true;
			}
			if ( '' !== $stored && strlen( $stored ) === strlen( $value ) && hash_equals( $stored, $value ) ) {
				return true;
			}
		}
		return false;
	}
}
