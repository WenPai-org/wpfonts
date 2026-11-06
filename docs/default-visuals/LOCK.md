# LOCK · 文风默认视觉资产

**状态**：LOCKED（含 logo + previewLine + **甲底纯白** + **specimen 亦 L1** + **fontPreview 文案 A** + **左下角单行 `文风·中文` + EN|拼音**）  
**时间**：2026-11-02 20:14 CST 锁全甲；2026-11-02 20:39 CST 锁 logo/previewLine；**2026-11-06 ~16:30 CST（Asia/Shanghai）** 锁甲量产底 **纯白 `#FFFFFF`**（Feng t690u / t691s3186）；**~16:38 CST** specimen **加 L1**（Feng t695u；无 logo 版废弃量产）；**~16:45 CST** fontPreview **文案 A**（Feng t695s3209）；**~16:55 CST** 左下角静音 **文风·{display}**（Feng t698u）；**~17:00 CST** 左下角曾两行（t699u）；**~17:10 CST** 单行（t706u）；**~17:15 CST** 单行补 EN|拼音（Feng t707u：`文风·中文` + 空格 + EN优先否则拼音；禁止换行；EN与拼音不同时出）  
**方向**：**全站甲 · 浅底纯白** + **logo L1（右下）** + **左下角单行名牌** + **previewLine 默认 off** + **fontPreview = 兰亭长句 A**

## 锁什么

### 甲方向（2026-11-02 20:14；**底色修订 2026-11-06 t691s3186**）
- `preview` / `card` / `OG`（及可选浅底方图）**一律甲**
- **甲量产浅底：纯白 `#FFFFFF`**（原甲纸白 `#F7F5F2` 仅 lab 历史对照，不再量产）
- **深底示意 / cover-dark**：近墨 `#1C1A18`～`#16181C`（金样用 `#1C1A18`）+ **L1 `icon-on-dark`**
- **不用乙**（冷雾微标保留为 lab 对照，不量产）

### logo（2026-11-02 20:39 · t714u；标色 20:45 · t655–656u；**极性校正 20:49 · t657u**）
- **L1 · 右下小方标**；落点不变
- **左下角单行静音名牌**（全 L1 资产 specimen/card/OG/fontPreview 浅深一律；**t707u** 修订 t706u）
  - **唯一一行**：`文风·{中文}` + 空格 + **EN（有英文名时）**，否则拼音
  - 例：`文风·玄宗体 XuanZongTi`；`文风·思源黑体 Source Han Sans SC`；无 EN 时 `文风·某某 xuǎn mǒu`
  - **禁止换行**；**禁止** EN 与拼音同时出现
  - L1 标仍右下/右端
- **浅底甲量产**用 `brand/icon-on-light.png`（**浅底用黑标** = 黑底白符方标）
- **深底**用 `brand/icon-on-dark.png`（**深底用白标** = 白底黑符方标）
- `icon.png` ≡ on-light（黑方标真源）
- OG 右下安全区；card 右端；**specimen 亦 L1**（右下小方标 ~40px / margin ~40；t695u）
- L2 / L3 不量产（lab 对照保留）

### 长句预览（2026-11-02 20:39 · t717–720）
- 字段 **`previewLine` 默认 off**
- 出盘默认图 = **短样**（四字/显示名等），只挂 **一套 URL**
- 长句 = 图上可选示文字（非诗歌功能）；用户换文 = **控台按需重渲，覆盖同路径**
- **禁止**按 API 调用出一套新图

### 字体预览 fontPreview（2026-11-06 · t695u · **LOCKED 文案 A · t695s3209**）
- 另套 **富文预览**（非四字短名）；量产金样 `*-fontpreview-l1-{white|ondark}.png`（**= 文案 A**，无字母后缀）
- 尺寸仍用 card **1200×280** / specimen **720×400**；**一律 L1**
- **量产文案 A（LOCKED）**：`永和九年，岁在癸丑。暮春之初，会于会稽山阴之兰亭。`
- **B/C lab-only**：`*-fontpreview-{b|c}-l1-*.png` + `options-font-preview.html`；**不量产**
- 与 `previewLine` 正交：短样量产仍可 off；fontPreview 是独立富文套

## 尺寸（不变）

| 资产 | 尺寸 |
|---|---|
| preview / specimen | **720×400** |
| card | **1200×280**（长条） |
| OG | **1200×630** |

## 真源

