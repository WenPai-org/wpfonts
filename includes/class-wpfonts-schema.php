<?php
/**
 * Wenpai_Admin_Settings schema for the wpfonts_settings option.
 *
 * Contract only: WPFonts_Settings::sanitize_form() stays the single
 * sanitize callback (registered by WPFonts_Settings::register() on the
 * 'wpfonts' settings group), so the kit instance's own register_setting()
 * is removed right after register(). Fields that keep a custom widget
 * (system checkgroup, CJK selects, catalog picker, API key, project slug,
 * link preview) are registered for depends_on / contract completeness but
 * are never rendered through field_html().
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the kit settings schema for wpfonts_settings.
 */
class WPFonts_Schema {

	/**
	 * Option name; inputs post as wpfonts_settings[field].
	 */
	const OPTION = 'wpfonts_settings';

	/**
	 * Settings API group used by settings_fields( 'wpfonts' ).
	 */
	const GROUP = 'wpfonts';

	/**
	 * Shared kit instance, registered once. The kit's admin_init
	 * register_setting is removed: WPFonts_Settings::sanitize_form() owns
	 * the option (surface gating, API key option, health check, faces
	 * normalization), and two sanitize passes would run twice.
	 *
	 * @return Wenpai_Admin_Settings|null
	 */
	public static function framework() {
		if ( ! class_exists( 'Wenpai_Admin_Settings', false ) ) {
			return null;
		}
		$fw = Wenpai_Admin_Settings::get_instance( self::OPTION );
		if ( null === $fw ) {
			$fw = Wenpai_Admin_Settings::register(
				self::OPTION,
				self::fields(),
				array(
					// 'group' is the Settings API group of the real form
					// (settings_fields( 'wpfonts' )); the kit's own
					// register_setting() is removed, so this only feeds
					// group() / saving_from_options_php() bookkeeping.
					'group'   => self::GROUP,
					'strings' => self::strings(),
					// No 'sections': simple/advanced is a ?mode= page-level
					// switch, not a ?section= form group. Advanced-only
					// fields are not rendered in simple mode at all.
				)
			);
			if ( function_exists( 'remove_action' ) ) {
				remove_action( 'admin_init', array( $fw, 'register_setting' ) );
			}
		}
		return $fw;
	}

	/**
	 * admin_init entry point, called from WPFonts_Admin::register_settings().
	 *
	 * @return void
	 */
	public static function register() {
		self::framework();
	}

