# WPFonts

文派字库给 WordPress 字体库加上中文、英文、日文、韩文的系统字体栈。文风 CDN 默认关着。打开之后，前台只多一条允许主机上的样式，网页里看不到 API Key。

这一版是 `2.0.0-rc.1`。系统字体栈沿用 1.3。CDN 能在本地对上地址和健康检查的判断，CI 里没有一把真 Key，所以还不把这次叫正式版。

WPFonts 2.0.0-rc.1 keeps the 1.3 system font stacks and adds an opt-in Windfonts CDN client. The front prints an allowlisted `<link>` only. It does not upload fonts, slice them, or fetch CSS into PHP.

## 后台

设置 → 文派字库。三页：概览、设置（简单 / 高级）、工具。帮助在页头，不是第四个页签。

壳来自 `lib/wenpai-admin-ui/`，这一份是 **0.3.1**（和 WPSlug 里同一份）。加载时谁的版本新就用谁，样式句柄固定 `wenpai-admin-ui`。套件仓库的 0.3.2 在这台环境里读不到，所以没有假装钉到 0.3.2。

## 打开 CDN 之后

- 主机只有 `cn.windfonts.com`、`hk.windfonts.com`、`en.windfonts.com`。
- 填了项目，就用 `https://主机/p/项目/index.css`。
- 没填项目，就用 `https://主机/api/css?family=字体短名&subset=子集`。公开样式没有字体短名时会返回 JSON 而不是 CSS，所以没填字体短名、也没填项目时，前台不加这条 link。
- Key 只在服务器上拿去问 `https://app.windfonts.com/api/key/status`。不进网页，也不进样式地址。
- Windfonts API Key 和文派许可证不是同一个东西。更新地址仍是 `https://updates.wenpai.net`。
- 上传字体用 [文风控制台](https://app.windfonts.com)。插件里不上传、不切字。
- 块主题全局样式、把字体名写进 `theme.json`，这一版不做。

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

插件头 `Update URI` 仍是 `https://updates.wenpai.net`。更新检查仍读 `https://updates.weixiaoduo.com/wpfonts.json`，而且只在后台、定时任务或 WP-CLI 里加载。

## 检查

```bash
php tests/validate-collections.php
php tests/link-allowlist.php
php tests/health-status.php
php tests/updater-gate.php
php lib/wenpai-admin-ui/scripts/wenpai-admin-lint.py --plugin-root .
```

卸载只删 `wpfonts_settings` 和 `wpfonts_api_key`。不删全局样式，也不删用户上传的字体。

CDN 关着时，前台不为字体去访问文风。打开之后，访客的浏览器会去要那一条允许主机上的样式。
