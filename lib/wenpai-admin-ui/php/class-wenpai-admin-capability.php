<?php
/**
 * Cross-plugin capability probe and referral for WenPai sibling products.
 *
 * Use with field type `status` (read-only) and `choice` overlays (hint +
 * disabled). Products pass the sibling's plugin file or callables plus their
 * own copy; the kit only shapes three states (available, installed but not
 * ready, missing) and the permission-aware next step (install, activate,
 * settings). Referral parts can be switched off site-wide, see enabled().
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Capability', false ) ) {

	/**
	 * Sibling-product referral helper (e.g. WPSlug → WenPai XinSi).
	 */
	class Wenpai_Admin_Capability {

		/**
		 * Default action labels. Products override per call with `labels`.
		 *
		 * @var array<string, string>
		 */
		const LABELS = array(
			'install'  => '安装',
			'activate' => '启用',
			'settings' => '打开设置',
			'learn'    => '了解更多',
		);

		/**
		 * Whether referral parts (recommend hint, install link, missing-state
		 * copy) are shown. Factual states of installed siblings are always shown.
		 *
		 * Off with define( 'WENPAI_ADMIN_UI_REFERRALS', false ) in wp-config.php,
		 * or the wenpai_admin_ui_referrals filter (bool $on, array $args).
		 *
		 * @param array $args Same args as probe(); passed to the filter.
		 * @return bool
		 */
		public static function enabled( array $args = array() ) {
			$on = ! ( defined( 'WENPAI_ADMIN_UI_REFERRALS' ) && ! WENPAI_ADMIN_UI_REFERRALS );
			if ( function_exists( 'apply_filters' ) ) {
				$on = (bool) apply_filters( 'wenpai_admin_ui_referrals', $on, $args );
			}
			return $on;
		}

		/**
		 * Resolve the sibling's state.
		 *
		 * @param array $args {
		 *     Probe arguments.
		 *
		 *     @type string        $plugin    Sibling plugin file relative to the plugins dir (wpmind/wpmind.php). Fills installed / active.
		 *     @type callable|bool $installed Sibling present (files or class).
		 *     @type callable|bool $active    Sibling plugin active.
		 *     @type callable|bool $available Sibling ready to use (active and configured).
		 * }
		 * @return array{state: string, installed: bool, active: bool, available: bool} state is available|installed|missing.
		 */
		public static function probe( array $args ) {
			$plugin    = isset( $args['plugin'] ) ? (string) $args['plugin'] : '';
			$installed = array_key_exists( 'installed', $args ) ? self::flag( $args['installed'] ) : self::plugin_installed( $plugin );
			$active    = array_key_exists( 'active', $args ) ? self::flag( $args['active'] ) : self::plugin_active( $plugin );
			$available = array_key_exists( 'available', $args ) ? self::flag( $args['available'] ) : $active;
			if ( $available ) {
				$active = true;
			}
			if ( $active ) {
				$installed = true;
			}
			if ( $available ) {
				$state = 'available';
			} elseif ( $installed ) {
				$state = 'installed';
			} else {
				$state = 'missing';
			}
			return array(
				'state'     => $state,
				'installed' => $installed,
				'active'    => $active,
				'available' => $available,
			);
		}

		/**
		 * Next step for the current user, as a status link, or null.
		 *
		 * When missing: install_url if the user can install plugins (and referrals
		 * are on), else learn_url in a new tab. Installed but inactive: a nonce
		 * activate link when the user can activate plugins. Active (ready or
		 * not): settings_url when the user can manage options.
		 *
		 * @param array $args probe() args plus install_url, learn_url, settings_url, labels.
		 * @return array{url: string, label: string, target?: string}|null
		 */
		public static function action( array $args ) {
			$probe  = self::probe( $args );
			$labels = array_merge( self::LABELS, isset( $args['labels'] ) ? (array) $args['labels'] : array() );
			if ( 'missing' === $probe['state'] ) {
				if ( ! self::enabled( $args ) ) {
					return null;
				}
				if ( ! empty( $args['install_url'] ) && self::can( 'install_plugins' ) ) {
					return array(
						'url'   => (string) $args['install_url'],
						'label' => $labels['install'],
					);
				}
				if ( ! empty( $args['learn_url'] ) ) {
					return array(
						'url'    => (string) $args['learn_url'],
						'label'  => $labels['learn'],
						'target' => '_blank',
					);
				}
				return null;
			}
			$plugin = isset( $args['plugin'] ) ? (string) $args['plugin'] : '';
			if ( ! $probe['active'] && '' !== $plugin && self::can( 'activate_plugin', $plugin ) && function_exists( 'wp_nonce_url' ) ) {
				$base = function_exists( 'self_admin_url' ) ? self_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
				return array(
					'url'   => wp_nonce_url( $base . '?action=activate&plugin=' . rawurlencode( $plugin ), 'activate-plugin_' . $plugin ),
					'label' => $labels['activate'],
				);
			}
			if ( $probe['active'] && ! empty( $args['settings_url'] ) && self::can( 'manage_options' ) ) {
				return array(
					'url'   => (string) $args['settings_url'],
					'label' => $labels['settings'],
				);
			}
			return null;
		}

		/**
		 * Status payload for field type `status`.
		 *
		 * Each state's array may set level/text/desc/link; a missing link is
		 * filled from action(). With referrals off, the missing state keeps
		 * only its pill text (a fact, no pitch): no desc, no link.
		 * Installed and active but not ready uses setup_status when given,
		 * else installed_status.
		 *
		 * @param array $args probe() / action() args plus available_status,
		 *                    installed_status, setup_status, missing_status.
		 * @return array{level: string, text: string, desc: string[], link?: array}
		 */
		public static function status( array $args ) {
			$probe = self::probe( $args );
			$key   = $probe['state'] . '_status';
			if ( 'installed' === $probe['state'] && $probe['active'] && isset( $args['setup_status'] ) ) {
				$key = 'setup_status';
			}
			$st  = isset( $args[ $key ] ) && is_array( $args[ $key ] ) ? $args[ $key ] : array();
			$out = array(
				'level' => isset( $st['level'] ) ? (string) $st['level'] : ( 'available' === $probe['state'] ? 'ok' : ( 'installed' === $probe['state'] ? 'warn' : 'mute' ) ),
				'text'  => isset( $st['text'] ) ? (string) $st['text'] : '',
				'desc'  => isset( $st['desc'] ) ? array_values( (array) $st['desc'] ) : array(),
			);
			if ( 'missing' === $probe['state'] && ! self::enabled( $args ) ) {
				$out['desc'] = array();
				return $out;
			}
			$link = isset( $st['link'] ) && is_array( $st['link'] ) ? $st['link'] : self::action( $args );
			if ( $link ) {
				$out['link'] = $link;
			}
			return $out;
		}

		/**
		 * Choice-card overlay for a capability-gated option.
		 *
		 * When missing: disabled, with hint_missing (e.g. 推荐) so the card stays as
		 * a referral; with referrals off it is hidden instead (the product
		 * drops the choice). installed / available: enabled, product copy owns
		 * the label; runtime fallback stays with the product.
		 *
		 * @param array $args probe() args plus optional hint_missing.
		 * @return array{disabled: bool, hint: string, hidden: bool}
		 */
		public static function choice_gate( array $args ) {
			$probe   = self::probe( $args );
			$missing = 'missing' === $probe['state'];
			$on      = self::enabled( $args );
			return array(
				'disabled' => $missing,
				'hint'     => $missing && $on && isset( $args['hint_missing'] ) ? (string) $args['hint_missing'] : '',
				'hidden'   => $missing && ! $on,
			);
		}

		/**
		 * Whether the plugin file exists in the plugins dir.
		 *
		 * @param string $plugin Plugin file relative to the plugins dir.
		 * @return bool
		 */
		private static function plugin_installed( $plugin ) {
			if ( '' === $plugin || ! defined( 'WP_PLUGIN_DIR' ) || false !== strpos( $plugin, '..' ) ) {
				return false;
			}
			return is_file( WP_PLUGIN_DIR . '/' . $plugin );
		}

		/**
		 * Whether the plugin is active on this site or network-wide.
		 *
		 * @param string $plugin Plugin file relative to the plugins dir.
		 * @return bool
		 */
		private static function plugin_active( $plugin ) {
			if ( '' === $plugin ) {
				return false;
			}
			if ( function_exists( 'is_plugin_active' ) ) {
				return is_plugin_active( $plugin );
			}
			if ( in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) ) {
				return true;
			}
			if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
				return array_key_exists( $plugin, (array) get_site_option( 'active_sitewide_plugins', array() ) );
			}
			return false;
		}

		/**
		 * Current user capability check, false outside WordPress.
		 *
		 * @param string $cap  Capability.
		 * @param mixed  ...$a Extra args for meta caps.
		 * @return bool
		 */
		private static function can( $cap, ...$a ) {
			return function_exists( 'current_user_can' ) && current_user_can( $cap, ...$a );
		}

		/**
		 * Bool from a flag or a callable.
		 *
		 * @param callable|bool $v Flag or callable returning bool.
		 * @return bool
		 */
		private static function flag( $v ) {
			if ( is_callable( $v ) ) {
				return (bool) call_user_func( $v );
			}
			return (bool) $v;
		}
	}
}
