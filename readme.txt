=== WPFonts ===
Contributors: wenpai
Tags: fonts, typography, font-library, chinese, cjk
Requires at least: 6.5
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 2.0.0-rc.22
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

System font stacks, plus an optional Windfonts CDN link. Release candidate: CI does not verify a live API key.

== Description ==

WPFonts adds Chinese, English, Japanese, and Korean system font stacks to the WordPress Font Library. Those stacks use fonts already installed on each visitor's device. The collections are named 系统 · 中文, 系统 · 英文, 系统 · 日文, and 系统 · 韩文.

Version 2.0.0-rc.22 can print one allowlisted Windfonts stylesheet for each confirmed cloud face. Settings can also assign an enabled package or a confirmed cloud face to body text, headings, and a site Chinese fallback. The fallback is appended to Latin stacks, including `.has-*-font-family` rules, by plugin CSS and a filter on the generated global stylesheet. That path does not edit theme.json or the user Global Styles post. The CDN is off until a site owner turns it on and a server-side key check succeeds. The API key is not printed in HTML, and it is not included in a settings export. The plugin does not upload font files, slice them, or pull CSS into PHP. Settings pick fontFamily values from a local JSON snapshot of the app.windfonts.com directory. Opening settings does not call that directory. A new install queues one immediate snapshot refresh when none exists yet. Enabled on-site packages and every confirmed cloud face are registered in the Font Library and copied into user Global Styles, so they show under Typography. Removing one cloud face, or disabling a package, removes only that WPFonts entry. System font collections can be turned off per language. The plugin does not edit theme files, and uninstall does not delete Global Styles. A package can also be installed, replaced, or deleted with WP-CLI (`wp fonts package`). After a successful install or replace, and before a package directory is removed, other plugins may listen for that change. This plugin does not upload the package anywhere else.

The public CSS URL needs a font name (`family`) or a project name. Without either, the front stays on system fonts. Servers are only cn.windfonts.com, hk.windfonts.com, and en.windfonts.com.

Family slugs stay language-prefixed (`cn-`, `en-`, `jp-`, `kr-`). Enabled on-site packages appear in the Font Library as 文风字体 (Windfonts in English), ahead of the system collections. Installing a package records `wenfeng-*` when the zip names `WF-*`, `windfonts-*`, or `wenfeng-*`, and rewrites that name into `local.css`. A zip that does not name a catalog family keeps its file name. The Font Library, the package list, and the block inspector show 文风· plus the font's own name, for example 文风·玄宗体. The package id and the CSS font-family stay `wenfeng-*`. Package directories already installed stay put. A stored name that is only a `wenfeng-*`, `WF-*`, or `windfonts-*` token is replaced with that visible name. The id is not renamed, and `local.css` is not rewritten again. 文风云 is unchanged. A confirmed catalog face appears there too, and both are copied into user Global Styles. Settings still call the installable packages 文风字体包. The block inspector has a thin 文派字库 panel for the current block. It does not add an admin-bar item.

The custom update checker contacts `https://updates.weixiaoduo.com/` from WordPress admin, WP-Cron, or WP-CLI. It is not loaded during ordinary front-end page views.

== Installation ==

1. Install and activate WPFonts on WordPress 6.5 or later.
2. Use a block theme and open Appearance > Editor > Styles > Typography > Fonts to install a system stack.
3. Optional: open Settings > 字库 to turn on the Windfonts CDN. The page brand stays 文派字库.

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

Draft notes for the stable release. Plugin version, WPFONTS_VERSION, and Stable tag stay at 2.0.0-rc.22 until Feng says ship. These notes describe behavior already in 2.0.0-rc.22. Nothing is tagged 2.0.0.

* Assign Chinese fonts from Settings after a package is enabled or a 文风云 face is confirmed. Keys on `wpfonts_settings` are `cjk_body`, `cjk_heading`, and `cjk_fallback`. Empty body and heading follow `cjk_fallback`. Stored values are `pkg:{package id}` or `cloud:{wenfeng-* token}`. The printed CSS name is the package family, or the confirmed cloud `css_face`.

