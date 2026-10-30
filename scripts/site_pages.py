#!/usr/bin/env python3
"""Shared helpers for the bigdata docs, driver, and driver-index pages."""
from __future__ import annotations

import html
import re
from pathlib import Path

import markdown

ROOT = Path(__file__).resolve().parents[1]
SITE = ROOT / "site"
GITHUB = "https://github.com/erikwang2013/bigdata-php"
RAW = "https://raw.githubusercontent.com/erikwang2013/bigdata-php/main"
SITE_URL = "https://package.erik.xyz/bigdata"

LANGS = {
    "zh": {
        "shell": "index.html",
        "page": "docs.html",
        "other": "en-docs.html",
        "home": "index.html",
        "readme": ROOT / "README.md",
        "start": "## 3. ",
        "end": "## 项目宠物",
        "html_lang": "zh-CN",
        "title": "bigdata 文档 · 安装、框架与驱动",
        "description": "bigdata-php 完整文档：安装、快速开始、Laravel / ThinkPHP / Webman / Hyperf / Yii2 / Yii3 集成、21 个驱动矩阵、可选依赖与边界说明。",
        "canonical": f"{SITE_URL}/docs.html",
        "locale": "zh_CN",
        "image": f"{SITE_URL}/assets/img/social-preview.png",
        "hero_kicker": "BIGDATA / DOCS",
        "hero_title": "bigdata-php 文档",
        "hero_lead": "19 个平台、21 个驱动键。先安装，再按框架或驱动选择示例；本页内容由仓库 README 生成。",
        "readme_label": "GitHub README",
        "home_label": "返回首页",
        "toc_title": "文档目录",
        "search_type": "文档",
        "search_index": "assets/search-index-docs.json",
        "home_index": "assets/search-index.json",
        "home_search_type": "安装与使用",
        "cta_title": "安装与使用",
        "cta_sub": "一条 Composer 命令接入 19 个平台。完整的安装、六个框架集成、21 个驱动示例和边界说明已经整理到独立文档页。",
        "cta_button": "阅读完整文档",
        "cta_install": "安装",
        "cta_quick": "快速开始",
        "cta_home_search": "一条 Composer 命令接入 19 个平台；完整文档见独立文档页。",
        "search_hint_old": "输入关键词，例如“上传”“迁移”“JWT”。",
        "search_hint": "输入关键词，例如“Trino”“Laravel”“Airflow”。",
        "drivers_page": "drivers.html",
        "driver_dir": "drivers",
        "driver_nav": "驱动",
        "driver_index_title": "驱动文档",
        "driver_index_lead": "19 个平台、21 个驱动键。选择驱动查看安装、配置、用法和错误说明。",
        "driver_breadcrumb_docs": "文档",
        "driver_breadcrumb_drivers": "驱动",
        "driver_keys_label": "驱动键",
        "optional_label": "可选依赖",
        "none_label": "无",
        "all_drivers_label": "全部驱动",
        "page_toc_label": "本页目录",
        "driver_search_type": "驱动文档",
        "driver_title_suffix": " · bigdata 文档",
        "drivers_other": "en-drivers.html",
        "driver_index_search": "assets/search-index-drivers.json",
    },
    "en": {
        "shell": "en.html",
        "page": "en-docs.html",
        "other": "docs.html",
        "home": "en.html",
        "readme": ROOT / "README_EN.md",
        "start": "## 3. Requirements",
        "end": "## Project Pet",
        "html_lang": "en",
        "title": "bigdata Docs · Install, frameworks, and drivers",
        "description": "Complete bigdata-php documentation: installation, quick start, Laravel / ThinkPHP / Webman / Hyperf / Yii2 / Yii3 integration, the 21-driver matrix, optional dependencies, and limitations.",
        "canonical": f"{SITE_URL}/en-docs.html",
        "locale": "en_US",
        "image": f"{SITE_URL}/assets/img/social-preview-en.png",
        "hero_kicker": "BIGDATA / DOCS",
        "hero_title": "bigdata-php documentation",
        "hero_lead": "19 platforms, 21 driver keys. Install once, then pick a framework or driver example. This page is generated from the repository README.",
        "readme_label": "GitHub README",
        "home_label": "Back home",
        "toc_title": "On this page",
        "search_type": "Docs",
        "search_index": "assets/search-index-docs-en.json",
        "home_index": "assets/search-index-en.json",
        "home_search_type": "Install and usage",
        "cta_title": "Install and use",
        "cta_sub": "One Composer command connects 19 platforms. Full installation, six framework integrations, 21 driver examples, and limitations live on the documentation page.",
        "cta_button": "Read the docs",
        "cta_install": "Install",
        "cta_quick": "Quick start",
        "cta_home_search": "One Composer command connects 19 platforms; the full documentation is on its own page.",
        "search_hint_old": "Try “upload”, “migration” or “JWT”.",
        "search_hint": "Try “Trino”, “Laravel” or “Airflow”.",
        "drivers_page": "en-drivers.html",
        "driver_dir": "en-drivers",
        "driver_nav": "Drivers",
        "driver_index_title": "Driver docs",
        "driver_index_lead": "19 platforms, 21 driver keys. Pick a driver for installation, configuration, usage, and errors.",
        "driver_breadcrumb_docs": "Docs",
        "driver_breadcrumb_drivers": "Drivers",
        "driver_keys_label": "Driver keys",
        "optional_label": "Optional dependency",
        "none_label": "none",
        "all_drivers_label": "All drivers",
        "page_toc_label": "On this page",
        "driver_search_type": "Driver docs",
        "driver_title_suffix": " · bigdata Docs",
        "drivers_other": "drivers.html",
        "driver_index_search": "assets/search-index-drivers-en.json",
    },
}

