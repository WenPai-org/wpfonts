<?php
/**
 * Markup helpers for kit surfaces (tile, pill, notice, card, …).
 *
 * @package Wenpai_Admin_UI
 * @since 0.3.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_UI', false ) ) {
	/**
	 * Static HTML helpers. foo() echoes, foo_html() returns the same string.
	 */
	class Wenpai_Admin_UI {

		/**
		 * Tile / pill / notice states from the interface.
		 *
		 * @var string[]
		 */
		private static $tile_states = array( '', 'ok', 'warn', 'bad', 'accent' );

		/**
		 * Pill states. mute is pill-only.
		 *
		 * @var string[]
		 */
		private static $pill_states = array( '', 'ok', 'warn', 'bad', 'mute' );

		/**
		 * Progressbar states (Track E / E14). Ban ProgressCard.
		 *
		 * @var string[]
		 */
		private static $progress_states = array( '', 'warn', 'bad' );

		/**
		 * Notice levels.
		 *
		 * @var string[]
		 */
		private static $notice_levels = array( 'info', 'ok', 'warn', 'bad' );

		/**
		 * Button variants.
		 *
		 * @var string[]
		 */
		private static $button_variants = array( 'primary', 'secondary', 'ghost', 'danger' );

		/**
		 * Dashboard widget ids already registered by dashboard_widget() (0.8).
		 * Repeat calls with the same id are ignored so the helper is idempotent.
		 *
		 * @var array<string, bool>
		 */
		private static $dashboard_widgets = array();

		/**
		 * Filter caller HTML: post tags plus kit icon SVG, or SVG only.
		 *
		 * Same allowlist Settings used to keep as private kses(). Without
		 * wp_kses (outside WP) the string is escaped.
		 *
		 * @param string $html    Caller HTML.
		 * @param string $context 'svg' or 'post'.
		 * @return string
		 */
		public static function kses( $html, $context ) {
			if ( ! function_exists( 'wp_kses' ) ) {
				return esc_html( $html );
			}
			$svg   = array(
				'svg'            => array(
					'class'               => true,
					'viewbox'             => true,
					'width'               => true,
					'height'              => true,
					'fill'                => true,
					'stroke'              => true,
					'stroke-width'        => true,
					'stroke-linecap'      => true,
					'stroke-linejoin'     => true,
					'aria-hidden'         => true,
					'focusable'           => true,
					'role'                => true,
					'xmlns'               => true,
					'preserveaspectratio' => true,
				),
				'g'              => array(
					'fill'      => true,
					'stroke'    => true,
					'transform' => true,
				),
				'path'           => array(
					'd'               => true,
					'fill'            => true,
					'fill-rule'       => true,
					'clip-rule'       => true,
					'stroke'          => true,
					'stroke-width'    => true,
					'stroke-linecap'  => true,
					'stroke-linejoin' => true,
				),
				'circle'         => array(
					'cx' => true,
					'cy' => true,
					'r'  => true,
				),
				'ellipse'        => array(
					'cx' => true,
					'cy' => true,
					'rx' => true,
					'ry' => true,
				),
				'rect'           => array(
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
					'ry'     => true,
				),
				'line'           => array(
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				),
				'polyline'       => array( 'points' => true ),
				'polygon'        => array( 'points' => true ),
				'title'          => array(),
				'defs'           => array(),
				'lineargradient' => array(
					'id' => true,
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				),
				'stop'           => array(
					'offset'       => true,
					'stop-color'   => true,
					'stop-opacity' => true,
				),
			);
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
			foreach ( array( 'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon', 'lineargradient', 'stop' ) as $tag ) {
				$svg[ $tag ] = array_merge( $svg[ $tag ], $paint );
			}
			$allowed = 'svg' === $context ? $svg : array_merge( wp_kses_allowed_html( 'post' ), $svg );
			if ( 'control' === $context ) {
				$allowed = array_merge( $allowed, self::control_tags() );
			}
			return wp_kses( $html, $allowed );
		}

		/**
		 * Form controls for kses( …, 'control' ): what Settings::render_control_html()
		 * prints (switch, inputs, selects, textareas) plus the kit's data-* and aria-* hooks.
		 *
		 * @return array<string, array<string, bool>>
		 */
		private static function control_tags() {
			$common = array_fill_keys(
				array( 'class', 'id', 'role', 'hidden', 'inert', 'title', 'dir', 'lang', 'tabindex', 'data-*', 'aria-label', 'aria-labelledby', 'aria-describedby', 'aria-hidden', 'aria-invalid', 'aria-checked', 'aria-disabled', 'aria-expanded', 'aria-controls', 'aria-live', 'aria-busy', 'aria-required', 'aria-pressed', 'aria-valuenow', 'aria-valuemin', 'aria-valuemax', 'aria-valuetext' ),
				true
			);
			$tags   = array(
				'span'     => array(),
				'div'      => array(),
				'label'    => array( 'for' => true ),
				'input'    => array_fill_keys( array( 'type', 'name', 'value', 'checked', 'disabled', 'readonly', 'required', 'min', 'max', 'step', 'minlength', 'maxlength', 'pattern', 'placeholder', 'autocomplete', 'inputmode', 'size', 'list', 'multiple', 'accept', 'form', 'spellcheck' ), true ),
				'select'   => array_fill_keys( array( 'name', 'disabled', 'required', 'multiple', 'size', 'autocomplete', 'form' ), true ),
				'option'   => array_fill_keys( array( 'value', 'selected', 'disabled', 'label' ), true ),
				'optgroup' => array_fill_keys( array( 'label', 'disabled' ), true ),
				'textarea' => array_fill_keys( array( 'name', 'rows', 'cols', 'disabled', 'readonly', 'required', 'placeholder', 'maxlength', 'spellcheck', 'autocomplete', 'wrap' ), true ),
				'button'   => array_fill_keys( array( 'type', 'name', 'value', 'disabled', 'form' ), true ),
				'form'     => array_fill_keys( array( 'method', 'action', 'enctype', 'target' ), true ),
				'fieldset' => array_fill_keys( array( 'disabled', 'name' ), true ),
				'legend'   => array(),
			);
			foreach ( $tags as $tag => $attrs ) {
				$tags[ $tag ] = array_merge( $common, $attrs );
			}
			return $tags;
		}

		/**
		 * Echo already-escaped helper HTML.
		 *
		 * @param string $html Markup from *_html().
		 */
		private static function emit( $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
		}

		/**
		 * Echo a tile.
		 *
		 * @param array $args Arguments.
		 */
		public static function tile( $args = array() ) {
			self::emit( self::tile_html( $args ) );
		}

		/**
		 * Return tile markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $state ok|warn|bad|accent|''. mute becomes ''.
		 *     @type string $icon  Wenpai_Admin_Icons name; unknown → empty tile.
		 *     @type string $icon_html Raw product SVG (kses svg), wins over $icon.
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes (id, data-*, aria-*, role, hidden).
		 * }
		 * @return string
		 */
		public static function tile_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::tile' );
			$state = self::state( isset( $args['state'] ) ? $args['state'] : '', self::$tile_states, '', 'Wenpai_Admin_UI::tile', true );
			$icon  = self::icon_svg( isset( $args['icon'] ) ? $args['icon'] : '' );
			if ( isset( $args['icon_html'] ) && '' !== (string) $args['icon_html'] ) {
				$icon = self::kses( (string) $args['icon_html'], 'svg' );
			}
			$class = self::classes( array( 'tile', $state, self::extra_class( $args ) ) );
			return '<span class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . $icon . '</span>';
		}

		/**
		 * Echo a pill.
		 *
		 * @param array $args Arguments.
		 */
		public static function pill( $args = array() ) {
			self::emit( self::pill_html( $args ) );
		}

		/**
		 * Return pill markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $text  Label.
		 *     @type string $state ok|warn|bad|mute|''.
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function pill_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::pill' );
			$state = self::state( isset( $args['state'] ) ? $args['state'] : '', self::$pill_states, '', 'Wenpai_Admin_UI::pill' );
			$text  = isset( $args['text'] ) ? (string) $args['text'] : '';
			$class = self::classes( array( 'pill', $state, self::extra_class( $args ) ) );
			return '<span class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . esc_html( $text ) . '</span>';
		}

		/**
		 * Echo a notice.
		 *
		 * @param array $args Arguments.
		 */
		public static function notice( $args = array() ) {
			self::emit( self::notice_html( $args ) );
		}

		/**
		 * Return notice markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $text      Plain text (escaped).
		 *     @type string $text_html Caller HTML (kses post).
		 *     @type string $level     info|ok|warn|bad. Default info.
		 *     @type bool   $fade      Adds data-wenpai-fade.
		 *     @type bool   $wrap      Wrap in .wenpai-notice-wrap. Default true.
		 *     @type string $class     Extra classes on .notice.
		 *     @type array  $attrs     Extra attributes on .notice.
		 * }
		 * @return string
		 */
		public static function notice_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::notice' );
			$level = isset( $args['level'] ) ? (string) $args['level'] : 'info';
			if ( ! in_array( $level, self::$notice_levels, true ) ) {
				self::doing_it_wrong( sprintf( 'Invalid notice level 「%s」.', $level ), 'Wenpai_Admin_UI::notice' );
				$level = 'info';
			}
			$inner = array_key_exists( 'text_html', $args )
				? self::kses( (string) $args['text_html'], 'post' )
				: esc_html( isset( $args['text'] ) ? (string) $args['text'] : '' );
			$icon  = self::icon_svg( 'info' );
			$class = self::classes( array( 'notice', $level, self::extra_class( $args ) ) );
			$role  = '';
			if ( 'bad' === $level ) {
				$role = 'alert';
			} elseif ( 'ok' === $level || 'warn' === $level ) {
				$role = 'status';
			}
			$attr = ' class="' . esc_attr( $class ) . '"';
			if ( '' !== $role ) {
				$attr .= ' role="' . esc_attr( $role ) . '"';
			}
			$skip = array( 'role' );
			if ( ! empty( $args['fade'] ) ) {
				$attr  .= ' data-wenpai-fade';
				$skip[] = 'data-wenpai-fade';
			}
			$attr .= self::extra_attrs( $args, $skip );
			$html  = '<div' . $attr . '>' . $icon . '<span>' . $inner . '</span></div>';
			$wrap  = ! array_key_exists( 'wrap', $args ) || $args['wrap'];
			return $wrap ? '<div class="wenpai-notice-wrap">' . $html . '</div>' : $html;
		}

		/**
		 * Echo the opening card markup.
		 *
		 * @param array $args Arguments.
		 */
		public static function card_open( $args = array() ) {
			self::emit( self::card_open_html( $args ) );
		}

		/**
		 * Return the opening card markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title    Card title.
		 *     @type string $sub      Subtitle (escaped).
		 *     @type string $sub_html Subtitle HTML (kses post).
		 *     @type string $icon     Tile icon.
		 *     @type string $state    Tile state.
		 *     @type bool   $tight    .card.tight.
		 *     @type int    $heading  Title level, default 2.
		 *     @type string $class    Extra classes on section.card.
		 *     @type array  $attrs    Extra attributes on section.card.
		 *     @type string $head_actions_html Right slot HTML (kses post) after the title block,
		 *                                wrapped in .head-actions (0.5.0).
		 * }
		 * @return string
		 */
		public static function card_open_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::card_open' );
			$tight   = ! empty( $args['tight'] );
			$class   = self::classes( array( 'card', $tight ? 'tight' : '', self::extra_class( $args ) ) );
			$html    = '<section class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			$title   = isset( $args['title'] ) ? (string) $args['title'] : '';
			$has_sub = array_key_exists( 'sub_html', $args ) || ( isset( $args['sub'] ) && '' !== (string) $args['sub'] );
			$icon    = isset( $args['icon'] ) ? (string) $args['icon'] : '';
			if ( '' === $title && ! $has_sub && '' === $icon ) {
				return $html;
			}
			$heading = self::heading( isset( $args['heading'] ) ? $args['heading'] : 2, 'Wenpai_Admin_UI::card_open' );
			$tile    = self::tile_html(
				array(
					'icon'      => $icon,
					'state'     => isset( $args['state'] ) ? $args['state'] : '',
					'icon_html' => isset( $args['icon_html'] ) ? $args['icon_html'] : '',
				)
			);
			$html   .= '<div class="card-head">' . $tile . '<div>';
			if ( '' !== $title ) {
				$html .= '<h' . $heading . ' class="card-title">' . esc_html( $title ) . '</h' . $heading . '>';
			}
			if ( array_key_exists( 'sub_html', $args ) ) {
				$sub = self::kses( (string) $args['sub_html'], 'post' );
				if ( '' !== $sub ) {
					$html .= '<p class="card-sub">' . $sub . '</p>';
				}
			} elseif ( isset( $args['sub'] ) && '' !== (string) $args['sub'] ) {
				$html .= '<p class="card-sub">' . esc_html( (string) $args['sub'] ) . '</p>';
			}
			$html .= '</div>';
			if ( isset( $args['head_actions_html'] ) && '' !== (string) $args['head_actions_html'] ) {
				$html .= '<span class="head-actions">' . self::kses( (string) $args['head_actions_html'], 'post' ) . '</span>';
			}
			return $html . '</div>';
		}

		/**
		 * Close a card opened with card_open().
		 */
		public static function card_close() {
			self::emit( self::card_close_html() );
		}

		/**
		 * Return the closing card tag.
		 *
		 * @return string
		 */
		public static function card_close_html() {
			return '</section>';
		}

		/**
		 * Echo a card.
		 *
		 * @param array $args Arguments.
		 */
		public static function card( $args = array() ) {
			self::emit( self::card_html( $args ) );
		}

		/**
		 * Return a full card.
		 *
		 * The body_html arg (and a string foot) goes through kses( …, 'post' ): wp_kses
		 * post tags plus kit SVG. That list has no input, select or option, so
		 * form controls are stripped. For a card that holds a form, use
		 * card_open_html() / card_close_html() and write the form between them.
		 *
		 * @param array $args card_open() args plus body_html and foot.
		 * @return string
		 */
		public static function card_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::card' );
			$html = self::card_open_html( $args );
			if ( isset( $args['body_html'] ) ) {
				$html .= self::kses( (string) $args['body_html'], 'post' );
			}
			if ( isset( $args['foot'] ) ) {
				if ( is_array( $args['foot'] ) ) {
					$html .= self::card_foot_html( $args['foot'] );
				} else {
					$html .= self::kses( (string) $args['foot'], 'post' );
				}
			}
			return $html . self::card_close_html();
		}

		/**
		 * Echo a card footer.
		 *
		 * @param array $args Arguments.
		 */
		public static function card_foot( $args = array() ) {
			self::emit( self::card_foot_html( $args ) );
		}

		/**
		 * Return card footer markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $meta         Meta text, wrapped in span.meta.
		 *     @type string $meta_html    Meta HTML (kses post), used instead of meta.
		 *     @type array  $actions      Button argument arrays.
		 *     @type string $actions_html Extra action HTML (kses post).
		 *     @type bool   $start        .card-foot.start.
		 *     @type string $class        Extra classes.
		 *     @type array  $attrs        Extra attributes.
		 * }
		 * @return string
		 */
		public static function card_foot_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::card_foot' );
			$start = ! empty( $args['start'] );
			$class = self::classes( array( 'card-foot', $start ? 'start' : '', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			if ( array_key_exists( 'meta_html', $args ) ) {
				$html .= self::kses( (string) $args['meta_html'], 'post' );
			} elseif ( isset( $args['meta'] ) && '' !== (string) $args['meta'] ) {
				$html .= '<span class="meta">' . esc_html( (string) $args['meta'] ) . '</span>';
			}
			if ( isset( $args['actions'] ) && is_array( $args['actions'] ) ) {
				foreach ( $args['actions'] as $btn ) {
					if ( is_array( $btn ) ) {
						$html .= self::button_html( $btn );
					}
				}
			}
			if ( isset( $args['actions_html'] ) ) {
				$html .= self::kses( (string) $args['actions_html'], 'post' );
			}
			return $html . '</div>';
		}

		/**
		 * Echo an async task block (progress + counts + live meta).
		 *
		 * @param array $args Arguments.
		 */
		public static function task( $args = array() ) {
			self::emit( self::task_html( $args ) );
		}

		/**
		 * Return task block markup. Product wraps it in card_open / card_foot.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type int    $now    Current progress value.
		 *     @type int    $total  Maximum value.
		 *     @type bool   $stopped Bar turns warn (paused / stalled).
		 *     @type bool   $bad    Bar turns bad (failed batch).
		 *     @type array  $counts Pill argument arrays (ok / pending / failed / state).
		 *     @type string $meta   Live region sentence (aria-live polite).
		 *     @type string $class  Extra classes on .wenpai-task.
		 *     @type array  $attrs  Extra attributes.
		 * }
		 * @return string
		 */
		public static function task_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::task' );
			$now   = (int) ( isset( $args['now'] ) ? $args['now'] : 0 );
			$total = (int) ( isset( $args['total'] ) ? $args['total'] : 0 );
			$pct   = $total > 0 ? min( 100, (int) round( $now * 100 / $total ) ) : 0;
			$bar   = 'wenpai-task-bar';
			if ( ! empty( $args['stopped'] ) ) {
				$bar .= ' is-stopped';
			}
			if ( ! empty( $args['bad'] ) ) {
				$bar .= ' is-bad';
			}
			$now      = (int) ( isset( $args['now'] ) ? $args['now'] : 0 );
			$total    = (int) ( isset( $args['total'] ) ? $args['total'] : 0 );
			$pct      = $total > 0 ? min( 100, (int) round( $now * 100 / $total ) ) : 0;
			$bar_html = '<div class="' . esc_attr( $bar ) . '" role="progressbar" aria-valuemin="0" aria-valuemax="' . esc_attr( (string) $total ) . '" aria-valuenow="' . esc_attr( (string) $now ) . '"><i style="width:' . $pct . '%"></i></div>';
			$counts   = isset( $args['counts'] ) && is_array( $args['counts'] ) ? $args['counts'] : array();
			$html     = '<div class="' . esc_attr( self::classes( array( 'wenpai-task', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>' . $bar_html;
			if ( array() !== $counts ) {
				$html .= '<div class="wenpai-task-counts">';
				foreach ( $counts as $pill ) {
					if ( is_array( $pill ) ) {
						$html .= self::pill_html( $pill );
					}
				}
				$html .= '</div>';
			}
			if ( isset( $args['meta'] ) && '' !== (string) $args['meta'] ) {
				$html .= '<p class="wenpai-task-meta" aria-live="polite">' . esc_html( (string) $args['meta'] ) . '</p>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo a read-only log list.
		 *
		 * @param array $args Arguments.
		 */
		public static function log_list( $args = array() ) {
			self::emit( self::log_list_html( $args ) );
		}

		/**
		 * Return log list markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array $items array of array{ tone, label, count?, when?, detail }.
		 *     @type array $empty Optional empty_state arguments when items is empty.
		 *     @type string $class Extra classes on ul.wenpai-log.
		 *     @type array $attrs  Extra attributes.
		 * }
		 * @return string
		 */
		public static function log_list_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::log_list' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			if ( array() === $items ) {
				return isset( $args['empty'] ) && is_array( $args['empty'] ) ? self::empty_state_html( $args['empty'] ) : '';
			}
			$html = '<ul class="' . esc_attr( self::classes( array( 'wenpai-log', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$html .= '<li><div class="n">';
				$html .= self::pill_html(
					array(
						'state' => isset( $item['tone'] ) ? (string) $item['tone'] : '',
						'text'  => isset( $item['label'] ) ? (string) $item['label'] : '',
					)
				);
				if ( ! empty( $item['count'] ) && 1 < (int) $item['count'] ) {
					$html .= '<span class="meta"> ×' . (int) $item['count'] . '</span>';
				}
				$html .= '</div>';
				if ( isset( $item['when'] ) && '' !== (string) $item['when'] ) {
					$html .= '<div class="when">' . esc_html( (string) $item['when'] ) . '</div>';
				}
				$html .= '<div class="p">' . esc_html( isset( $item['detail'] ) ? (string) $item['detail'] : '' ) . '</div></li>';
			}
			return $html . '</ul>';
		}

		/**
		 * Attributes that wire an element to the kit confirm dialog (0.5.3).
		 *
		 * @param string $text Confirmation sentence shown in the dialog body.
		 * @param array  $args Optional. title / gate / danger / ok_label / cancel_label.
		 * @return string Escaped attribute string with a leading space.
		 */
		public static function confirm_attrs( $text, $args = array() ) {
			$out  = ' data-wenpai-confirm="' . esc_attr( (string) $text ) . '"';
			$args = is_array( $args ) ? $args : array();
			if ( ! empty( $args['title'] ) ) {
				$out .= ' data-wenpai-confirm-title="' . esc_attr( (string) $args['title'] ) . '"';
			}
			if ( ! empty( $args['gate'] ) ) {
				$out .= ' data-wenpai-confirm-gate="' . esc_attr( (string) $args['gate'] ) . '"';
			}
			if ( ! empty( $args['danger'] ) ) {
				$out .= ' data-wenpai-confirm-danger="1"';
			}
			if ( ! empty( $args['ok_label'] ) ) {
				$out .= ' data-wenpai-confirm-ok="' . esc_attr( (string) $args['ok_label'] ) . '"';
			}
			if ( ! empty( $args['cancel_label'] ) ) {
				$out .= ' data-wenpai-confirm-cancel="' . esc_attr( (string) $args['cancel_label'] ) . '"';
			}
			return $out;
		}

		/**
		 * Open a wizard page shell (narrow column + steps + optional jump notice).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $steps  steps() items.
		 *     @type int    $current Current step index for steps().
		 *     @type string $notice Optional jump-back sentence (kit notice, info).
		 *     @type string $class  Extra classes on .wenpai-wizard.
		 * }
		 * @return void
		 */
		public static function wizard_open( $args = array() ) {
			$class = self::classes( array( 'wenpai-wizard', self::extra_class( $args ) ) );
			echo '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- extra_attrs escapes each value.
			if ( isset( $args['steps'] ) && is_array( $args['steps'] ) ) {
				self::steps(
					array(
						'items'   => $args['steps'],
						'current' => isset( $args['current'] ) ? (int) $args['current'] : 0,
					)
				);
			}
			if ( isset( $args['notice'] ) && '' !== (string) $args['notice'] ) {
				self::notice(
					array(
						'level' => 'info',
						'text'  => (string) $args['notice'],
						'wrap'  => false,
					)
				);
			}
		}

		/**
		 * Close the wizard shell.
		 *
		 * @return void
		 */
		public static function wizard_close() {
			echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static closing tag.
		}

		/**
		 * Echo a media-library column cell (pill + one-line key).
		 *
		 * @param array $args Arguments.
		 */
		public static function native_column( $args = array() ) {
			self::emit( self::native_column_html( $args ) );
		}

		/**
		 * Return column cell markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $pill Pill arguments (text, state).
		 *     @type string $code Optional object key shown as one-line code chip.
		 *     @type string $meta Optional extra meta line.
		 * }
		 * @return string
		 */
		public static function native_column_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::native_column' );
			$html = '<div class="wenpai-native wenpai-native-col">';
			if ( isset( $args['pill'] ) && is_array( $args['pill'] ) ) {
				$html .= self::pill_html( $args['pill'] );
			}
			if ( isset( $args['code'] ) && '' !== (string) $args['code'] ) {
				$html .= '<code>' . esc_html( (string) $args['code'] ) . '</code>';
			}
			if ( isset( $args['meta'] ) && '' !== (string) $args['meta'] ) {
				$html .= '<span class="meta">' . esc_html( (string) $args['meta'] ) . '</span>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo an attachment metabox body (status + lines + actions + note).
		 *
		 * @param array $args Arguments.
		 */
		public static function native_metabox( $args = array() ) {
			self::emit( self::native_metabox_html( $args ) );
		}

		/**
		 * Return metabox body markup. Product registers the metabox itself.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $pill    Status pill arguments.
		 *     @type array  $lines   array of array{ kind: meta|code|link, text, href? }.
		 *     @type array  $actions Button argument arrays (href renders <a>).
		 *     @type string $note    Closing warning sentence.
		 * }
		 * @return string
		 */
		public static function native_metabox_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::native_metabox' );
			$html = '<div class="wenpai-native wenpai-native-metabox">';
			if ( isset( $args['pill'] ) && is_array( $args['pill'] ) ) {
				$html .= '<div class="head">' . self::pill_html( $args['pill'] ) . '</div>';
			}
			$lines = isset( $args['lines'] ) && is_array( $args['lines'] ) ? $args['lines'] : array();
			foreach ( $lines as $line ) {
				if ( ! is_array( $line ) ) {
					continue;
				}
				$kind = isset( $line['kind'] ) ? (string) $line['kind'] : 'meta';
				$text = isset( $line['text'] ) ? (string) $line['text'] : '';
				if ( 'code' === $kind ) {
					$html .= '<p class="meta"><code>' . esc_html( $text ) . '</code></p>';
				} elseif ( 'link' === $kind && ! empty( $line['href'] ) ) {
					$html .= '<p class="meta"><a href="' . esc_url( (string) $line['href'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $text ) . '</a></p>';
				} else {
					$html .= '<p class="meta">' . esc_html( $text ) . '</p>';
				}
			}
			$actions = isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : array();
			if ( array() !== $actions ) {
				$html .= '<div class="actions">';
				foreach ( $actions as $btn ) {
					if ( is_array( $btn ) ) {
						$html .= self::button_html( $btn );
					}
				}
				$html .= '</div>';
			}
			if ( isset( $args['note'] ) && '' !== (string) $args['note'] ) {
				$html .= '<p class="meta">' . esc_html( (string) $args['note'] ) . '</p>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo a section title.
		 *
		 * @param array $args Arguments.
		 */
		public static function section_title( $args = array() ) {
			self::emit( self::section_title_html( $args ) );
		}

		/**
		 * Return section title markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title   Title.
		 *     @type string $desc    Optional description.
		 *     @type string $desc_html Optional description HTML (kses post), wins over $desc.
		 *     @type int    $heading Level, default 2.
		 *     @type string $class   Extra classes on the heading.
		 *     @type array  $attrs   Extra attributes on the heading.
		 * }
		 * @return string
		 */
		public static function section_title_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::section_title' );
			$heading = self::heading( isset( $args['heading'] ) ? $args['heading'] : 2, 'Wenpai_Admin_UI::section_title' );
			$title   = isset( $args['title'] ) ? (string) $args['title'] : '';
			$class   = self::classes( array( 'section-title', self::extra_class( $args ) ) );
			$html    = '<h' . $heading . ' class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . esc_html( $title ) . '</h' . $heading . '>';
			if ( isset( $args['desc'] ) && '' !== (string) $args['desc'] ) {
				$html .= '<p class="section-desc">' . esc_html( (string) $args['desc'] ) . '</p>';
			}
			return $html;
		}

		/**
		 * Echo a status row.
		 *
		 * @param array $args Arguments.
		 */
		public static function row( $args = array() ) {
			self::emit( self::row_html( $args ) );
		}

		/**
		 * Return status row markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title      .t text.
		 *     @type string $desc       .d text.
		 *     @type string $icon       Tile icon.
		 *     @type string $state      Tile state.
		 *     @type array  $pill       array( text, state ) or keyed.
		 *     @type string $title_id   id on .t, for the control's aria-labelledby.
		 *     @type string $desc_id    id on .d, for the control's aria-describedby.
		 *     @type string $right_html Extra .r HTML (kses post: form controls are stripped).
		 *     @type string $control_html Control row: control in .r, kses with form
		 *                                controls kept (e.g. $fw->render_control_html()).
		 *                                Outputs a bare .simple-row; href is ignored.
		 *     @type string $href       Whole row is a link (.is-action); else .is-static.
		 *     @type string $class      Extra classes.
		 *     @type array  $attrs      Extra attributes.
		 * }
		 * @return string
		 */
		public static function row_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::row' );
			$href    = isset( $args['href'] ) ? (string) $args['href'] : '';
			$control = array_key_exists( 'control_html', $args );
			if ( $control && '' !== $href ) {
				self::doing_it_wrong( 'row control_html and href cannot be combined; href is ignored.', 'Wenpai_Admin_UI::row' );
				$href = '';
			}
			$mod   = '' !== $href ? 'is-action' : 'is-static';
			$mod   = $control ? '' : $mod;
			$class = self::classes( array( 'simple-row', $mod, self::extra_class( $args ) ) );
			$tile  = self::tile_html(
				array(
					'icon'      => isset( $args['icon'] ) ? $args['icon'] : '',
					'state'     => isset( $args['state'] ) ? $args['state'] : '',
					'icon_html' => isset( $args['icon_html'] ) ? $args['icon_html'] : '',
				)
			);
			$body  = '<div>';
			if ( isset( $args['title'] ) && '' !== (string) $args['title'] ) {
				$body .= '<div class="t"' . self::id_attr( $args, 'title_id' ) . '>' . esc_html( (string) $args['title'] ) . '</div>';
			}
			if ( array_key_exists( 'desc_html', $args ) && '' !== (string) $args['desc_html'] ) {
				$body .= '<div class="d"' . self::id_attr( $args, 'desc_id' ) . '>' . self::kses( (string) $args['desc_html'], 'post' ) . '</div>';
			} elseif ( isset( $args['desc'] ) && '' !== (string) $args['desc'] ) {
				$body .= '<div class="d"' . self::id_attr( $args, 'desc_id' ) . '>' . esc_html( (string) $args['desc'] ) . '</div>';
			}
			$body .= '</div>';
			$right = '';
			if ( isset( $args['pill'] ) && is_array( $args['pill'] ) ) {
				$pill   = self::item_kv( $args['pill'], array( 'text', 'state' ) );
				$right .= self::pill_html( $pill );
			}
			if ( isset( $args['right_html'] ) ) {
				$right .= self::kses( (string) $args['right_html'], 'post' );
			}
			if ( $control ) {
				$right .= self::kses( (string) $args['control_html'], 'control' );
			}
			if ( '' !== $right ) {
				$body .= '<div class="r">' . $right . '</div>';
			}
			if ( '' !== $href ) {
				return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $href ) . '"' . self::extra_attrs( $args ) . '>' . $tile . $body . '</a>';
			}
			return '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . $tile . $body . '</div>';
		}

		/**
		 * Echo a next-step bar.
		 *
		 * @param array $args Arguments.
		 */
		public static function next( $args = array() ) {
			self::emit( self::next_html( $args ) );
		}

		/**
		 * Return next-step bar markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title   Strong text.
		 *     @type string $desc    .meta text.
		 *     @type string $icon    Tile icon.
		 *     @type array  $actions Button argument arrays.
		 *     @type string $class   Extra classes.
		 *     @type array  $attrs   Extra attributes.
		 * }
		 * @return string
		 */
		public static function next_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::next' );
			$class = self::classes( array( 'next', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			$html .= self::tile_html(
				array(
					'icon'      => isset( $args['icon'] ) ? $args['icon'] : '',
					'state'     => isset( $args['state'] ) ? $args['state'] : '',
					'icon_html' => isset( $args['icon_html'] ) ? $args['icon_html'] : '',
				)
			);
			$html .= '<div>';
			if ( isset( $args['title'] ) && '' !== (string) $args['title'] ) {
				$html .= '<strong>' . esc_html( (string) $args['title'] ) . '</strong>';
			}
			if ( isset( $args['desc'] ) && '' !== (string) $args['desc'] ) {
				$html .= '<div class="meta">' . esc_html( (string) $args['desc'] ) . '</div>';
			}
			$html .= '</div>';
			if ( isset( $args['actions'] ) && is_array( $args['actions'] ) && array() !== $args['actions'] ) {
				$html .= '<div class="next-actions">';
				foreach ( $args['actions'] as $btn ) {
					if ( is_array( $btn ) ) {
						$html .= self::button_html( $btn );
					}
				}
				$html .= '</div>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo an empty state.
		 *
		 * @param array $args Arguments.
		 */
		public static function empty_state( $args = array() ) {
			self::emit( self::empty_state_html( $args ) );
		}

		/**
		 * Return empty state markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $text   Main line.
		 *     @type string $meta   Secondary line.
		 *     @type string $icon   Optional tile.
		 *     @type array  $action Optional button args.
		 *     @type string $class  Extra classes.
		 *     @type array  $attrs  Extra attributes.
		 * }
		 * @return string
		 */
		public static function empty_state_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::empty_state' );
			$class = self::classes( array( 'empty', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			if ( isset( $args['icon'] ) && '' !== (string) $args['icon'] ) {
				$html .= self::tile_html(
					array(
						'icon'      => $args['icon'],
						'state'     => isset( $args['state'] ) ? $args['state'] : '',
						'icon_html' => isset( $args['icon_html'] ) ? $args['icon_html'] : '',
					)
				);
			}
			$html .= '<p>' . esc_html( isset( $args['text'] ) ? (string) $args['text'] : '' ) . '</p>';
			if ( isset( $args['meta'] ) && '' !== (string) $args['meta'] ) {
				$html .= '<p class="meta">' . esc_html( (string) $args['meta'] ) . '</p>';
			}
			if ( isset( $args['action'] ) && is_array( $args['action'] ) ) {
				$html .= self::button_html( $args['action'] );
			}
			return $html . '</div>';
		}

		/**
		 * Echo a StatArea card (P2-charts: label + value + desc + optional Area SVG).
		 *
		 * @param array $args Arguments.
		 */
		public static function stat_area( $args = array() ) {
			self::emit( self::stat_area_html( $args ) );
		}

		/**
		 * Return StatArea markup. Area only (no Chart.js). SVG when !empty and points>=2.
		 *
		 * @param array $args {
		 *     Arguments for the StatArea card.
		 *     @type string $label  Visible label.
		 *     @type string $value  Big number or empty-state text.
		 *     @type string $unit   Optional unit beside value.
		 *     @type string $delta  Optional positive delta text (.delta; color --wenpai-ok-ink).
		 *     @type string $desc   Description (.d); when empty, say when data appears.
		 *     @type array  $points Number list for Area sparkline.
		 *     @type bool   $empty  True → .v.none, no SVG.
		 *     @type bool   $busy   True → .is-busy + spin line (no .btn.is-loading).
		 *     @type string $icon   Optional icon name for .k.
		 *     @type string $class  Extra classes.
		 *     @type array  $attrs  Extra attributes.
		 * }
		 * @return string
		 */
		public static function stat_area_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::stat_area' );
			$empty = ! empty( $args['empty'] );
			$busy  = ! empty( $args['busy'] );
			$class = self::classes(
				array(
					'card',
					'stat-a',
					$busy ? 'is-busy' : '',
					self::extra_class( $args ),
				)
			);
			$attrs = $args;
			if ( $busy ) {
				if ( ! isset( $attrs['attrs'] ) || ! is_array( $attrs['attrs'] ) ) {
					$attrs['attrs'] = array();
				}
				$attrs['attrs']['aria-busy'] = 'true';
			}
			$html  = '<article class="' . esc_attr( $class ) . '"' . self::extra_attrs( $attrs ) . '>';
			$html .= '<div class="k">';
			if ( isset( $args['icon'] ) && '' !== (string) $args['icon'] ) {
				$html .= self::icon_svg( (string) $args['icon'] );
			}
			$html .= esc_html( isset( $args['label'] ) ? (string) $args['label'] : '' );
			$html .= '</div>';

			$value = isset( $args['value'] ) ? (string) $args['value'] : '';
			if ( $empty ) {
				$html .= '<div class="v none">' . esc_html( $value ) . '</div>';
			} else {
				$html .= '<div class="v">' . esc_html( $value );
				if ( isset( $args['unit'] ) && '' !== (string) $args['unit'] ) {
					$html .= '<small>' . esc_html( (string) $args['unit'] ) . '</small>';
				}
				if ( isset( $args['delta'] ) && '' !== (string) $args['delta'] ) {
					$html .= '<span class="delta">' . esc_html( (string) $args['delta'] ) . '</span>';
				}
				$html .= '</div>';
			}

			$html .= '<div class="d">' . esc_html( isset( $args['desc'] ) ? (string) $args['desc'] : '' ) . '</div>';

			if ( $busy ) {
				$html .= '<div class="stat-a-busy"><span class="spin" aria-hidden="true"></span>' . esc_html( '正在…' ) . '</div>';
			}

			$points = isset( $args['points'] ) && is_array( $args['points'] ) ? $args['points'] : array();
			if ( ! $empty && count( $points ) >= 2 ) {
				$html .= self::area_svg_html( $points );
			}

			return $html . '</article>';
		}

		/**
		 * Inline Area sparkline SVG (viewBox 300×44). Trusted path math; kses for safety.
		 *
		 * @param array $points Numbers.
		 * @return string
		 */
		private static function area_svg_html( array $points ) {
			$w  = 300;
			$h  = 44;
			$n  = count( $points );
			$mx = 0.0;
			foreach ( $points as $p ) {
				$v = (float) $p;
				if ( $v > $mx ) {
					$mx = $v;
				}
			}
			if ( $mx <= 0 ) {
				$mx = 1.0;
			}
			$coords = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$x        = ( 1 === $n ) ? 0.0 : ( $i * $w ) / ( $n - 1 );
				$y        = $h - 6 - ( ( (float) $points[ $i ] ) / $mx ) * ( $h - 12 );
				$coords[] = sprintf( '%.1f,%.1f', $x, $y );
			}
			$d    = 'M' . implode( ' L', $coords );
			$fill = $d . ' L' . $w . ',' . $h . ' L0,' . $h . ' Z';
			$gid  = 'wenpai-area-' . substr( md5( $d ), 0, 8 );
			$svg  = '<svg class="area" viewBox="0 0 300 44" preserveAspectRatio="none" aria-hidden="true">';
			$svg .= '<defs><linearGradient id="' . esc_attr( $gid ) . '" x1="0" y1="0" x2="0" y2="1">';
			$svg .= '<stop offset="0" stop-color="var(--wenpai-accent)" stop-opacity=".18" />';
			$svg .= '<stop offset="1" stop-color="var(--wenpai-accent)" stop-opacity="0" />';
			$svg .= '</linearGradient></defs>';
			$svg .= '<path d="' . esc_attr( $fill ) . '" fill="url(#' . esc_attr( $gid ) . ')" />';
			$svg .= '<path d="' . esc_attr( $d ) . '" fill="none" stroke="var(--wenpai-accent)" stroke-width="1.75" stroke-linejoin="round" />';
			$svg .= '</svg>';
			return self::kses( $svg, 'svg' );
		}

		/**
		 * Echo a progressbar (Track E / E14 · P2x). Budget occupancy display.
		 * Not StatArea. Ban ProgressCard.
		 *
		 * @param array $args Arguments.
		 */
		public static function progressbar( $args = array() ) {
			self::emit( self::progressbar_html( $args ) );
		}

		/**
		 * Return progressbar markup. role=progressbar + aria-valuenow/min/max.
		 *
		 * Batch-task tick loops stay in the product (ARCHITECTURE §1.11).
		 * This helper is display only.
		 *
		 * @param array $args {
		 *     Arguments for the progressbar.
		 *     @type float  $value          Current occupancy. Ignored if indeterminate.
		 *     @type float  $min            Default 0.
		 *     @type float  $max            Default 100.
		 *     @type string $state          ''|warn|bad.
		 *     @type string $label          aria-label (e.g. 预算占用).
		 *     @type string $text           Visible percent; default derived from value/max.
		 *     @type bool   $indeterminate  True → omit aria-valuenow; .is-indeterminate.
		 *     @type string $class          Extra classes on .progress.
		 *     @type array  $attrs          Extra attributes on the progressbar node.
		 * }
		 * @return string
		 */
		public static function progressbar_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::progressbar' );
			$state = self::state( isset( $args['state'] ) ? $args['state'] : '', self::$progress_states, '', 'Wenpai_Admin_UI::progressbar' );
			$min   = isset( $args['min'] ) ? (float) $args['min'] : 0.0;
			$max   = isset( $args['max'] ) ? (float) $args['max'] : 100.0;
			if ( $max <= $min ) {
				self::doing_it_wrong( 'max must be greater than min.', 'Wenpai_Admin_UI::progressbar' );
				$max = $min + 1.0;
			}
			$indet = ! empty( $args['indeterminate'] );
			$value = isset( $args['value'] ) ? (float) $args['value'] : 0.0;
			$span  = $max - $min;
			$raw   = $indet ? 0.0 : ( ( $value - $min ) / $span ) * 100.0;
			$fill  = max( 0.0, min( 100.0, $raw ) );
			$now   = max( $min, min( $max, $value ) );
			if ( array_key_exists( 'text', $args ) ) {
				$text = (string) $args['text'];
			} elseif ( $indet ) {
				$text = '';
			} else {
				$text = ( abs( $raw - round( $raw ) ) < 0.05 )
					? (string) (int) round( $raw ) . '%'
					: rtrim( rtrim( sprintf( '%.1F', $raw ), '0' ), '.' ) . '%';
			}
			$class = self::classes(
				array(
					'progress',
					$state,
					$indet ? 'is-indeterminate' : '',
					self::extra_class( $args ),
				)
			);
			$aria  = ' role="progressbar"';
			$aria .= ' aria-valuemin="' . esc_attr( self::progress_number( $min ) ) . '"';
			$aria .= ' aria-valuemax="' . esc_attr( self::progress_number( $max ) ) . '"';
			if ( ! $indet ) {
				$aria .= ' aria-valuenow="' . esc_attr( self::progress_number( $now ) ) . '"';
				if ( '' !== $text ) {
					$aria .= ' aria-valuetext="' . esc_attr( $text ) . '"';
				}
			}
			if ( isset( $args['label'] ) && '' !== (string) $args['label'] ) {
				$aria .= ' aria-label="' . esc_attr( (string) $args['label'] ) . '"';
			}
			$html  = '<div class="progress-wrap">';
			$html .= '<div class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, array( 'role' ) ) . '>';
			if ( $indet ) {
				$html .= '<i aria-hidden="true"></i>';
			} else {
				$html .= '<i aria-hidden="true" style="width:' . esc_attr( self::progress_number( $fill ) ) . '%"></i>';
			}
			$html .= '</div>';
			if ( '' !== $text ) {
				$html .= '<span class="progress-pct">' . esc_html( $text ) . '</span>';
			}
			return $html . '</div>';
		}

		/**
		 * Format a number for attributes (dot decimal, trim trailing zeros).
		 *
		 * @param float $n Number.
		 * @return string
		 */
		private static function progress_number( $n ) {
			if ( abs( $n - round( $n ) ) < 0.0005 ) {
				return (string) (int) round( $n );
			}
			return rtrim( rtrim( sprintf( '%.2F', $n ), '0' ), '.' );
		}

		/**
		 * Echo a button or button-styled link.
		 *
		 * @param array $args Arguments.
		 */
		public static function button( $args = array() ) {
			self::emit( self::button_html( $args ) );
		}

		/**
		 * Return button markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $label      Button text.
		 *     @type string $variant    primary|secondary|ghost|danger. Default secondary.
		 *     @type string $href       Output <a> when set.
		 *     @type string $target     For <a>; _blank adds rel and screen-reader hint.
		 *     @type string $type       button|submit for <button>. Default button.
		 *     @type string $name       <button> name.
		 *     @type string $value      <button> value.
		 *     @type bool   $disabled   Disabled. With href: <a role="link" aria-disabled="true"
		 *                              tabindex="-1"> and no href, target or rel.
		 *     @type bool   $need_apply data-wenpai-need-apply.
		 *     @type string $busy_label data-wenpai-busy.
		 *     @type string $new_tab_label Screen-reader hint after a target="_blank" link.
		 *                                 Default 「（在新标签页打开）」 (same as Shell new_tab_label
		 *                                 and Settings strings['new_tab']); '' prints none.
		 *     @type string $class      Extra classes.
		 *     @type array  $attrs      Extra attributes.
		 * }
		 * @return string
		 */
		public static function button_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::button' );
			$variant = isset( $args['variant'] ) ? (string) $args['variant'] : 'secondary';
			if ( ! in_array( $variant, self::$button_variants, true ) ) {
				self::doing_it_wrong( sprintf( 'Invalid button variant 「%s」.', $variant ), 'Wenpai_Admin_UI::button' );
				$variant = 'secondary';
			}
			$label = isset( $args['label'] ) ? (string) $args['label'] : '';
			$class = self::classes( array( 'btn', 'btn-' . $variant, self::extra_class( $args ) ) );
			$href  = isset( $args['href'] ) ? (string) $args['href'] : '';
			$skip  = array();
			$extra = '';
			if ( ! empty( $args['need_apply'] ) ) {
				$extra .= ' data-wenpai-need-apply';
			}
			if ( isset( $args['busy_label'] ) && '' !== (string) $args['busy_label'] ) {
				$extra .= ' data-wenpai-busy="' . esc_attr( (string) $args['busy_label'] ) . '"';
			}
			if ( '' !== $href ) {
				$target = isset( $args['target'] ) ? trim( (string) $args['target'] ) : '';
				if ( in_array( strtolower( $target ), array( '_blank', '_self', '_parent', '_top' ), true ) ) {
					$target = strtolower( $target );
				}
				if ( ! empty( $args['disabled'] ) ) {
					// No href, out of the Tab order: a disabled link must not navigate or take focus.
					// Styled by .btn[aria-disabled="true"]. Without permission, do not call button() at all.
					return '<a class="' . esc_attr( $class ) . '" role="link" aria-disabled="true" tabindex="-1"' . self::extra_attrs( $args, array( 'role', 'aria-disabled' ) ) . '>' . esc_html( $label ) . '</a>';
				}
				$html = '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $href ) . '"';
				if ( '' !== $target ) {
					$html .= ' target="' . esc_attr( $target ) . '"';
				}
				$hint = '';
				if ( '_blank' === $target ) {
					$html .= ' rel="noopener noreferrer"';
					$tab   = array_key_exists( 'new_tab_label', $args ) ? (string) $args['new_tab_label'] : '（在新标签页打开）';
					$hint  = '' === $tab ? '' : '<span class="screen-reader-text">' . esc_html( $tab ) . '</span>';
				}
				$html .= $extra . self::extra_attrs( $args, $skip ) . '>' . esc_html( $label ) . $hint . '</a>';
				return $html;
			}
			$type = isset( $args['type'] ) ? (string) $args['type'] : 'button';
			if ( ! in_array( $type, array( 'button', 'submit' ), true ) ) {
				self::doing_it_wrong( sprintf( 'Invalid button type 「%s」.', $type ), 'Wenpai_Admin_UI::button' );
				$type = 'button';
			}
			$html = '<button type="' . esc_attr( $type ) . '" class="' . esc_attr( $class ) . '"';
			if ( isset( $args['name'] ) && '' !== (string) $args['name'] ) {
				$html .= ' name="' . esc_attr( (string) $args['name'] ) . '"';
			}
			if ( array_key_exists( 'value', $args ) ) {
				$html .= ' value="' . esc_attr( (string) $args['value'] ) . '"';
			}
			if ( ! empty( $args['disabled'] ) ) {
				$html .= ' disabled';
			}
			$html .= $extra . self::extra_attrs( $args, $skip ) . '>' . esc_html( $label ) . '</button>';
			return $html;
		}

		/**
		 * Echo a confirmation gate.
		 *
		 * @param array $args Arguments.
		 */
		public static function gate( $args = array() ) {
			self::emit( self::gate_html( $args ) );
		}

		/**
		 * Return confirmation gate markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $name  Checkbox name.
		 *     @type string $label Confirmation sentence.
		 *     @type string $note  Optional p.meta after the gate.
		 *     @type string $text_id Wraps the label text in <span id>; point the button's
		 *                           aria-describedby here (settings-0.3.0.md §8).
		 *     @type string $id    Optional id on the checkbox itself.
		 *     @type string $class Extra classes on .wenpai-gate.
		 *     @type array  $attrs Extra attributes on the checkbox.
		 * }
		 * @return string
		 */
		public static function gate_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::gate' );
			$name  = isset( $args['name'] ) ? (string) $args['name'] : '';
			$label = isset( $args['label'] ) ? (string) $args['label'] : '';
			$id    = isset( $args['id'] ) ? (string) $args['id'] : '';
			$class = self::classes( array( 'wenpai-gate', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"><label><input type="checkbox"';
			if ( '' !== $name ) {
				$html .= ' name="' . esc_attr( $name ) . '"';
			}
			$html .= ' value="1"';
			if ( '' !== $id ) {
				$html .= ' id="' . esc_attr( $id ) . '"';
			}
			$text = esc_html( $label );
			if ( isset( $args['text_id'] ) && '' !== (string) $args['text_id'] ) {
				$text = '<span id="' . esc_attr( (string) $args['text_id'] ) . '">' . $text . '</span>';
			}
			$html .= self::extra_attrs( $args, array( 'id' ) ) . '> ' . $text . '</label></div>';
			if ( isset( $args['note'] ) && '' !== (string) $args['note'] ) {
				$html .= '<p class="meta">' . esc_html( (string) $args['note'] ) . '</p>';
			}
			return $html;
		}

		/**
		 * Echo a simple/advanced switch.
		 *
		 * @param array $args Arguments.
		 */
		public static function mode_switch( $args = array() ) {
			self::emit( self::mode_switch_html( $args ) );
		}

		/**
		 * Return mode switch markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items array of array( label, href, current ).
		 *     @type string $icon  Optional icon before .seg.
		 *     @type string $label aria-label; also adds role="group" so it is announced.
		 *     @type bool   $wrap  Output outer .mode. Default true.
		 *     @type string $class Extra classes on .mode (or .seg when wrap is false).
		 *     @type array  $attrs Extra attributes on that element.
		 * }
		 * @return string
		 */
		public static function mode_switch_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::mode_switch' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$links = '';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$row = self::item_kv( $item, array( 'label', 'href', 'current' ) );
				if ( '' === (string) $row['href'] ) {
					self::doing_it_wrong( 'mode_switch item needs href.', 'Wenpai_Admin_UI::mode_switch' );
					continue;
				}
				$on     = ! empty( $row['current'] );
				$links .= '<a href="' . esc_url( (string) $row['href'] ) . '"';
				if ( $on ) {
					$links .= ' class="on" aria-current="page"';
				}
				$links .= '>' . esc_html( (string) $row['label'] ) . '</a>';
			}
			$icon = '';
			if ( isset( $args['icon'] ) && '' !== (string) $args['icon'] ) {
				$icon = self::icon_svg( (string) $args['icon'] );
			}
			$aria = '';
			$skip = array();
			if ( isset( $args['label'] ) && '' !== (string) $args['label'] ) {
				// aria-label is ignored on a role-less div/span; group makes screen readers announce it.
				$aria = ' role="group" aria-label="' . esc_attr( (string) $args['label'] ) . '"';
				$skip = array( 'role', 'aria-label' );
			}
			$wrap = ! array_key_exists( 'wrap', $args ) || $args['wrap'];
			if ( ! $wrap ) {
				$class = self::classes( array( 'seg', self::extra_class( $args ) ) );
				return $icon . '<span class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, $skip ) . '>' . $links . '</span>';
			}
			$class = self::classes( array( 'mode', self::extra_class( $args ) ) );
			return '<div class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, $skip ) . '>' . $icon . '<span class="seg">' . $links . '</span></div>';
		}

		/**
		 * Current display mode from ?mode= (0.9). Only 「simple」 and
		 * 「advanced」 exist; anything else (or no param) is 「simple」.
		 *
		 * @return string 'simple'|'advanced'
		 */
		public static function mode() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$mode = isset( $_GET['mode'] ) ? sanitize_key( (string) wp_unslash( $_GET['mode'] ) ) : '';
			return 'advanced' === $mode ? 'advanced' : 'simple';
		}

		/**
		 * Attribute string for hand-written markup that belongs to one display
		 * mode (0.9). Always prints data-wenpai-mode; when $mode is not the
		 * current mode also prints data-wenpai-view-hidden + hidden, so the
		 * block stays hidden without JS and kit JS keeps it hidden when
		 * depends_on re-evaluates.
		 *
		 * Usage: <div class="x"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); ?>>
		 * The element still submits its inputs (hidden, not disabled).
		 *
		 * @param string $mode 'simple'|'advanced'.
		 * @return string
		 */
		public static function mode_attrs( $mode ) {
			$mode = 'advanced' === $mode ? 'advanced' : 'simple';
			$out  = ' data-wenpai-mode="' . $mode . '"';
			if ( self::mode() !== $mode ) {
				$out .= ' data-wenpai-view-hidden hidden';
			}
			return $out;
		}

		/**
		 * Resolve the current ?section= against a group's id list (0.9).
		 *
		 * With $groups (id => label map): an unknown or missing section falls
		 * back to $fallback when it is a valid id, else the first key.
		 * Without $groups: returns the sanitized raw section, or $fallback.
		 *
		 * @param array  $groups   Optional id => label map of valid sections.
		 * @param string $fallback Fallback section id.
		 * @return string
		 */
		public static function section( $groups = array(), $fallback = '' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = isset( $_GET['section'] ) ? sanitize_key( (string) wp_unslash( $_GET['section'] ) ) : '';
			if ( ! is_array( $groups ) || array() === $groups ) {
				return '' !== $raw ? $raw : (string) $fallback;
			}
			if ( isset( $groups[ $raw ] ) ) {
				return $raw;
			}
			if ( '' !== (string) $fallback && isset( $groups[ (string) $fallback ] ) ) {
				return (string) $fallback;
			}
			$ids = array_keys( $groups );
			return (string) reset( $ids );
		}

		/**
		 * Attribute string for one group panel inside a [data-wenpai-groups]
		 * container (0.9). Pass the section id resolved by section(); when it
		 * differs from $current the panel prints data-wenpai-view-hidden +
		 * hidden (hidden without JS, kept hidden by kit JS). An empty $current
		 * means "no constraint": the group attr prints without hiding.
		 *
		 * Usage:
		 *   <div data-wenpai-groups data-wenpai-groups-default="connect">
		 *     <div class="x"<?php echo Wenpai_Admin_UI::group_attrs( 'connect', $section ); ?>>…</div>
		 *   </div>
		 * Pair with subnav_html( array( 'slug' => …, 'current' => $section ) )
		 * or explicit item urls carrying ?section=id.
		 *
		 * @param string $id      Group id (sanitized).
		 * @param string $current Current section id from section().
		 * @return string
		 */
		public static function group_attrs( $id, $current ) {
			$id      = sanitize_key( (string) $id );
			$current = sanitize_key( (string) $current );
			$out     = ' data-wenpai-group="' . esc_attr( $id ) . '"';
			if ( '' !== $current && $current !== $id ) {
				$out .= ' data-wenpai-view-hidden hidden';
			}
			return $out;
		}

		/**
		 * Echo in-tab secondary navigation (D-24 / Track A).
		 *
		 * Real links with ?section= (via Menu::url when slug is set). Not role=tablist.
		 *
		 * @param array $args Arguments.
		 */
		public static function subnav( $args = array() ) {
			self::emit( self::subnav_html( $args ) );
		}

		/**
		 * Return in-tab secondary navigation markup.
		 *
		 * Shell tabs stay ≤4; conditional modules (e.g. 7) use this .seg chain under the active tab.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items   array of array( id, label, url|href?, badge?, current? ). Positional id/label/url ok.
		 *                           badge: int count or array( count, label ) for a screen-reader suffix (0.10).
		 *                           When url/href empty and $slug is set, builds Menu::url( $slug, query+section ).
		 *     @type string $current Current section id. Also honors item['current'].
		 *     @type string $slug    Optional page slug for Wenpai_Admin_Menu::url.
		 *     @type array  $query   Extra query args preserved on each Menu::url link (e.g. tab).
		 *     @type string $label   nav aria-label. Default 「二级导航」; '' prints none.
		 *     @type string $class   Extra classes on nav.wenpai-subnav.
		 *     @type array  $attrs   Extra attributes on nav.
		 * }
		 * @return string
		 */
		public static function subnav_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::subnav' );
			$items   = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$current = isset( $args['current'] ) ? (string) $args['current'] : '';
			$slug    = isset( $args['slug'] ) ? sanitize_key( (string) $args['slug'] ) : '';
			$query   = isset( $args['query'] ) && is_array( $args['query'] ) ? $args['query'] : array();
			$links   = '';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$row = self::item_kv( $item, array( 'id', 'label', 'url' ) );
				if ( '' === (string) $row['url'] && array_key_exists( 'href', $item ) ) {
					$row['url'] = $item['href'];
				}
				$id  = sanitize_key( (string) $row['id'] );
				$url = (string) $row['url'];
				if ( '' === $url ) {
					if ( '' === $slug || '' === $id || ! class_exists( 'Wenpai_Admin_Menu', false ) ) {
						self::doing_it_wrong( 'subnav item needs url/href, or slug+id with Wenpai_Admin_Menu.', 'Wenpai_Admin_UI::subnav' );
						continue;
					}
					$url = Wenpai_Admin_Menu::url( $slug, array_merge( $query, array( 'section' => $id ) ) );
				}
				$on     = ! empty( $item['current'] ) || ( '' !== $current && sanitize_key( $current ) === $id );
				$links .= '<a href="' . esc_url( $url ) . '"';
				if ( $on ) {
					$links .= ' class="on" aria-current="page"';
				}
				$links .= '>' . esc_html( (string) $row['label'] );
				// 0.10: optional attention badge (e.g. per-tab error count from
				// Wenpai_Admin_Settings::visible_tabs()['errors']). `badge` is an
				// int, or array( 'count' => n, 'label' => screen-reader text ).
				$badge = isset( $item['badge'] ) ? $item['badge'] : 0;
				$bn    = is_array( $badge ) ? ( isset( $badge['count'] ) ? (int) $badge['count'] : 0 ) : (int) $badge;
				if ( $bn > 0 ) {
					$blabel = is_array( $badge ) && isset( $badge['label'] ) ? (string) $badge['label'] : '';
					$links .= ' <span class="wenpai-subnav__badge" aria-hidden="true">' . $bn . '</span>';
					if ( '' !== $blabel ) {
						$links .= '<span class="screen-reader-text"> ' . esc_html( $blabel ) . '</span>';
					}
				}
				$links .= '</a>';
			}
			$class = self::classes( array( 'wenpai-subnav', self::extra_class( $args ) ) );
			$aria  = '';
			$skip  = array();
			if ( ! array_key_exists( 'label', $args ) ) {
				$args['label'] = '二级导航';
			}
			if ( '' !== (string) $args['label'] ) {
				$aria = ' aria-label="' . esc_attr( (string) $args['label'] ) . '"';
				$skip = array( 'aria-label' );
			}
			return '<nav class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, $skip ) . '><span class="seg">' . $links . '</span></nav>';
		}

		/**
		 * Echo wizard steps.
		 *
		 * @param array $args Arguments.
		 */
		public static function steps( $args = array() ) {
			self::emit( self::steps_html( $args ) );
		}

		/**
		 * Return wizard steps markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items   Labels.
		 *     @type int    $current 0-based current index.
		 *     @type string $label   nav aria-label. Default 「设置向导步骤」; '' prints none.
		 *     @type string $done_label Screen-reader text after a done step's ✓ (aria-hidden).
		 *                              Default 「已完成」; '' prints none.
		 *     @type string $class   Extra classes.
		 *     @type array  $attrs   Extra attributes.
		 * }
		 * @return string
		 */
		public static function steps_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::steps' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? array_values( $args['items'] ) : array();
			$n     = count( $items );
			$cur   = isset( $args['current'] ) ? (int) $args['current'] : 0;
			if ( $n > 0 && ( $cur < 0 || $cur >= $n ) ) {
				self::doing_it_wrong( 'steps current is out of range.', 'Wenpai_Admin_UI::steps' );
				$cur = max( 0, min( $n - 1, $cur ) );
			}
			$class = self::classes( array( 'steps', self::extra_class( $args ) ) );
			$name  = array_key_exists( 'label', $args ) ? (string) $args['label'] : '设置向导步骤';
			$aria  = '' !== $name ? ' aria-label="' . esc_attr( $name ) . '"' : '';
			$done  = array_key_exists( 'done_label', $args ) ? (string) $args['done_label'] : '已完成';
			$tick  = '<span aria-hidden="true">✓</span>' . ( '' !== $done ? '<span class="screen-reader-text">' . esc_html( $done ) . '</span>' : '' );
			$html  = '<nav class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, array( 'aria-label' ) ) . '>';
			foreach ( $items as $i => $item ) {
				if ( $i > 0 ) {
					$html .= '<span class="bar" aria-hidden="true"></span>';
				}
				$label = is_array( $item ) ? (string) ( isset( $item['label'] ) ? $item['label'] : ( isset( $item[0] ) ? $item[0] : '' ) ) : (string) $item;
				$st    = 'st';
				$num   = esc_html( (string) ( $i + 1 ) );
				$now   = '';
				if ( $i < $cur ) {
					$st .= ' done';
					$num = $tick;
				} elseif ( $i === $cur ) {
					$st .= ' cur';
					$now = ' aria-current="step"';
				}
				$html .= '<span class="' . esc_attr( $st ) . '"' . $now . '><span class="n">' . $num . '</span><span>' . esc_html( $label ) . '</span></span>';
			}
			return $html . '</nav>';
		}

		/**
		 * Echo a table.
		 *
		 * @param array $args Arguments.
		 */
		public static function table( $args = array() ) {
			self::emit( self::table_html( $args ) );
		}

		/**
		 * Return table markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $columns key => label, or key => array( label, num ).
		 *     @type array  $rows    Row maps keyed by column key. A cell may be
		 *                           array( 'html' => … ) for rich content (pill,
		 *                           buttons, row action forms; kses control).
		 *     @type string $caption Caption; screen-reader only by default.
		 *     @type bool   $caption_visible Show the caption on screen (components.md 表格).
		 *     @type array  $empty   empty_state() args when there are no rows.
		 *     @type string $class   Extra classes.
		 *     @type array  $attrs   Extra attributes.
		 * }
		 * @return string
		 */
		public static function table_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::table' );
			$rows = isset( $args['rows'] ) && is_array( $args['rows'] ) ? $args['rows'] : array();
			if ( array() === $rows && isset( $args['empty'] ) ) {
				$empty = $args['empty'];
				return self::empty_state_html( is_array( $empty ) ? $empty : array( 'text' => (string) $empty ) );
			}
			$columns = isset( $args['columns'] ) && is_array( $args['columns'] ) ? $args['columns'] : array();
			$cols    = array();
			foreach ( $columns as $key => $col ) {
				if ( is_array( $col ) ) {
					$kv     = self::item_kv( $col, array( 'label', 'num' ) );
					$cols[] = array(
						'key'   => (string) $key,
						'label' => (string) $kv['label'],
						'num'   => ! empty( $kv['num'] ),
					);
				} else {
					$cols[] = array(
						'key'   => (string) $key,
						'label' => (string) $col,
						'num'   => false,
					);
				}
			}
			$class = self::classes( array( 'tbl', self::extra_class( $args ) ) );
			$html  = '<table class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			if ( isset( $args['caption'] ) && '' !== (string) $args['caption'] ) {
				$cap   = empty( $args['caption_visible'] ) ? ' class="screen-reader-text"' : '';
				$html .= '<caption' . $cap . '>' . esc_html( (string) $args['caption'] ) . '</caption>';
			}
			$html .= '<thead><tr>';
			foreach ( $cols as $col ) {
				$html .= $col['num']
					? '<th scope="col" class="num">' . esc_html( $col['label'] ) . '</th>'
					: '<th scope="col">' . esc_html( $col['label'] ) . '</th>';
			}
			$html .= '</tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$row   = is_array( $row ) ? $row : array();
				$html .= '<tr>';
				foreach ( $cols as $col ) {
					$cell = array_key_exists( $col['key'], $row ) ? $row[ $col['key'] ] : '';
					if ( is_array( $cell ) && isset( $cell['html'] ) ) {
						// Rich cell (0.7): pills, buttons, row action forms. kses control keeps form controls.
						$cell  = self::kses( (string) $cell['html'], 'control' );
						$html .= $col['num']
							? '<td class="num">' . $cell . '</td>'
							: '<td>' . $cell . '</td>';
						continue;
					}
					$cell  = is_scalar( $cell ) ? (string) $cell : '';
					$html .= $col['num']
						? '<td class="num">' . esc_html( $cell ) . '</td>'
						: '<td>' . esc_html( $cell ) . '</td>';
				}
				$html .= '</tr>';
			}
			return $html . '</tbody></table>';
		}

		/**
		 * Echo a WP_List_Table inside the kit shell (Track 0.4-list · D-22).
		 *
		 * Does not rewrite WP_List_Table. Skin only.
		 *
		 * @param object $table Table with display().
		 * @param array  $args  Arguments.
		 */
		public static function list_table( $table, $args = array() ) {
			self::emit( self::list_table_html( $table, $args ) );
		}

		/**
		 * Return WP_List_Table wrap markup.
		 *
		 * Render views() + search_box() + display() inside one GET form with hidden page.
		 * Pagination / bulk / sort / screen options stay WP core.
		 *
		 * @param object $table Table implementing display() (WP_List_Table).
		 * @param array  $args {
		 *     Arguments for the list-table wrapper.
		 *     @type bool   $search       Default true.
		 *     @type bool   $views        Default true.
		 *     @type string $page         Hidden input name=page.
		 *     @type string $search_label search_box() text.
		 *     @type string $search_id    search_box() input id.
		 *     @type array  $hidden       Extra hidden GET fields.
		 *     @type string $class        Extra classes on .list-table.
		 *     @type array  $attrs        Extra attributes.
		 * }
		 * @return string
		 */
		public static function list_table_html( $table, $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::list_table' );
			if ( ! is_object( $table ) || ! method_exists( $table, 'display' ) ) {
				self::doing_it_wrong( 'table must implement display() (WP_List_Table). Do not rewrite List Table.', 'Wenpai_Admin_UI::list_table' );
				return '';
			}
			$search = ! isset( $args['search'] ) || $args['search'];
			$views  = ! isset( $args['views'] ) || $args['views'];
			$page   = isset( $args['page'] ) ? (string) $args['page'] : '';
			$class  = self::classes( array( 'list-table', self::extra_class( $args ) ) );
			ob_start();
			echo '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- class and attributes are escaped by their helpers.
			echo '<form method="get">';
			if ( '' !== $page ) {
				echo '<input type="hidden" name="page" value="' . esc_attr( $page ) . '" />';
			}
			if ( isset( $args['hidden'] ) && is_array( $args['hidden'] ) ) {
				foreach ( $args['hidden'] as $hk => $hv ) {
					if ( ! is_scalar( $hv ) ) {
						continue;
					}
					echo '<input type="hidden" name="' . esc_attr( (string) $hk ) . '" value="' . esc_attr( (string) $hv ) . '" />';
				}
			}
			if ( $views && method_exists( $table, 'views' ) ) {
				$table->views();
			}
			if ( $search && method_exists( $table, 'search_box' ) ) {
				$label = isset( $args['search_label'] ) ? (string) $args['search_label'] : '搜索';
				$sid   = isset( $args['search_id'] ) ? (string) $args['search_id'] : 'wenpai-list';
				$table->search_box( $label, $sid );
			}
			$table->display();
			echo '</form></div>';
			return ob_get_clean();
		}

		/**
		 * Echo a lightweight list filter bar (0.7).
		 *
		 * @param array $args Arguments.
		 */
		public static function list_bar( $args = array() ) {
			self::emit( self::list_bar_html( $args ) );
		}

		/**
		 * Return list filter bar markup: a GET form with filter selects, hidden
		 * params and an optional count. Kit JS submits the form on select change;
		 * a noscript submit button covers no-JS.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $filters array of array( name, label, options, current?, attrs? ).
		 *                           options: value => label map, or a list of
		 *                           array( value, label ) / plain strings.
		 *     @type array  $hidden  Extra hidden GET fields (name => scalar value).
		 *     @type string $page    Hidden input name=page.
		 *     @type string $action  Form action URL (default: current URL).
		 *     @type string $count   Count text, e.g. 「5 条」.
		 *     @type string $count_html Count HTML (kses post), wins over $count.
		 *     @type string $submit_label noscript submit label. Default 「筛选」.
		 *     @type string $class   Extra classes on the form.
		 *     @type array  $attrs   Extra attributes on the form.
		 * }
		 * @return string
		 */
		public static function list_bar_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::list_bar' );
			$filters = isset( $args['filters'] ) && is_array( $args['filters'] ) ? $args['filters'] : array();
			$hidden  = isset( $args['hidden'] ) && is_array( $args['hidden'] ) ? $args['hidden'] : array();
			$page    = isset( $args['page'] ) ? (string) $args['page'] : '';
			$action  = isset( $args['action'] ) ? (string) $args['action'] : '';
			$submit  = isset( $args['submit_label'] ) && '' !== (string) $args['submit_label'] ? (string) $args['submit_label'] : '筛选';
			$class   = self::classes( array( 'wenpai-list-bar', self::extra_class( $args ) ) );
			$html    = '<form class="' . esc_attr( $class ) . '" method="get" action="' . esc_url( $action ) . '"' . self::extra_attrs( $args ) . '>';
			if ( '' !== $page ) {
				$html .= '<input type="hidden" name="page" value="' . esc_attr( $page ) . '" />';
			}
			foreach ( $hidden as $hk => $hv ) {
				if ( is_scalar( $hv ) ) {
					$html .= '<input type="hidden" name="' . esc_attr( (string) $hk ) . '" value="' . esc_attr( (string) $hv ) . '" />';
				}
			}
			foreach ( $filters as $filter ) {
				if ( ! is_array( $filter ) || empty( $filter['name'] ) ) {
					continue;
				}
				$name    = (string) $filter['name'];
				$label   = isset( $filter['label'] ) ? (string) $filter['label'] : $name;
				$options = isset( $filter['options'] ) && is_array( $filter['options'] ) ? $filter['options'] : array();
				$current = isset( $filter['current'] ) ? (string) $filter['current'] : '';
				$fattrs  = isset( $filter['attrs'] ) && is_array( $filter['attrs'] ) ? $filter['attrs'] : array();
				$sattrs  = '';
				foreach ( $fattrs as $an => $av ) {
					$an = strtolower( (string) $an );
					if ( 0 === strpos( $an, 'data-' ) || 0 === strpos( $an, 'aria-' ) ) {
						$sattrs .= ' ' . $an . '="' . esc_attr( is_scalar( $av ) ? (string) $av : '' ) . '"';
					}
				}
				$html .= '<span class="wenpai-filter">'
					. '<label class="screen-reader-text" for="' . esc_attr( 'wenpai-filter-' . $name ) . '">' . esc_html( $label ) . '</label>'
					. '<select id="' . esc_attr( 'wenpai-filter-' . $name ) . '" name="' . esc_attr( $name ) . '"' . $sattrs . '>';
				foreach ( $options as $ok => $ol ) {
					$pair  = is_array( $ol )
						? self::item_kv( $ol, array( 'value', 'label' ) )
						: array(
							'value' => $ok,
							'label' => $ol,
						);
					$oval  = is_scalar( $pair['value'] ) ? (string) $pair['value'] : '';
					$html .= '<option value="' . esc_attr( $oval ) . '"' . selected( $current, $oval, false ) . '>' . esc_html( is_scalar( $pair['label'] ) ? (string) $pair['label'] : $oval ) . '</option>';
				}
				$html .= '</select></span>';
			}
			if ( isset( $args['count_html'] ) && '' !== (string) $args['count_html'] ) {
				$html .= '<span class="wenpai-list-bar__count">' . self::kses( (string) $args['count_html'], 'post' ) . '</span>';
			} elseif ( isset( $args['count'] ) && '' !== (string) $args['count'] ) {
				$html .= '<span class="wenpai-list-bar__count">' . esc_html( (string) $args['count'] ) . '</span>';
			}
			$html .= '<noscript><button type="submit" class="btn btn-ghost">' . esc_html( $submit ) . '</button></noscript>';
			return $html . '</form>';
		}

		/**
		 * Echo a table row-actions cluster (0.7).
		 *
		 * @param array $args Arguments.
		 */
		public static function row_actions( $args = array() ) {
			self::emit( self::row_actions_html( $args ) );
		}

		/**
		 * Return row-actions cluster markup for a table cell.
		 *
		 * Each item is a button_html() argument array, an array with 'html'
		 * (kses control: forms and inputs allowed), or a raw HTML string
		 * (kses control).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array $items Action items.
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function row_actions_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::row_actions' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$html  = '';
			foreach ( $items as $item ) {
				if ( is_string( $item ) ) {
					$html .= self::kses( $item, 'control' );
				} elseif ( is_array( $item ) ) {
					if ( isset( $item['html'] ) ) {
						$html .= self::kses( (string) $item['html'], 'control' );
					} else {
						$html .= self::button_html( $item );
					}
				}
			}
			if ( '' === $html ) {
				return '';
			}
			$class = self::classes( array( 'wenpai-row-actions', self::extra_class( $args ) ) );
			return '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . $html . '</div>';
		}

		/**
		 * Echo a suggest dropdown box (0.7).
		 *
		 * @param array $args Arguments.
		 */
		public static function suggest_out( $args = array() ) {
			self::emit( self::suggest_out_html( $args ) );
		}

		/**
		 * Return suggest dropdown markup. The product owns the input and the
		 * fetch; this is the result slot. Kit JS moves .is-active with
		 * ArrowUp/ArrowDown and activates the item on Enter.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items array of array( label, meta?, html?, active? ).
		 *                        'html' items pass kses control.
		 *     @type string $empty Empty-state text (shown when no items).
		 *     @type string $empty_html Empty-state HTML (kses post), wins over $empty.
		 *     @type bool   $hidden Start hidden (default false).
		 *     @type string $id    Element id (for aria-controls).
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function suggest_out_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::suggest_out' );
			$items  = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$hid    = empty( $args['hidden'] ) ? '' : ' hidden';
			$idattr = self::id_attr( $args, 'id' );
			$class  = self::classes( array( 'wenpai-suggest__out', self::extra_class( $args ) ) );
			$html   = '<div class="' . esc_attr( $class ) . '"' . $idattr . $hid . ' aria-live="polite"' . self::extra_attrs( $args, array( 'id' ) ) . '>';
			$has    = false;
			$html  .= '<ul class="wenpai-suggest__list">';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$has    = true;
				$active = ! empty( $item['active'] ) ? ' class="is-active"' : '';
				$html  .= '<li' . $active . '>';
				if ( isset( $item['html'] ) ) {
					$html .= self::kses( (string) $item['html'], 'control' );
				} else {
					$label = isset( $item['label'] ) ? (string) $item['label'] : '';
					$meta  = isset( $item['meta'] ) ? (string) $item['meta'] : '';
					$html .= '<button type="button" class="wenpai-suggest__item">' . esc_html( $label );
					if ( '' !== $meta ) {
						$html .= '<span class="wenpai-suggest__meta">' . esc_html( $meta ) . '</span>';
					}
					$html .= '</button>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
			if ( ! $has ) {
				if ( isset( $args['empty_html'] ) && '' !== (string) $args['empty_html'] ) {
					$html .= '<p class="wenpai-suggest__empty">' . self::kses( (string) $args['empty_html'], 'post' ) . '</p>';
				} elseif ( isset( $args['empty'] ) && '' !== (string) $args['empty'] ) {
					$html .= '<p class="wenpai-suggest__empty">' . esc_html( (string) $args['empty'] ) . '</p>';
				}
			}
			return $html . '</div>';
		}

		/**
		 * Echo a preview output block (0.7).
		 *
		 * @param array $args Arguments.
		 */
		public static function preview( $args = array() ) {
			self::emit( self::preview_html( $args ) );
		}

		/**
		 * Return preview output markup: key/value rows (or a single text/html line) inside a state frame.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $state '', 'ok' or 'bad'.
		 *     @type array  $rows  array of array( k, v, v_html? ). v_html is kses post.
		 *     @type string $text  Single-line content (escaped). Wins over $rows.
		 *     @type string $html  Single-line content, kses post. Wins over $rows.
		 *     @type string $variant '', 'line' (dashed frame, no fill; for single-value previews).
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function preview_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::preview' );
			$state = isset( $args['state'] ) ? (string) $args['state'] : '';
			if ( ! in_array( $state, array( '', 'ok', 'bad' ), true ) ) {
				self::doing_it_wrong( 'preview state must be ok or bad.', 'Wenpai_Admin_UI::preview' );
				$state = '';
			}
			$rows    = isset( $args['rows'] ) && is_array( $args['rows'] ) ? $args['rows'] : array();
			$variant = isset( $args['variant'] ) ? (string) $args['variant'] : '';
			if ( ! in_array( $variant, array( '', 'line' ), true ) ) {
				self::doing_it_wrong( 'preview variant must be line.', 'Wenpai_Admin_UI::preview' );
				$variant = '';
			}
			$class = self::classes( array( 'wenpai-preview', 'line' === $variant ? 'wenpai-preview--line' : '', '' !== $state ? 'is-' . $state : '', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '" aria-live="polite"' . self::extra_attrs( $args ) . '>';
			if ( isset( $args['html'] ) && '' !== (string) $args['html'] ) {
				$html .= '<p class="wenpai-preview__text">' . self::kses( (string) $args['html'], 'post' ) . '</p>';
			} elseif ( isset( $args['text'] ) && '' !== (string) $args['text'] ) {
				$html .= '<p class="wenpai-preview__text">' . esc_html( (string) $args['text'] ) . '</p>';
			} else {
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$kv    = self::item_kv( $row, array( 'k', 'v' ) );
					$html .= '<div class="wenpai-preview__row"><span class="wenpai-preview__k">' . esc_html( (string) $kv['k'] ) . '</span><span class="wenpai-preview__v">';
					if ( isset( $row['v_html'] ) && '' !== (string) $row['v_html'] ) {
						$html .= self::kses( (string) $row['v_html'], 'post' );
					} else {
						$html .= esc_html( (string) $kv['v'] );
					}
					$html .= '</span></div>';
				}
			}
			return $html . '</div>';
		}

		/**
		 * Echo a help page skeleton (0.7).
		 *
		 * @param array $args Arguments.
		 */
		public static function help( $args = array() ) {
			self::emit( self::help_html( $args ) );
		}

		/**
		 * Return help page markup: titled groups of numbered steps plus links.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $groups array of array( title, numbered?, steps ).
		 *                        steps: array of array( title, body?, body_html?, no? ).
		 *                        Steps auto-number inside a numbered group;
		 *                        'no' overrides, numbered=false prints 「·」.
		 *     @type array  $links  array of array( label, href ) or strings (label only).
		 *     @type string $class  Extra classes.
		 *     @type array  $attrs  Extra attributes.
		 * }
		 * @return string
		 */
		public static function help_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::help' );
			$groups = isset( $args['groups'] ) && is_array( $args['groups'] ) ? $args['groups'] : array();
			$class  = self::classes( array( 'wenpai-help', self::extra_class( $args ) ) );
			$html   = '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $groups as $group ) {
				if ( ! is_array( $group ) ) {
					continue;
				}
				$title    = isset( $group['title'] ) ? (string) $group['title'] : '';
				$numbered = ! isset( $group['numbered'] ) || $group['numbered'];
				$steps    = isset( $group['steps'] ) && is_array( $group['steps'] ) ? $group['steps'] : array();
				$html    .= '<div class="wenpai-help__group">';
				if ( '' !== $title ) {
					$html .= '<h3>' . esc_html( $title ) . '</h3>';
				}
				$i = 0;
				foreach ( $steps as $step ) {
					if ( ! is_array( $step ) ) {
						continue;
					}
					++$i;
					$no = $numbered ? (string) $i : '·';
					if ( isset( $step['no'] ) && '' !== (string) $step['no'] ) {
						$no = (string) $step['no'];
					}
					$html .= '<div class="wenpai-help__step"><span class="wenpai-help__no">' . esc_html( $no ) . '</span><div class="wenpai-help__body">';
					if ( isset( $step['title'] ) && '' !== (string) $step['title'] ) {
						$html .= '<h4>' . esc_html( (string) $step['title'] ) . '</h4>';
					}
					if ( isset( $step['body_html'] ) && '' !== (string) $step['body_html'] ) {
						$html .= self::kses( (string) $step['body_html'], 'post' );
					} elseif ( isset( $step['body'] ) && '' !== (string) $step['body'] ) {
						$html .= '<p>' . esc_html( (string) $step['body'] ) . '</p>';
					}
					$html .= '</div></div>';
				}
				$html .= '</div>';
			}
			$links = isset( $args['links'] ) && is_array( $args['links'] ) ? $args['links'] : array();
			if ( array() !== $links ) {
				$html .= '<div class="wenpai-help__links">';
				foreach ( $links as $link ) {
					if ( is_string( $link ) ) {
						$html .= '<span>' . esc_html( $link ) . '</span>';
						continue;
					}
					if ( ! is_array( $link ) || empty( $link['href'] ) ) {
						continue;
					}
					$html .= '<a href="' . esc_url( (string) $link['href'] ) . '">' . esc_html( isset( $link['label'] ) ? (string) $link['label'] : (string) $link['href'] ) . '</a>';
				}
				$html .= '</div>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo a copy-to-clipboard button (0.8).
		 *
		 * @param array $args Arguments.
		 */
		public static function copy_button( $args = array() ) {
			self::emit( self::copy_button_html( $args ) );
		}

		/**
		 * Return copy button markup. Kit JS handles the click: it writes
		 * data-wenpai-copy-text verbatim, or reads textContent/value from the
		 * data-wenpai-copy-target selector. A target starting with "self:"
		 * resolves inside the closest .wenpai-code ancestor (code_block uses
		 * "self:code").
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $text       Literal text to copy (data-wenpai-copy-text).
		 *     @type string $target     CSS selector to copy from (data-wenpai-copy-target).
		 *                              text and target together → doing_it_wrong, text wins.
		 *     @type string $label      Button text and data-label. Default 「复制」.
		 *     @type string $done_label data-done-label while .is-done. Default 「已复制」.
		 *     @type string $variant    primary|secondary|ghost|danger|'' (default secondary).
		 *     @type string $class      Extra classes.
		 *     @type array  $attrs      Extra attributes.
		 * }
		 * @return string
		 */
		public static function copy_button_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::copy_button' );
			$text   = isset( $args['text'] ) ? (string) $args['text'] : '';
			$target = isset( $args['target'] ) ? trim( (string) $args['target'] ) : '';
			if ( '' !== $text && '' !== $target ) {
				self::doing_it_wrong( 'copy_button text and target cannot be combined; text wins.', 'Wenpai_Admin_UI::copy_button' );
				$target = '';
			}
			if ( '' === $text && '' === $target ) {
				self::doing_it_wrong( 'copy_button needs text or target.', 'Wenpai_Admin_UI::copy_button' );
			}
			$variant = isset( $args['variant'] ) ? (string) $args['variant'] : '';
			if ( '' === $variant ) {
				$variant = 'secondary';
			}
			if ( ! in_array( $variant, self::$button_variants, true ) ) {
				self::doing_it_wrong( sprintf( 'Invalid button variant 「%s」.', $variant ), 'Wenpai_Admin_UI::copy_button' );
				$variant = 'secondary';
			}
			$label = isset( $args['label'] ) && '' !== (string) $args['label'] ? (string) $args['label'] : '复制';
			$done  = isset( $args['done_label'] ) ? (string) $args['done_label'] : '已复制';
			$class = self::classes( array( 'btn', 'btn-' . $variant, 'wenpai-copy', self::extra_class( $args ) ) );
			$html  = '<button type="button" class="' . esc_attr( $class ) . '"';
			if ( '' !== $text ) {
				$html .= ' data-wenpai-copy-text="' . esc_attr( $text ) . '"';
			} elseif ( '' !== $target ) {
				$html .= ' data-wenpai-copy-target="' . esc_attr( $target ) . '"';
			}
			$html .= ' data-label="' . esc_attr( $label ) . '" data-done-label="' . esc_attr( $done ) . '"';
			$html .= self::extra_attrs( $args ) . '>' . esc_html( $label ) . '</button>';
			return $html;
		}

		/**
		 * Echo a code block with optional language tag and copy button (0.8).
		 *
		 * @param array $args Arguments.
		 */
		public static function code_block( $args = array() ) {
			self::emit( self::code_block_html( $args ) );
		}

		/**
		 * Return code block markup.
		 *
		 * The code text is esc_html'd as a whole: it is code content, not HTML,
		 * so kses does not apply. lang feeds a language-{lang} class (sanitized)
		 * and the bar label.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $code       Code content (required; missing → doing_it_wrong + '').
		 *     @type string $lang       Language name, e.g. php. Adds .wenpai-code__lang
		 *                              and class="language-{lang}" on <code>.
		 *     @type bool   $copy       Add a copy_button targeting "self:code". Default true.
		 *     @type int    $max_height Max height in px; adds inline max-height + overflow.
		 *     @type string $class       Extra classes on .wenpai-code.
		 *     @type array  $attrs       Extra attributes on .wenpai-code.
		 * }
		 * @return string
		 */
		public static function code_block_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::code_block' );
			if ( ! array_key_exists( 'code', $args ) ) {
				self::doing_it_wrong( 'code_block needs code.', 'Wenpai_Admin_UI::code_block' );
				return '';
			}
			$lang = isset( $args['lang'] ) ? (string) $args['lang'] : '';
			$copy = ! array_key_exists( 'copy', $args ) || $args['copy'];
			$bar  = '';
			if ( '' !== $lang || $copy ) {
				$bar = '<div class="wenpai-code__bar"><span class="wenpai-code__lang">' . esc_html( $lang ) . '</span>';
				if ( $copy ) {
					$bar .= self::copy_button_html(
						array(
							'target'  => 'self:code',
							'variant' => 'ghost',
						)
					);
				}
				$bar .= '</div>';
			}
			$code_attr = '';
			if ( '' !== $lang ) {
				$code_attr = ' class="language-' . esc_attr( sanitize_html_class( $lang ) ) . '"';
			}
			$wrap = '<div class="' . esc_attr( self::classes( array( 'wenpai-code', self::extra_class( $args ) ) ) ) . '"';
			if ( isset( $args['max_height'] ) && (float) $args['max_height'] > 0 ) {
				$wrap .= ' style="max-height:' . esc_attr( (string) (int) $args['max_height'] ) . 'px;overflow:auto"';
			}
			$wrap .= self::extra_attrs( $args ) . '>';
			return $wrap . $bar . '<pre><code' . $code_attr . '>' . esc_html( (string) $args['code'] ) . '</code></pre></div>';
		}

		/**
		 * Echo a one-line code value with a copy button (0.9).
		 *
		 * For short inline values (webhook URL, key, command). Multi-line or
		 * language-tagged content belongs to code_block.
		 *
		 * @param array $args Arguments.
		 */
		public static function code_inline( $args = array() ) {
			self::emit( self::code_inline_html( $args ) );
		}

		/**
		 * Return one-line code + copy markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $code    Code content (required; missing → doing_it_wrong + '').
		 *     @type bool   $copy    Add a ghost copy_button with the literal text. Default true.
		 *     @type array  $button  Extra copy_button args (label, done_label, variant…);
		 *                           text is always the code value, variant defaults ghost.
		 *     @type string $class   Extra classes on the .wenpai-code-row wrapper.
		 *     @type array  $attrs   Extra attributes on the wrapper.
		 * }
		 * @return string
		 */
		public static function code_inline_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::code_inline' );
			if ( ! array_key_exists( 'code', $args ) ) {
				self::doing_it_wrong( 'code_inline needs code.', 'Wenpai_Admin_UI::code_inline' );
				return '';
			}
			$code = (string) $args['code'];
			$html = '<span class="' . esc_attr( self::classes( array( 'wenpai-code-row', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>'
				. '<code class="wenpai-code">' . esc_html( $code ) . '</code>';
			if ( ! array_key_exists( 'copy', $args ) || $args['copy'] ) {
				$btn         = isset( $args['button'] ) && is_array( $args['button'] ) ? $args['button'] : array();
				$btn         = array_merge( array( 'variant' => 'ghost' ), $btn );
				$btn['text'] = $code;
				$html       .= self::copy_button_html( $btn );
			}
			return $html . '</span>';
		}

		/**
		 * Echo a status list (0.8).
		 *
		 * @param array $args Arguments.
		 */
		public static function status_list( $args = array() ) {
			self::emit( self::status_list_html( $args ) );
		}

		/**
		 * Return status list markup: a <dl> of label/value rows with an
		 * optional state chip (.wenpai-status-list__state.is-{state}).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array $items array of array(
		 *                       'label'       => row label (dt),
		 *                       'value'       => row value (dd, escaped),
		 *                       'value_html'  => row value HTML (kses post), wins over value,
		 *                       'state'       => ''|ok|warn|bad|accent → is-{state} chip,
		 *                       'state_label' => chip text (default: the state token),
		 *                       'desc'        => optional .meta note inside dd,
		 *                     ).
		 *     @type string $class Extra classes on dl.
		 *     @type array  $attrs Extra attributes on dl.
		 * }
		 * @return string
		 */
		public static function status_list_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::status_list' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$html  = '<dl class="' . esc_attr( self::classes( array( 'wenpai-status-list', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$kv    = self::item_kv( $item, array( 'label', 'value', 'state' ) );
				$state = self::state( $kv['state'], array( '', 'ok', 'warn', 'bad', 'accent' ), '', 'Wenpai_Admin_UI::status_list' );
				$html .= '<div class="wenpai-status-list__row"><dt>' . esc_html( (string) $kv['label'] ) . '</dt><dd>';
				if ( isset( $item['value_html'] ) && '' !== (string) $item['value_html'] ) {
					$html .= self::kses( (string) $item['value_html'], 'post' );
				} else {
					$html .= esc_html( (string) $kv['value'] );
				}
				if ( '' !== $state ) {
					$stext = isset( $item['state_label'] ) && '' !== (string) $item['state_label'] ? (string) $item['state_label'] : $state;
					$html .= ' <span class="wenpai-status-list__state is-' . esc_attr( $state ) . '">' . esc_html( $stext ) . '</span>';
				}
				if ( isset( $item['desc'] ) && '' !== (string) $item['desc'] ) {
					$html .= ' <span class="meta">' . esc_html( (string) $item['desc'] ) . '</span>';
				}
				$html .= '</dd></div>';
			}
			return $html . '</dl>';
		}

		/**
		 * Echo a license / plan card (0.8).
		 *
		 * @param array $args Arguments.
		 */
		public static function license_card( $args = array() ) {
			self::emit( self::license_card_html( $args ) );
		}

		/**
		 * Return license card markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title        Plan name (h3 in .wenpai-license__head).
		 *     @type string $status       ''|ok|warn|bad → .wenpai-license__state.is-{status}.
		 *     @type string $status_label Chip text (default: the status token).
		 *     @type string $pitch        Marketing line (.wenpai-license__pitch).
		 *     @type array  $meta         Rows: label => value map, or a list of
		 *                                array( 'label', 'value', 'value_html'? ).
		 *                                value_html is kses post.
		 *     @type array  $actions      button() argument arrays (.wenpai-license__actions).
		 *     @type string $class        Extra classes on .wenpai-license.
		 *     @type array  $attrs        Extra attributes on .wenpai-license.
		 * }
		 * @return string
		 */
		public static function license_card_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::license_card' );
			$title  = isset( $args['title'] ) ? (string) $args['title'] : '';
			$status = isset( $args['status'] ) ? (string) $args['status'] : '';
			if ( ! in_array( $status, array( '', 'ok', 'warn', 'bad' ), true ) ) {
				self::doing_it_wrong( sprintf( 'Invalid license status 「%s」.', $status ), 'Wenpai_Admin_UI::license_card' );
				$status = '';
			}
			$meta  = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
			$class = self::classes( array( 'wenpai-license', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			if ( '' !== $title || '' !== $status ) {
				$html .= '<div class="wenpai-license__head">';
				if ( '' !== $title ) {
					$html .= '<h3>' . esc_html( $title ) . '</h3>';
				}
				if ( '' !== $status ) {
					$stext = isset( $args['status_label'] ) && '' !== (string) $args['status_label'] ? (string) $args['status_label'] : $status;
					$html .= '<span class="wenpai-license__state is-' . esc_attr( $status ) . '">' . esc_html( $stext ) . '</span>';
				}
				$html .= '</div>';
			}
			if ( isset( $args['pitch'] ) && '' !== (string) $args['pitch'] ) {
				$html .= '<p class="wenpai-license__pitch">' . esc_html( (string) $args['pitch'] ) . '</p>';
			}
			if ( array() !== $meta ) {
				$html .= '<dl class="wenpai-license__meta">';
				foreach ( $meta as $mk => $mv ) {
					if ( is_array( $mv ) ) {
						$row = self::item_kv( $mv, array( 'label', 'value' ) );
						$lab = '' !== (string) $row['label'] ? (string) $row['label'] : (string) $mk;
						$val = isset( $mv['value_html'] ) && '' !== (string) $mv['value_html']
							? self::kses( (string) $mv['value_html'], 'post' )
							: esc_html( (string) $row['value'] );
					} else {
						$lab = (string) $mk;
						$val = esc_html( (string) $mv );
					}
					$html .= '<div><dt>' . esc_html( $lab ) . '</dt><dd>' . $val . '</dd></div>';
				}
				$html .= '</dl>';
			}
			$actions = isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : array();
			$btns    = '';
			foreach ( $actions as $btn ) {
				if ( is_array( $btn ) ) {
					$btns .= self::button_html( $btn );
				}
			}
			if ( '' !== $btns ) {
				$html .= '<div class="wenpai-license__actions">' . $btns . '</div>';
			}
			return $html . '</div>';
		}

		/**
		 * Register a wp-admin dashboard widget (0.8).
		 *
		 * Wraps wp_add_dashboard_widget() on wp_dashboard_setup. The product's
		 * $callback prints the body; call dashboard_widget_html() inside it for
		 * the .wenpai-dash card. Repeated calls with the same $id are ignored
		 * (idempotent). Multisite: wp_dashboard_setup does not fire on the
		 * network dashboard (wp_network_dashboard_setup); products needing a
		 * network-admin widget hook that action themselves.
		 *
		 * @param string          $id       Widget id.
		 * @param string          $title    Widget title.
		 * @param callable        $callback Render callback.
		 * @param callable|string $args     Passed as wp_add_dashboard_widget()'s
		 *                                  fourth argument (control callback / args).
		 */
		public static function dashboard_widget( $id, $title, $callback, $args = array() ) {
			$id = (string) $id;
			if ( '' === $id ) {
				self::doing_it_wrong( 'dashboard_widget needs an id.', 'Wenpai_Admin_UI::dashboard_widget' );
				return;
			}
			if ( ! is_callable( $callback ) ) {
				self::doing_it_wrong( 'dashboard_widget callback must be callable.', 'Wenpai_Admin_UI::dashboard_widget' );
				return;
			}
			if ( isset( self::$dashboard_widgets[ $id ] ) ) {
				return;
			}
			self::$dashboard_widgets[ $id ] = true;
			add_action(
				'wp_dashboard_setup',
				function () use ( $id, $title, $callback, $args ) {
					if ( function_exists( 'wp_add_dashboard_widget' ) ) {
						wp_add_dashboard_widget( $id, $title, $callback, $args );
					}
				}
			);
		}

		/**
		 * Return dashboard widget body markup (.wenpai-dash card, 0.8).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title     Card title (h3 in .wenpai-dash__head).
		 *     @type string $desc      Description (.wenpai-dash__desc).
		 *     @type string $desc_html Description HTML (kses post), wins over desc.
		 *     @type array  $stats     array of array( 'num'|'value', 'label' ) →
		 *                             .wenpai-dash__stats > .wenpai-dash__stat (.n + .label).
		 *     @type string $body_html Body HTML (kses post).
		 *     @type string $class     Extra classes on .wenpai-dash.
		 *     @type array  $attrs     Extra attributes on .wenpai-dash.
		 * }
		 * @return string
		 */
		public static function dashboard_widget_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::dashboard_widget' );
			$title = isset( $args['title'] ) ? (string) $args['title'] : '';
			$desc  = '';
			if ( isset( $args['desc_html'] ) && '' !== (string) $args['desc_html'] ) {
				$desc = '<p class="wenpai-dash__desc">' . self::kses( (string) $args['desc_html'], 'post' ) . '</p>';
			} elseif ( isset( $args['desc'] ) && '' !== (string) $args['desc'] ) {
				$desc = '<p class="wenpai-dash__desc">' . esc_html( (string) $args['desc'] ) . '</p>';
			}
			$html = '<div class="' . esc_attr( self::classes( array( 'wenpai-dash', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>';
			if ( '' !== $title || '' !== $desc ) {
				$html .= '<div class="wenpai-dash__head">';
				if ( '' !== $title ) {
					$html .= '<h3 class="wenpai-dash__title">' . esc_html( $title ) . '</h3>';
				}
				$html .= $desc . '</div>';
			}
			$stats = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
			if ( array() !== $stats ) {
				$html .= '<div class="wenpai-dash__stats">';
				foreach ( $stats as $stat ) {
					if ( ! is_array( $stat ) ) {
						continue;
					}
					$kv    = self::item_kv( $stat, array( 'num', 'label' ) );
					$num   = '' !== (string) $kv['num'] ? $kv['num'] : ( isset( $stat['value'] ) ? $stat['value'] : ( isset( $stat[0] ) ? $stat[0] : '' ) );
					$lab   = '' !== (string) $kv['label'] ? $kv['label'] : ( isset( $stat['label'] ) ? $stat['label'] : ( isset( $stat[1] ) ? $stat[1] : '' ) );
					$html .= '<div class="wenpai-dash__stat"><span class="n">' . esc_html( (string) $num ) . '</span><span class="label">' . esc_html( (string) $lab ) . '</span></div>';
				}
				$html .= '</div>';
			}
			if ( isset( $args['body_html'] ) ) {
				$html .= self::kses( (string) $args['body_html'], 'post' );
			}
			return $html . '</div>';
		}

		/**
		 * Echo a quick-edit fieldset for WP inline edit (0.8).
		 *
		 * @param array $args Arguments.
		 */
		public static function quick_edit_box( $args = array() ) {
			self::emit( self::quick_edit_box_html( $args ) );
		}

		/**
		 * Return quick-edit fieldset markup.
		 *
		 * Hook usage: add_action( 'quick_edit_custom_box', … ) prints this inside
		 * the column div; WP core inlineEditPost shows/hides it. Populating the
		 * controls on row open is the product's job.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $legend Fieldset legend (.inline-edit-heading).
		 *     @type array  $fields array of array( 'name', 'label', 'control_html' ).
		 *                           control_html is kses 'control' (form controls kept).
		 *                           name lands on the row as data-name for product JS.
		 * }
		 * @return string
		 */
		public static function quick_edit_box_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::quick_edit_box' );
			$legend = isset( $args['legend'] ) ? (string) $args['legend'] : '';
			$fields = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array();
			$html   = '<fieldset class="inline-edit-col-inline wenpai-qe"><legend class="inline-edit-heading">' . esc_html( $legend ) . '</legend>';
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$kv    = self::item_kv( $field, array( 'name', 'label', 'control_html' ) );
				$ctl   = isset( $field['control_html'] ) ? self::kses( (string) $field['control_html'], 'control' ) : '';
				$html .= '<div class="wenpai-qe__row" data-name="' . esc_attr( (string) $kv['name'] ) . '"><label>' . esc_html( (string) $kv['label'] ) . '</label>' . $ctl . '</div>';
			}
			return $html . '</fieldset>';
		}

		/**
		 * Echo plugins.php-style inline action links (0.8).
		 *
		 * @param array $items Items array; see action_links_html().
		 */
		public static function action_links( $items ) {
			self::emit( self::action_links_html( $items ) );
		}

		/**
		 * Return inline action links joined by ' | ' (plugins.php row convention).
		 *
		 * Items whose capability the current user lacks are skipped silently.
		 *
		 * @param array $items array of array( 'label', 'href', 'capability'?, 'current'? ).
		 *                     capability empty → no check; current → aria-current="page".
		 * @return string
		 */
		public static function action_links_html( $items ) {
			if ( ! is_array( $items ) ) {
				self::doing_it_wrong( 'action_links needs an items array.', 'Wenpai_Admin_UI::action_links' );
				return '';
			}
			$links = array();
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$kv = self::item_kv( $item, array( 'label', 'href', 'capability', 'current' ) );
				if ( '' !== (string) $kv['capability'] && function_exists( 'current_user_can' ) && ! current_user_can( (string) $kv['capability'] ) ) {
					continue;
				}
				$href = (string) $kv['href'];
				if ( '' === $href ) {
					continue;
				}
				$link = '<a href="' . esc_url( $href ) . '"';
				if ( ! empty( $kv['current'] ) ) {
					$link .= ' aria-current="page"';
				}
				$link   .= '>' . esc_html( (string) $kv['label'] ) . '</a>';
				$links[] = $link;
			}
			return implode( ' | ', $links );
		}

		/**
		 * Coerce $args to an array.
		 *
		 * @param mixed  $args  Caller args.
		 * @param string $where Function name for _doing_it_wrong.
		 * @return array
		 */
		private static function args( $args, $where ) {
			if ( is_array( $args ) ) {
				return $args;
			}
			self::doing_it_wrong( 'Arguments must be an array.', $where );
			return array();
		}

		/**
		 * Normalize a state token.
		 *
		 * @param mixed    $value         Caller value.
		 * @param string[] $allowed       Allowed tokens.
		 * @param string   $fallback      Fallback.
		 * @param string   $where         Function name.
		 * @param bool     $mute_to_empty tile(): mute becomes ''.
		 * @return string
		 */
		private static function state( $value, array $allowed, $fallback, $where, $mute_to_empty = false ) {
			$value = is_scalar( $value ) ? (string) $value : '';
			if ( $mute_to_empty && 'mute' === $value ) {
				return '';
			}
			if ( in_array( $value, $allowed, true ) ) {
				return $value;
			}
			self::doing_it_wrong( sprintf( 'Invalid state 「%s」.', $value ), $where );
			return $fallback;
		}

		/**
		 * Heading level 1–6, default 2.
		 *
		 * @param mixed  $heading Caller value.
		 * @param string $where   Function name.
		 * @return int
		 */
		private static function heading( $heading, $where ) {
			$level = (int) $heading;
			if ( $level < 1 || $level > 6 ) {
				self::doing_it_wrong( 'heading must be 1–6.', $where );
				return 2;
			}
			return $level;
		}

		/**
		 * Extra class string from args['class'].
		 *
		 * @param array $args Args.
		 * @return string
		 */
		private static function extra_class( array $args ) {
			if ( ! isset( $args['class'] ) ) {
				return '';
			}
			if ( is_array( $args['class'] ) ) {
				return implode( ' ', $args['class'] );
			}
			return (string) $args['class'];
		}

		/**
		 * Join sanitized class names, dropping empties.
		 *
		 * @param array $parts Class fragments (strings or arrays).
		 * @return string
		 */
		private static function classes( array $parts ) {
			$out = array();
			foreach ( $parts as $p ) {
				$list = is_array( $p ) ? $p : preg_split( '/\s+/', trim( (string) $p ) );
				foreach ( (array) $list as $c ) {
					$c = sanitize_html_class( (string) $c );
					if ( '' !== $c && ! in_array( $c, $out, true ) ) {
						$out[] = $c;
					}
				}
			}
			return implode( ' ', $out );
		}

		/**
		 * Extra attributes: id, data-*, aria-*, role, hidden.
		 *
		 * @param array    $args Args with optional attrs.
		 * @param string[] $skip Names already printed.
		 * @return string
		 */
		private static function extra_attrs( array $args, array $skip = array() ) {
			if ( ! isset( $args['attrs'] ) || ! is_array( $args['attrs'] ) ) {
				return '';
			}
			$html = '';
			foreach ( $args['attrs'] as $name => $value ) {
				$name = strtolower( (string) $name );
				if ( in_array( $name, $skip, true ) ) {
					continue;
				}
				$ok = ( 'id' === $name || 'role' === $name || 'hidden' === $name
					|| 0 === strpos( $name, 'data-' ) || 0 === strpos( $name, 'aria-' ) );
				if ( ! $ok ) {
					continue;
				}
				if ( false === $value || null === $value ) {
					continue;
				}
				$bool = ( true === $value || '' === $value || ( 'hidden' === $name && $value ) );
				if ( $bool ) {
					$html .= ' ' . $name;
					continue;
				}
				$html .= ' ' . $name . '="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '"';
			}
			return $html;
		}

		/**
		 * ' id="…"' from $args[ $key ], or ''.
		 *
		 * @param array  $args Args.
		 * @param string $key  Arg holding the id.
		 * @return string
		 */
		private static function id_attr( array $args, $key ) {
			if ( ! isset( $args[ $key ] ) || '' === (string) $args[ $key ] ) {
				return '';
			}
			return ' id="' . esc_attr( (string) $args[ $key ] ) . '"';
		}

		/**
		 * Read keyed or positional item fields.
		 *
		 * @param array    $item Caller item.
		 * @param string[] $keys Field names in positional order.
		 * @return array
		 */
		private static function item_kv( array $item, array $keys ) {
			$out = array();
			foreach ( $keys as $i => $k ) {
				if ( array_key_exists( $k, $item ) ) {
					$out[ $k ] = $item[ $k ];
				} elseif ( array_key_exists( $i, $item ) ) {
					$out[ $k ] = $item[ $i ];
				} else {
					$out[ $k ] = '';
				}
			}
			return $out;
		}

		/**
		 * Inline SVG for a known icon name. Unknown names return ''.
		 *
		 * Icons::svg() falls back to info; helpers must not.
		 *
		 * @param string $name Icon name.
		 * @return string
		 */
		private static function icon_svg( $name ) {
			if ( ! class_exists( 'Wenpai_Admin_Icons', false ) || ! Wenpai_Admin_Icons::has( $name ) ) {
				return '';
			}
			return Wenpai_Admin_Icons::svg( (string) $name );
		}

		/**
		 * Developer notice, only with WP_DEBUG on.
		 *
		 * @param string $message Message.
		 * @param string $where   Function to name.
		 */
		private static function doing_it_wrong( $message, $where ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( '_doing_it_wrong' ) ) {
				_doing_it_wrong( $where, $message, '0.3.4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain developer text; core escapes it when displaying.
			}
		}
	}
}