	/**
	 * Field schema for every key the settings form can write into
	 * wpfonts_settings, plus the stored state keys, plus api_key (which
	 * actually lives in the separate wpfonts_api_key option and is handled
	 * entirely by WPFonts_Settings::sanitize_form()).
	 *
	 * 'mode' => 'advanced' is contract documentation for the two fields that
	 * only render on ?mode=advanced; the simple-mode fields show on both
	 * modes, so they carry no mode. depends_on uses the kit contract
	 * (field + value); custom widgets print the same data attributes via
	 * WPFonts_Admin::cdn_depends().
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields() {
		$cdn = array( 'field' => 'cdn_enabled', 'value' => '1' );

		$cjk_choices = static function () {
			$out  = array();
			$rows = class_exists( 'WPFonts_CJK', false ) ? WPFonts_CJK::choices() : array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || ! isset( $row['value'] ) ) {
					continue;
				}
				$entry = array( 'label' => isset( $row['label'] ) ? (string) $row['label'] : (string) $row['value'] );
				if ( isset( $row['group'] ) && '' !== (string) $row['group'] ) {
					$entry['group'] = (string) $row['group'];
				}
				$out[ (string) $row['value'] ] = $entry;
			}
			return $out;
		};
		// Kit select optgroups; the custom renderer additionally prints the
		// 「已保存，当前不可用」 fallback option, which the schema cannot.
		$cjk_groups = array(
			'package' => __( '文风字体包', 'wpfonts' ),
			'cloud'   => __( '文风云', 'wpfonts' ),
		);

		$system = array();
		foreach ( array(
			'cn' => __( '系统 · 中文', 'wpfonts' ),
			'en' => __( '系统 · 英文', 'wpfonts' ),
			'jp' => __( '系统 · 日文', 'wpfonts' ),
			'kr' => __( '系统 · 韩文', 'wpfonts' ),
		) as $code => $label ) {
			$system[ 'system_' . $code ] = array(
				'type'    => 'toggle',
				'default' => 1,
				'label'   => $label,
				'desc'    => __( '默认都开启。取消勾选后，这一组不再出现在字体库。不依赖文风云。', 'wpfonts' ),
			);
		}

		return array_merge(
			// --- CDN master switch: rendered inside the status row via
			// WPFonts_Admin::cdn_switch() (kit switch_html), registered so
			// depends_on triggers resolve against wpfonts_settings[cdn_enabled].
			array(
				'cdn_enabled' => array(
					'type'    => 'toggle',
					'default' => 0,
					'label'   => __( '开启文风云', 'wpfonts' ),
					'desc'    => __( '默认关闭。开启后，前台才会请求文风样式。关闭后不再请求远程字体，系统字体仍然可用。', 'wpfonts' ),
				),
			),
			$system,
			// --- CJK assignments: custom selects (optgroups + saved-but-
			// unavailable fallback). Registered for the contract; the
			// cjk_form sentinel input stays in render_cjk_fields().
			array(
				'cjk_fallback' => array(
					'type'     => 'select',
					'default'  => '',
					'label'    => __( '中文回退', 'wpfonts' ),
					'desc'     => __( '从已启用的文风字体包，或已确认的文风云里选。留空则这一项不单独指定。', 'wpfonts' ),
					'choices'  => $cjk_choices,
					'groups'   => $cjk_groups,
				),
				'cjk_body'     => array(
					'type'     => 'select',
					'default'  => '',
					'label'    => __( '正文', 'wpfonts' ),
					'choices'  => $cjk_choices,
					'groups'   => $cjk_groups,
				),
				'cjk_heading'  => array(
					'type'     => 'select',
					'default'  => '',
					'label'    => __( '标题', 'wpfonts' ),
					'choices'  => $cjk_choices,
					'groups'   => $cjk_groups,
				),
			),
			// --- CDN-gated fields ---
			array(
				'region' => array(
					'type'       => 'choice',
					'default'    => 'cn',
					'label'      => __( '区域', 'wpfonts' ),
					'desc'       => __( '选择文风服务器。只有这三台。', 'wpfonts' ),
					'choices'    => array(
						'cn' => array( 'label' => __( '中国大陆', 'wpfonts' ), 'desc' => 'cn.windfonts.com' ),
						'hk' => array( 'label' => __( '中国香港', 'wpfonts' ), 'desc' => 'hk.windfonts.com' ),
						'en' => array( 'label' => __( '国际', 'wpfonts' ), 'desc' => 'en.windfonts.com' ),
					),
					'depends_on' => $cdn,
				),
				// families[] is the catalog picker input name; sanitize_form()
				// normalizes it into faces[]. Registered as a declaration
				// only — the picker, families_form sentinel and
				// #wpfonts-family-holds hidden backfill stay custom.
				'families'     => array(
					'type'       => 'multicheck',
					'default'    => array(),
					'label'      => __( '选用字体', 'wpfonts' ),
					'choices'    => array(),
					'depends_on' => $cdn,
				),
				'family'      => array(
					'type'       => 'text',
					'default'    => '',
					'label'      => __( '字体名', 'wpfonts' ),
					'desc'       => __( '也可直接填写，例如 wenfeng-ibmps。填了项目名后，公开地址可以不填字体名。', 'wpfonts' ),
					'depends_on' => $cdn,
				),
				'subset'      => array(
					'type'       => 'choice',
					'default'    => 'zh-common',
					'label'      => __( '字符范围', 'wpfonts' ),
					'desc'       => __( '字符范围决定远程字体包大小。插件不在这里切字。', 'wpfonts' ),
					'choices'    => array(
						'en'        => array( 'label' => __( '西文', 'wpfonts' ), 'desc' => __( '体积最小，主要覆盖拉丁字符。', 'wpfonts' ) ),
						'zh-common' => array( 'label' => __( '常用中文（推荐）', 'wpfonts' ), 'desc' => __( '日常中文站点够用，体积适中。', 'wpfonts' ) ),
						'zh'        => array( 'label' => __( '较全中文', 'wpfonts' ), 'desc' => __( '生僻字更多，体积明显更大。', 'wpfonts' ) ),
						'full'      => array( 'label' => __( '全量', 'wpfonts' ), 'desc' => __( '最大包，仅在确实需要时使用。', 'wpfonts' ) ),
					),
					'depends_on' => $cdn,
				),
				// api_key is NOT a wpfonts_settings key: the form posts
				// wpfonts_settings[api_key] but sanitize_form() writes the
				// separate wpfonts_api_key option (clean_secret + clear_api_key
				// + health check). Kit secret/__wenpai_clear does not apply.
				// Registered for contract completeness; rendered by
				// render_key_field() only.
				'api_key'       => array(
					'type'       => 'secret',
					'default'    => '',
					'label'      => __( '文风 API Key', 'wpfonts' ),
					'mode'       => 'advanced',
					'depends_on' => $cdn,
				),
				'project_slug'  => array(
					'type'       => 'text',
					'default'    => '',
					'label'      => __( '项目名', 'wpfonts' ),
					'desc'       => __( '选填。填写后前台改用项目样式。域名白名单在文风控制台配置，不在本插件里改。', 'wpfonts' ),
					'mode'       => 'advanced',
					'depends_on' => $cdn,
				),
			),
			// --- Stored state keys (written by sanitize_form / write()).
			// Not user-edited, never rendered; registered so every key that
			// lands in wpfonts_settings is declared.
			array(
				'faces'       => array(
					'type'    => 'multicheck',
					'default' => array(),
					'label'   => __( '已确认的文风云字体', 'wpfonts' ),
				),
				'key_status'  => array(
					'type'    => 'text',
					'default' => '',
					'label'   => __( 'API Key 验证状态', 'wpfonts' ),
				),
				'key_checked' => array(
					'type'    => 'number',
					'default' => 0,
					'label'   => __( 'API Key 验证时间', 'wpfonts' ),
				),
				'css_status'  => array(
					'type'    => 'text',
					'default' => '',
					'label'   => __( '样式表检查状态', 'wpfonts' ),
				),
				'css_face'    => array(
					'type'    => 'text',
					'default' => '',
					'label'   => __( '样式表字体名', 'wpfonts' ),
				),
			)
		);
	}

	/**
	 * Kit UI strings in the wpfonts text domain. Wording follows the kit
	 * defaults (the kit has no text domain of its own).
	 *
	 * @return array<string, string>
	 */
	public static function strings() {
		return array(
			'on'                     => __( '已开启', 'wpfonts' ),
			'off'                    => __( '未开启', 'wpfonts' ),
			'secret_saved'           => __( '已保存一个值，出于安全不会显示。留空表示不修改。', 'wpfonts' ),
			'secret_empty'           => __( '还没有填写。', 'wpfonts' ),
			'secret_clear'           => __( '清除已保存的值', 'wpfonts' ),
			'secret_show'            => __( '显示', 'wpfonts' ),
			'secret_hide'            => __( '隐藏', 'wpfonts' ),
			'secret_multiline'       => __( '暂时无法保存密钥，请填写单行的值。', 'wpfonts' ),
			'media_select'           => __( '选择', 'wpfonts' ),
			'media_clear'            => __( '清除', 'wpfonts' ),
			'media_empty'            => __( '未选择', 'wpfonts' ),
			'saved'                  => __( '设置已保存。', 'wpfonts' ),
			'saving'                 => __( '正在保存…', 'wpfonts' ),
			'partial_saved'          => __( '其余设置已保存。', 'wpfonts' ),
			/* translators: %d: number of fields that were not saved. */
			'invalid'                => __( '暂时无法保存 %d 项设置，请按字段下的提示修改。', 'wpfonts' ),
			/* translators: %d: total number of fields with errors. */
			'invalid_more'           => __( '等 %d 项', 'wpfonts' ),
			'invalid_value'          => __( '这一项的值无效，已保留原来的设置。', 'wpfonts' ),
			'follow_global'          => __( '跟随全局设置', 'wpfonts' ),
			/* translators: %s: what would be listed, e.g. 文章类型. */
			'empty_choices'          => __( '还没有可选的%s。', 'wpfonts' ),
			/* translators: 1: hidden tab label, 2: first tab label. */
			'tab_hidden'             => __( '当前模式不需要%1$s的设置，已切回%2$s。', 'wpfonts' ),
			'action_busy'            => __( '正在处理', 'wpfonts' ),
			'action_ok'              => __( '已完成。', 'wpfonts' ),
			'action_failed'          => __( '暂时无法完成操作，请稍后再试。', 'wpfonts' ),
			'tab_expired'            => __( '暂时无法保存，页面已过期，请刷新后再试。', 'wpfonts' ),
			'action_denied'          => __( '暂时无法完成操作，请刷新页面后再试。', 'wpfonts' ),
			'confirm_needed'         => __( '暂时无法完成操作，请先勾选确认。', 'wpfonts' ),
			'invalid_email'          => __( '请填写有效的邮箱地址。', 'wpfonts' ),
			'invalid_url'            => __( '请填写以 http:// 或 https:// 开头的地址。', 'wpfonts' ),
			'new_tab'                => __( '（在新标签页打开）', 'wpfonts' ),
			'dismiss'                => __( '不再提示', 'wpfonts' ),
			/* translators: %s: the recommended product. Screen-reader only. */
			'dismiss_context'        => __( '：%s的推荐', 'wpfonts' ),
			'dismissed'              => __( '已关闭这条推荐。', 'wpfonts' ),
			'imported'               => __( '已导入设置。', 'wpfonts' ),
			/* translators: %d: number of fields that were not imported. */
			'imported-partial'       => __( '已导入设置，其中 %d 项无效，已保留原来的值。', 'wpfonts' ),
			'import-invalid'         => __( '暂时无法导入这份文件，请确认它是本插件导出的设置。', 'wpfonts' ),
			'import-newer'           => __( '暂时无法导入较新版本的设置，请先升级插件。', 'wpfonts' ),
			'import-size'            => __( '暂时无法导入这份文件，请确认文件小于 256 KB。', 'wpfonts' ),
			'reset'                  => __( '已恢复默认设置。', 'wpfonts' ),
			'reset-confirm'          => __( '暂时无法重置，请先勾选确认。', 'wpfonts' ),
			'import-confirm'         => __( '暂时无法导入，请先勾选确认。', 'wpfonts' ),
			'reset_confirm'          => __( '我已确认要把本插件的设置恢复为默认值', 'wpfonts' ),
			'tools_export_title'     => __( '导出设置', 'wpfonts' ),
			'tools_export_sub'       => __( '下载一份设置文件（JSON），不含密钥。', 'wpfonts' ),
			'tools_export_button'    => __( '导出设置', 'wpfonts' ),
			'tools_import_title'     => __( '导入设置', 'wpfonts' ),
			'tools_import_sub'       => __( '用之前导出的文件恢复设置。密钥保持不变。', 'wpfonts' ),
			'tools_import_button'    => __( '导入设置', 'wpfonts' ),
			'tools_import_file'      => __( '设置文件', 'wpfonts' ),
			'tools_import_confirm'   => __( '我已确认用这份文件覆盖当前设置', 'wpfonts' ),
			'tools_import_need_file' => __( '先选择文件', 'wpfonts' ),
			'tools_reset_title'      => __( '重置设置', 'wpfonts' ),
			/* translators: %s: in-page link labelled 导出一份. */
			'tools_reset_sub'        => __( '把本插件的设置恢复为默认值，不可撤销。建议先%s。', 'wpfonts' ),
			'tools_reset_export'     => __( '导出一份', 'wpfonts' ),
			'tools_reset_button'     => __( '重置设置', 'wpfonts' ),
			'tools_reset_clear'      => __( '同时清除已保存的密钥', 'wpfonts' ),
			'tools_reset_busy'       => __( '正在重置…', 'wpfonts' ),
			'repeater_item'          => __( '第 %d 项', 'wpfonts' ),
			'repeater_delete'        => __( '删除第 %d 项', 'wpfonts' ),
			'repeater_add'           => __( '添加一项', 'wpfonts' ),
			'repeater_move_up'       => __( '上移', 'wpfonts' ),
			'repeater_move_down'     => __( '下移', 'wpfonts' ),
			/* translators: 1: selected count, 2: total count. */
			'checkgroup_count'       => __( '已选 %1$d / %2$d', 'wpfonts' ),
			'checkgroup_all'         => __( '全选', 'wpfonts' ),
			'checkgroup_none'        => __( '清空', 'wpfonts' ),
			'catalog_filter'         => __( '过滤…', 'wpfonts' ),
			'entity_search'          => __( '搜索…', 'wpfonts' ),
			'entity_empty'           => __( '没有匹配的结果', 'wpfonts' ),
			'entity_clear'           => __( '清除', 'wpfonts' ),
			'file_choose'            => __( '选择文件', 'wpfonts' ),
			'file_drop'              => __( '拖入文件，或点击选择', 'wpfonts' ),
			'file_remove'            => __( '移除', 'wpfonts' ),
			'file_uploading'         => __( '上传中…', 'wpfonts' ),
			'file_failed'            => __( '上传失败', 'wpfonts' ),
			'tags_placeholder'       => __( '输入后回车添加', 'wpfonts' ),
			/* translators: %s: human-readable next run time. */
			'schedule_next'          => __( '下次运行：%s', 'wpfonts' ),
			'schedule_none'          => __( '未安排', 'wpfonts' ),
			'generate'               => __( '生成', 'wpfonts' ),
		);
	}
}
