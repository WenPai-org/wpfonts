#!/usr/bin/env python3
"""Lint a wenpai-admin-ui kit tree (and, with --plugin-root, product admin pages).

0 css/php files → SKIP, never PASS.
0 prototype html / 0 plugin templates → SKIP that subcheck; do not treat the skip as PASS.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

TEMPLATE_SKIP_PARTS = ("gutenberg", "block-editor", "list-table")

# Raw colors. Only css/wenpai-tokens.css (generated from tokens/tokens.json) may hold them.
TOKENS_CSS = "wenpai-tokens.css"
_COMMENT = re.compile(r"/\*.*?\*/", re.S)
_URL = re.compile(r"url\([^)]*\)", re.I)
_DECL = re.compile(r"([a-z-]+)\s*:\s*([^;{}]+)")
_HEX = re.compile(r"#[0-9a-fA-F]{3,8}\b")
_FUNC = re.compile(r"\b(?:rgba?|hsla?)\(", re.I)
_FALLBACK = re.compile(r"var\(\s*--[a-z0-9-]+\s*,\s*#[0-9a-fA-F]{3,8}\b", re.I)


def raw_colors(text: str) -> list[tuple[int, str, str]]:
    """Return (line, property, value) for declarations that carry a raw color.

    Selectors such as #wpbody-content are ignored: only text inside { } that
    looks like `prop: value` is checked. url(...) payloads (data-URI chevrons)
    and comments are blanked first, keeping line numbers.
    """
    def blank(m: re.Match) -> str:
        return re.sub(r"[^\n]", " ", m.group(0))

    clean = _URL.sub(blank, _COMMENT.sub(blank, text))
    hits: list[tuple[int, str, str]] = []
    depth = 0
    i = 0
    n = len(clean)
    while i < n:
        ch = clean[i]
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth = max(0, depth - 1)
        elif depth > 0:
            m = _DECL.match(clean, i)
            if m and (i == 0 or not (clean[i - 1].isalnum() or clean[i - 1] in "-_")):
                prop, value = m.group(1), m.group(2)
                if not prop.startswith("--") and (_HEX.search(value) or _FUNC.search(value)):
                    line = clean.count("\n", 0, i) + 1
                    hits.append((line, prop, value.strip()))
                i = m.end()
                continue
        i += 1
    return hits


def skip(reason: str) -> int:
    print(f"SKIP {reason}")
    return 0


def lint_kit(root: Path, vendored: bool = False) -> tuple[list[dict], int, int]:
    findings: list[dict] = []
    css_dir = root / "css"
    php_dir = root / "php"
    css_files = sorted(css_dir.glob("*.css")) if css_dir.is_dir() else []
    php_files = sorted(php_dir.glob("*.php")) if php_dir.is_dir() else []

    banned_css = (
        (":root", "token on :root"),
        (".wpcy-", "old wpcy prefix"),
        (".wpsss-", "product selector in kit"),
        (".wpslug-", "product selector in kit"),
        (".stat-a", "leaf console primitive"),
        (".hero-w", "leaf hero"),
        (".eco", "leaf eco block"),
    )
    tokens_css = css_dir / TOKENS_CSS
    # A vendored kit older than 0.3 has no tokens file and still carries raw
    # colors; that is the kit's debt, not the plugin's. Only judge copies that
    # ship tokens (or the kit repo itself).
    check_colors = (not vendored) or tokens_css.is_file()
    if css_files and not tokens_css.is_file() and not vendored:
        findings.append({"file": str(tokens_css), "kind": "CSS", "why": "missing generated tokens file (run scripts/build-tokens.py)"})
    for f in css_files:
        text = f.read_text(errors="replace")
        if check_colors and f.name != TOKENS_CSS:
            for line, prop, value in raw_colors(text):
                findings.append({"file": f"{f}:{line}", "kind": "CSS", "why": f"raw color in {prop}; use a --wenpai-* token", "needle": value})
        for needle, why in banned_css:
            if needle == ".eco":
                if ".eco" in text or "eco-" in text:
                    findings.append({"file": str(f), "kind": "CSS", "why": why})
                continue
            if needle in text:
                findings.append({"file": str(f), "kind": "CSS", "why": why, "needle": needle})

    handle_ok = False
    class_guard = 0
    class_decl = 0
    for f in php_files:
        text = f.read_text(errors="replace")
        if "wenpai-admin-ui" in text and "STYLE_HANDLE" in text:
            handle_ok = True
        if "class Wenpai_Admin_" in text:
            class_decl += 1
            if "class_exists" in text:
                class_guard += 1
        if "function wenpai_admin_ui_register" in text and "function_exists" not in text:
            findings.append({"file": str(f), "kind": "PHP", "why": "register without function_exists"})

    if class_decl and class_guard < class_decl:
        findings.append(
            {
                "file": str(php_dir),
                "kind": "PHP",
                "why": f"class_exists missing ({class_guard}/{class_decl})",
            }
        )
    if php_files and not handle_ok:
        loader = php_dir / "class-wenpai-admin-loader.php"
        if loader.exists() and "wenpai-admin-ui" not in loader.read_text(errors="replace"):
            findings.append({"file": str(loader), "kind": "PHP", "why": "handle not wenpai-admin-ui"})

    shell = php_dir / "class-wenpai-admin-shell.php"
    if shell.is_file():
        shell_text = shell.read_text(errors="replace")
        if "wenpai-main" not in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell missing wenpai-main"})
        if 'class="foot"' not in shell_text and "class='foot'" not in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell missing footer.foot"})
        if "wenpai-foot" in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell uses wenpai-foot not foot"})
        if "wenpai-mode" in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell uses wenpai-mode not mode"})

    js_file = root / "js" / "wenpai-admin.js"
    if js_file.is_file():
        js_text = js_file.read_text(errors="replace")
        if "form.action" in js_text:
            findings.append({"file": str(js_file), "kind": "JS", "why": "js reads form.action"})

    wp_css = css_dir / "wenpai-admin-wp.css"
    if wp_css.is_file():
        wp_text = wp_css.read_text(errors="replace")
        if "max-width: none" not in wp_text and "max-width:none" not in wp_text:
            findings.append({"file": str(wp_css), "kind": "CSS", "why": "overlay missing .card max-width none"})
        if not re.search(r"\.notice \+ \.notice \{\s*margin-top:\s*(12px|var\(--wenpai-space-3\))", wp_text):
            findings.append({"file": str(wp_css), "kind": "CSS", "why": "overlay missing notice sibling 12px"})

    return findings, len(css_files), len(php_files)


def lint_prototypes(root: Path) -> tuple[list[dict], str | None]:
    proto = root / "prototypes"
    if not proto.is_dir():
        return [], "0 prototype html"
    htmls = sorted(p for p in proto.rglob("*.html") if p.is_file())
    if not htmls:
        return [], "0 prototype html"
    findings: list[dict] = []
    for f in htmls:
        text = f.read_text(errors="replace")
        if "wenpai-admin.css" not in text:
            continue
        i_css = text.find("wenpai-admin.css")
        i_wp = text.find("wenpai-admin-wp.css")
        i_tok = text.find(TOKENS_CSS)
        if i_tok < 0:
            findings.append({"file": str(f), "kind": "HTML", "why": "prototype missing wenpai-tokens.css"})
        elif i_tok > i_css:
            findings.append({"file": str(f), "kind": "HTML", "why": "wenpai-tokens.css must load before wenpai-admin.css"})
        if i_wp < 0:
            findings.append({"file": str(f), "kind": "HTML", "why": "prototype missing wenpai-admin-wp.css"})
        elif i_wp < i_css:
            findings.append({"file": str(f), "kind": "HTML", "why": "overlay before surface css"})
        if "wenpai-ui-page" not in text:
            findings.append({"file": str(f), "kind": "HTML", "why": "prototype body missing wenpai-ui-page"})
    return findings, None


def _under_templates(plugin_root: Path, path: Path) -> bool:
    try:
        return path.relative_to(plugin_root).parts[0] == "templates"
    except ValueError:
        return False


def iter_plugin_page_files(plugin_root: Path) -> list[Path]:
    out: list[Path] = []
    templates = plugin_root / "templates"
    if templates.is_dir():
        out.extend(sorted(templates.rglob("*.php")))
        out.extend(sorted(templates.rglob("*.html")))
    includes = plugin_root / "includes"
    if includes.is_dir():
        out.extend(sorted(includes.rglob("*.php")))
    src = plugin_root / "src"
    if src.is_dir():
        out.extend(sorted(src.rglob("*.php")))
    js_dir = plugin_root / "assets" / "js"
    if js_dir.is_dir():
        out.extend(sorted(js_dir.rglob("*.js")))
    cleaned: list[Path] = []
    for path in out:
        posix = path.as_posix()
        if "lib/wenpai-admin-ui" in posix:
            continue
        cleaned.append(path)
    return cleaned


def lint_plugin_pages(plugin_root: Path) -> tuple[list[dict], str | None]:
    files = iter_plugin_page_files(plugin_root)
    if not files:
        return [], "0 plugin templates/js"
    findings: list[dict] = []
    for path in files:
        rel = path.relative_to(plugin_root).as_posix().lower()
        text = path.read_text(errors="replace")
        kind = "JS" if path.suffix == ".js" else "PHP"
        skip_form = any(part in rel for part in TEMPLATE_SKIP_PARTS)
        in_templates = _under_templates(plugin_root, path)
        kit_page = in_templates or ("wenpai-app" in text) or ("wenpai-main" in text)
        if (not skip_form) and kit_page and "form-table" in text:
            findings.append({"file": str(path), "kind": kind, "why": "kit page uses form-table"})
        if "wpcy-app" in text:
            findings.append({"file": str(path), "kind": kind, "why": "product still uses wpcy-app"})
    return findings, None


import re as _re
PLUGIN_RULES = (
    (_re.compile(r"\badd_menu_page\s*\("), "top-level menu; use Wenpai_Admin_Menu::register"),
    (_re.compile(r"add_\w+_page\s*\([^;]*,\s*8[0-2]\s*\)"), "menu position 80-82 is reserved"),
    (_re.compile(r"admin\.php\?page=|admin_url\(\s*[\x27\"]admin\.php[\x27\"]"), "hardcoded admin.php; use Wenpai_Admin_Menu::url"),
    (_re.compile(r"class=[\x27\"][^\x27\"]*\b(wenpai-top|wenpai-brand)\b|class=[\x27\"]foot[\x27\"]"), "hand-written chrome; use Wenpai_Admin_Shell"),
)


def lint_plugin_rules(plugin_root):
    findings = []
    used_shell = False
    for path in sorted(plugin_root.rglob("*.php")):
        posix = path.as_posix()
        if "lib/wenpai-admin-ui" in posix or "/vendor/" in posix or "/node_modules/" in posix:
            continue
        text = path.read_text(errors="replace")
        if "Wenpai_Admin_Shell::open" in text:
            used_shell = True
        for i, line in enumerate(text.splitlines(), 1):
            for rx, why in PLUGIN_RULES:
                if rx.search(line):
                    findings.append({"file": f"{path.relative_to(plugin_root)}:{i}", "kind": "RULE", "why": why})
    if not used_shell:
        findings.append({"file": str(plugin_root), "kind": "RULE", "why": "plugin never calls Wenpai_Admin_Shell::open"})
    return findings


CONSUMER_CSS_SKIP = ("lib/wenpai-admin-ui", "/vendor/", "/node_modules/", "/dist/", "/build/", ".min.css")


def lint_plugin_colors(plugin_root: Path) -> list[dict]:
    """Consumer mode: raw colors in the product's own admin CSS.

    Real consumers today are wpsss and wpslug (tiers.yml A, on kit 0.2.5).
    Default is WARN only; --strict-colors turns them into FAIL. Hex inside a
    var(--wenpai-*, #hex) fallback is reported separately: it duplicates a
    token value and drifts when the token changes.
    """
    out: list[dict] = []
    for path in sorted(plugin_root.rglob("*.css")):
        posix = path.as_posix()
        if any(part in posix for part in CONSUMER_CSS_SKIP):
            continue
        text = path.read_text(errors="replace")
        rel = path.relative_to(plugin_root).as_posix()
        for line, prop, value in raw_colors(text):
            why = "token fallback duplicates a value" if _FALLBACK.search(value) else f"raw color in {prop}"
            out.append({"file": f"{rel}:{line}", "kind": "COLOR", "why": why, "needle": value})
    return out


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default="", help="kit repo root")
    ap.add_argument("--plugin-root", default="", help="plugin root; lints lib/wenpai-admin-ui plus templates")
    ap.add_argument("--json", action="store_true")
    ap.add_argument("--strict-colors", action="store_true", help="with --plugin-root: raw colors in product CSS FAIL instead of WARN")
    args = ap.parse_args()

    plugin_root: Path | None = None
    if args.plugin_root:
        plugin_root = Path(args.plugin_root).resolve()
        root = plugin_root / "lib" / "wenpai-admin-ui"
        if not root.is_dir():
            print(f"FAIL missing {root}")
            return 1
    elif args.root:
        root = Path(args.root).resolve()
    else:
        print("usage: --root <kit> or --plugin-root <plugin>", file=sys.stderr)
        return 2

    css_dir = root / "css"
    php_dir = root / "php"
    if not css_dir.is_dir() and not php_dir.is_dir():
        return skip("no css/ or php/ under --root")

    findings, n_css, n_php = lint_kit(root, vendored=plugin_root is not None)
    if not n_css and not n_php:
        return skip("0 css/php files")

    proto_findings, proto_skip = lint_prototypes(root)
    findings.extend(proto_findings)

    plugin_skip: str | None = None
    if plugin_root is not None:
        page_findings, plugin_skip = lint_plugin_pages(plugin_root)
        findings.extend(page_findings)
        findings.extend(lint_plugin_rules(plugin_root))
        color_findings = lint_plugin_colors(plugin_root)
    else:
        color_findings = []

    if proto_skip:
        proto_status = "SKIP"
    elif proto_findings:
        proto_status = "FAIL"
    elif (root / "prototypes").is_dir():
        proto_status = "PASS"
    else:
        proto_status = "SKIP"

    if plugin_root is None:
        plugin_status = None
    elif plugin_skip:
        plugin_status = "SKIP"
    elif any(item.get("why") in {"kit page uses form-table", "product still uses wpcy-app"} for item in findings) or any(item.get("kind") == "RULE" for item in findings):
        plugin_status = "FAIL"
    else:
        plugin_status = "PASS"

    result = {
        "root": str(root),
        "css": n_css,
        "php": n_php,
        "findings": findings,
        "status": "FAIL" if findings else "PASS",
        "prototypes": proto_status,
        "plugin_pages": plugin_status,
        "plugin_pages_skip": plugin_skip,
        "prototypes_skip": proto_skip,
        "product_colors": color_findings,
        "product_colors_level": ("FAIL" if args.strict_colors else "WARN") if plugin_root is not None else None,
    }
    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        extra = f" prototypes={proto_status}"
        if plugin_status is not None:
            extra += f" plugin_pages={plugin_status}"
            if plugin_skip:
                extra += f" ({plugin_skip})"
        print(f"{result['status']} css={n_css} php={n_php} findings={len(findings)}{extra}")
        for item in findings:
            print(f"  {item['kind']} {item['file']}: {item['why']}")
        if color_findings:
            level = "FAIL" if args.strict_colors else "WARN"
            print(f"{level} product colors: {len(color_findings)} raw color declaration(s) outside kit tokens")
            for item in color_findings:
                print(f"  {level} {item['file']}: {item['why']}: {item['needle']}")
    if args.strict_colors and color_findings:
        return 1
    return 1 if findings else 0


if __name__ == "__main__":
    sys.exit(main())
