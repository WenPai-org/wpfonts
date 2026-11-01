# 2.0.0 发布闸门

Foundry 在 Feng 说可以发正式版之前，还差下面一项。这一支不打 `2.0.0` 标签，也不把版本改成 `2.0.0`。插件版本、`WPFONTS_VERSION` 和 readme 的 Stable tag 仍是 `2.0.0-rc.10`。

Release gate before Feng says ship the stable release. This branch does not tag `2.0.0` and does not change the version to `2.0.0`. Plugin version, `WPFONTS_VERSION`, and Stable tag stay at `2.0.0-rc.10`.

## 还要做

- [ ] **zh_CN 目视。** 仍未做。在中文后台和块编辑器里看一遍：字体库「文风字体」「文风云」，系统「中文 / 英文 / 日文 / 韩文」开关，侧栏「文派字库」，字体包健康说明，工具页导出与导入（文件里没有 Key）。新装的包短名是 `wenfeng-*`，已经装好的包目录不变。

## 已经齐了

- [x] **字体包 normalize 已合并。** `2.0.0-rc.8` 已在 main。安装时把 `WF-*`、`windfonts-*` 收成 `wenfeng-*`，并写进 `local.css` 的 `font-family`。已经是 `wenfeng-*` 的只做小写。认不出目录名时仍用 zip 文件名，字体库 slug 继续用 `wpfonts-pkg-` 前缀。已经装好的旧包不改目录。woff2 文件名不动。
- [x] **字体包钩子和命令行。** `2.0.0-rc.9` 在安装或替换成功后发出 `wpfonts_package_installed`。`wpfonts_package_deleted` 在删除目录之前发出，目录当时还在。命令是 `wp fonts package install`、`wp fonts package delete`、`wp fonts package list`。
- [x] **中文回退。** `2.0.0-rc.10` 在设置里选正文、标题和中文回退。键是 `cjk_body`、`cjk_heading`、`cjk_fallback`。中文回退接在生成出来的西文栈后面，包括带 `!important` 的 `.has-*-font-family`。样式句柄 `wpfonts-cjk`，并过滤 `wp_get_global_stylesheet`。不改主题 JSON，也不把这条路交给全站样式同步。
- [x] **文档 cocobook 已对齐 rc.8。** 不挡这次闸门。本支只补仓库里的 `readme.txt` / `README.md` 草稿和这份清单。

## 这一支不要做

- 不要把 readme 的 Stable tag 改成 `2.0.0`。
- 不要改 `Version` 和 `WPFONTS_VERSION`。现在是 `2.0.0-rc.10`，不要写成 `2.0.0`。
- 不要打 `2.0.0` git 标签。
