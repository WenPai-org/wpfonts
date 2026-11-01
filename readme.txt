=== WPFonts ===
Contributors: wenpai
Tags: fonts, typography, font-library, chinese, cjk
Requires at least: 6.5
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 2.0.0-rc.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

System font stacks, plus an optional Windfonts CDN link. Release candidate: CI does not verify a live API key.

== Description ==

WPFonts adds Chinese, English, Japanese, and Korean system font stacks to the WordPress Font Library. Those stacks use fonts already installed on each visitor's device. The collections are named 系统 · 中文, 系统 · 英文, 系统 · 日文, and 系统 · 韩文.

Version 2.0.0-rc.10 can print one allowlisted Windfonts stylesheet for each confirmed cloud face. Settings can also assign an enabled package or a confirmed cloud face to body text, headings, and a site Chinese fallback. The fallback is appended to Latin stacks, including `.has-*-font-family` rules, by plugin CSS and a filter on the generated global stylesheet. That path does not edit theme.json or the user Global Styles post. The CDN is off until a site owner turns it on and a server-side key check succeeds. The API key is not printed in HTML, and it is not included in a settings export. The plugin does not upload font files, slice them, or pull CSS into PHP. Settings pick fontFamily values from a local JSON snapshot of the app.windfonts.com directory. Opening settings does not call that directory. A new install queues one immediate snapshot refresh when none exists yet. Enabled on-site packages and every confirmed cloud face are registered in the Font Library and copied into user Global Styles, so they show under Typography. Removing one cloud face, or disabling a package, removes only that WPFonts entry. System font collections can be turned off per language. The plugin does not edit theme files, and uninstall does not delete Global Styles. A package can also be installed, replaced, or deleted with WP-CLI (`wp fonts package`). After a successful install or replace, and before a package directory is removed, other plugins may listen for that change. This plugin does not upload the package anywhere else.

The public CSS URL needs a font name (`family`) or a project name. Without either, the front stays on system fonts. Servers are only cn.windfonts.com, hk.windfonts.com, and en.windfonts.com.

Family slugs stay language-prefixed (`cn-`, `en-`, `jp-`, `kr-`). Enabled on-site packages appear in the Font Library as 文风字体 (Windfonts in English), ahead of the system collections. Installing a package records `wenfeng-*` when the zip names `WF-*`, `windfonts-*`, or `wenfeng-*`, and rewrites that name into `local.css`. A zip that does not name a catalog family keeps its file name. Packages already installed are left in place. 文风云 is unchanged. A confirmed catalog face appears there too, and both are copied into user Global Styles. Settings still call the installable packages 文风字体包. The block inspector has a thin 文派字库 panel for the current block. It does not add an admin-bar item.

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

= 2.0.0 =

Draft notes for the stable release. Plugin version, WPFONTS_VERSION, and Stable tag stay at 2.0.0-rc.10 until Feng says ship. These notes describe behavior already in 2.0.0-rc.10. Nothing is tagged 2.0.0.

* Assign Chinese fonts from Settings after a package is enabled or a 文风云 face is confirmed. Keys on `wpfonts_settings` are `cjk_body`, `cjk_heading`, and `cjk_fallback`. Empty body and heading follow `cjk_fallback`. Stored values are `pkg:{package id}` or `cloud:{wenfeng-* token}`. The printed CSS name is the package family, or the confirmed cloud `css_face`.

* Keep two paths. 文风云 prints one allowlisted stylesheet for each confirmed cloud face. 文风字体包 hosts a package the site already downloaded, and serves that package's woff2 from this site. The paths do not depend on each other. The plugin does not upload font files to the Windfonts console, and it does not slice fonts.
* Store the font directory at uploads/wpfonts/catalog.json. Opening settings does not call app.windfonts.com. WP-Cron refreshes the snapshot about once a month. A failed refresh keeps the previous file. A new install queues one immediate refresh when none exists yet. The saved value is fontFamily (`wenfeng-*`).
* On install, align the package id, the Font Library slug, and the Global Styles slug to `wenfeng-*` when metadata or the first @font-face names `WF-*`, `windfonts-*`, or `wenfeng-*`. `WF-*` and `windfonts-*` become that same short name. A name that is already `wenfeng-*` stays, lowercased. `local.css` is rewritten so font-family matches. woff2 file names are not renamed. When neither source names a catalog family, the zip file name is kept, and the Font Library slug keeps the `wpfonts-pkg-` prefix. The name on the screen can stay the metadata label or the zip file name. Packages already installed are left in place.
* Register enabled on-site packages in the Font Library as 文风字体 (Windfonts in English) and confirmed cloud faces as 文风云, ahead of the system collections. Settings can turn off 系统 · 中文, 系统 · 英文, 系统 · 日文, and 系统 · 韩文. An unchecked language is not registered. Settings screens still say 文风字体包.
* Add a block inspector panel, 文派字库, for the current block. It lists enabled packages and every confirmed cloud face. It does not add an admin-bar item, and opening it does not search the directory.
* Copy those families into the user Global Styles post (`wp_global_styles`). Theme files, including theme.json, are not edited. Removing one cloud face, or disabling a package, removes only the entry this plugin wrote. Uninstall does not delete Global Styles.
* Save more than one confirmed 文风云 face. The Font Library and the block inspector list every confirmed face. A site that stored one face in 2.0.0-rc.6 keeps that face.
* On the packages screen, say when local.css is missing, when that CSS has no usable woff2, or when an enabled package would not load. An unreadable uploads directory is not described as a broken package.
* Export and import enabled package metadata and confirmed cloud faces from Tools. The file does not contain the API key. A file that still contains a key is refused.
* When a confirmed stylesheet is printed, the front also prints the fixed Windfonts license note (`CDN_LICENSE_NOTE`). That sentence is not paraphrased. The API key is not printed in HTML, and it is not included in a settings export.
* After a font package is installed or replaced, WordPress runs `wpfonts_package_installed`. Before that package's directory is removed, it runs `wpfonts_package_deleted`. The directory is still on disk for the second action, and the package is still in the list. The directory and the list entry are removed after the action returns. Listeners receive one array: `action` (`install`, `reinstall`, or `delete`), `slug`, `package_id`, `dir`, `uploads_rel`, `local_css`, and `files`. `files` may be an empty list. Read `dir` when you need the files that are there. Installing the same package id again replaces that package and does not remove the list entry first.
* Install, replace, and delete a package from WP-CLI without a browser upload: `wp fonts package install <zip>`, `wp fonts package delete <slug-or-id>`, and `wp fonts package list`. The packages screen still accepts a browser upload.