IMAGE_MAP = {
    "zh": {
        "docs/diagrams/zh/architecture.svg": "assets/img/gallery/01-architecture.svg",
        "docs/diagrams/zh/functional-design.svg": "assets/img/gallery/02-functional-design.svg",
        "docs/diagrams/zh/lifecycle.svg": "assets/img/gallery/03-lifecycle.svg",
    },
    "en": {
        "docs/diagrams/en/architecture.svg": "assets/img/gallery/01-en-architecture.svg",
        "docs/diagrams/en/functional-design.svg": "assets/img/gallery/02-en-functional-design.svg",
        "docs/diagrams/en/lifecycle.svg": "assets/img/gallery/03-en-lifecycle.svg",
    },
}

DRIVERS = [
    ("trino", "Trino"),
    ("hadoop", "Hadoop"),
    ("hive", "Hive"),
    ("spark", "Spark"),
    ("flink", "Flink"),
    ("doris", "Doris"),
    ("starrocks", "StarRocks"),
    ("kylin", "Kylin"),
    ("druid", "Druid"),
    ("iceberg", "Iceberg"),
    ("hudi", "Hudi"),
    ("paimon", "Paimon"),
    ("delta-lake", "Delta Lake"),
    ("dolphinscheduler", "DolphinScheduler"),
    ("airflow", "Airflow"),
    ("azkaban", "Azkaban"),
    ("oozie", "Oozie"),
    ("hbase", "HBase"),
    ("cassandra", "Cassandra"),
]
DRIVER_FILES = [key for key, _label in DRIVERS]



DOCS_CSS = (ROOT / "docs/site/docs-page.css").read_text(encoding="utf-8")
DOCS_JS = (ROOT / "docs/site/docs-page.js").read_text(encoding="utf-8")


def slugify(value: str, separator: str) -> str:
    value = value.strip().lower()
    value = re.sub(r"[^\w一-鿿\s-]", "", value)
    value = re.sub(r"\s+", separator, value)
    value = re.sub(re.escape(separator) + r"+", separator, value)
    return value.strip(separator)




def render_markdown(text: str, lang: str, *, localize_drivers: bool = False) -> tuple[str, list[dict]]:
    md = markdown.Markdown(
        extensions=["toc", "tables", "fenced_code", "sane_lists"],
        extension_configs={"toc": {"slugify": slugify, "toc_depth": "2-3"}},
    )
    body = md.convert(text)
    for rel, asset in IMAGE_MAP[lang].items():
        body = body.replace(f'src="{rel}"', f'src="{asset}"')
    body = re.sub(r'src="(docs/diagrams/[^"]+)"', lambda m: f'src="{RAW}/{m.group(1)}"', body)

    def rewrite_href(match: re.Match) -> str:
        href = match.group(1)
        if href.startswith("#") or re.match(r"https?://|mailto:", href):
            return match.group(0)
        return f'href="{GITHUB}/blob/main/{href}"'

    body = re.sub(r'href="([^"]+)"', rewrite_href, body)
    body = tag_driver_matrix(body)
    if localize_drivers:
        body = localize_driver_links(body, lang)
    return body, md.toc_tokens