* Keep two paths. 文风云 prints one allowlisted stylesheet for each confirmed cloud face. 文风字体包 hosts a package the site already downloaded, and serves that package's woff2 from this site. The paths do not depend on each other. The plugin does not upload font files to the Windfonts console, and it does not slice fonts.
* Store the font directory at uploads/wpfonts/catalog.json. Opening settings does not call app.windfonts.com. WP-Cron refreshes the snapshot about once a month. A failed refresh keeps the previous file. A new install queues one immediate refresh when none exists yet. The saved value is fontFamily (`wenfeng-*`). Before that value is saved or shown, aliases from the vendored storefront list (`data/catalog-allowlist.json`) are applied, and any fontFamily that is still not on that list is dropped. Typing a `wenfeng-*` token into the family field is unchanged. The list is vendored for now.
* On install, align the package id, the Font Library slug, and the Global Styles slug to `wenfeng-*` when metadata or the first @font-face names `WF-*`, `windfonts-*`, or `wenfeng-*`. `WF-*` and `windfonts-*` become that same short name. A name that is already `wenfeng-*` stays, lowercased. `local.css` is rewritten so font-family matches. woff2 file names are not renamed. When neither source names a catalog family, the zip file name is kept, and the Font Library slug keeps the `wpfonts-pkg-` prefix. The name on the screen is 文风· plus the font's own name when metadata, the matching catalog row, or the leading CSS comment has one. Otherwise the screen keeps the package id. Packages already installed keep their id and directory. A stored name that is empty, equal to that id, or only a `wenfeng-*`, `WF-*`, or `windfonts-*` token is filled in the same way, without rewriting `local.css`.
* Register enabled on-site packages in the Font Library as 文风字体 (Windfonts in English) and confirmed cloud faces as 文风云, ahead of the system collections. Settings can turn off 系统 · 中文, 系统 · 英文, 系统 · 日文, and 系统 · 韩文. An unchecked language is not registered. Settings screens still say 文风字体包.
* Add a block inspector panel, 文派字库, for the current block. It lists enabled packages and every confirmed cloud face. It does not add an admin-bar item, and opening it does not search the directory.
* Copy those families into the user Global Styles post (`wp_global_styles`). Theme files, including theme.json, are not edited. Removing one cloud face, or disabling a package, removes only the entry this plugin wrote. Uninstall does not delete Global Styles.
* Save more than one confirmed 文风云 face. The Font Library and the block inspector list every confirmed face. A site that stored one face in 2.0.0-rc.6 keeps that face.
* On the packages screen, say when local.css is missing, when that CSS has no usable woff2, or when an enabled package would not load. An unreadable uploads directory is not described as a broken package.
* Export and import enabled package metadata and confirmed cloud faces from Tools. The file does not contain the API key. A file that still contains a key is refused.
* When a confirmed stylesheet is printed, the front also prints the fixed Windfonts license note (`CDN_LICENSE_NOTE`). That sentence is not paraphrased. The API key is not printed in HTML, and it is not included in a settings export.
* After a font package is installed or replaced, WordPress runs `wpfonts_package_installed`. Before that package's directory is removed, it runs `wpfonts_package_deleted`. The directory is still on disk for the second action, and the package is still in the list. The directory and the list entry are removed after the action returns. If the local directory is already gone, `dir` is empty, `uploads_rel` is still set, and the filesystem is still asked to delete that prefix. Listeners receive one array: `action` (`install`, `reinstall`, or `delete`), `slug`, `package_id`, `dir`, `uploads_rel`, `local_css`, and `files`. `files` may be an empty list. Read `dir` when you need the files that are there. Installing the same package id again replaces that package and does not remove the list entry first.
* Install, replace, and delete a package from WP-CLI without a browser upload: `wp fonts package install <zip>`, `wp fonts package delete <slug-or-id>`, and `wp fonts package list`. The packages screen still accepts a browser upload.

= 2.0.0-rc.22 =
* 设置菜单的功能名改为「字库」。页顶品牌仍是「文派字库」，页脚为「文派字库（WPFonts） · {version}」加一条文档链接（https://wpcy.com/fonts）。反馈改为 https://wpcy.com/support。仍在「设置」下面，字体包页签保留，没有「服务」页签。The Settings submenu label is 字库. The shell brand stays 文派字库. The footer is the plugin name, version, and one docs link. Feedback opens https://wpcy.com/support. The page stays under Settings, the packages tab stays, and there is no 服务 tab.

= 2.0.0-rc.21 =
* 目录刷新的 HTTP 超时从 6 秒改为 30 秒。分页的 app.windfonts.com 在较慢的主机上经常超过 6 秒，内置刷新会停在旧快照。店面名单、`status=published` 和别名不变。Catalog refresh waits 30 seconds for each directory page. A 6 second limit often expired on a slow host and left the built-in refresh stale. The storefront allowlist, `status=published`, and aliases are unchanged.

= 2.0.0-rc.20 =
* 设置里的字体目录，以及保存下来的目录快照，先套用随插件附带的店面别名，再丢掉不在店面名单里的 fontFamily。拉取目录时带 `status=published`，页码仍在。行上如果还有 status，不是 published 的（归档、下线）直接丢掉。`wenfeng-zhi-mang-xing` 在没有 status 时收成 `wenfeng-zmxs`。Comfortaa、Cormorant、Cuprum 这类假家族不再出现在选用列表里。手填的 `wenfeng-*` 不受这份名单限制。名单在 `data/catalog-allowlist.json`，这一版先随插件附带。仍从 app.windfonts.com 拉取目录。Catalog rows and snapshot refreshes apply the vendored storefront aliases, then drop any fontFamily that is not on that list. Refresh requests `status=published` and still sends the page. A row that carries any other status is dropped. Without a status, `wenfeng-zhi-mang-xing` becomes `wenfeng-zmxs`. Fake families such as Comfortaa, Cormorant, and Cuprum are not offered. A typed `wenfeng-*` token is still accepted. The allowlist is vendored for now at `data/catalog-allowlist.json`. Refresh still fetches app.windfonts.com.

