# WPFonts

文派字库给 WordPress 字体库加上中文、英文、日文、韩文的系统字体。文风 CDN 默认关闭。开启之后，前台只多一条文风服务器上的样式链接，网页里不会出现 API Key。

这一版是 `2.0.0-rc.1`。系统字体沿用 1.3。CI 里没有真实的文风 API Key，所以还不把这次称为正式版。

WPFonts 2.0.0-rc.1 keeps the 1.3 system font stacks and adds an opt-in Windfonts CDN client. The front prints an allowlisted `<link>` only. It does not upload fonts, slice them, or fetch CSS into PHP.

## 后台

设置 → 文派字库。三页：概览、设置（简单 / 高级）、工具。帮助在页头，不是第四个页签。

壳来自 `lib/wenpai-admin-ui/`，这一份是 **0.3.1**（和 WPSlug 里同一份）。加载时谁的版本新就用谁，样式句柄固定 `wenpai-admin-ui`。套件仓库的 0.3.2 在这台环境里读不到，所以没有假装钉到 0.3.2。

## 开启 CDN 之后

- 服务器只有 `cn.windfonts.com`、`hk.windfonts.com`、`en.windfonts.com`。
- 填写了项目名，就用 `https://服务器/p/项目名/index.css`。
- 没有项目名时，使用 `https://服务器/api/css?family=字体名&subset=字符范围`。公开字体地址没有字体名时会返回 JSON 而不是 CSS，所以没填字体名、也没填项目名时，前台不加载这条链接。
- 文风 API Key 只在服务器上用于请求 `https://app.windfonts.com/api/key/status`。不进入网页，也不进入样式地址。
- 上传字体请使用 [文风控制台](https://app.windfonts.com)。插件不上传、不切分字体。
- 块主题全局样式、把字体名写入 `theme.json`，本版不做。

## 运行要求

- WordPress 6.5 或更新
- PHP 7.4 或更新
- 字体库那条路径需要块主题

## 字体栈前缀

| 文件 | 前缀 | 例子 |
| --- | --- | --- |
| `collection/chinese.json` | `cn-` | `cn-system-ui` |
| `collection/english.json` | `en-` | `en-humanist` |
| `collection/japanese.json` | `jp-` | `jp-system-ui` |
| `collection/korean.json` | `kr-` | `kr-system-ui` |

1.2 已经存进全局样式的旧 slug 不删、不改。

## 更新

插件头里的 Update URI 没有改动。更新检查仍读取 `https://updates.weixiaoduo.com/wpfonts.json`，而且只在后台、定时任务或 WP-CLI 中加载。后台不解释更新来源。

## 检查

```bash
php tests/validate-collections.php
php tests/link-allowlist.php
php tests/health-status.php
php tests/updater-gate.php
php lib/wenpai-admin-ui/scripts/wenpai-admin-lint.py --plugin-root .
```

卸载只删 `wpfonts_settings` 和 `wpfonts_api_key`。不删全局样式，也不删用户上传的字体。

CDN 关闭时，前台不为字体访问文风。开启之后，访客的浏览器会请求那一条文风服务器上的样式。