def localize_driver_links(body: str, lang: str) -> str:
    label = "查看" if lang == "zh" else "Open"
    for key, _driver_label in DRIVERS:
        docs_prefix = "docs/drivers/en/" if lang == "en" else "docs/drivers/"
        github_url = f"{GITHUB}/blob/main/{docs_prefix}{key}.md"
        local_url = f"{'en-' if lang == 'en' else ''}drivers/{key}.html"
        body = body.replace(github_url, local_url)
        body = body.replace(f'href="{local_url}">{key}.md</a>', f'href="{local_url}">{label}</a>')
    return body


def tag_driver_matrix(body: str) -> str:
    def repl(match: re.Match) -> str:
        table = match.group(0)
        header = table.split("</thead>", 1)[0] if "</thead>" in table else table[:500]
        if "驱动键" in header or "Driver key" in header:
            return table.replace("<table>", '<table class="driver-matrix">', 1)
        return table

    return re.sub(r"<table>.*?</table>", repl, body, flags=re.S)


def toc_list(tokens: list[dict]) -> str:
    items = []
    for token in tokens:
        if token["level"] > 3:
            continue
        css = "toc-h3" if token["level"] == 3 else "toc-h2"
        children = toc_list(token["children"]) if token["children"] else ""
        items.append(
            f'<li class="{css}"><a href="#{token["id"]}">{html.escape(token["name"])}</a>{children}</li>'
        )
    return f'<ul class="docs-toc-list">{"".join(items)}</ul>' if items else ""


def mobile_nav(tokens: list[dict]) -> str:
    return "".join(
        f'<a href="#{token["id"]}">{html.escape(token["name"])}</a>'
        for token in tokens
        if token["level"] == 2
    )


def build_head(
    shell: str,
    cfg: dict,
    *,
    title: str,
    description: str,
    canonical: str,
    alternate_zh: str,
    alternate_en: str,
    asset_prefix: str = "",
) -> str:
    head = re.search(r"<head>.*?</head>", shell, re.S)
    if head is None:
        raise SystemExit("error: site shell has no <head>")
    clean = re.sub(r"<noscript>.*?</noscript>", "", head.group(0), flags=re.S)
    styles = "\n".join(re.findall(r"<style[^>]*>.*?</style>", clean, re.S))
    return f"""<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{title}</title>
  <meta name="description" content="{description}">
  <meta name="author" content="艾瑞可erik">
  <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
  <meta name="theme-color" content="#f6f7fb" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0d1117" media="(prefers-color-scheme: dark)">
  <meta name="color-scheme" content="light dark">
  <link rel="canonical" href="{canonical}">
  <link rel="alternate" hreflang="zh-CN" href="{alternate_zh}">
  <link rel="alternate" hreflang="en" href="{alternate_en}">
  <link rel="alternate" hreflang="x-default" href="{alternate_zh}">
  <link rel="sitemap" type="application/xml" href="{asset_prefix}sitemap.xml">
  <link rel="icon" href="{asset_prefix}assets/img/pet.svg" type="image/svg+xml">
  <meta property="og:type" content="article">
  <meta property="og:title" content="{title}">
  <meta property="og:description" content="{description}">
  <meta property="og:url" content="{canonical}">
  <meta property="og:site_name" content="bigdata">
  <meta property="og:locale" content="{cfg['locale']}">
  <meta property="og:image" content="{cfg['image']}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{title}">
  <meta name="twitter:description" content="{description}">
  <meta name="twitter:image" content="{cfg['image']}">
{styles}
  <style id="docs-page-style">{DOCS_CSS}</style>
</head>"""


def extract(shell: str, start: str, end: str) -> str:
    begin = shell.index(start)
    finish = shell.index(end, begin) + len(end)
    return shell[begin:finish]