= 2.0.0-rc.19 =
* 装包详情只显示 card 长条（1200×280，`cardPreviewUrl` / `card.webp`）。不再叠 `fontpreview.webp`。目录勾选仍用 card。字体库仍用 preview。card 图缺失时才显示实时字形预览。字段名仍是 cardPreviewUrl 和 fontPreviewUrl。The installed package detail shows only the card strip. It does not render fontpreview. Catalog cards stay on card. The Font Library stays on preview. The live pangram appears only when the card image is missing.

= 2.0.0-rc.18 =
* 装包页字形预览请求小写 `fontpreview.webp`（以及 png）。字段名仍是 fontPreviewUrl。card、preview、og 的路径不变。The installed-package glyph preview loads `fontpreview.webp`. The field stays fontPreviewUrl.

= 2.0.0-rc.17 =
* 概览里未指定中文回退时，说明站点先用系统中文；安装文风字体包或开启文风云后，到设置里选一个。Empty Chinese fallback copy says the site uses system Chinese until a package is installed or 文风云 is on, then the choice is made in Settings.
* 文风云「选用字体」勾选沿用文派后台的 choice 卡片，样张为整条 card，不再裁成方块。The cloud face picker uses the existing choice cards. Card specimens stay full width with object-fit: contain.
* 装包页的长句样张使用 fontPreview（720×400，webp 然后 png）。字体库仍用 preview。The package screen shows fontPreview for the long sentence. The Font Library still uses preview. Derived like the other specimens, including the xxzt and syhtcjk folder aliases.
* 本地目录已经空了的字体包，删除时仍带上 uploads_rel，并仍请求文件系统删掉这个前缀。Deleting a package whose local directory is already empty still passes uploads_rel and still asks the filesystem to remove that prefix.

= 2.0.0-rc.16 =
* 装包页样张改用 card 长条（1200×280），字体库仍用 preview. Installed package cards use the card strip (1200×280). The Font Library still uses preview.

= 2.0.0-rc.15 =
* 装包短码与 CDN fonts-packages 目录别名（xxzt→xzt、syhtcjk→syhtsc）以免样张 404. Alias an installed package short onto the CDN fonts-packages folder (xxzt→xzt, syhtcjk→syhtsc) so the specimen does not 404. CSS and the package id stay wenfeng-*.

= 2.0.0-rc.14 =
* 文风字体 and 文风云 specimens prefer the CDN image at `https://{existing host}/fonts-packages/{norm}/preview/preview.webp`. Cards use `card.webp` in that same directory. `{norm}` is the lowercase short after `wenfeng-` (`wenfeng-xzt` → `xzt`). The host is the site region from the existing list (`cn.windfonts.com`, `hk.windfonts.com`, `en.windfonts.com`). No second CDN host is introduced.
* Prefer `.webp`. If that image is missing, try the `.png` sibling, then the display-name SVG or the existing glyph sample. Previews do not download font files.
* A pack `preview`, `previewUrl`, `preview_url`, or `assets.previewUrl` still wins over the derived URL. The catalog snapshot still stores only `name` and `fontFamily`. The settings screen adds `previewUrl` and `cardPreviewUrl` when it reads a row.

= 2.0.0-rc.13 =
* Font Library faces for a sliced package collapse to one row per font-weight and font-style. The front and the package screen still load the full local.css, including every unicode-range slice.
* 文风字体 families include a preview image. A pack may set preview, previewUrl, or preview_url to an https image in meta.json, metadata.json, or font.json. When that URL is missing, the plugin serves an SVG of the display name at `?wpfonts_specimen={package id}`.
* The package screen labels 字形预览 and paints it with the package font-family. Confirmed 文风云 rows do the same when a CSS face is known. The style-link card is labeled 链接文字（不加载字体）.

= 2.0.0-rc.12 =
* Show 文风· plus the font's own name in the Font Library, the package list, and the block inspector. Example: 文风·玄宗体. The package id and the CSS font-family stay `wenfeng-{short}`.
* On install, take that name from package metadata, then from the catalog row for the same family, then from the title before " - " in the first CSS comment. A `wenfeng-*`, `WF-*`, or `windfonts-*` token is not used as the name. When none of those is a real name, the screen keeps the package id.
* Packages already installed with a token for a name are updated the same way from `local.css` and the catalog. The id stays, and `local.css` is not rewritten again.

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
