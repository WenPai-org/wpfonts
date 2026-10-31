=== WPFonts ===
Contributors: wenpai
Tags: fonts, typography, font-library, chinese, cjk
Requires at least: 6.5
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 2.0.0-rc.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

System font stacks, plus an optional Windfonts CDN link. Release candidate: CI does not verify a live API key.

== Description ==

WPFonts adds Chinese, English, Japanese, and Korean system font stacks to the WordPress Font Library. Those stacks use fonts already installed on each visitor's device. The collections are named 系统 · 中文, 系统 · 英文, 系统 · 日文, and 系统 · 韩文.

Version 2.0.0-rc.6 can also print one allowlisted Windfonts stylesheet on the front. The CDN is off until a site owner turns it on and a server-side key check succeeds. The API key is not printed in HTML. The plugin does not upload font files, slice them, or pull CSS into PHP. Settings pick a fontFamily from a local JSON snapshot of the app.windfonts.com directory. Opening settings does not call that directory. A new install queues one immediate snapshot refresh when none exists yet. Enabled on-site packages and a confirmed cloud face are registered in the Font Library and copied into user Global Styles, so they show under Typography. Disabling a package, deleting it, or losing the confirmed stylesheet removes only those WPFonts entries. The plugin does not edit theme files, and uninstall does not delete Global Styles.

The public CSS URL needs a font name (`family`) or a project name. Without either, the front stays on system fonts. Servers are only cn.windfonts.com, hk.windfonts.com, and en.windfonts.com.

Family slugs stay language-prefixed (`cn-`, `en-`, `jp-`, `kr-`). Enabled on-site packages appear in the Font Library as 文风, ahead of the system collections. 文风云 is unchanged. A confirmed catalog face appears there too, and both are copied into user Global Styles. Settings still call the installable packages 文风字体包. The block inspector has a thin 文派字库 panel for the current block. It does not add an admin-bar item.

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

No. Global Styles belong to WordPress. Deactivation does not delete them. Uninstall deletes the WPFonts settings, API key, package list, and the catalog snapshot at uploads/wpfonts/catalog.json. It does not delete installed font package files.

= Does WPFonts work with classic themes? =

The collections target the WordPress Font Library. The CDN link is a normal front-end stylesheet and does not require a block theme. A block theme is still required for the Font Library screens.

== Changelog ==

= 2.0.0-rc.6 =
* Show the block inspector panel 文派字库. On WordPress 6.7 and later, inspector controls render only inside the block edit context, so the panel is added with the editor.BlockEdit filter. Choosing an enabled package or a confirmed cloud face sets that block's typography font family.
* Name the Font Library collection of enabled on-site packages 文风. 文风云 is unchanged. Settings screens still say 文风字体包.
* Register 文风 and 文风云 before the system collections, so those tabs sit ahead of 系统 · 中文.
* Rename the system collections to 系统 · 中文, 系统 · 英文, 系统 · 日文, and 系统 · 韩文. Expand the Japanese and Korean stacks, and replace faces that are not shipped with Windows, macOS, iOS, Android, or common Linux installs. Existing slugs stay. No font files are downloaded.

= 2.0.0-rc.5 =
* Copy enabled on-site package families, and the confirmed 文风云 face, into the user Global Styles post so they appear under Typography. The CSS stack is the one WPFonts_Library already computed.
* Remove those entries when a package is disabled or deleted, or when the stylesheet is no longer confirmed. Other Global Styles entries stay. Theme files are not edited. Uninstall still does not delete Global Styles.
* Reconcile on an editor, cron, or WP-CLI request when the stored list has drifted. Front-end visitors do not write the post.

= 2.0.0-rc.4 =
* Register enabled on-site font packages in the Font Library as 文风字体包, using the woff2 files already stored for that package. Disabled or deleted packages drop out of the collection. Catalog names register as 文风云, without font files.
* Queue one immediate catalog refresh when a site has no snapshot yet. Activation does not call the directory. A failed refresh still keeps a good snapshot.
* Add a block inspector panel, 文派字库, so an editor can apply an enabled package or a catalog family to the current block. No admin-bar button.
* Installing a family from the Font Library writes Global Styles. Uninstall still does not delete them.
* A saved public stylesheet that returns HTTP 404 is not embedded and is not described as loading. The Font Library cloud entry uses the face name declared in that CSS, not the catalog token, and only after the address has opened.

= 2.0.0-rc.3 =
* Store the font directory at wp-content/uploads/wpfonts/catalog.json. The settings screen reads that file and does not call app.windfonts.com on a normal render.
* Refresh the snapshot about once a month with WP-Cron (custom monthly schedule, 30 days). The settings button can refresh it sooner, with a 60 second cooldown.
* A failed refresh keeps the previous snapshot. The saved value is still fontFamily (wenfeng-*).

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