def rewrite_header(
    header: str,
    cfg: dict,
    *,
    active: str = "docs",
    prefix: str = "",
    other_href: str | None = None,
) -> str:
    home = prefix + cfg["home"]
    other_href = other_href or prefix + cfg["other"]
    docs_href = prefix + cfg["page"]
    drivers_href = prefix + cfg["drivers_page"]
    header = header.replace('"assets/', f'"{prefix}assets/')
    header = header.replace('<a class="brand" href="#intro"', f'<a class="brand" href="{home}"')

    def rewrite_nav(match: re.Match) -> str:
        href = match.group(1)
        if href == "#docs":
            return f'href="{docs_href}"'
        return f'href="{home}{href}"'

    header = re.sub(r'href="(#[a-z-]+)" data-section="[a-z-]+"', rewrite_nav, header)
    if cfg["html_lang"] == "zh-CN":
        header = header.replace('class="nav-lang" href="en.html"', f'class="nav-lang" href="{other_href}"')
        header = header.replace('class="lang-switch" href="en.html"', f'class="lang-switch" href="{other_href}"')
    else:
        header = header.replace('class="nav-lang" href="index.html"', f'class="nav-lang" href="{other_href}"')
        header = header.replace('class="lang-switch" href="index.html"', f'class="lang-switch" href="{other_href}"')

    if prefix:
        header = header.replace(f'href="{cfg["page"]}"', f'href="{docs_href}"')
        header = header.replace(f'href="{cfg["drivers_page"]}"', f'href="{drivers_href}"')

    if f'href="{drivers_href}"' not in header:
        pattern = re.compile(rf'(<a [^>]*href="{re.escape(docs_href)}"[^>]*>[^<]*</a>)')
        header, _ = pattern.subn(
            lambda match: match.group(1) + "\n      " + f'<a href="{drivers_href}">{cfg["driver_nav"]}</a>',
            header,
            count=1,
        )

    for href in (docs_href, drivers_href):
        header = re.sub(rf'(<a [^>]*href="{re.escape(href)}"[^>]*?) aria-current="true"', r"\1", header)
    active_href = docs_href if active == "docs" else drivers_href
    header = re.sub(rf'(<a href="{re.escape(active_href)}")', r'\1 aria-current="true"', header, count=1)
    return header


def rewrite_footer(footer: str, *, prefix: str = "") -> str:
    footer = footer.replace('"assets/', f'"{prefix}assets/')
    return footer.replace('<a href="#intro">回到顶部</a>', '<a href="#main">回到顶部</a>').replace(
        '<a href="#intro">Back to top</a>', '<a href="#main">Back to top</a>'
    )


def build_shell_page(
    shell: str,
    cfg: dict,
    *,
    prefix: str,
    active: str,
    other_href: str,
    title: str,
    description: str,
    canonical: str,
    alternate_zh: str,
    alternate_en: str,
    search_index: str,
    sidebar: str,
    mobile_nav_html: str,
    hero: str,
    content: str,
) -> str:
    head = build_head(
        shell,
        cfg,
        title=title,
        description=description,
        canonical=canonical,
        alternate_zh=alternate_zh,
        alternate_en=alternate_en,
        asset_prefix=prefix,
    )
    sprite = extract(shell, '<svg class="icon-sprite"', "</svg>")
    header = rewrite_header(
        extract(shell, '<header class="site-header">', "</header>"),
        cfg,
        active=active,
        prefix=prefix,
        other_href=other_href,
    )
    footer = rewrite_footer(extract(shell, '<footer class="section footer"', "</footer>"), prefix=prefix)
    tail = shell[shell.index('<button class="pet-top"'): shell.index("</body>")]
    tail = tail.replace('"assets/', f'"{prefix}assets/')
    tail = tail.replace("'./assets/", f"'{prefix}assets/")
    tail = tail.replace(
        "window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY, behavior });",
        "window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY"
        " - (document.querySelector('.site-header')?.offsetHeight || 0) - 16, behavior });",
    )
    skip = "跳到主要内容" if cfg["html_lang"] == "zh-CN" else "Skip to content"
    return f"""<!doctype html>
<html lang="{cfg['html_lang']}">
{head}
<body data-search-index="{search_index}">
  <a class="skip-link" href="#main">{skip}</a>
{sprite}
{header}
<main id="main" class="scroll-container" tabindex="-1">
  <div class="docs-page wrap">
{sidebar}
    <div class="docs-main">
{mobile_nav_html}
{hero}
      <article class="docs-content">
{content}
      </article>
    </div>
  </div>
{footer}
</main>
{tail}
<script id="docs-page-script">{DOCS_JS}</script>
</body>
</html>
"""


def search_entries(body: str, *, title: str, lead: str, entry_type: str) -> list[dict]:
    entries = [{
        "title": title,
        "text": lead,
        "anchor": "main",
        "type": entry_type,
    }]
    pattern = re.compile(r'<h([23]) id="([^"]+)">(.*?)</h\1>', re.S)
    matches = list(pattern.finditer(body))
    for index, match in enumerate(matches):
        start = match.end()
        end = matches[index + 1].start() if index + 1 < len(matches) else len(body)
        text = re.sub(r"<[^>]+>", " ", body[start:end])
        text = html.unescape(re.sub(r"\s+", " ", text)).strip()
        heading = html.unescape(re.sub(r"<[^>]+>", "", match.group(3))).strip()
        entries.append({
            "title": heading,
            "text": text[:500],
            "anchor": match.group(2),
            "type": entry_type,
        })
    return entries

