=== WPFonts ===
Contributors: wenpai
Tags: fonts, typography, font-library, chinese, cjk
Requires at least: 6.5
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 2.0.0-rc.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

System font stacks, plus an optional Windfonts CDN link. Release candidate: CI does not verify a live API key.

== Description ==

WPFonts adds 40 Chinese, English, Japanese, and Korean system font stacks to the WordPress Font Library. Those stacks use fonts already installed on each visitor's device.

Version 2.0.0-rc.2 can also print one allowlisted Windfonts stylesheet on the front. The CDN is off until a site owner turns it on and a server-side key check succeeds. The API key is not printed in HTML. The plugin does not upload font files, slice them, or pull CSS into PHP. Settings can pick a fontFamily from the read-only directory at app.windfonts.com.

The public CSS URL needs a font name (`family`) or a project name. Without either, the front stays on system fonts. Servers are only cn.windfonts.com, hk.windfonts.com, and en.windfonts.com.

Family slugs stay language-prefixed (`cn-`, `en-`, `jp-`, `kr-`). This version does not rewrite Global Styles or write `wenfeng-*` names into theme.json.

The custom update checker contacts `https://updates.weixiaoduo.com/` from WordPress admin, WP-Cron, or WP-CLI. It is not loaded during ordinary front-end page views.

== Installation ==

1. Install and activate WPFonts on WordPress 6.5 or later.
2. Use a block theme and open Appearance > Editor > Styles > Typography > Fonts to install a system stack.
3. Optional: open Settings > 文派字库 to turn on the Windfonts CDN.

On multisite, activate per site or network-wide. CDN settings are stored per site.

== Frequently Asked Questions ==

= Does WPFonts download or host fonts? =

The system stacks do not. When the CDN is on and the key check passed, the visitor's browser requests one allowlisted stylesheet. The plugin does not store that CSS.

= Does deactivation remove typography choices? =

No. Global Styles belong to WordPress. Deactivation does not delete them. Uninstall deletes only the WPFonts settings and API key.

= Does WPFonts work with classic themes? =

The collections target the WordPress Font Library. The CDN link is a normal front-end stylesheet and does not require a block theme. A block theme is still required for the Font Library screens.

== Changelog ==

= 2.0.0-rc.2 =
* Pick a font from the read-only app.windfonts.com directory. The saved value is fontFamily. A failed read keeps the manual field and does not wipe a cached page.
* Explain the four character-range choices on the settings screen. The plugin still does not slice fonts.
* Move the project name into a collapsed section with a link to the Windfonts console. Domain allowlists stay in that console.

= 2.0.0-rc.1 =
* Add an opt-in Windfonts CDN client: region, subset, masked API key, optional project slug, and optional font short name.
* Print only an allowlisted stylesheet link. Do not print the key, and do not embed when the key check has not passed.
* Add Overview, Settings, and Tools with the vendored wenpai-admin-ui 0.3.1 shell. Help stays in the header.
* Keep system font stacks working while the CDN is off.
* This is a release candidate. Automated tests do not call Windfonts with a real key.

= 1.3.0 =
* Require WordPress 6.5 and PHP 7.4, matching the Font Library collection API.
* Prefix every font-family slug with cn-, en-, jp-, or kr- so collections do not share generic slugs. Existing Global Styles are left untouched.
* Allow per-site or network activation on multisite.
* Load the custom updater only for wp-admin, WP-Cron, and WP-CLI.
* Remove the unused UpdatePulse licensing library.
* Document that the plugin ships system stacks, not a font CDN.

= 1.2.0 =
* Register Chinese, English, Japanese, and Korean system font collections.
