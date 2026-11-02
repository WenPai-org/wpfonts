<?php
/**
 * Semantic name → inline RemixIcon line path.
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Icons', false ) ) {
	/**
	 * Product plugins call svg( 'help' ). Do not pass remote SVG.
	 */
	class Wenpai_Admin_Icons {

		/**
		 * Lucide stroke icons (ISC). Multi-path; not Remix fill.
		 *
		 * @return array<string, list<string>>
		 */
		private static function lucide_paths() {
			return array(
				// lucide-static feather (stroke).
				'feather' => array(
					'M12.67 19a2 2 0 0 0 1.416-.588l6.154-6.172a6 6 0 0 0-8.49-8.49L5.586 9.914A2 2 0 0 0 5 11.328V18a1 1 0 0 0 1 1z',
					'M16 8 2 22',
					'M17.5 15H9',
				),
			);
		}

		/**
		 * Inline SVG markup for a semantic icon name.
		 *
		 * @param string $name  Semantic key from icons/map.json.
		 * @param string $class CSS class, default wenpai-ico.
		 * @return string Safe HTML.
		 */
		public static function svg( $name, $class = 'wenpai-ico' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound -- Public API; renaming would break PHP 8 named-argument callers.
			$lucide = self::lucide_paths();
			if ( isset( $lucide[ $name ] ) ) {
				$inner = '';
				foreach ( $lucide[ $name ] as $d ) {
					$inner .= '<path d="' . esc_attr( $d ) . '"/>';
				}
				return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
			}
			$dir   = function_exists( 'wenpai_admin_ui_winner_dir' ) ? wenpai_admin_ui_winner_dir() : dirname( __DIR__ );
			$file  = $dir . '/icons/paths.php';
			$paths = is_readable( $file ) ? include $file : array();
			if ( ! is_array( $paths ) ) {
				$paths = array();
			}
			$d = isset( $paths[ $name ] ) ? $paths[ $name ] : ( isset( $paths['info'] ) ? $paths['info'] : '' );
			if ( '' === $d ) {
				return '';
			}
			return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="' . esc_attr( $d ) . '"/></svg>';
		}
	}
}
