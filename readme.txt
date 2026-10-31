=== WPFonts ===
Contributors: wenpai
Tags: fonts, typography, font-library, chinese, cjk
Requires at least: 6.5
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-friendly CJK and Latin system font stacks for the WordPress Font Library.

== Description ==

WPFonts adds 40 Chinese, English, Japanese, and Korean system font stacks to the WordPress Font Library. The collections use fonts already installed on each visitor's device.

The plugin does not inject front-end CSS, download font binaries, contact font CDNs, store settings, or collect personal data. WordPress stores a chosen stack in Global Styles/theme.json only after an authorized user selects it in the Font Library.

Family slugs are language-prefixed (`cn-`, `en-`, `jp-`, `kr-`) so Chinese and English no longer share generic slugs such as `system-ui`. Installing 1.3 does not rewrite or delete Global Styles created with older slugs.

A Windfonts web-font / CDN client is future and optional. Version 1.3 has no settings page, API key, or stylesheet embed.

The custom update checker contacts `https://updates.weixiaoduo.com/` from WordPress admin, WP-Cron, or WP-CLI. It is not loaded during ordinary front-end page views. The plugin header Update URI remains `https://updates.wenpai.net`.

== Installation ==

1. Install and activate WPFonts on WordPress 6.5 or later.
2. Use a block theme and open Appearance > Editor > Styles > Typography > Fonts.
3. Open a WPFonts collection and install a system stack.
4. Assign the installed stack through Global Styles.

On multisite, WPFonts supports either per-site activation or network activation. It creates no site or network options.

== Frequently Asked Questions ==

= Does WPFonts download or host fonts? =

No. Version 1.3 contains system font stacks only. Rendering varies according to fonts installed on the visitor's device.

= Does deactivation remove typography choices? =

No. Installed Global Styles belong to WordPress. Deactivation removes the collections but does not delete user-created Global Styles or uploaded fonts.

= Does WPFonts work with classic themes? =

The collections target the WordPress Font Library and Site Editor. A block theme is required for the intended user interface.

= Is this the Windfonts CDN client? =

No. This version only registers local system stacks. A web-font / CDN client would be a later, optional feature and is not included here.

== Changelog ==

= 1.3.0 =
* Require WordPress 6.5 and PHP 7.4, matching the Font Library collection API.
* Prefix every font-family slug with cn-, en-, jp-, or kr- so collections do not share generic slugs. Existing Global Styles are left untouched.
* Allow per-site or network activation on multisite.
* Load the custom updater only for wp-admin, WP-Cron, and WP-CLI.
* Remove the unused UpdatePulse licensing library.
* Document that the plugin ships system stacks, not a font CDN.

= 1.2.0 =
* Register Chinese, English, Japanese, and Korean system font collections.
