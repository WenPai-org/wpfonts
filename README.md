# WPFonts

文派字库给 WordPress 字体库加上系统字体，分成「系统 · 中文」「系统 · 英文」「系统 · 日文」「系统 · 韩文」。文风云默认关闭。开启之后，每条已确认的文风云字体各加一条文风服务器上的样式链接，网页里不会出现 API Key。

这一版是 `2.0.0-rc.8`。系统字体沿用 1.3，四种语言都可以在设置里单独关掉。CI 里没有真实的文风 API Key，所以还不把这次称为正式版。下面的 `2.0.0` 一节是正式版说明草稿，版本号和 Stable tag 都没有改。

WPFonts 2.0.0-rc.8 keeps the 1.3 system font stacks and adds an opt-in Windfonts client. The `2.0.0` section below is a draft. The version and Stable tag stay at `2.0.0-rc.8`. The front prints one allowlisted `<link>` for each confirmed cloud face. It does not upload fonts, slice them, or fetch CSS into PHP.

Settings → WPFonts has four screens: Overview, Settings, Font packages, and Tools. Help stays in the header. Enabled packages register in the Font Library as 文风字体 (Windfonts in English), using the woff2 files stored at install. That tab, and 文风云, are registered before the system collections. 文风云 is registered only after the saved stylesheet URL has opened; the Font Library name is the face declared in that CSS, not the catalog token. Those same families are copied into the user Global Styles post, so Appearance → Styles lists them under typography. Disabling a package, deleting it, or losing the confirmed stylesheet removes only the entries this plugin wrote. A saved URL that returns 404 is not embedded and is not described as loading. The plugin does not edit theme files, and uninstall does not delete Global Styles. The block inspector has a 文派字库 panel for the current block. It does not add an admin-bar item. A new install with no catalog snapshot queues one immediate refresh; activation itself does not call the directory.

## 后台

设置 → 文派字库。四页：概览、设置（简单 / 高级）、字体包、工具。帮助在页头，不是第五个页签。

壳来自 `lib/wenpai-admin-ui/`，这一份是 **0.3.1**（和 WPSlug 里同一份）。加载时谁的版本新就用谁，样式句柄固定 `wenpai-admin-ui`。套件仓库的 0.3.2 在这台环境里读不到，所以没有假装钉到 0.3.2。

## 文风字体包

把文风已切好的字体包装到本站，由本站托管。切分字体仍在文风完成，插件不切字。安装后的文件在 `wp-content/uploads/wpfonts/packages/`，和文风云开关互不影响。

安装时，包的短名尽量用目录里的 `wenfeng-*`。旧包里的 `WF-*`、`windfonts-*` 会换成这个短名，并写进 `local.css` 的 `font-family`。已经是 `wenfeng-*` 的只做小写。认不出目录名时，仍用 zip 文件名，字体库里继续用 `wpfonts-pkg-` 前缀。已经装好的包不改目录。列表里仍显示原来的字体名。woff2 文件名不动。

已启用的包会登记到字体库的「文风字体」，排在系统字体前面。英文界面写作 Windfonts。字体文件用的是安装时已经校验过的 woff2。停用或删除后，字体库里不再出现。启用后，字体名会写进全站样式，外观 → 样式里可以直接选用。停用或删除时，只去掉这项，其它全站样式不动。插件不改主题文件，卸载时也不删全站样式。设置页仍称这些包为文风字体包。

## 编辑器

块编辑器里选中区块后，侧栏有「文派字库」。分组名是「文风字体」和「文风云」。可以把已启用的包，或每一条已确认的文风云字体，用在当前区块。侧栏只改当前区块。打开侧栏不会搜索文风目录。不占顶栏。这些字体也会出现在全站样式里，再到外观 → 样式 → 排版里选用。英文界面里这一组写作 Windfonts。

## 2.0.0

正式版说明草稿，还没有打标签，也没有改 Stable tag。插件版本和 `WPFONTS_VERSION` 仍是 `2.0.0-rc.8`。下面按 rc.8 已经有的行为来写。

Draft notes for stable 2.0.0. Nothing is tagged. `Version`, `WPFONTS_VERSION`, and Stable tag stay at `2.0.0-rc.8`.

- 两条路并存。文风云给每条已确认的字体加一条文风服务器上的样式链接。文风字体包把站长已经下载好的包装到本站，由本站托管 woff2。两条路互不影响。插件不往文风控制台上传字体，也不切字。
- 字体目录写在 `wp-content/uploads/wpfonts/catalog.json`。打开设置不会请求文风。WP-Cron 大约每月更新一次。更新失败会留下上一份。新装站点还没有这份文件时，会排一次马上执行的任务去拉取。选中后写入 `fontFamily`（`wenfeng-*`）。
- 安装文风字体包时，包 id、字体库和全站样式的短名对齐目录里的 `wenfeng-*`。`WF-*`、`windfonts-*` 会换成同一个短名；已经是 `wenfeng-*` 的只做小写。这个短名会写进 `local.css` 的 `font-family`。woff2 文件名不动，也不重命名字体文件。包内元数据和样式都认不出目录名时，仍用 zip 文件名，字体库 slug 继续用 `wpfonts-pkg-` 前缀。页面上的显示名可以仍是元数据里的中文名，或 zip 文件名。已经装好的旧包不改目录。
- 已启用的包登记为字体库里的「文风字体」，英文界面写作 Windfonts。已确认的云登记为「文风云」。这两项排在系统字体前面。设置里可以分别关掉「系统 · 中文」「系统 · 英文」「系统 · 日文」「系统 · 韩文」。关掉的那一组不会登记。设置页仍称这些包为文风字体包。
- 块侧栏有「文派字库」，只改当前区块。列出已启用的包，以及每一条已确认的文风云字体。不占顶栏。打开侧栏也不会搜索文风目录。
- 这些字体名写入用户的全站样式帖（`wp_global_styles`）。不改主题文件，也不写 `theme.json`。去掉一条云字体，或停用一个包，只拿掉这一项。卸载不删全站样式。
- 文风云可以保存多条已确认的 `wenfeng-*`。字体库和侧栏都会列出。从 2.0.0-rc.6 只存了一条的站点，会继续用那一条。
- 字体包页面会写明原因：找不到 `local.css`、样式里没有可用的 woff2，或已经启用但不会加载。读不到上传目录时，不把正常的包说成坏了。
- 工具页可以导出、导入已启用的字体包记录和已确认的文风云。文件里没有 API Key。文件里如果还带着密钥，整份拒绝，不会写入。
- 前台打印已确认的样式链接时，同时打印文风授权说明（`CDN_LICENSE_NOTE`）。这句话不改写。API Key 不进入网页，也不进入导出文件。

