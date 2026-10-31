<?php
/**
 * Declarative settings framework (roadmap layer 3).
 *
 * @package WenpaiAdminUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Settings' ) ) {
	/**
	 * One instance per option.
	 *
	 * Field schema keys: type, default, label, desc, choices, rows, row_label,
	 * col_label, unset_value, min, max, secret, sanitize, validate, depends_on,
	 * tab, layout, actions, status, render.
	 * Types: toggle, select, radio, choice, number, text, textarea, multicheck,
	 * multiselect, matrix, secret, map, status. See docs/framework/settings.md.
	 */
	class Wenpai_Admin_Settings {

		/**
		 * Submitted list of secret field ids to clear: {option}[__wenpai_clear][].
		 */
		const CLEAR_KEY = '__wenpai_clear';

		/**
		 * Submitted tab id: {option}[__wenpai_tab]. Only that tab's fields are written.
		 */
		const TAB_KEY = '__wenpai_tab';

		/**
		 * Posted key carrying the nonce of the tab marker.
		 */
		const TAB_NONCE_KEY = '__wenpai_tab_nonce';

		/**
		 * Posted list of secret field ids where kit JS caught a multi-line paste.
		 */
		const MULTILINE_KEY = '__wenpai_multiline';

		/**
		 * Settings error code that tags an options.php save of this option, so the
		 * redirect can be recognised and core's duplicate 「Settings saved.」 dropped.
		 */
		const SAVED_CODE = 'wenpai_saved';

		/**
		 * Instances keyed by option name.
		 *
		 * @var array<string, Wenpai_Admin_Settings>
		 */
		private static $registry = array();

		/**
		 * Option name in wp_options.
		 *
		 * @var string
		 */
		private $option;

		/**
		 * Settings API option group.
		 *
		 * @var string
		 */
		private $group;

		/**
		 * Field schema keyed by field id, with defaults filled in.
		 *
		 * @var array<string, array>
		 */
		private $fields;

		/**
		 * UI strings. The kit has no text domain; plugins pass translated strings.
		 *
		 * @var array<string, string>
		 */
		private $strings;

		/**
		 * Field errors recorded during sanitize() in this request: id => message.
		 *
		 * @var array<string, string>
		 */
		private $errors = array();

		/**
		 * Matrix rows in error: field id => row keys.
		 *
		 * @var array<string, string[]>
		 */
		private $error_rows = array();

		/**
		 * Output of the last sanitize() call, so a second pass over the same
		 * values (a plugin sanitize_callback wrapping ours) changes nothing.
		 *
		 * @var array|null
		 */
		private $last_sanitized = null;

		/**
		 * Whether the "tabbed schema, no tab marker" notice was given this request.
		 *
		 * @var bool
		 */
		private $warned_marker = false;

		/**
		 * Form-level error of this request (e.g. an expired tab marker).
		 *
		 * @var string
		 */
		private $form_error = '';

		/**
		 * Settings errors of this option taken out of core's list on the page
		 * that shows them (see claim_core_notices()); null until claimed.
		 *
		 * @var array|null
		 */
		private $claimed = null;

		/**
		 * True while import() runs sanitize(): no tab-marker notice there.
		 *
		 * @var bool
		 */
		private $importing = false;

		/**
		 * Create and register a settings instance for one option.
		 *
		 * @param string $option Option name.
		 * @param array  $fields Field schema keyed by field id.
		 * @param array  $args   Optional. Supports 'group', 'strings', 'layout' (row|stack, default row).
		 * @return Wenpai_Admin_Settings
		 */
		public static function register( $option, array $fields, array $args = array() ) {
			$inst                      = new self( $option, $fields, $args );
			self::$registry[ $option ] = $inst;
			if ( function_exists( 'add_action' ) ) {
				add_action( 'admin_init', array( $inst, 'register_setting' ) );
				// in_admin_header runs before options-head.php's settings_errors() and before every
				// admin_notices callback (admin-header.php), so neither core nor a plugin calling
				// settings_errors() there prints this option's entries; the page shows one kit notice.
				add_action( 'in_admin_header', array( $inst, 'claim_core_notices' ), 0 );
				// Fallback for screens that print notices without in_admin_header; no-op once claimed.
				add_action( 'all_admin_notices', array( $inst, 'claim_core_notices' ), PHP_INT_MAX );
			}
			return $inst;
		}

		/**
		 * Look up a registered instance.
		 *
		 * @param string $option Option name.
		 * @return Wenpai_Admin_Settings|null
		 */
		public static function get_instance( $option ) {
			return isset( self::$registry[ $option ] ) ? self::$registry[ $option ] : null;
		}

		/**
		 * Default UI strings (zh_CN, same wording as the WPSlug and wpsss packs).
		 *
		 * @return array<string, string>
		 */
		public static function default_strings() {
			return array(
				'on'               => '已开启',
				'off'              => '未开启',
				'secret_saved'     => '已保存一个值，出于安全不会显示。留空表示不修改。',
				'secret_empty'     => '还没有填写。',
				'secret_clear'     => '清除已保存的值',
				'saved'            => '设置已保存。',
				'saving'           => '正在保存…',
				'partial_saved'    => '其余设置已保存。',
				/* translators: %d: number of fields that were not saved. */
				'invalid'          => '暂时无法保存 %d 项设置，请按字段下的提示修改。',
				/* translators: %d: total number of fields with errors. */
				'invalid_more'     => '等 %d 项',
				'invalid_value'    => '这一项的值无效，已保留原来的设置。',
				'follow_global'    => '跟随全局设置',
				/* translators: %s: what would be listed, e.g. 文章类型. */
				'empty_choices'    => '还没有可选的%s。',
				/* translators: 1: hidden tab label, 2: first tab label. */
				'tab_hidden'       => '当前模式不需要%1$s的设置，已切回%2$s。',
				// In-progress copy without the trailing 「…」: kit JS appends it.
				'action_busy'      => '正在处理',
				'action_ok'        => '已完成。',
				// Global fallback only. A handler names the service in its WP_Error message.
				'action_failed'    => '暂时无法完成操作，请稍后再试。',
				'tab_expired'      => '暂时无法保存，页面已过期，请刷新后再试。',
				'action_denied'    => '暂时无法完成操作，请刷新页面后再试。',
				// Screen-reader hint after a status link with target=_blank.
				'new_tab'          => '（在新标签页打开）',
				// A secret can only be one line (password inputs drop line breaks).
				'secret_multiline' => '暂时无法保存密钥，请填写单行的值。',
			);
		}

		/**
		 * Use register() instead.
		 *
		 * @param string $option Option name.
		 * @param array  $fields Field schema keyed by field id.
		 * @param array  $args   Optional args.
		 */
		private function __construct( $option, array $fields, array $args ) {
			$this->option  = $option;
			$this->group   = isset( $args['group'] ) ? $args['group'] : $option . '_group';
			$this->strings = array_merge(
				self::default_strings(),
				isset( $args['strings'] ) && is_array( $args['strings'] ) ? $args['strings'] : array()
			);
			// 0.3.1: row (label left, control right; stacks under 1100px) is the default again, as in 0.2.x. 'stack' opts out.
			$layout       = isset( $args['layout'] ) && 'stack' === $args['layout'] ? 'stack' : 'row';
			$this->fields = array();
			foreach ( $fields as $id => $f ) {
				$f = wp_parse_args(
					$f,
					array(
						'type'        => 'text',
						'default'     => '',
						'label'       => $id,
						'desc'        => '',
						'choices'     => array(),
						'rows'        => array(),
						'row_label'   => '',
						'col_label'   => '',
						'unset_value' => null,
						'secret'      => false,
						'validate'    => null,
						'depends_on'  => array(),
						'tab'         => '',
						'layout'      => $layout,
						'actions'     => array(),
						'status'      => null,
						'render'      => null,
					)
				);
				if ( 'secret' === $f['type'] ) {
					$f['secret'] = true;
				}
				if ( ! empty( $f['secret'] ) && 'secret' !== $f['type'] ) {
					// A secret is always a password box, so its value never reaches the page source.
					if ( 'text' !== $f['type'] ) {
						self::doing_it_wrong( sprintf( 'Field 「%s」: a secret is always rendered as a password input; type 「%s」 is ignored.', $id, $f['type'] ) );
					}
					$f['type'] = 'secret';
				}
				$tree = self::dep_tree( $f['depends_on'] );
				if ( null !== $tree && 'disable' === $tree['mode'] && ! self::tree_has_reason( $tree ) ) {
					self::doing_it_wrong( sprintf( 'Field 「%s」: depends_on mode 「disable」 needs a 「reason」 telling the user why and how to enable it.', $id ) );
				}
				$this->fields[ $id ] = $f;
			}
		}

		/**
		 * Filter caller HTML: choice icon_html (SVG only) and status render
		 * (post HTML plus SVG). Without wp_kses (outside WP) it is escaped.
		 *
		 * @param string $html    Caller HTML.
		 * @param string $context 'svg' or 'post'.
		 * @return string
		 */
		private static function kses( $html, $context ) {
			if ( ! function_exists( 'wp_kses' ) ) {
				return esc_html( $html );
			}
			$svg = array(
				'svg'      => array(
					'class'           => true,
					'viewbox'         => true,
					'width'           => true,
					'height'          => true,
					'fill'            => true,
					'stroke'          => true,
					'stroke-width'    => true,
					'stroke-linecap'  => true,
					'stroke-linejoin' => true,
					'aria-hidden'     => true,
					'focusable'       => true,
					'role'            => true,
					'xmlns'           => true,
				),
				'g'        => array(
					'fill'      => true,
					'stroke'    => true,
					'transform' => true,
				),
				'path'     => array(
					'd'               => true,
					'fill'            => true,
					'fill-rule'       => true,
					'clip-rule'       => true,
					'stroke'          => true,
					'stroke-width'    => true,
					'stroke-linecap'  => true,
					'stroke-linejoin' => true,
				),
				'circle'   => array(
					'cx' => true,
					'cy' => true,
					'r'  => true,
				),
				'ellipse'  => array(
					'cx' => true,
					'cy' => true,
					'rx' => true,
					'ry' => true,
				),
				'rect'     => array(
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
					'ry'     => true,
				),
				'line'     => array(
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				),
				'polyline' => array( 'points' => true ),
				'polygon'  => array( 'points' => true ),
				'title'    => array(),
			);
			// Paint attributes on every shape. defs, clipPath, mask and use are not supported.
			$paint = array(
				'fill'            => true,
				'fill-opacity'    => true,
				'fill-rule'       => true,
				'opacity'         => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-opacity'  => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'transform'       => true,
			);
			foreach ( array( 'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon' ) as $tag ) {
				$svg[ $tag ] = array_merge( $svg[ $tag ], $paint );
			}
			$allowed = 'svg' === $context ? $svg : array_merge( wp_kses_allowed_html( 'post' ), $svg );
			return wp_kses( $html, $allowed );
		}

		/**
		 * Developer notice, only with WP_DEBUG on.
		 *
		 * @param string $message  Message (English, developer-facing, plain text; core escapes it when displaying).
		 * @param string $where   Function to name.
		 */
		private static function doing_it_wrong( $message, $where = 'Wenpai_Admin_Settings::register' ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( '_doing_it_wrong' ) ) {
				_doing_it_wrong( $where, $message, '0.3.0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain developer text from field ids; core escapes it when displaying.
			}
		}

		/**
		 * Whether a depends_on tree carries reason copy anywhere.
		 *
		 * @param array $node Normalized node.
		 * @return bool
		 */
		private static function tree_has_reason( array $node ) {
			if ( isset( $node['reason'] ) && '' !== $node['reason'] ) {
				return true;
			}
			foreach ( isset( $node['rules'] ) ? $node['rules'] : array() as $r ) {
				if ( self::tree_has_reason( $r ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Register the option with the Settings API. Hooked on admin_init.
		 */
		public function register_setting() {
			register_setting(
				$this->group,
				$this->option,
				array(
					'type'              => 'array',
					'default'           => array(),
					'show_in_rest'      => false,
					'sanitize_callback' => array( $this, 'sanitize' ),
				)
			);
		}

		/**
		 * Settings API option group.
		 *
		 * @return string
		 */
		public function group() {
			return $this->group;
		}

		/**
		 * Option name.
		 *
		 * @return string
		 */
		public function option() {
			return $this->option;
		}

		/**
		 * Field schema with defaults filled in.
		 *
		 * @return array
		 */
		public function fields() {
			return $this->fields;
		}

		/**
		 * Nonce action of a tab marker.
		 *
		 * @param string $tab Tab id.
		 * @return string
		 */
		private function tab_nonce_action( $tab ) {
			return 'wenpai_tab_' . $this->option . '_' . $tab;
		}

		/**
		 * Whether a posted tab marker was issued by tab_input_html().
		 *
		 * @param string $tab   Tab id.
		 * @param mixed  $nonce Posted nonce.
		 * @return bool
		 */
		private function tab_nonce_ok( $tab, $nonce ) {
			if ( ! function_exists( 'wp_verify_nonce' ) ) {
				return true;
			}
			return is_scalar( $nonce ) && false !== wp_verify_nonce( (string) $nonce, $this->tab_nonce_action( $tab ) );
		}

		/**
		 * Whether a field is posted as checkboxes: when nothing is checked the
		 * browser sends nothing, so "absent" means "empty", not "not on this
		 * page". The kit adds a hidden 0 / empty sentinel for these, but
		 * hand-written forms may not.
		 *
		 * @param array $f Field schema.
		 * @return bool
		 */
		private static function is_checkbox_like( array $f ) {
			return in_array( $f['type'], array( 'toggle', 'multicheck', 'multiselect' ), true ) && ! self::is_secret( $f );
		}

		/**
		 * Whether any field declares a tab.
		 *
		 * @return bool
		 */
		private function has_tabs() {
			foreach ( $this->fields as $f ) {
				if ( array() !== array_filter( array_map( 'strval', (array) $f['tab'] ), 'strlen' ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Whether a field stores a value (status fields only display).
		 *
		 * @param array $f Field schema.
		 * @return bool
		 */
		private static function stores( array $f ) {
			return 'status' !== $f['type'];
		}

		/**
		 * Default value of every stored field.
		 *
		 * @return array
		 */
		public function defaults() {
			$out = array();
			foreach ( $this->fields as $id => $f ) {
				if ( self::stores( $f ) ) {
					$out[ $id ] = $f['default'];
				}
			}
			return $out;
		}

		/**
		 * Stored option as saved, without defaults.
		 *
		 * @return array
		 */
		private function stored() {
			$stored = get_option( $this->option, array() );
			return is_array( $stored ) ? $stored : array();
		}

		/**
		 * Stored values merged over defaults.
		 *
		 * @return array
		 */
		public function all() {
			return wp_parse_args( $this->stored(), $this->defaults() );
		}

		/**
		 * Value of one field.
		 *
		 * @param string $id Field id.
		 * @return mixed|null Null for an unknown field.
		 */
		public function get( $id ) {
			$all = $this->all();
			return isset( $all[ $id ] ) ? $all[ $id ] : null;
		}

		/**
		 * Whether a field never echoes its value.
		 *
		 * @param array $f Field schema.
		 * @return bool
		 */
		private static function is_secret( array $f ) {
			return ! empty( $f['secret'] );
		}

		/**
		 * Whether a field belongs to a tab. A field may list several tabs.
		 *
		 * @param array  $f   Field schema.
		 * @param string $tab Tab id.
		 * @return bool
		 */
		private static function in_tab( array $f, $tab ) {
			return in_array( (string) $tab, array_map( 'strval', (array) $f['tab'] ), true );
		}

		/**
		 * Ids of the fields in a tab, in schema order.
		 *
		 * @param string $tab Tab id.
		 * @return string[]
		 */
		public function fields_in_tab( $tab ) {
			$out = array();
			foreach ( $this->fields as $id => $f ) {
				if ( self::in_tab( $f, $tab ) ) {
					$out[] = (string) $id;
				}
			}
			return $out;
		}

		/**
		 * Resolve a list that may be given lazily as a Closure (for example post types).
		 *
		 * @param mixed $items Array, or a Closure returning one.
		 * @param array $f     Field schema, passed to the Closure.
		 * @return array
		 */
		private static function resolve( $items, array $f ) {
			if ( $items instanceof Closure ) {
				$items = call_user_func( $items, $f );
			}
			return is_array( $items ) ? $items : array();
		}

		/**
		 * Sanitize callback for the whole option.
		 *
		 * Secret fields: an empty value keeps the saved one; ids listed under
		 * {option}[__wenpai_clear][] are cleared. When {option}[__wenpai_tab] is
		 * posted only that tab's fields are sanitized and written; every other
		 * field keeps its saved value. A 'validate' callback that does not return
		 * true keeps the saved value and records a field error. A field whose
		 * depends_on fails for the values being saved (hide or disable mode)
		 * keeps its saved value; its sanitize and validate never run.
		 *
		 * @param mixed $input Raw submitted value.
		 * @return array
		 */
		public function sanitize( $input ) {
			$input = is_array( $input ) ? $input : array();
			if ( null !== $this->last_sanitized && $input === $this->last_sanitized ) {
				return $input;
			}
			$stored = $this->stored();
			$clear  = isset( $input[ self::CLEAR_KEY ] ) && is_array( $input[ self::CLEAR_KEY ] )
				? array_map( 'strval', $input[ self::CLEAR_KEY ] )
				: array();
			$tab    = isset( $input[ self::TAB_KEY ] ) && is_scalar( $input[ self::TAB_KEY ] )
				? sanitize_key( (string) $input[ self::TAB_KEY ] )
				: null;
			if ( null !== $tab && ! $this->tab_nonce_ok( $tab, isset( $input[ self::TAB_NONCE_KEY ] ) ? $input[ self::TAB_NONCE_KEY ] : '' ) ) {
				// A tab marker we did not issue: write nothing rather than guess which fields were on the page.
				$this->form_error = $this->strings['tab_expired'];
				if ( function_exists( 'add_settings_error' ) ) {
					add_settings_error( $this->option, 'wenpai_tab', $this->form_error, 'error' );
				}
				$kept = array();
				foreach ( $this->fields as $id => $f ) {
					if ( self::stores( $f ) ) {
						$kept[ $id ] = array_key_exists( $id, $stored ) ? $stored[ $id ] : $f['default'];
					}
				}
				$this->last_sanitized = $kept;
				return $kept;
			}
			$multi = isset( $input[ self::MULTILINE_KEY ] ) && is_array( $input[ self::MULTILINE_KEY ] )
				? array_map( 'strval', $input[ self::MULTILINE_KEY ] )
				: array();
			if ( null === $tab && ! $this->importing && ! $this->warned_marker && $this->has_tabs() ) {
				$this->warned_marker = true;
				self::doing_it_wrong( sprintf( 'Option 「%s」 declares tabs but the saved form has no kit tab marker, so every unsubmitted toggle, multicheck and multiselect is saved as unchecked. Output render_tab() or tab_input_html() inside each tab form.', $this->option ), 'Wenpai_Admin_Settings::sanitize' );
			}
			$out      = array();
			$prevs    = array();
			$pending  = array();
			$deferred = array();
			foreach ( $this->fields as $id => $f ) {
				if ( ! self::stores( $f ) ) {
					continue;
				}
				$raw  = array_key_exists( $id, $input ) ? $input[ $id ] : null;
				$prev = array_key_exists( $id, $stored ) ? $stored[ $id ] : $f['default'];
				if ( null !== $tab && ! self::in_tab( $f, $tab ) ) {
					$out[ $id ] = $prev;
					continue;
				}
				$prevs[ $id ] = $prev;
				$tree         = self::dep_tree( $f['depends_on'] );
				if ( null !== $tree ) {
					// Decided below, before any sanitize runs: a disabled or hidden field gets its stored value,
					// never sanitize( null ) or sanitize( what was posted ).
					$deferred[ $id ] = $raw;
					$out[ $id ]      = $prev;
					continue;
				}
				if ( null === $raw && self::is_secret( $f ) && in_array( (string) $id, $clear, true ) ) {
					// Clear without the (empty) password input in the request: still a clear.
					$out[ $id ] = '';
					continue;
				}
				if ( null === $raw && ! self::is_checkbox_like( $f ) ) {
					// Not on the submitted page (text, select, number, matrix …): keep it.
					// An unchecked checkbox is different: it sends nothing, see is_checkbox_like().
					$out[ $id ] = $prev;
					continue;
				}
				if ( self::is_secret( $f ) && ( in_array( (string) $id, $multi, true ) || ( is_string( $raw ) && preg_match( '/[\r\n]/', $raw ) ) ) ) {
					// Multi-line value (kit JS flags a pasted one, as the browser would fold it into spaces): refuse, keep the stored one.
					$out[ $id ] = $prev;
					$this->add_error( $id, $this->strings['secret_multiline'] );
					continue;
				}
				if ( self::is_secret( $f ) ) {
					$typed = is_scalar( $raw ) && '' !== trim( (string) $raw );
					if ( ! $typed ) {
						// A typed new value wins over "clear"; clear only applies to an empty box.
						$out[ $id ] = in_array( (string) $id, $clear, true ) ? '' : $prev;
						continue;
					}
				}
				$out[ $id ] = $this->sanitize_field( $f, $raw );
				if ( is_callable( $f['validate'] ) ) {
					$pending[ $id ] = true;
				}
			}

			// Fields with depends_on: settle which are active for the values being
			// saved (stored values stand in for the undecided ones), then sanitize
			// only those. A disabled or hidden field keeps its stored value without
			// its sanitize callback ever running (no side effects, e.g. remote checks).
			// Decide in dependency order: a field waits until every depends_on
			// field it depends on is decided, so sanitize runs at most once and
			// only for fields that end up enabled. Cycles keep the stored value.
			$decided = array();
			$total   = count( $deferred );
			for ( $pass = 0; $pass <= $total; $pass++ ) {
				if ( count( $decided ) >= $total ) {
					break;
				}
				$off = $this->inactive_fields( $out );
				foreach ( $deferred as $id => $raw ) {
					if ( isset( $decided[ $id ] ) ) {
						continue;
					}
					$wait = false;
					foreach ( $this->upstream( $id ) as $trigger ) {
						if ( isset( $deferred[ $trigger ] ) && ! isset( $decided[ $trigger ] ) ) {
							$wait = true;
						}
					}
					if ( $wait ) {
						continue;
					}
					$decided[ $id ] = true;
					if ( ! isset( $off[ $id ] ) ) {
						$out[ $id ] = $this->sanitize_deferred( $id, $raw, $prevs[ $id ], $clear, $multi, $pending );
						$off        = $this->inactive_fields( $out );
					}
				}
			}

			// depends_on, checked against the values being saved: a disabled or
			// hidden field always keeps the saved value, silently (not validated,
			// not in the error summary). Backstop for the loop above.
			foreach ( array_keys( $this->inactive_fields( $out ) ) as $id ) {
				if ( ! array_key_exists( $id, $prevs ) ) {
					continue;
				}
				unset( $pending[ $id ] );
				$out[ $id ] = $prevs[ $id ];
			}

			foreach ( array_keys( $pending ) as $id ) {
				$f  = $this->fields[ $id ];
				$ok = call_user_func( $f['validate'], $out[ $id ], $f );
				if ( true === $ok ) {
					continue;
				}
				$msg  = $this->strings['invalid_value'];
				$rows = array();
				if ( is_string( $ok ) && '' !== $ok ) {
					$msg = $ok;
				} elseif ( is_array( $ok ) ) {
					$msg  = isset( $ok['message'] ) && '' !== (string) $ok['message'] ? (string) $ok['message'] : $msg;
					$rows = isset( $ok['rows'] ) ? array_map( 'strval', (array) $ok['rows'] ) : array();
				}
				$this->add_error( $id, $msg, $rows );
				$out[ $id ] = $prevs[ $id ];
			}
			if ( empty( $this->errors ) && $this->saving_from_options_php() && function_exists( 'add_settings_error' ) ) {
				// Tags the options.php redirect as this option's, so claim_core_notices() knows the page;
				// it also keeps core from adding its own general/settings_updated.
				add_settings_error( $this->option, self::SAVED_CODE, $this->strings['saved'], 'success' );
			}
			$this->last_sanitized = $out;
			return $out;
		}

		/**
		 * Whether this request is options.php saving this option's group.
		 *
		 * @return bool
		 */
		private function saving_from_options_php() {
			return isset( $GLOBALS['pagenow'], $GLOBALS['option_page'] )
				&& 'options.php' === $GLOBALS['pagenow']
				&& $this->group === $GLOBALS['option_page'];
		}

		/**
		 * Field ids a depends_on tree looks at.
		 *
		 * @param array|null $node Normalized node.
		 * @return string[]
		 */
		private static function dep_fields( $node ) {
			if ( null === $node ) {
				return array();
			}
			if ( isset( $node['field'] ) ) {
				return array( $node['field'] );
			}
			$out = array();
			foreach ( $node['rules'] as $r ) {
				$out = array_merge( $out, self::dep_fields( $r ) );
			}
			return $out;
		}

		/**
		 * Every field a field's depends_on reaches, directly or through other
		 * fields' depends_on (a hide-mode field in between counts too).
		 *
		 * @param string $id Field id.
		 * @return string[]
		 */
		private function upstream( $id ) {
			$seen  = array();
			$queue = array( $id );
			while ( $queue ) {
				$cur = array_shift( $queue );
				if ( ! isset( $this->fields[ $cur ] ) ) {
					continue;
				}
				foreach ( self::dep_fields( self::dep_tree( $this->fields[ $cur ]['depends_on'] ) ) as $up ) {
					if ( ! isset( $seen[ $up ] ) && $up !== $id ) {
						$seen[ $up ] = true;
						$queue[]     = $up;
					}
				}
			}
			return array_keys( $seen );
		}

		/**
		 * Sanitize an enabled disable-mode field the same way as any other field.
		 *
		 * @param string $id      Field id.
		 * @param mixed  $raw     Posted value or null.
		 * @param mixed  $prev    Stored value.
		 * @param array  $clear   Secret ids to clear.
		 * @param array  $multi   Secret ids flagged multi-line by kit JS.
		 * @param array  $pending Field ids waiting for validate (by reference).
		 * @return mixed
		 */
		private function sanitize_deferred( $id, $raw, $prev, array $clear, array $multi, array &$pending ) {
			$f = $this->fields[ $id ];
			if ( null === $raw && ! self::is_checkbox_like( $f ) ) {
				return self::is_secret( $f ) && in_array( (string) $id, $clear, true ) ? '' : $prev;
			}
			if ( self::is_secret( $f ) ) {
				if ( in_array( (string) $id, $multi, true ) || ( is_string( $raw ) && preg_match( '/[\r\n]/', $raw ) ) ) {
					$this->add_error( $id, $this->strings['secret_multiline'] );
					return $prev;
				}
				if ( ! is_scalar( $raw ) || '' === trim( (string) $raw ) ) {
					return in_array( (string) $id, $clear, true ) ? '' : $prev;
				}
			}
			if ( is_callable( $f['validate'] ) ) {
				$pending[ $id ] = true;
			}
			return $this->sanitize_field( $f, $raw );
		}

		/**
		 * Fields whose depends_on fails for the given values, with their mode.
		 * A field that depends on an inactive field is inactive too.
		 *
		 * @param array $values Field values.
		 * @return array<string, string> Field id => 'hide' | 'disable'.
		 */
		public function inactive_fields( array $values ) {
			$off = array();
			for ( $pass = 0; $pass < 5; $pass++ ) {
				$changed = false;
				foreach ( $this->fields as $id => $f ) {
					$tree = self::dep_tree( $f['depends_on'] );
					if ( null === $tree ) {
						continue;
					}
					$met = $this->node_met( $tree, $values, $off );
					if ( ! $met && ! isset( $off[ $id ] ) ) {
						$off[ $id ] = $tree['mode'];
						$changed    = true;
					} elseif ( $met && isset( $off[ $id ] ) ) {
						unset( $off[ $id ] );
						$changed = true;
					}
				}
				if ( ! $changed ) {
					break;
				}
			}
			return $off;
		}

		/**
		 * Record a field error for this request and for the Settings API redirect.
		 * Matrix rows in error travel in the error code as "id:row1,row2".
		 *
		 * @param string   $id      Field id.
		 * @param string   $message Translated message.
		 * @param string[] $rows    Optional matrix rows in error.
		 */
		private function add_error( $id, $message, array $rows = array() ) {
			$this->errors[ $id ] = $message;
			if ( ! empty( $rows ) ) {
				$this->error_rows[ $id ] = $rows;
			}
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( $this->option, (string) $id . ( empty( $rows ) ? '' : ':' . implode( ',', $rows ) ), $message, 'error' );
			}
		}

		/**
		 * Matrix rows in error, from this request or the last redirect.
		 *
		 * @param string $id Field id.
		 * @return string[]
		 */
		public function error_rows( $id ) {
			$this->errors();
			return isset( $this->error_rows[ $id ] ) ? $this->error_rows[ $id ] : array();
		}

		/**
		 * Field errors from this request or from the last options.php redirect.
		 *
		 * @return array<string, string> Field id => message.
		 */
		public function errors() {
			$errs = $this->errors;
			foreach ( $this->own_settings_errors() as $e ) {
				if ( ! isset( $e['code'], $e['message'] ) || self::SAVED_CODE === $e['code'] ) {
					continue;
				}
				$parts = explode( ':', (string) $e['code'], 2 );
				$code  = $parts[0];
				if ( isset( $this->fields[ $code ] ) && ! isset( $errs[ $code ] ) ) {
					$errs[ $code ] = $e['message'];
					if ( isset( $parts[1] ) && '' !== $parts[1] ) {
						$this->error_rows[ $code ] = explode( ',', $parts[1] );
					}
				}
			}
			return $errs;
		}

		/**
		 * This option's settings errors: the ones claimed from core on this page
		 * plus any still in core's list (this request, or before the claim).
		 *
		 * @return array[]
		 */
		private function own_settings_errors() {
			$list = null !== $this->claimed ? $this->claimed : array();
			if ( function_exists( 'get_settings_errors' ) ) {
				$list = array_merge( $list, (array) get_settings_errors( $this->option ) );
			}
			return $list;
		}

		/**
		 * One kit notice per page (in_admin_header, priority 0; all_admin_notices fallback).
		 *
		 * After options.php saves this option it redirects back with its
		 * settings errors in a transient; core then prints them again through
		 * settings_errors() on Settings submenu pages (options-head.php), and a
		 * plugin page may call settings_errors() itself. This takes this
		 * option's entries (field errors, the tab marker error, the saved tag)
		 * into the instance, where errors() and notices_html() read them, and
		 * removes them plus core's success general/settings_updated from the
		 * global list, so core prints nothing for this save. A general
		 * settings_updated of another type (core's error) is kept. Pages that
		 * did not just save this option are left alone.
		 */
		public function claim_core_notices() {
			if ( null !== $this->claimed || ! function_exists( 'get_settings_errors' ) ) {
				return;
			}
			$mine = array();
			// get_settings_errors() also merges (and deletes) the options.php transient.
			foreach ( (array) get_settings_errors() as $e ) {
				if ( isset( $e['setting'] ) && $this->option === $e['setting'] ) {
					$mine[] = $e;
				}
			}
			if ( empty( $mine ) ) {
				return;
			}
			$this->claimed = $mine;
			$keep          = array();
			foreach ( (array) ( isset( $GLOBALS['wp_settings_errors'] ) ? $GLOBALS['wp_settings_errors'] : array() ) as $e ) {
				$setting = isset( $e['setting'] ) ? $e['setting'] : '';
				$code    = isset( $e['code'] ) ? $e['code'] : '';
				$type    = isset( $e['type'] ) ? $e['type'] : '';
				if ( $this->option === $setting || ( 'general' === $setting && 'settings_updated' === $code && in_array( $type, array( 'success', 'updated' ), true ) ) ) {
					continue;
				}
				$keep[] = $e;
			}
			$GLOBALS['wp_settings_errors'] = $keep; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- core's own settings errors list; only this option's entries and its duplicate 「Settings saved.」 are removed.
		}

		/**
		 * Sanitize one field value by its type.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return mixed
		 */
		private function sanitize_field( array $f, $v ) {
			if ( isset( $f['sanitize'] ) && is_callable( $f['sanitize'] ) ) {
				return call_user_func( $f['sanitize'], $v, $f );
			}
			switch ( $f['type'] ) {
				case 'toggle':
					return ! empty( $v );
				case 'number':
					$n = null === $v || '' === $v ? (int) $f['default'] : (int) $v;
					if ( isset( $f['min'] ) ) {
						$n = max( (int) $f['min'], $n );
					}
					if ( isset( $f['max'] ) ) {
						$n = min( (int) $f['max'], $n );
					}
					return $n;
				case 'select':
				case 'radio':
				case 'choice':
					$v = is_scalar( $v ) ? (string) $v : '';
					return array_key_exists( $v, self::resolve( $f['choices'], $f ) ) ? $v : $f['default'];
				case 'multicheck':
					$v = is_array( $v ) ? array_values( array_filter( array_map( 'sanitize_key', $v ), 'strlen' ) ) : array();
					return empty( $f['choices'] ) ? $v : array_values( array_intersect( $v, array_keys( self::resolve( $f['choices'], $f ) ) ) );
				case 'multiselect':
					return self::sanitize_multiselect( $f, $v );
				case 'matrix':
					return self::sanitize_matrix( $f, $v );
				case 'map':
					$res = array();
					foreach ( ( is_array( $v ) ? $v : array() ) as $k => $val ) {
						$res[ sanitize_key( $k ) ] = sanitize_key( $val );
					}
					return $res;
				case 'textarea':
					return null === $v ? $f['default'] : sanitize_textarea_field( (string) $v );
				default:
					return null === $v || ! is_scalar( $v ) ? $f['default'] : sanitize_text_field( (string) $v );
			}
		}

		/**
		 * Multiselect: list of choice keys, in choice order, no duplicates.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value. The empty sentinel from the form is dropped.
		 * @return string[]
		 */
		private static function sanitize_multiselect( array $f, $v ) {
			$picked = array();
			foreach ( ( is_array( $v ) ? $v : array() ) as $item ) {
				if ( is_scalar( $item ) && '' !== (string) $item ) {
					$picked[] = sanitize_text_field( (string) $item );
				}
			}
			$choices = self::resolve( $f['choices'], $f );
			if ( empty( $choices ) ) {
				return array_values( array_unique( $picked ) );
			}
			$out = array();
			foreach ( array_keys( $choices ) as $k ) {
				if ( in_array( (string) $k, $picked, true ) ) {
					$out[] = (string) $k;
				}
			}
			return $out;
		}

		/**
		 * Matrix: row key => choice key. Unknown rows and choices are dropped;
		 * rows set to unset_value are not stored (they follow the global setting).
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return array<string, string>
		 */
		private static function sanitize_matrix( array $f, $v ) {
			$rows    = self::resolve( $f['rows'], $f );
			$choices = self::resolve( $f['choices'], $f );
			$out     = array();
			foreach ( ( is_array( $v ) ? $v : array() ) as $row => $val ) {
				$row = sanitize_key( (string) $row );
				$val = is_scalar( $val ) ? (string) $val : '';
				if ( '' === $row || ( ! empty( $rows ) && ! array_key_exists( $row, $rows ) ) ) {
					continue;
				}
				if ( ! array_key_exists( $val, $choices ) ) {
					continue;
				}
				if ( null !== $f['unset_value'] && (string) $f['unset_value'] === $val ) {
					continue;
				}
				$out[ $row ] = $val;
			}
			return $out;
		}

		/**
		 * Values for export, with secret fields removed.
		 *
		 * @return array
		 */
		public function export() {
			$data = $this->all();
			foreach ( $this->fields as $id => $f ) {
				if ( self::is_secret( $f ) ) {
					unset( $data[ $id ] );
				}
			}
			return $data;
		}

		/**
		 * Import values; secret fields keep their current value.
		 *
		 * @param array $data Values to import.
		 * @return bool Result of update_option().
		 */
		public function import( array $data ) {
			$current = $this->all();
			foreach ( $this->fields as $id => $f ) {
				if ( self::is_secret( $f ) ) {
					unset( $data[ $id ] );
				}
			}
			unset( $data[ self::TAB_KEY ], $data[ self::CLEAR_KEY ] );
			$this->importing = true;
			try {
				$clean = $this->sanitize( wp_parse_args( $data, $current ) );
			} finally {
				$this->importing = false;
			}
			foreach ( $this->fields as $id => $f ) {
				if ( self::is_secret( $f ) ) {
					$clean[ $id ] = $current[ $id ];
				}
			}
			return update_option( $this->option, $clean );
		}

		// --- Output ---

		/**
		 * Echo the markup for one field.
		 *
		 * @param string $id Field id.
		 */
		public function render_field( $id ) {
			echo $this->field_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts; caller HTML (choice icon_html, status render) goes through wp_kses in field_html().
		}

		/**
		 * Echo every field of a tab (schema order) plus the hidden tab marker, so
		 * saving this form writes only these fields.
		 *
		 * @param string $tab Tab id.
		 */
		public function render_tab( $tab ) {
			$out = $this->tab_input_html( $tab );
			foreach ( $this->fields_in_tab( $tab ) as $id ) {
				$out .= $this->field_html( $id );
			}
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}

		/**
		 * Hidden marker for per-tab save, with its nonce. Put it once inside the
		 * form. On save only fields whose 'tab' is this tab are validated and
		 * written; the kit merges every other field from the saved option. A
		 * marker whose nonce does not verify writes nothing.
		 *
		 * @param string $tab Tab id.
		 * @return string
		 */
		public function tab_input_html( $tab ) {
			$tab   = sanitize_key( (string) $tab );
			$nonce = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $this->tab_nonce_action( $tab ) ) : '';
			return '<input type="hidden" name="' . esc_attr( $this->option . '[' . self::TAB_KEY . ']' ) . '" value="' . esc_attr( $tab ) . '">'
				. '<input type="hidden" name="' . esc_attr( $this->option . '[' . self::TAB_NONCE_KEY . ']' ) . '" value="' . esc_attr( $nonce ) . '">';
		}

		/**
		 * Evaluate a depends_on tree and name the reason of the first failing
		 * condition. Only the field's own conditions are looked at: a trigger
		 * that is itself hidden or disabled simply counts as unmet, and the
		 * reason stays this field's own (「开启 B 后可用」), not the upstream one.
		 *
		 * @param array $node     Normalized node.
		 * @param array $values   Values.
		 * @param array $inactive Inactive field ids (keys).
		 * @return array{0: bool, 1: string} Result and reason.
		 */
		private function eval_reason( array $node, array $values, array $inactive ) {
			if ( isset( $node['field'] ) ) {
				$ok = $this->node_met( $node, $values, $inactive );
				return array( $ok, $ok ? '' : $node['reason'] );
			}
			$reason = '';
			foreach ( $node['rules'] as $r ) {
				list( $ok, $why ) = $this->eval_reason( $r, $values, $inactive );
				if ( 'OR' === $node['relation'] && $ok ) {
					return array( true, '' );
				}
				if ( 'AND' === $node['relation'] && ! $ok ) {
					return array( false, '' !== $why ? $why : $node['reason'] );
				}
				if ( '' === $reason ) {
					$reason = $why;
				}
			}
			return 'AND' === $node['relation'] ? array( true, '' ) : array( false, '' !== $reason ? $reason : $node['reason'] );
		}

		/**
		 * Initial depends_on state from saved values, the same decision the
		 * server makes on save. Printed so the page is right without JS and
		 * before JS runs.
		 *
		 * @param mixed $dep Schema depends_on.
		 * @return array{mode: string, reason: string}|null Set only when the condition fails.
		 */
		private function server_off( $dep ) {
			$tree = self::dep_tree( $dep );
			if ( null === $tree ) {
				return null;
			}
			$values           = $this->all();
			list( $ok, $why ) = $this->eval_reason( $tree, $values, $this->inactive_fields( $values ) );
			if ( $ok ) {
				return null;
			}
			return array(
				'mode'   => $tree['mode'],
				'reason' => '' !== $why ? $why : $tree['reason'],
			);
		}

		/**
		 * Disable every form control in a block of field HTML, hidden companions
		 * included, so a field disabled by depends_on submits nothing.
		 *
		 * @param string $html Field HTML.
		 * @return string
		 */
		private static function disable_controls( $html ) {
			return (string) preg_replace( '/<(input|select|textarea|button)\b(?![^>]*\sdisabled(?=[\s>=\/]))/', '<$1 disabled data-wenpai-dep-dis', $html );
		}

		/**
		 * Echo only the control of one field, for layouts that draw their own
		 * label (simple-row). Pass 'labelledby' with the id of that label.
		 *
		 * @param string $id   Field id.
		 * @param array  $args Optional. 'labelledby', 'describedby'.
		 */
		public function render_control( $id, array $args = array() ) {
			if ( ! isset( $this->fields[ $id ] ) ) {
				return;
			}
			$f     = $this->fields[ $id ];
			$fid   = $this->field_id( $id );
			$ctl   = array(
				'labelledby'  => isset( $args['labelledby'] ) ? (string) $args['labelledby'] : '',
				'describedby' => isset( $args['describedby'] ) ? (string) $args['describedby'] : '',
				'invalid'     => isset( $this->errors()[ $id ] ),
			);
			$off   = $this->server_off( $f['depends_on'] );
			$inner = $this->control_html( $id, $f, $fid, $ctl );
			$attrs = $this->depends_attrs( $f['depends_on'] );
			if ( null !== $off && 'disable' === $off['mode'] ) {
				$inner  = self::disable_controls( $inner );
				$attrs .= ' data-wenpai-dep-disabled';
			} elseif ( null !== $off ) {
				$attrs .= ' hidden inert data-wenpai-dep-hidden';
			}
			$out = '<span class="wenpai-control" data-wenpai-field="' . esc_attr( $id ) . '"' . $attrs . '>' . $inner . '</span>';
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}

		/**
		 * HTML id of a field's control.
		 *
		 * @param string $id Field id.
		 * @return string
		 */
		public function field_id( $id ) {
			return 'wenpai-f-' . $this->option . '-' . $id;
		}

		/**
		 * Form input name of a field.
		 *
		 * @param string $id Field id.
		 * @return string
		 */
		public function field_name( $id ) {
			return $this->option . '[' . $id . ']';
		}

		/**
		 * Markup for one field: wrapper, label (or legend), control, actions,
		 * description, state, error. Layout 'row' outputs the two-column
		 * .field > .field-label + .field-ctl structure.
		 *
		 * @param string $id Field id.
		 * @return string Escaped HTML, or '' for an unknown field.
		 */
		public function field_html( $id ) {
			if ( ! isset( $this->fields[ $id ] ) ) {
				return '';
			}
			$f      = $this->fields[ $id ];
			$fid    = $this->field_id( $id );
			$errors = $this->errors();
			$err    = isset( $errors[ $id ] ) ? $errors[ $id ] : '';
			$row    = 'row' === $f['layout'];
			$group  = in_array( $f['type'], array( 'radio', 'choice', 'multicheck', 'multiselect', 'matrix', 'status' ), true );
			$secret = self::is_secret( $f );
			$tree   = self::dep_tree( $f['depends_on'] );
			$reason = null !== $tree && 'disable' === $tree['mode'];
			$rows   = 'matrix' === $f['type'] ? $this->error_rows( $id ) : array();
			$off    = $this->server_off( $f['depends_on'] );

			$described = array();
			if ( '' !== $err ) {
				$described[] = $fid . '-error';
			}
			if ( $reason ) {
				$described[] = $fid . '-reason';
			}
			if ( '' !== $f['desc'] ) {
				$described[] = $fid . '-desc';
			}
			if ( $secret ) {
				$described[] = $fid . '-state';
			}
			foreach ( $this->actions( $id, $f ) as $a ) {
				$described[] = $a['result_id'];
			}
			$ctl = array(
				'labelledby'   => '',
				'describedby'  => $group ? '' : implode( ' ', $described ),
				'invalid'      => '' !== $err && 'matrix' !== $f['type'],
				'invalid_rows' => $rows,
				'error_id'     => '' !== $err ? $fid . '-error' : '',
			);

			$control = $this->control_html( $id, $f, $fid, $ctl );
			$actions = $this->actions_html( $id, $f );
			if ( '' !== $actions ) {
				$control = '<div class="field-ctl-row wenpai-field__row">' . $control . $actions . '</div>';
			}
			$after = '';
			if ( '' !== $err ) {
				$icon   = class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'info' ) : '';
				$after .= '<p class="wenpai-field__error" id="' . esc_attr( $fid . '-error' ) . '">' . $icon . '<span>' . esc_html( $err ) . '</span></p>';
			}
			if ( $reason ) {
				$why    = null !== $off ? $off['reason'] : '';
				$after .= '<p class="wenpai-field__reason" id="' . esc_attr( $fid . '-reason' ) . '"' . ( '' === $why ? ' hidden' : '' ) . '>' . esc_html( $why ) . '</p>';
			}
			if ( '' !== $f['desc'] ) {
				$after .= '<p class="wenpai-field__desc" id="' . esc_attr( $fid . '-desc' ) . '">' . esc_html( $f['desc'] ) . '</p>';
			}
			if ( $secret ) {
				$after .= $this->secret_extra_html( $id, $fid );
			}

			$state = '';
			if ( null !== $off && 'disable' === $off['mode'] ) {
				// Disabled from saved values, controls included (hidden companions too), so nothing is submitted.
				$control = self::disable_controls( $control );
				$after   = self::disable_controls( $after );
				$state   = ' data-wenpai-dep-disabled';
			} elseif ( null !== $off ) {
				$state = ' hidden inert data-wenpai-dep-hidden';
			}
			$cls  = 'wenpai-field wenpai-field--' . $f['type'] . ( $row ? ' field wenpai-field--row' : '' ) . ( '' !== $err ? ' is-invalid' : '' ) . ( '' !== $state && null !== $off && 'disable' === $off['mode'] ? ' is-disabled' : '' );
			$open = '<div class="' . esc_attr( $cls ) . '" data-wenpai-field="' . esc_attr( $id ) . '"' . $this->depends_attrs( $f['depends_on'] ) . $state;
			$aria = empty( $described ) ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"';

			if ( $row ) {
				if ( $group ) {
					$labelled = $fid . '-label' . ( false !== strpos( $control, 'id="' . $fid . '-pill"' ) ? ' ' . $fid . '-pill' : '' );
					$html     = $open . ' id="' . esc_attr( $fid ) . '" role="group" aria-labelledby="' . esc_attr( $labelled ) . '"' . $aria . '>';
					$html    .= '<div class="field-label" id="' . esc_attr( $fid . '-label' ) . '">' . esc_html( $f['label'] ) . '</div>';
				} else {
					$html  = $open . '>';
					$html .= '<div class="field-label"><label class="wenpai-field__label" for="' . esc_attr( $fid ) . '">' . esc_html( $f['label'] ) . '</label></div>';
				}
				return $html . '<div class="field-ctl">' . $control . $after . '</div></div>';
			}
			if ( 'status' === $f['type'] ) {
				$labelled = $fid . '-label' . ( false !== strpos( $control, 'id="' . $fid . '-pill"' ) ? ' ' . $fid . '-pill' : '' );
				$html     = $open . ' role="group" aria-labelledby="' . esc_attr( $labelled ) . '"' . $aria . '>';
				$html    .= '<span class="wenpai-field__label" id="' . esc_attr( $fid . '-label' ) . '">' . esc_html( $f['label'] ) . '</span>';
				return $html . $control . $after . '</div>';
			}
			if ( $group ) {
				$html  = $open . '>';
				$html .= '<fieldset class="wenpai-field__group" id="' . esc_attr( $fid ) . '"' . $aria . '>';
				$html .= '<legend class="wenpai-field__label">' . esc_html( $f['label'] ) . '</legend>';
				return $html . $control . '</fieldset>' . $after . '</div>';
			}
			$html  = $open . '>';
			$html .= '<label class="wenpai-field__label" for="' . esc_attr( $fid ) . '">' . esc_html( $f['label'] ) . '</label>';
			return $html . $control . $after . '</div>';
		}

		/**
		 * Secret field state line and the clear checkbox.
		 *
		 * @param string $id  Field id.
		 * @param string $fid Control id.
		 * @return string
		 */
		private function secret_extra_html( $id, $fid ) {
			$stored = $this->stored();
			$saved  = isset( $stored[ $id ] ) && is_scalar( $stored[ $id ] ) && '' !== (string) $stored[ $id ];
			$html   = '<p class="wenpai-field__state ' . ( $saved ? 'is-saved' : 'is-empty' ) . '" id="' . esc_attr( $fid . '-state' ) . '">' . esc_html( $saved ? $this->strings['secret_saved'] : $this->strings['secret_empty'] ) . '</p>';
			if ( $saved ) {
				$html .= '<label class="wenpai-field__clear"><input type="checkbox" name="' . esc_attr( $this->option . '[' . self::CLEAR_KEY . '][]' ) . '" value="' . esc_attr( $id ) . '"> ' . esc_html( $this->strings['secret_clear'] ) . '</label>';
			}
			return $html;
		}

		/**
		 * Normalized action button of a field (at most one, always .btn-secondary).
		 *
		 * Schema: 'actions' => array( array( 'id', 'label', 'ajax' (wp_ajax action),
		 * 'nonce' (defaults to the ajax action), 'include' (field ids sent along,
		 * defaults to this field), 'busy' (in-progress copy WITHOUT the trailing
		 * 「…」, e.g. 正在测试; kit JS appends 「…」; default action_busy 正在处理),
		 * 'data' (extra static params), 'requires' (field ids that must have a
		 * value), 'requires_note' (why the button is disabled, e.g. 先填写 API
		 * 密钥。) ) ).
		 *
		 * Receipt copy comes from the handler (see ajax_action()): a failure
		 * returns WP_Error whose message may name the service, e.g.
		 * 暂时无法连接百度翻译，请检查密钥。 action_ok / action_failed are only the
		 * global fallback (bare true/false, network error, timeout).
		 *
		 * @param string $id Field id.
		 * @param array  $f  Field schema.
		 * @return array<int, array>
		 */
		private function actions( $id, array $f ) {
			foreach ( (array) $f['actions'] as $i => $a ) {
				if ( ! is_array( $a ) || empty( $a['ajax'] ) || empty( $a['label'] ) ) {
					continue;
				}
				$aid = isset( $a['id'] ) ? sanitize_key( (string) $a['id'] ) : (string) $i;
				return array(
					array(
						'id'            => $aid,
						'label'         => (string) $a['label'],
						'ajax'          => (string) $a['ajax'],
						'nonce'         => isset( $a['nonce'] ) ? (string) $a['nonce'] : (string) $a['ajax'],
						'include'       => isset( $a['include'] ) ? (array) $a['include'] : array( $id ),
						'busy'          => isset( $a['busy'] ) ? (string) $a['busy'] : $this->strings['action_busy'],
						'data'          => isset( $a['data'] ) && is_array( $a['data'] ) ? $a['data'] : array(),
						'requires'      => isset( $a['requires'] ) ? (array) $a['requires'] : array(),
						'requires_note' => isset( $a['requires_note'] ) ? (string) $a['requires_note'] : '',
						'result_id'     => $this->field_id( $id ) . '-act-' . $aid,
					),
				);
			}
			return array();
		}

		/**
		 * The action button followed by its receipt (role=status, filled by kit
		 * JS). The button carries `hidden` until kit JS runs: without JS there
		 * is nothing to press.
		 *
		 * @param string $id Field id.
		 * @param array  $f  Field schema.
		 * @return string
		 */
		private function actions_html( $id, array $f ) {
			$out = '';
			foreach ( $this->actions( $id, $f ) as $a ) {
				$names = array();
				foreach ( $a['include'] as $inc ) {
					$names[] = $this->field_name( (string) $inc );
				}
				$needs = array();
				foreach ( $a['requires'] as $req ) {
					$needs[] = $this->field_name( (string) $req );
				}
				$out .= '<button type="button" class="btn btn-secondary" hidden'
					. ' data-wenpai-action="' . esc_attr( $a['ajax'] ) . '"'
					. ' data-wenpai-nonce="' . esc_attr( function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $a['nonce'] ) : '' ) . '"'
					. ' data-wenpai-url="' . esc_attr( function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : 'admin-ajax.php' ) . '"'
					. ' data-wenpai-include="' . esc_attr( wp_json_encode( $names ) ) . '"'
					. ( empty( $a['data'] ) ? '' : ' data-wenpai-data="' . esc_attr( wp_json_encode( $a['data'] ) ) . '"' )
					. ( empty( $needs ) ? '' : ' data-wenpai-requires="' . esc_attr( wp_json_encode( $needs ) ) . '" data-wenpai-requires-note="' . esc_attr( $a['requires_note'] ) . '"' )
					. ' data-wenpai-result="' . esc_attr( $a['result_id'] ) . '"'
					. ' data-wenpai-busy="' . esc_attr( $a['busy'] ) . '"'
					. ' data-wenpai-ok="' . esc_attr( $this->strings['action_ok'] ) . '"'
					. ' data-wenpai-fail="' . esc_attr( $this->strings['action_failed'] ) . '"'
					. ' aria-describedby="' . esc_attr( $a['result_id'] ) . '">'
					. esc_html( $a['label'] ) . '</button>';
				$out .= '<p class="wenpai-field__result" id="' . esc_attr( $a['result_id'] ) . '" role="status" aria-live="polite"></p>';
			}
			return $out;
		}

		/**
		 * Control markup by type.
		 *
		 * @param string $id  Field id.
		 * @param array  $f   Field schema.
		 * @param string $fid Control id.
		 * @param array  $ctl 'labelledby', 'describedby', 'invalid'.
		 * @return string
		 */
		private function control_html( $id, array $f, $fid, array $ctl ) {
			$v    = $this->get( $id );
			$name = $this->field_name( $id );
			$aria = '';
			if ( '' !== $ctl['labelledby'] ) {
				$aria .= ' aria-labelledby="' . esc_attr( $ctl['labelledby'] ) . '"';
			}
			if ( '' !== $ctl['describedby'] ) {
				$aria .= ' aria-describedby="' . esc_attr( $ctl['describedby'] ) . '"';
			}
			$inv = $ctl['invalid'] ? ' aria-invalid="true"' : '';
			$out = '';
			if ( in_array( $f['type'], array( 'radio', 'choice', 'multicheck', 'multiselect', 'matrix' ), true ) ) {
				$list = self::resolve( 'matrix' === $f['type'] ? $f['rows'] : $f['choices'], $f );
				if ( empty( $list ) ) {
					$noun = isset( $f['empty_noun'] ) ? (string) $f['empty_noun'] : $f['label'];
					return '<p class="wenpai-field__empty">' . esc_html( sprintf( $this->strings['empty_choices'], $noun ) ) . '</p>';
				}
			}
			switch ( $f['type'] ) {
				case 'status':
					return $this->status_html( $f, $fid );
				case 'toggle':
					return self::switch_html(
						array(
							'id'          => $fid,
							'name'        => $name,
							'checked'     => (bool) $v,
							'on'          => $this->strings['on'],
							'off'         => $this->strings['off'],
							'labelledby'  => $ctl['labelledby'],
							'describedby' => $ctl['describedby'],
							'invalid'     => $ctl['invalid'],
						)
					);
				case 'select':
					$out .= '<select id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '"' . $aria . $inv . '>';
					foreach ( self::resolve( $f['choices'], $f ) as $k => $lab ) {
						$out .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $v, (string) $k, false ) . '>' . esc_html( self::choice_label( $lab ) ) . '</option>';
					}
					return $out . '</select>';
				case 'radio':
				case 'multicheck':
					$multi = 'multicheck' === $f['type'];
					$out  .= '<div class="' . ( $multi ? 'chk' : 'opts' ) . '">';
					foreach ( self::resolve( $f['choices'], $f ) as $k => $lab ) {
						$on   = $multi ? in_array( (string) $k, array_map( 'strval', (array) $v ), true ) : ( (string) $v === (string) $k );
						$out .= '<label><input type="' . ( $multi ? 'checkbox' : 'radio' ) . '" name="' . esc_attr( $name . ( $multi ? '[]' : '' ) ) . '" value="' . esc_attr( $k ) . '"' . checked( $on, true, false ) . $inv . '> ' . esc_html( self::choice_label( $lab ) ) . '</label>';
					}
					return $out . '</div>';
				case 'choice':
					return $this->choice_html( $f, $name, (string) $v, $inv );
				case 'multiselect':
					$picked = array_map( 'strval', (array) $v );
					$out   .= '<div class="chk-grid">';
					$out   .= '<input type="hidden" name="' . esc_attr( $name . '[]' ) . '" value="">';
					foreach ( self::resolve( $f['choices'], $f ) as $k => $lab ) {
						$out .= '<label><input type="checkbox" name="' . esc_attr( $name . '[]' ) . '" value="' . esc_attr( $k ) . '"' . checked( in_array( (string) $k, $picked, true ), true, false ) . $inv . '> ' . esc_html( self::choice_label( $lab ) ) . '</label>';
					}
					return $out . '</div>';
				case 'matrix':
					return $this->matrix_html( $f, $fid, $name, is_array( $v ) ? $v : array(), $ctl );
				case 'number':
					return '<input type="number" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . ( isset( $f['min'] ) ? ' min="' . esc_attr( $f['min'] ) . '"' : '' ) . ( isset( $f['max'] ) ? ' max="' . esc_attr( $f['max'] ) . '"' : '' ) . $aria . $inv . '>';
				case 'textarea':
					return '<textarea id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" rows="4"' . $aria . $inv . '>' . esc_textarea( $v ) . '</textarea>';
				case 'secret':
					$saved = is_scalar( $v ) && '' !== (string) $v;
					return '<input type="password" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="" autocomplete="new-password" spellcheck="false" data-wenpai-secret="' . esc_attr( $id ) . '" data-wenpai-multiline="' . esc_attr( $this->strings['secret_multiline'] ) . '"' . ( $saved ? ' data-wenpai-saved' : '' ) . $aria . $inv . '>';
				default:
					return '<input type="text" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $v ) ? $v : '' ) . '" autocomplete="off"' . $aria . $inv . '>';
			}
		}

		/**
		 * Choice cards. A choice may be a label, or array( label, desc, icon
		 * (Wenpai_Admin_Icons name, or dashicons-{name}), dashicon ({name}),
		 * icon_html (SVG, filtered with wp_kses to an SVG allowlist), hint,
		 * disabled, locked ).
		 *
		 * @param array  $f    Field schema.
		 * @param string $name Input name.
		 * @param string $v    Current value.
		 * @param string $inv  aria-invalid attribute or ''.
		 * @return string
		 */
		private function choice_html( array $f, $name, $v, $inv ) {
			$choices = self::resolve( $f['choices'], $f );
			$out     = '<div class="choice-cards' . ( 2 === count( $choices ) ? ' cols-2' : '' ) . '">';
			foreach ( $choices as $k => $c ) {
				$c    = is_array( $c ) ? $c : array( 'label' => (string) $c );
				$on   = $v === (string) $k;
				$dis  = ! empty( $c['disabled'] );
				$icon = '';
				$dash = ! empty( $c['dashicon'] ) ? (string) $c['dashicon'] : ( ! empty( $c['icon'] ) && 0 === strpos( (string) $c['icon'], 'dashicons-' ) ? substr( (string) $c['icon'], 10 ) : '' );
				if ( ! empty( $c['icon_html'] ) ) {
					$icon = self::kses( (string) $c['icon_html'], 'svg' );
				} elseif ( '' !== $dash ) {
					$icon = '<span class="dashicons dashicons-' . esc_attr( sanitize_html_class( $dash ) ) . '"></span>';
				} elseif ( ! empty( $c['icon'] ) && class_exists( 'Wenpai_Admin_Icons', false ) ) {
					$icon = Wenpai_Admin_Icons::svg( (string) $c['icon'] );
				}
				$cls  = 'choice' . ( $on ? ' is-on' : '' ) . ( $dis ? ' is-soon' : '' ) . ( ! empty( $c['locked'] ) ? ' is-locked' : '' );
				$out .= '<label class="' . esc_attr( $cls ) . '">';
				$out .= '<input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $k ) . '"' . checked( $on, true, false ) . disabled( $dis, true, false ) . $inv . '>';
				$out .= '' !== $icon ? '<span class="choice-ico" aria-hidden="true">' . $icon . '</span>' : '';
				$out .= '<span><span class="t">' . esc_html( self::choice_label( $c ) ) . '</span>';
				$out .= isset( $c['desc'] ) && '' !== (string) $c['desc'] ? '<span class="d">' . esc_html( (string) $c['desc'] ) . '</span>' : '';
				$out .= isset( $c['hint'] ) && '' !== (string) $c['hint'] ? '<span class="hint">' . esc_html( (string) $c['hint'] ) . '</span>' : '';
				$out .= '</span></label>';
			}
			return $out . '</div>';
		}

		/**
		 * Read-only status block. Not submitted, not stored.
		 *
		 * 'status' => array|Closure returning array( level (ok|warn|bad|''), text,
		 * desc (string or list), link (array( url, label )) ). Or 'render' =>
		 * callable returning HTML; it is filtered with wp_kses (post tags + SVG),
		 * but should still be built by the plugin, not from user input.
		 *
		 * @param array  $f   Field schema.
		 * @param string $fid Field control id; the pill gets {fid}-pill.
		 * @return string
		 */
		private function status_html( array $f, $fid ) {
			if ( is_callable( $f['render'] ) ) {
				return '<div class="wenpai-field__status" data-wenpai-status>' . self::kses( (string) call_user_func( $f['render'], $f, $this ), 'post' ) . '</div>';
			}
			$st    = $f['status'] instanceof Closure ? call_user_func( $f['status'], $f, $this ) : $f['status'];
			$st    = is_array( $st ) ? $st : array();
			$level = isset( $st['level'] ) && in_array( $st['level'], array( 'ok', 'warn', 'bad', 'mute' ), true ) ? $st['level'] : 'mute';
			$out   = '<div class="wenpai-field__status" data-wenpai-status="' . esc_attr( $level ) . '">';
			if ( ! empty( $st['text'] ) ) {
				$out .= '<span class="pill ' . esc_attr( $level ) . '" id="' . esc_attr( $fid . '-pill' ) . '">' . esc_html( (string) $st['text'] ) . '</span>';
			}
			$link = '';
			if ( isset( $st['link']['url'], $st['link']['label'] ) ) {
				$target = isset( $st['link']['target'] ) ? trim( (string) $st['link']['target'] ) : '';
				// Keywords (_blank, _self, ...) compare and print lowercase; frame names keep their case.
				if ( in_array( strtolower( $target ), array( '_blank', '_self', '_parent', '_top' ), true ) ) {
					$target = strtolower( $target );
				}
				$blank = '_blank' === $target;
				$rel   = self::link_rel( isset( $st['link']['rel'] ) ? $st['link']['rel'] : null, $blank );
				$link  = ' <a href="' . esc_url( (string) $st['link']['url'] ) . '"'
					. ( '' !== $target ? ' target="' . esc_attr( $target ) . '"' : '' )
					. ( '' !== $rel ? ' rel="' . esc_attr( $rel ) . '"' : '' ) . '>'
					. esc_html( (string) $st['link']['label'] )
					. ( $blank ? '<span class="screen-reader-text">' . esc_html( $this->strings['new_tab'] ) . '</span>' : '' )
					. '</a>';
			}
			$lines = isset( $st['desc'] ) ? array_values( (array) $st['desc'] ) : array();
			foreach ( $lines as $i => $line ) {
				$out .= '<p class="wenpai-field__note">' . esc_html( (string) $line ) . ( count( $lines ) - 1 === $i ? $link : '' ) . '</p>';
			}
			if ( empty( $lines ) && '' !== $link ) {
				$out .= '<p class="wenpai-field__note">' . ltrim( $link ) . '</p>';
			}
			return $out . '</div>';
		}

		/**
		 * The rel attribute of a status link. Each token keeps only [a-z-]; duplicates go. With
		 * target=_blank, noopener is always there; no rel given means
		 * 「noopener noreferrer」.
		 *
		 * @param mixed $rel   Caller rel (string or list) or null.
		 * @param bool  $blank Whether the link opens a new tab.
		 * @return string
		 */
		private static function link_rel( $rel, $blank ) {
			if ( null === $rel ) {
				return $blank ? 'noopener noreferrer' : '';
			}
			$words = array();
			foreach ( preg_split( '/\s+/', strtolower( implode( ' ', array_map( 'strval', (array) $rel ) ) ) ) as $w ) {
				$w = (string) preg_replace( '/[^a-z-]/', '', $w );
				if ( '' !== $w && ! in_array( $w, $words, true ) ) {
					$words[] = $w;
				}
			}
			if ( $blank && ! in_array( 'noopener', $words, true ) ) {
				$words[] = 'noopener';
			}
			return implode( ' ', $words );
		}

		/**
		 * Matrix table: one select per row.
		 *
		 * @param array  $f    Field schema.
		 * @param string $fid  Control id.
		 * @param string $name Input name.
		 * @param array  $v    Current map.
		 * @param array  $ctl  Control args; 'invalid_rows' gets aria-invalid, only those rows.
		 * @return string
		 */
		private function matrix_html( array $f, $fid, $name, array $v, array $ctl ) {
			$choices = self::resolve( $f['choices'], $f );
			if ( null !== $f['unset_value'] ) {
				// The "follow global" option always comes first.
				$uv    = (string) $f['unset_value'];
				$label = isset( $choices[ $uv ] ) && '' !== self::choice_label( $choices[ $uv ] ) ? $choices[ $uv ] : $this->strings['follow_global'];
				unset( $choices[ $uv ] );
				$choices = array( $uv => $label ) + $choices;
			}
			$fall    = null !== $f['unset_value'] ? (string) $f['unset_value'] : (string) key( $choices );
			$badrows = array_map( 'strval', $ctl['invalid_rows'] );
			$col     = $fid . '-col';
			$out     = '<table class="tbl wenpai-matrix"><thead><tr>';
			$out    .= '<th scope="col">' . esc_html( $f['row_label'] ) . '</th>';
			$out    .= '<th scope="col" id="' . esc_attr( $col ) . '">' . esc_html( $f['col_label'] ) . '</th>';
			$out    .= '</tr></thead><tbody>';
			foreach ( self::resolve( $f['rows'], $f ) as $row => $r ) {
				$rid  = $fid . '-r-' . sanitize_key( (string) $row );
				$cur  = isset( $v[ $row ] ) && array_key_exists( (string) $v[ $row ], $choices ) ? (string) $v[ $row ] : $fall;
				$desc = is_array( $r ) && isset( $r['desc'] ) ? (string) $r['desc'] : '';
				$bad  = in_array( (string) $row, $badrows, true );
				$inv  = $bad ? ' aria-invalid="true"' . ( '' !== $ctl['error_id'] ? ' aria-describedby="' . esc_attr( $ctl['error_id'] ) . '"' : '' ) : '';
				$out .= '<tr' . ( $bad ? ' class="is-invalid"' : '' ) . '><th scope="row" id="' . esc_attr( $rid ) . '"><span class="t">' . esc_html( self::choice_label( $r ) ) . '</span>' . ( '' !== $desc ? '<span class="d">' . esc_html( $desc ) . '</span>' : '' ) . '</th>';
				$out .= '<td data-label="' . esc_attr( $f['col_label'] ) . '"><select name="' . esc_attr( $name . '[' . $row . ']' ) . '" aria-labelledby="' . esc_attr( $rid . ' ' . $col ) . '"' . $inv . '>';
				foreach ( $choices as $k => $lab ) {
					$out .= '<option value="' . esc_attr( $k ) . '"' . selected( $cur, (string) $k, false ) . '>' . esc_html( self::choice_label( $lab ) ) . '</option>';
				}
				$out .= '</select></td></tr>';
			}
			return $out . '</tbody></table>';
		}

		/**
		 * Label of a choice or row given as a string or array('label' => ...).
		 *
		 * @param mixed $c Choice.
		 * @return string
		 */
		private static function choice_label( $c ) {
			if ( is_array( $c ) ) {
				return isset( $c['label'] ) ? (string) $c['label'] : '';
			}
			return (string) $c;
		}

		/**
		 * The one kit switch: a native checkbox with role="switch", styled as a
		 * track. Works without JS. A hidden "0" input makes "off" submit.
		 *
		 * @param array $a {
		 *     Switch arguments.
		 *
		 *     @type string $id          Control id. Required.
		 *     @type string $name        Input name. Omit for a display-only switch.
		 *     @type bool   $checked     Current state.
		 *     @type string $on          State text when on (translated).
		 *     @type string $off         State text when off (translated).
		 *     @type bool   $disabled    Disabled.
		 *     @type string $labelledby  Id of the visible label, when not using <label for>.
		 *     @type string $describedby Space-separated ids.
		 *     @type bool   $invalid     Adds aria-invalid.
		 * }
		 * @return string
		 */
		public static function switch_html( array $a ) {
			$a    = wp_parse_args(
				$a,
				array(
					'id'          => '',
					'name'        => '',
					'checked'     => false,
					'on'          => '',
					'off'         => '',
					'disabled'    => false,
					'labelledby'  => '',
					'describedby' => '',
					'invalid'     => false,
				)
			);
			$attr = ' id="' . esc_attr( $a['id'] ) . '"';
			if ( '' !== $a['name'] ) {
				$attr .= ' name="' . esc_attr( $a['name'] ) . '" value="1"';
			}
			if ( '' !== $a['labelledby'] ) {
				$attr .= ' aria-labelledby="' . esc_attr( $a['labelledby'] ) . '"';
			}
			if ( '' !== $a['describedby'] ) {
				$attr .= ' aria-describedby="' . esc_attr( $a['describedby'] ) . '"';
			}
			if ( $a['invalid'] ) {
				$attr .= ' aria-invalid="true"';
			}
			$out = '<span class="wenpai-switch">';
			if ( '' !== $a['name'] ) {
				$out .= '<input type="hidden" name="' . esc_attr( $a['name'] ) . '" value="0">';
			}
			$out .= '<input type="checkbox" role="switch" class="wenpai-switch__input"' . $attr . checked( (bool) $a['checked'], true, false ) . disabled( (bool) $a['disabled'], true, false ) . '>';
			$out .= '<span class="wenpai-switch__track" aria-hidden="true"></span>';
			if ( '' !== $a['on'] || '' !== $a['off'] ) {
				$out .= '<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on">' . esc_html( $a['on'] ) . '</span><span class="wenpai-switch__off">' . esc_html( $a['off'] ) . '</span></span>';
			}
			return $out . '</span>';
		}

		// --- depends_on ---

		/**
		 * Normalize depends_on into a condition tree.
		 *
		 * Accepted: one condition array( field, value, not ); a list (all must
		 * hold); or a group array( 'mode' => hide|disable, 'relation' => AND|OR,
		 * 'rules' => array( conditions or nested groups ) ).
		 *
		 * @param mixed $dep Schema value.
		 * @return array|null Group array( mode, relation, rules ) or null.
		 */
		private static function dep_tree( $dep ) {
			if ( empty( $dep ) || ! is_array( $dep ) ) {
				return null;
			}
			$mode = isset( $dep['mode'] ) && 'disable' === $dep['mode'] ? 'disable' : 'hide';
			$node = self::dep_node( $dep );
			if ( null === $node ) {
				return null;
			}
			if ( ! isset( $node['rules'] ) ) {
				$node = array(
					'relation' => 'AND',
					'rules'    => array( $node ),
					'reason'   => '',
				);
			}
			$node['mode'] = $mode;
			return $node;
		}

		/**
		 * Normalize one node of a depends_on tree.
		 *
		 * @param mixed $n Condition, list or group.
		 * @return array|null
		 */
		private static function dep_node( $n ) {
			if ( ! is_array( $n ) ) {
				return null;
			}
			if ( isset( $n['field'] ) ) {
				$vals = array_key_exists( 'value', $n ) ? $n['value'] : true;
				$norm = array();
				foreach ( ( is_array( $vals ) ? $vals : array( $vals ) ) as $x ) {
					$norm[] = is_bool( $x ) ? ( $x ? '1' : '0' ) : (string) $x;
				}
				return array(
					'field'  => (string) $n['field'],
					'value'  => $norm,
					'not'    => ! empty( $n['not'] ),
					'reason' => isset( $n['reason'] ) ? (string) $n['reason'] : '',
				);
			}
			$relation = isset( $n['relation'] ) && 'OR' === strtoupper( (string) $n['relation'] ) ? 'OR' : 'AND';
			$list     = $n;
			if ( isset( $n['any'] ) && is_array( $n['any'] ) ) {
				$relation = 'OR';
				$list     = $n['any'];
			} elseif ( isset( $n['all'] ) && is_array( $n['all'] ) ) {
				$relation = 'AND';
				$list     = $n['all'];
			} elseif ( isset( $n['rules'] ) && is_array( $n['rules'] ) ) {
				$list = $n['rules'];
			}
			$rules = array();
			foreach ( $list as $k => $child ) {
				if ( in_array( $k, array( 'mode', 'relation', 'reason' ), true ) ) {
					continue;
				}
				$c = self::dep_node( $child );
				if ( null !== $c ) {
					$rules[] = $c;
				}
			}
			if ( empty( $rules ) ) {
				return null;
			}
			return array(
				'relation' => $relation,
				'rules'    => $rules,
				'reason'   => isset( $n['reason'] ) ? (string) $n['reason'] : '',
			);
		}

		/**
		 * Tree with field ids replaced by input names, for the JSON attribute.
		 *
		 * @param array $node Normalized node.
		 * @return array
		 */
		private function dep_json( array $node ) {
			if ( isset( $node['field'] ) ) {
				$c = array(
					'on'    => $this->field_name( $node['field'] ),
					'value' => $node['value'],
					'not'   => $node['not'],
				);
				if ( '' !== $node['reason'] ) {
					$c['reason'] = $node['reason'];
				}
				$saved = $this->saved_flag( $node );
				if ( null !== $saved ) {
					$c['saved'] = $saved;
				}
				return $c;
			}
			$out = array(
				'relation' => $node['relation'],
				'rules'    => array(),
			);
			if ( '' !== $node['reason'] ) {
				$out['reason'] = $node['reason'];
			}
			if ( isset( $node['mode'] ) ) {
				$out = array( 'mode' => $node['mode'] ) + $out;
			}
			foreach ( $node['rules'] as $r ) {
				$out['rules'][] = $this->dep_json( $r );
			}
			return $out;
		}

		/**
		 * Result of one condition against saved values, for kit JS to use when
		 * the trigger's control is not on the page. Only a boolean is emitted,
		 * never a value; conditions on secret fields emit nothing.
		 *
		 * @param array $node Normalized condition.
		 * @return bool|null
		 */
		private function saved_flag( array $node ) {
			if ( ! isset( $this->fields[ $node['field'] ] ) || self::is_secret( $this->fields[ $node['field'] ] ) ) {
				return null;
			}
			$values = $this->all();
			return $this->node_met( $node, $values, $this->inactive_fields( $values ) );
		}

		/**
		 * Data attributes for kit JS. The initial state (hidden / disabled) is
		 * printed by the caller from saved values, see server_off().
		 *
		 * Each condition also carries `saved` (data-wenpai-depends-saved="1|0"
		 * in the plain form): its result against saved values, used by kit JS
		 * when the trigger control is not on the page.
		 *
		 * One plain condition: data-wenpai-depends-on, data-wenpai-depends-value
		 * (a value, or a JSON list for "any of"), data-wenpai-depends-not,
		 * data-wenpai-depends-mode="disable". Anything else: data-wenpai-depends,
		 * a JSON tree {mode, relation, rules: [{on, value, not} | group]}.
		 *
		 * @param mixed $dep Schema depends_on.
		 * @return string
		 */
		private function depends_attrs( $dep ) {
			$tree = self::dep_tree( $dep );
			if ( null === $tree ) {
				return '';
			}
			if ( 1 === count( $tree['rules'] ) && isset( $tree['rules'][0]['field'] ) ) {
				$c   = $tree['rules'][0];
				$val = 1 === count( $c['value'] ) ? $c['value'][0] : wp_json_encode( $c['value'] );
				$why = '' !== $c['reason'] ? $c['reason'] : $tree['reason'];
				return ' data-wenpai-depends-on="' . esc_attr( $this->field_name( $c['field'] ) ) . '" data-wenpai-depends-value="' . esc_attr( $val ) . '"'
					. ( $c['not'] ? ' data-wenpai-depends-not' : '' )
					. ( 'disable' === $tree['mode'] ? ' data-wenpai-depends-mode="disable"' : '' )
					. ( 'disable' === $tree['mode'] && '' !== $why ? ' data-wenpai-depends-reason="' . esc_attr( $why ) . '"' : '' )
					. ( null !== $this->saved_flag( $c ) ? ' data-wenpai-depends-saved="' . ( $this->saved_flag( $c ) ? '1' : '0' ) . '"' : '' );
			}
			return ' data-wenpai-depends="' . esc_attr( wp_json_encode( $this->dep_json( $tree ) ) ) . '"';
		}

		/**
		 * Attributes for a hand-written block, card, section or tab link that
		 * should follow a field.
		 *
		 * The condition is checked against saved values and ' hidden inert
		 * data-wenpai-dep-hidden' is added when it fails, so the block is right
		 * without JS. Hand-written blocks only get hide mode on the server; put
		 * fields with mode=disable through render_field().
		 *
		 * @param array $depends_on Same shape as the schema key.
		 * @param array $args       Optional. 'server' (bool, kept for 0.3.0-dev callers; always on now).
		 * @return string Leading space included.
		 */
		public function depends_on_attrs( array $depends_on, array $args = array() ) {
			$out = $this->depends_attrs( $depends_on );
			unset( $args );
			if ( ! $this->condition_met( $depends_on ) ) {
				$out .= ' hidden inert data-wenpai-dep-hidden';
			}
			return $out;
		}

		/**
		 * Evaluate depends_on against saved values (for tabs, sections and
		 * server-side decisions).
		 *
		 * @param array $depends_on Same shape as the schema key.
		 * @return bool True when there is no condition.
		 */
		public function condition_met( array $depends_on ) {
			$tree = self::dep_tree( $depends_on );
			return null === $tree ? true : $this->node_met( $tree, $this->all(), $this->inactive_fields( $this->all() ) );
		}

		/**
		 * Evaluate one node against values.
		 *
		 * @param array $node     Normalized node.
		 * @param array $values   Field values.
		 * @param array $inactive Field ids (keys) that are hidden or disabled; they never match.
		 * @return bool
		 */
		private function node_met( array $node, array $values, array $inactive = array() ) {
			if ( isset( $node['field'] ) ) {
				if ( ! array_key_exists( $node['field'], $values ) ) {
					return true;
				}
				if ( isset( $inactive[ $node['field'] ] ) ) {
					// A hidden or disabled trigger never satisfies a condition, `not` included (spec §12).
					return false;
				}
				$have = array();
				foreach ( ( is_array( $values[ $node['field'] ] ) ? $values[ $node['field'] ] : array( $values[ $node['field'] ] ) ) as $x ) {
					$have[] = is_bool( $x ) ? ( $x ? '1' : '0' ) : (string) $x;
				}
				$match = array() !== array_intersect( $have, $node['value'] );
				return $node['not'] ? ! $match : $match;
			}
			foreach ( $node['rules'] as $r ) {
				$ok = $this->node_met( $r, $values, $inactive );
				if ( 'OR' === $node['relation'] && $ok ) {
					return true;
				}
				if ( 'AND' === $node['relation'] && ! $ok ) {
					return false;
				}
			}
			return 'AND' === $node['relation'];
		}

		// --- AJAX actions (field buttons, plugin preview cards) ---

		/**
		 * Register a wp_ajax_{action} handler with nonce and capability checks.
		 *
		 * The handler gets ( $values, $settings ): the posted values of this
		 * option, sanitized per field, where an empty secret means the saved
		 * one. It returns true, false, a WP_Error, a message string (success),
		 * or array( 'ok' => bool, 'message' => string, 'data' => mixed ).
		 *
		 * @param string   $action  AJAX action; also the nonce action unless 'nonce' is given.
		 * @param callable $handler Handler.
		 * @param array    $args    Optional. 'capability' (default manage_options), 'nonce'.
		 */
		public function ajax_action( $action, $handler, array $args = array() ) {
			$cap   = isset( $args['capability'] ) ? (string) $args['capability'] : 'manage_options';
			$nonce = isset( $args['nonce'] ) ? (string) $args['nonce'] : (string) $action;
			$self  = $this;
			add_action(
				'wp_ajax_' . $action,
				function () use ( $self, $nonce, $handler, $cap ) {
					$self->handle_ajax( $nonce, $handler, $cap );
				}
			);
		}

		/**
		 * Run one AJAX action and send JSON. Public only for ajax_action().
		 *
		 * @param string   $nonce   Nonce action.
		 * @param callable $handler Handler.
		 * @param string   $cap     Capability.
		 */
		public function handle_ajax( $nonce, $handler, $cap ) {
			if ( ! check_ajax_referer( $nonce, '_wpnonce', false ) || ! current_user_can( $cap ) ) {
				wp_send_json_error( array( 'message' => $this->strings['action_denied'] ), 403 );
			}
			$res = call_user_func( $handler, $this->posted_values(), $this );
			$out = self::action_result( $res, $this->strings );
			if ( $out['ok'] ) {
				wp_send_json_success( $out );
			}
			wp_send_json_error( $out );
		}

		/**
		 * Normalize a handler result.
		 *
		 * @param mixed $res     Handler return value.
		 * @param array $strings UI strings.
		 * A WP_Error sends only its code and message to the page by default. Its
		 * data stays on the server (it may hold an HTTP response body, keys or
		 * signed URLs), except what the handler puts under the 'public' key
		 * ( new WP_Error( 'quota', $msg, array( 'public' => array( 'retry' => 60 ) ) ) )
		 * or what the 'wenpai_admin_ui_action_error_data' filter returns.
		 *
		 * @return array{ok: bool, message: string, code: string, data: mixed}
		 */
		public static function action_result( $res, array $strings = array() ) {
			$strings = array_merge( self::default_strings(), $strings );
			if ( is_object( $res ) && is_a( $res, 'WP_Error' ) ) {
				$msg    = (string) $res->get_error_message();
				$raw    = $res->get_error_data();
				$public = is_array( $raw ) && array_key_exists( 'public', $raw ) ? $raw['public'] : null;
				if ( function_exists( 'apply_filters' ) ) {
					/**
					 * Error data of a failed field action that may go to the page.
					 *
					 * @param mixed    $public Default: the 'public' key of the error data, else null.
					 * @param WP_Error $res    The handler's error.
					 */
					$public = apply_filters( 'wenpai_admin_ui_action_error_data', $public, $res );
				}
				return array(
					'ok'      => false,
					'message' => '' !== $msg ? $msg : $strings['action_failed'],
					'code'    => (string) $res->get_error_code(),
					'data'    => self::json_safe( $public ),
				);
			}
			if ( is_array( $res ) ) {
				$ok = ! empty( $res['ok'] );
				return array(
					'ok'      => $ok,
					'message' => isset( $res['message'] ) && '' !== (string) $res['message'] ? (string) $res['message'] : ( $ok ? $strings['action_ok'] : $strings['action_failed'] ),
					'code'    => isset( $res['code'] ) && is_scalar( $res['code'] ) ? (string) $res['code'] : '',
					'data'    => isset( $res['data'] ) ? $res['data'] : null,
				);
			}
			if ( is_string( $res ) && '' !== $res ) {
				return array(
					'ok'      => true,
					'message' => $res,
					'code'    => '',
					'data'    => null,
				);
			}
			$ok = true === $res;
			return array(
				'ok'      => $ok,
				'message' => $ok ? $strings['action_ok'] : $strings['action_failed'],
				'code'    => '',
				'data'    => null,
			);
		}

		/**
		 * WP_Error data for the JSON response: scalars and arrays of them only.
		 * Objects (a WP_HTTP response, a resource) are dropped, so internals do
		 * not leak to the page.
		 *
		 * @param mixed $data Error data.
		 * @param int   $depth Recursion guard.
		 * @return mixed
		 */
		private static function json_safe( $data, $depth = 0 ) {
			if ( null === $data || is_scalar( $data ) ) {
				return $data;
			}
			if ( ! is_array( $data ) || $depth > 4 ) {
				return null;
			}
			$out = array();
			foreach ( $data as $k => $v ) {
				if ( null === $v || is_scalar( $v ) || is_array( $v ) ) {
					$out[ $k ] = self::json_safe( $v, $depth + 1 );
				}
			}
			return $out;
		}

		/**
		 * Posted values of this option for an AJAX action: fields that were not
		 * sent keep their saved value; an empty secret means the saved one.
		 * Nonce is checked by handle_ajax() before this runs.
		 *
		 * @param array|null $raw Optional raw values (tests); default $_POST[option].
		 * @return array
		 */
		public function posted_values( $raw = null ) {
			if ( null === $raw ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in handle_ajax(); each field is sanitized below.
				$raw = isset( $_POST[ $this->option ] ) && is_array( $_POST[ $this->option ] ) ? wp_unslash( $_POST[ $this->option ] ) : array();
			}
			$saved = $this->all();
			$out   = array();
			foreach ( $this->fields as $id => $f ) {
				if ( ! self::stores( $f ) ) {
					continue;
				}
				if ( ! array_key_exists( $id, $raw ) ) {
					$out[ $id ] = $saved[ $id ];
					continue;
				}
				if ( self::is_secret( $f ) && is_scalar( $raw[ $id ] ) && '' === trim( (string) $raw[ $id ] ) ) {
					$out[ $id ] = $saved[ $id ];
					continue;
				}
				$out[ $id ] = $this->sanitize_field( $f, $raw[ $id ] );
			}
			return $out;
		}

		/**
		 * Kit notice for the settings page. One notice only, also on Settings
		 * submenu pages: claim_core_notices() keeps core from repeating it.
		 * - errors: role="alert" summary with an in-page link per field (at most
		 *   five, then 「等 N 项」), plus 「其余设置已保存。」 when the rest was saved;
		 * - otherwise a saved receipt (role="status") that fades after 5 s.
		 *
		 * @param bool|null $updated Whether the form was just saved. Null reads
		 *                           settings-updated from the options.php redirect.
		 * @return string
		 */
		public function notices_html( $updated = null ) {
			if ( null === $updated ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by options.php redirect.
				$updated = isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) );
			}
			$errs = $this->errors();
			$icon = class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'info' ) : '';
			$form = $this->form_error;
			if ( '' === $form ) {
				foreach ( $this->own_settings_errors() as $e ) {
					if ( isset( $e['code'], $e['message'] ) && 'wenpai_tab' === $e['code'] ) {
						$form = (string) $e['message'];
					}
				}
			}
			if ( '' !== $form ) {
				return '<div class="wenpai-notice-wrap"><div class="notice bad" role="alert">' . $icon . '<span>' . esc_html( $form ) . '</span></div></div>';
			}
			if ( ! empty( $errs ) ) {
				$items = '';
				$n     = 0;
				foreach ( array_keys( $errs ) as $id ) {
					if ( ++$n > 5 ) {
						break;
					}
					$label  = isset( $this->fields[ $id ] ) ? $this->fields[ $id ]['label'] : $id;
					$items .= '<li><a href="#' . esc_attr( $this->field_id( $id ) ) . '">' . esc_html( $label ) . '</a></li>';
				}
				if ( count( $errs ) > 5 ) {
					$items .= '<li>' . esc_html( sprintf( $this->strings['invalid_more'], count( $errs ) ) ) . '</li>';
				}
				$html  = '<div class="wenpai-notice-wrap"><div class="notice bad wenpai-error-summary" role="alert">' . $icon . '<div class="wenpai-error-summary__body">';
				$html .= '<p>' . esc_html( sprintf( $this->strings['invalid'], count( $errs ) ) ) . '</p>';
				$html .= '<ul class="wenpai-error-links">' . $items . '</ul>';
				if ( $updated ) {
					$html .= '<p>' . esc_html( $this->strings['partial_saved'] ) . '</p>';
				}
				return $html . '</div></div></div>';
			}
			if ( $updated ) {
				// data-wenpai-fade sits on the notice: core moves .notice out of a plain .wrap container.
				return '<div class="wenpai-notice-wrap"><div class="notice ok" role="status" data-wenpai-announce data-wenpai-fade>' . $icon . '<span>' . esc_html( $this->strings['saved'] ) . '</span></div></div>';
			}
			return '';
		}

		// --- Tabs decided by saved values ---

		/**
		 * Drop tabs whose 'depends_on' fails against saved values. A hidden tab is
		 * not output at all (no live JS switching). When the requested tab is
		 * hidden, fall back to the first visible one with an info notice.
		 *
		 * @param array  $tabs    Tab id => label, or id => array( 'label', 'depends_on', ... ).
		 * @param string $current Requested tab id.
		 * @return array{tabs: array, current: string, notice: string}
		 */
		public function visible_tabs( array $tabs, $current ) {
			$shown = array();
			foreach ( $tabs as $tid => $t ) {
				if ( is_array( $t ) && ! empty( $t['depends_on'] ) && ! $this->condition_met( (array) $t['depends_on'] ) ) {
					continue;
				}
				if ( is_array( $t ) ) {
					unset( $t['depends_on'] );
				}
				$shown[ $tid ] = $t;
			}
			$current = (string) $current;
			$notice  = '';
			if ( ! isset( $shown[ $current ] ) && ! empty( $shown ) ) {
				$first = (string) key( $shown );
				if ( isset( $tabs[ $current ] ) ) {
					$from   = is_array( $tabs[ $current ] ) ? ( isset( $tabs[ $current ]['label'] ) ? (string) $tabs[ $current ]['label'] : $current ) : (string) $tabs[ $current ];
					$to     = is_array( $shown[ $first ] ) ? ( isset( $shown[ $first ]['label'] ) ? (string) $shown[ $first ]['label'] : $first ) : (string) $shown[ $first ];
					$icon   = class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'info' ) : '';
					$notice = '<div class="wenpai-notice-wrap"><div class="notice info" role="status">' . $icon . '<span>' . esc_html( sprintf( $this->strings['tab_hidden'], $from, $to ) ) . '</span></div></div>';
				}
				$current = $first;
			}
			return array(
				'tabs'    => $shown,
				'current' => $current,
				'notice'  => $notice,
			);
		}
	}
}
