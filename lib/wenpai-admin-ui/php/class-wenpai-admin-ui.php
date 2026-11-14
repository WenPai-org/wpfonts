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
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes (id, data-*, aria-*, role, hidden).
		 * }
		 * @return string
		 */
		public static function tile_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::tile' );
			$state = self::state( isset( $args['state'] ) ? $args['state'] : '', self::$tile_states, '', 'Wenpai_Admin_UI::tile', true );
			$icon  = self::icon_svg( isset( $args['icon'] ) ? $args['icon'] : '' );
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
					'icon'  => $icon,
					'state' => isset( $args['state'] ) ? $args['state'] : '',
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
			return $html . '</div></div>';
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
					'icon'  => isset( $args['icon'] ) ? $args['icon'] : '',
					'state' => isset( $args['state'] ) ? $args['state'] : '',
				)
			);
			$body  = '<div>';
			if ( isset( $args['title'] ) && '' !== (string) $args['title'] ) {
				$body .= '<div class="t"' . self::id_attr( $args, 'title_id' ) . '>' . esc_html( (string) $args['title'] ) . '</div>';
			}
			if ( isset( $args['desc'] ) && '' !== (string) $args['desc'] ) {
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
					'icon'  => isset( $args['icon'] ) ? $args['icon'] : '',
					'state' => isset( $args['state'] ) ? $args['state'] : '',
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
						'icon'  => $args['icon'],
						'state' => isset( $args['state'] ) ? $args['state'] : '',
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
		 *     @type array  $items   array of array( id, label, url|href? ). Positional id/label/url ok.
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
				$links .= '>' . esc_html( (string) $row['label'] ) . '</a>';
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
		 *     @type array  $rows    Row maps keyed by column key.
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
					$cell  = array_key_exists( $col['key'], $row ) ? $row[ $col['key'] ] : '';
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