- 规格：`SPEC.md`（气质：优雅克制、贴文风）
- 甲对照：`OPTIONS.md`；logo：`OPTIONS-logo.md` · `PLACEMENT-logo.md`
- 长句：`OPTIONS-preview-line.md`；API：`META.md`（含 §7 同路径重渲）
- **量产金样（纯白浅底 · 一律 L1）**：`samples/*/specimen-a-l1-white.png`、`card-a-l1-white.png`、`og-a-l1-white.png`
- **深底示意金样（一律 L1）**：`samples/*/specimen-a-l1-ondark.png`、`card-a-l1-ondark.png`、`og-a-l1-ondark.png`、`cover-dark-a-l1.png`
- **字体预览量产金样（文案 A · LOCKED t695s3209）**：`samples/*/card-a-fontpreview-l1-white.png`、`card-a-fontpreview-l1-ondark.png`、`specimen-a-fontpreview-l1-white.png`、`specimen-a-fontpreview-l1-ondark.png`
- **字体预览 B/C lab-only**：`samples/*/card-a-fontpreview-{b|c}-l1-*.png`、`specimen-a-fontpreview-{b|c}-l1-*.png`
- **无 logo 历史 lab（不再量产）**：`specimen-a-white.png` / `specimen-a-ondark.png` / `specimen-a-l0.png`（仅对照）
- **纸白历史 lab**（不再量产）：`samples/*/…-a.png`、`…-a-l1.png`（底 `#F7F5F2`）
- 浅/深对照页：`/workspace/wpfonts-bg-white-lab-20261106/options-bg-white-dark.html`
- **字体预览对照页**：`/workspace/wpfonts-bg-white-lab-20261106/options-font-preview.html`
- Foundry 交接：`HANDOFF-bg-white-Foundry.md`

## 下游

| 谁 | 做什么 |
|---|---|
| Foundry | 插件：previewUrl / cardPreviewUrl / ogImageUrl + `logoVariant=L1` + `previewLine`；**字段名/路径模板不变**；对齐 catalog；不做 per-request asset URL |
| 当康 | 按甲+**纯白底**+L1（**含 specimen**）短样批量重渲 → CDN → 写 meta；控台 on 时重渲覆盖同路径；深底近墨 + on-dark；**fontPreview 按文案 A** 批量（金样 `*-fontpreview-l1-{white|ondark}.png`）；CDN 仍需 Feng 点名 |
| Designer | 后续字号/边距/标微调仍走 options lab，不上生产直改 |

## 不改

- 不上生产直改店面/CDN（量产走当康流水线；本锁仅规格）
- 不复用 Motuicons/CocoBook 脸
- **不 bump 插件版本**仅因底色；meta 字段名不变

## 附录 · 标色两套（t655–657u · 以 t657u 为准）

| 套 | 路径 | 长相 | 用在 |
|---|---|---|---|
| on-light · 浅底黑标 | `brand/icon-on-light.png` | 黑底白符方标 | 甲浅底（现 `#FFFFFF`）specimen/OG/card/cover-light 量产 |
| on-dark · 深底白标 | `brand/icon-on-dark.png` | 白底黑符方标 | 深底 cover-dark / `*-ondark` 示意 |

规则：**图片白底 → 黑 logo；图片黑底 → 白 logo**。两套并存、同 L1 落点。详见 `PLACEMENT-logo.md`。

**LOCKED 2026-11-02 20:51 CST（Feng t658u）**：浅底图→黑方标（白符）；深底图→白方标（黑符）。  
**LOCKED 2026-11-06 ~16:30 CST（Feng t691s3186）**：甲量产浅底 → 纯白 `#FFFFFF`。
**LOCKED 2026-11-06 ~16:38 CST（Feng t695u）**：specimen **加** L1；无 logo 版不要。  
**LOCKED 2026-11-06 ~16:45 CST（Feng t695s3209）**：fontPreview 量产文案 **A** 兰亭长句；金样 `*-fontpreview-l1-white.png` / `*-fontpreview-l1-ondark.png`；B/C lab-only。
**LOCKED 2026-11-06 ~16:55 CST（Feng t698u）**：全 L1 资产左下角静音小字 `文风·{display}`；L1 标仍右下/右端。
**LOCKED 2026-11-06 ~17:00 CST（Feng t699u）**：左下角两行 = `文风·{中文}` + `{EN} · {pinyin}`；L1 标仍右下/右端。
**LOCKED 2026-11-06 ~17:10 CST（Feng t705u/t706u）**：左下角**单行** `文风·{display}`；禁止换行；EN·拼音不放 BL。
**LOCKED 2026-11-06 ~17:15 CST（Feng t707u）**：左下角单行 `文风·中文` + 空格 + EN（优先）否则拼音；禁止换行；EN 与拼音不同时出。
