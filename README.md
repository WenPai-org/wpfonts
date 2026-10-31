# WPFonts

文派字库 🀄️（WPFonts）向 WordPress 字体库注册中文、英文、日文、韩文的**系统字体栈**。它不打包、不下载字体文件，也不是文风（Windfonts）CDN 客户端。网页字体 / CDN 嵌入是后续可选能力；本版本没有设置页、API Key 或样式表嵌入。

WPFonts adds privacy-friendly Chinese, English, Japanese, Korean, and Latin **system font stacks** to the WordPress Font Library. It does not bundle or fetch font binaries.

## Runtime requirements

- WordPress 6.5 or later (Font Library collection API)
- PHP 7.4 or later
- A block theme for the intended Site Editor / Font Library workflow

## Family slug prefixes

Every font-family `slug` is language-prefixed so collections no longer share generic slugs such as `system-ui` or `humanist`:

| Collection file | Prefix | Example |
| --- | --- | --- |
| `collection/chinese.json` | `cn-` | `cn-system-ui` |
| `collection/english.json` | `en-` | `en-humanist` |
| `collection/japanese.json` | `jp-` | `jp-system-ui` |
| `collection/korean.json` | `kr-` | `kr-system-ui` |

Japanese and Korean slugs that already ended in `-jp` or `-kr` drop that suffix before the prefix is added (`system-ui-jp` becomes `jp-system-ui`). Existing Global Styles are not migrated or deleted. A site that already installed a 1.2 slug keeps that WordPress-owned record; the Font Library will show the prefixed slug as a separate family.

## Updates

The plugin header `Update URI` stays `https://updates.wenpai.net`. Plugin Update Checker still reads `https://updates.weixiaoduo.com/wpfonts.json`, and only from wp-admin, WP-Cron, or WP-CLI. Front-end page views do not load that library.

## Development checks

```bash
php tests/validate-collections.php
php tests/updater-gate.php
find . -type f -name "*.php" -not -path "./lib/*" -print0 | xargs -0 -n1 php -l
wp eval-file tests/wp-runtime-smoke.php --path=/path/to/wordpress
```

The runtime smoke test needs WordPress 6.5+ with this plugin active. It checks four collections, 40 language-prefixed families, the authenticated Font Collections REST response, and a `theme.json` font-family round trip.

## Privacy and persistence

Front-end visitors do not contact a font CDN: stacks resolve to fonts already installed on their device. The plugin injects no CSS until WordPress itself persists an administrator-selected family into Global Styles. Deactivation or uninstall does not delete Global Styles or uploaded fonts. There is no uninstall routine.

See `docs/AUDIT_2026-08-09.md` for the supply-chain limits of the custom updater and for follow-ups left to a later phase.
