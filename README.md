# WPFonts

文派字库给 WordPress 字体库加上中文、英文、日文、韩文的系统字体。文风云默认关闭。开启之后，前台只多一条文风服务器上的样式链接，网页里不会出现 API Key。

这一版是 `2.0.0-rc.4`。系统字体沿用 1.3。CI 里没有真实的文风 API Key，所以还不把这次称为正式版。

WPFonts 2.0.0-rc.4 keeps the 1.3 system font stacks and adds an opt-in Windfonts client. The front prints an allowlisted `<link>` only. It does not upload fonts, slice them, or fetch CSS into PHP.

Settings → WPFonts has four screens: Overview, Settings, Font packages, and Tools. Help stays in the header. Enabled packages register in the Font Library as 文风字体包, using the woff2 files stored at install. 文风云 is registered only after the saved stylesheet URL has opened; the Font Library name is the face declared in that CSS, not the catalog token. Installing either writes Global Styles the usual WordPress way. A saved URL that returns 404 is not embedded and is not described as loading. The plugin does not edit theme files, and uninstall does not delete Global Styles. The block inspector has a 文派字库 panel for the current block. It does not add an admin-bar item. A new install with no catalog snapshot queues one immediate refresh; activation itself does not call the directory.

## 后台

设置 → 文派字库。四页：概览、设置（简单 / 高级）、字体包、工具。帮助在页头，不是第五个页签。

壳来自 `lib/wenpai-admin-ui/`，这一份是 **0.3.1**（和 WPSlug 里同一份）。加载时谁的版本新就用谁，样式句柄固定 `wenpai-admin-ui`。套件仓库的 0.3.2 在这台环境里读不到，所以没有假装钉到 0.3.2。

## 文风字体包

把文风已切好的字体包装到本站，由本站托管。切分字体仍在文风完成，插件不切字。安装后的文件在 `wp-content/uploads/wpfonts/packages/`，和文风云开关互不影响。

已启用的包会登记到字体库的「文风字体包」。字体文件用的是安装时已经校验过的 woff2。停用或删除后，字体库里不再出现。在字体库里安装，会按 WordPress 自己的方式写入全站样式；插件不改主题文件，卸载时也不删全站样式。

## 编辑器

块编辑器里选中区块后，侧栏有「文派字库」。可以把已启用的文风字体包，或目录里的文风字体名，用在当前区块。不占顶栏。全站安装仍走字体库。

## 选用字体

设置页只读本站这份目录：`wp-content/uploads/wpfonts/catalog.json`。打开设置不会请求文风。文件形如 `{ "updated_at": unix, "fonts": [ { "name", "fontFamily" } ] }`。WP-Cron 使用自定义 `monthly` 间隔（约 30 天）拉取 `https://app.windfonts.com/api/fonts` 的全部分页并写成这一份快照。新装站点如果还没有这份文件，会再排一次马上执行的任务去拉取；激活本身不发请求，失败也不会写一份空目录。站长也可以点「更新字体目录」立刻更新，两次之间至少隔 60 秒。选中后写入 `fontFamily`（`wenfeng-*`）。更新失败不会盖掉已有快照。没有快照时仍可手填字体名。页面上的说明是：目录保存在本站，大约每月自动更新一次；也可点按钮立刻更新。字符范围四档写在选项上。项目名收在「项目与白名单」里，域名白名单到文风控制台配置。旧的按页 transient 不再读取。

目录里的 `fontFamily` 是样式地址用的字体名，不一定等于 CSS 里的 `font-family`。保存或再检查时会看一眼这条地址。打不开就不会放到前台，也不会标成会加载。字体库里的「文风云」只登记已经确认能打开、并且读到了真实字体名的那一个。

## 开启文风云之后

- 服务器只有 `cn.windfonts.com`、`hk.windfonts.com`、`en.windfonts.com`。
- 填写了项目名，就用 `https://服务器/p/项目名/index.css`。
- 没有项目名时，使用 `https://服务器/api/css?family=字体名&subset=字符范围`。公开字体地址没有字体名时会返回 JSON 而不是 CSS，所以没填字体名、也没填项目名时，前台不加载这条链接。
- 文风 API Key 只在服务器上用于请求 `https://app.windfonts.com/api/key/status`。不进入网页，也不进入样式地址。
- 插件不切字。已切好的包在「字体包」页安装到本站。
- 全站样式请在字体库里安装。插件不改主题文件，卸载时也不删除全站样式。

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
php tests/font-library.php
php tests/updater-gate.php
php lib/wenpai-admin-ui/scripts/wenpai-admin-lint.py --plugin-root .
```

卸载删除 `wpfonts_settings`、`wpfonts_api_key`、`wpfonts_packages`，以及 `uploads/wpfonts/catalog.json`。不删全局样式，也不删已安装的文风字体包。

CDN 关闭时，前台不为字体访问文风。开启之后，访客的浏览器会请求文风服务器上的样式。全站样式里另选的文风字体名，也会各加一条同样的链接。
