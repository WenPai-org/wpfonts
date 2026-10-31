# 2.0.0 发布闸门

Foundry 在 Feng 说可以发之前，还差下面两项。这一支不打 `2.0.0` 标签，不合并，Stable tag 仍是 `2.0.0-rc.7`。

Release gate before Feng says ship. This branch does not tag `2.0.0`, does not merge, and leaves Stable tag at `2.0.0-rc.7`.

## 还要做

- [ ] **zh_CN 目视。** 在中文后台和块编辑器里看一遍：字体库「文风字体」「文风云」，系统「中文 / 英文 / 日文 / 韩文」开关，侧栏「文派字库」，字体包健康说明，工具页导出与导入（文件里没有 Key）。
- [ ] **字体包 normalize 已合并。** 安装时把 slug 和 family 从 `WF` 收成 `wenfeng-*` 还在另一支，可能先发成 `2.0.0-rc.8`。没合进去，或 Feng 明确从这一版拿掉之前，不要把 Stable tag 改成 `2.0.0`。

## 已经齐了

- [x] **文档 cocobook 已在 rc.7。** 不挡这次闸门。本支只补仓库里的 `readme.txt` / `README.md` 草稿。

## 这一支不要做

- 不要把 readme 的 Stable tag 改成 `2.0.0`。
- 不要改 `Version` 和 `WPFONTS_VERSION`。下一档如果先合 normalize，写成 `2.0.0-rc.8`，不要写成 `2.0.0`。
- 不要打 `2.0.0` git 标签，不要合并。
