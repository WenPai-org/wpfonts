# WPFonts

文派字库给 WordPress 字体库加上中文、英文、日文、韩文的系统字体。文风云默认关闭。开启之后，前台只多一条文风服务器上的样式链接，网页里不会出现 API Key。

这一版是 `2.0.0-rc.3`。系统字体沿用 1.3。CI 里没有真实的文风 API Key，所以还不把这次称为正式版。

WPFonts 2.0.0-rc.3 keeps the 1.3 system font stacks and adds an opt-in Windfonts client. The front prints an allowlisted `<link>` only. It does not upload fonts, slice them, or fetch CSS into PHP.

## 后台

设置 → 文派字库。三页：概览、设置（简单 / 高级）、工具。帮助在页头，不是第四个页签。

壳来自 `lib/wenpai-admin-ui/`，这一份是 **0.3.1**（和 WPSlug 里同一份）。加载时谁的版本新就用谁，样式句柄固定 `wenpai-admin-ui`。套件仓库的 0.3.2 在这台环境里读不到，所以没有假装钉到 0.3.2。

## 文风字体包

把文风已切好的字体包装到本站，由本站托管。切分字体仍在文风完成，插件不切字。安装后的文件在 `wp-content/uploads/wpfonts/packages/`，和文风云开关互不影响。

## 选用字体

设置页只读本站这份目录：`wp-content/uploads/wpfonts/catalog.json`。打开设置不会请求文风。文件形如 `{ "updated_at": unix, "fonts": [ { "name", "fontFamily" } ] }`。WP-Cron 使用自定义 `monthly` 间隔（约 30 天）拉取 `https://app.windfonts.com/api/fonts` 的全部分页并写成这一份快照。站长也可以点「更新字体目录」立刻更新，两次之间至少隔 60 秒。选中后写入 `fontFamily`（`wenfeng-*`）。更新失败不会盖掉已有快照。没有快照时仍可手填字体名。页面上的说明是：目录保存在本站，大约每月自动更新一次；也可点按钮立刻更新。字符范围四档写在选项上。项目名收在「项目与白名单」里，域名白名单到文风控制台配置。旧的按页 transient 不再读取。

## 开启文风云之后

- 服务器只有 `cn.windfonts.com`、`hk.windfonts.com`、`en.windfonts.com`。
- 填写了项目名，就用 `https://服务器/p/项目名/index.css`。
- 没有项目名时，使用 `https://服务器/api/css?family=字体名&subset=字符范围`。公开字体地址没有字体名时会返回 JSON 而不是 CSS，所以没填字体名、也没填项目名时，前台不加载这条链接。
- 文风 API Key 只在服务器上用于请求 `https://app.windfonts.com/api/key/status`。不进入网页，也不进入样式地址。
- 插件不切字。已切好的包在「字体包」页安装到本站。
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
php tests/catalog.php
php tests/updater-gate.php
php lib/wenpai-admin-ui/scripts/wenpai-admin-lint.py --plugin-root .
```

卸载删除 `wpfonts_settings`、`wpfonts_api_key`、`wpfonts_packages`，以及 `uploads/wpfonts/catalog.json`。不删全局样式，也不删已安装的文风字体包。

CDN 关闭时，前台不为字体访问文风。开启之后，访客的浏览器会请求那一条文风服务器上的样式。
