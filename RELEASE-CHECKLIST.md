# 2.0.0 发布闸门

Foundry 在 Feng 说可以发正式版之前，还差下面一项。这一支不打 `2.0.0` 标签，也不把版本改成 `2.0.0`。插件版本、`WPFONTS_VERSION` 和 readme 的 Stable tag 仍是 `2.0.0-rc.20`。

Release gate before Feng says ship the stable release. This branch does not tag `2.0.0` and does not change the version to `2.0.0`. Plugin version, `WPFONTS_VERSION`, and Stable tag stay at `2.0.0-rc.20`.

## 还要做

- [ ] **zh_CN 目视。** 仍未做。在中文后台和块编辑器里看一遍：字体库「文风字体」「文风云」，系统「中文 / 英文 / 日文 / 韩文」开关，侧栏「文派字库」，字体包健康说明，工具页导出与导入（文件里没有 Key）。新装的包短名是 `wenfeng-*`，已经装好的包目录不变。

## 已经齐了

- [x] **字体包 normalize 已合并。** `2.0.0-rc.8` 已在 main。安装时把 `WF-*`、`windfonts-*` 收成 `wenfeng-*`，并写进 `local.css` 的 `font-family`。已经是 `wenfeng-*` 的只做小写。认不出目录名时仍用 zip 文件名，字体库 slug 继续用 `wpfonts-pkg-` 前缀。已经装好的旧包不改目录。woff2 文件名不动。
- [x] **字体包钩子和命令行。** `2.0.0-rc.9` 在安装或替换成功后发出 `wpfonts_package_installed`。`wpfonts_package_deleted` 在删除目录之前发出，目录当时还在。命令是 `wp fonts package install`、`wp fonts package delete`、`wp fonts package list`。
- [x] **中文回退。** `2.0.0-rc.10` 在设置里选正文、标题和中文回退。键是 `cjk_body`、`cjk_heading`、`cjk_fallback`。中文回退接在生成出来的西文栈后面，包括带 `!important` 的 `.has-*-font-family`。样式句柄 `wpfonts-cjk`，并过滤 `wp_get_global_stylesheet`。不改主题 JSON，也不把这条路交给全站样式同步。
- [x] **字体包显示名。** `2.0.0-rc.12` 字体库、字体包列表和块侧栏显示「文风·」加字体原名，例如「文风·玄宗体」。包 id 和 CSS 的 `font-family` 仍是 `wenfeng-*`。已经装好、名字仍是 `wenfeng-*`、`WF-*` 或 `windfonts-*` 的，从 `local.css` 和字体目录改显示名。不改 id，也不重写 CSS。
- [x] **切片与预览。** `2.0.0-rc.13` 字体库每个字重只列一条。前台仍加载完整 `local.css`。「文风字体」带预览图；没有包内 `preview` 时用显示名 SVG。字体包页和已确认的文风云显示字形预览。样式链接标明「链接文字（不加载字体）」。
- [x] **CDN 样张。** `2.0.0-rc.14` 文风字体和文风云优先用 `fonts-packages/{短名}/preview/preview.webp`，卡片用 `card.webp`。主机是已有的 `cn` / `hk` / `en`。webp 打不开再试 png，再回到 SVG 或字形预览。不下载字体文件。目录快照仍只存名字和 `fontFamily`。
- [x] **样张目录别名。** `2.0.0-rc.15` 装包短码与 CDN fonts-packages 目录别名（xxzt→xzt、syhtcjk→syhtsc）以免样张 404。CSS 和包 id 仍是 `wenfeng-*`。
- [x] **装包页长条样张。** `2.0.0-rc.16` 装包页样张改用 card 长条（1200×280），字体库仍用 preview。
- [x] **长句样张与空态文案。** `2.0.0-rc.17` 装包页字形预览使用 `fontPreview`。中文回退未指定时说明先用系统中文。
- [x] **字形预览文件名。** `2.0.0-rc.18` 装包页字形预览请求 CDN 上的小写 `fontpreview.webp`（以及 png）。字段名仍是 `fontPreviewUrl`。`card`、`preview`、`og` 路径不变。
- [x] **装包详情只留 card。** `2.0.0-rc.19` 装包详情只显示 `card.webp`（1200×280）。不再叠 `fontpreview.webp`。目录勾选仍用 card。字体库仍用 preview。card 图缺失时才显示实时字形预览。字段名仍是 `cardPreviewUrl` / `fontPreviewUrl`。
- [x] **目录店面名单。** `2.0.0-rc.20` 选用字体和目录快照先套用 `data/catalog-allowlist.json` 里的别名，再丢掉不在店面名单里的 `fontFamily`。拉取带 `status=published`，页码仍在。行上的 status 不是 published 时丢掉。`wenfeng-zhi-mang-xing` 在没有 status 时收成 `wenfeng-zmxs`。Comfortaa、Cormorant、Cuprum 不出现。手填的 `wenfeng-*` 仍可用。名单这一版先随插件附带。仍从 app.windfonts.com 拉取。
- [x] **文档 cocobook 已对齐 rc.8。** 不挡这次闸门。本支只补仓库里的 `readme.txt` / `README.md` 草稿和这份清单。

## 这一支不要做

- 不要把 readme 的 Stable tag 改成 `2.0.0`。
- 不要改 `Version` 和 `WPFONTS_VERSION`。现在是 `2.0.0-rc.20`，不要写成 `2.0.0`。
- 不要打 `2.0.0` git 标签。