## 2.0.0-rc.8

- 安装文风字体包时，包 id、字体库和全站样式的短名对齐目录里的 `wenfeng-*`。`WF-*`、`windfonts-*` 会换成同一个短名，并写进 `local.css` 的 `font-family`。已经是 `wenfeng-*` 的只做小写。认不出目录名时仍用 zip 文件名，字体库 slug 继续用 `wpfonts-pkg-` 前缀。已经装好的旧包不改目录。页面上的名字可以仍是原来的字体名。woff2 文件名不动。插件仍然不切字。

## 2.0.0-rc.7

- 文风云可以保存多条已确认的 `wenfeng-*`。字体库和侧栏都会列出。从 2.0.0-rc.6 只存了一条的站点，会继续用那一条。去掉一条时，只从全站样式里拿掉这一条。
- 设置里可以分别关掉「系统 · 中文」「系统 · 英文」「系统 · 日文」「系统 · 韩文」。默认都开。关掉的那一组不会登记到字体库。
- 帮助里用两句话写清：侧栏改当前区块；启用的包和已确认的云进入全站样式，再到排版里选用。
- 字体包页面会写明原因：找不到 `local.css`、样式里没有可用的 woff2，或已经启用但不会加载。读不到上传目录时不把正常的包说成坏了。
- 工具页可以导出、导入已启用的字体包记录和已确认的文风云。文件里没有 API Key。文件里如果还带着密钥，整份拒绝，不会写入。

工具页的导出沿用文派对象存储（wpsss）工具页的做法：`admin-post` 下载 JSON，卡片上写明密钥不会出现在文件里。导入沿用文派素格（wpslug）的做法：密钥字段丢掉，不写入本站。勾选确认后再导入，和本插件删除字体包时的闸门一样。

## 选用字体

设置页只读本站这份目录：`wp-content/uploads/wpfonts/catalog.json`。打开设置不会请求文风。文件形如 `{ "updated_at": unix, "fonts": [ { "name", "fontFamily" } ] }`。WP-Cron 使用自定义 `monthly` 间隔（约 30 天）拉取 `https://app.windfonts.com/api/fonts` 的全部分页并写成这一份快照。新装站点如果还没有这份文件，会再排一次马上执行的任务去拉取；激活本身不发请求，失败也不会写一份空目录。站长也可以点「更新字体目录」立刻更新，两次之间至少隔 60 秒。选中后写入 `fontFamily`（`wenfeng-*`）。更新失败不会盖掉已有快照。没有快照时仍可手填字体名。页面上的说明是：目录保存在本站，大约每月自动更新一次；也可点按钮立刻更新。字符范围四档写在选项上。项目名收在「项目与白名单」里，域名白名单到文风控制台配置。旧的按页 transient 不再读取。

目录里的 `fontFamily` 是样式地址用的字体名，不一定等于 CSS 里的 `font-family`。保存或再检查时会看一眼这条地址。打不开就不会放到前台，也不会标成会加载。字体库里的「文风云」只登记已经确认能打开、并且读到了真实字体名的那一个。确认通过后，这个字体名会写进全站样式；不再确认时，只从全站样式里去掉它。

## 开启文风云之后

- 服务器只有 `cn.windfonts.com`、`hk.windfonts.com`、`en.windfonts.com`。
- 填写了项目名，就用 `https://服务器/p/项目名/index.css`。
- 没有项目名时，使用 `https://服务器/api/css?family=字体名&subset=字符范围`。公开字体地址没有字体名时会返回 JSON 而不是 CSS，所以没填字体名、也没填项目名时，前台不加载这条链接。
- 文风 API Key 只在服务器上用于请求 `https://app.windfonts.com/api/key/status`。不进入网页，也不进入样式地址。
- 插件不切字。已切好的包在「字体包」页安装到本站。
- 已启用的文风字体包，以及确认能打开的文风云，会出现在全站样式里。插件不改主题文件，卸载时也不删除全站样式。

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
php tests/global-styles.php
php tests/phase4.php
php tests/updater-gate.php
php lib/wenpai-admin-ui/scripts/wenpai-admin-lint.py --plugin-root .
```

卸载删除 `wpfonts_settings`、`wpfonts_api_key`、`wpfonts_packages`，以及 `uploads/wpfonts/catalog.json`。不删全局样式，也不删已安装的文风字体包。

CDN 关闭时，前台不为字体访问文风。开启之后，访客的浏览器会请求文风服务器上的样式。全站样式里另选的文风字体名，也会各加一条同样的链接。