= 2.0.0-rc.10 =
* Assign Chinese fonts from Settings. `wpfonts_settings` stores `cjk_body`, `cjk_heading`, and `cjk_fallback`. An empty body or heading uses `cjk_fallback`. Values are `pkg:{package id}` or `cloud:{wenfeng-* token}`.
* Append `cjk_fallback` to Latin stacks in the generated global stylesheet: `--wp--preset--font-family--*` variables and `font-family` declarations, including `.has-*-font-family` rules that use `!important`. The Chinese family is inserted before the first generic family (`system-ui`, `sans-serif`, and the other generics). `@font-face` names are not changed.
* Print plugin stylesheet `wpfonts-cjk` on the front and in the editor. It assigns body and headings, and repeats `.has-*-font-family` rules with the fallback and `!important`, so the assignment does not depend on intact theme or user Global Styles JSON. Existing Global Styles sync for owned families is unchanged.

= 2.0.0-rc.9 =
* After a font package is installed or replaced, WordPress runs `wpfonts_package_installed`. Before the package directory is removed, it runs `wpfonts_package_deleted` while that directory is still on disk and the package is still listed. The directory and the list entry are removed after that action. The listener receives one array: `action`, `slug`, `package_id`, `dir`, `uploads_rel`, `local_css`, and `files`. `files` may be empty. Installing the same package id again replaces that package.
* Add WP-CLI commands that use the same install and delete path: `wp fonts package install <zip>`, `wp fonts package delete <slug-or-id>`, and `wp fonts package list`. A browser upload is unchanged.

= 2.0.0-rc.8 =
* Installing a 文风字体包 uses wenfeng-{short} as the package id, the Font Library slug, and the Global Styles slug when metadata or the first @font-face names WF-*, windfonts-*, or wenfeng-*. WF-* and windfonts-* become that same short name, and local.css is rewritten so font-family matches. A name that is already wenfeng-* stays, lowercased. woff2 file names are left as they are. When neither source names a catalog family, the zip file name is kept and the Font Library slug keeps the wpfonts-pkg- prefix. Packages already installed are not renamed. The plugin still does not slice fonts.

= 2.0.0-rc.7 =
* Save more than one confirmed 文风云 face. The Font Library and the block inspector list every confirmed face. A site that stored one face in 2.0.0-rc.6 keeps that face. Removing a face drops only the Global Styles entry this plugin wrote for it.
* Add settings checkboxes for 系统 · 中文, 系统 · 英文, 系统 · 日文, and 系统 · 韩文. They stay on until unchecked. An unchecked language is not registered.
* State the two paths in Help: the inspector changes the current block; enabled packages and confirmed cloud faces go into site-wide styles, then Typography.
* Explain on the packages screen, in Chinese, when local.css is missing, when that CSS has no usable woff2, or when an enabled package would not load.
* Export and import enabled package metadata and confirmed cloud faces from Tools. The file does not contain the API key. A file that still contains a key is refused.

= 2.0.0-rc.6 =
* Show the block inspector panel 文派字库. On WordPress 6.7 and later, inspector controls render only inside the block edit context, so the panel is added with the editor.BlockEdit filter. Choosing an enabled package or a confirmed cloud face sets that block's typography font family.
* Name the Font Library collection of enabled on-site packages 文风字体. English shows Windfonts. The package group inside the block inspector panel uses the same label. 文风云 is unchanged. Settings screens still say 文风字体包.
* Register 文风字体 and 文风云 before the system collections, so those tabs sit ahead of 系统 · 中文.
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
