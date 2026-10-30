#!/usr/bin/env python3
"""Build the driver documentation index and one page per driver.

Driver pages reuse the shared shell helpers from build-docs-page.py and render
docs/drivers/<key>.md (or docs/drivers/en/<key>.md) with a driver sidebar and
an in-page table of contents. The main docs page links its driver matrix here.
"""
from __future__ import annotations

import datetime
import html
import json
import re
from pathlib import Path

from site_pages import (
    DRIVERS,
    LANGS,
    ROOT,
    SITE,
    SITE_URL,
    build_shell_page,
    mobile_nav,
    render_markdown,
    search_entries,
    toc_list,
)


def lang_key(cfg: dict) -> str:
    return "zh" if cfg["html_lang"] == "zh-CN" else "en"


def driver_markdown(cfg: dict, key: str) -> str:
    sub = Path("en") if lang_key(cfg) == "en" else Path("")
    return (ROOT / "docs/drivers" / sub / f"{key}.md").read_text(encoding="utf-8")


def plain(value: str) -> str:
    value = re.sub(r"\[([^\]]+)\]\([^)]+\)", r"\1", value)
    value = value.replace("`", "")
    value = re.sub(r"[*_>#]", "", value)
    return re.sub(r"\s+", " ", value).strip()


def parse_driver(markdown_text: str) -> tuple[str, list[str], str, str]:
    heading = re.search(r"^# (.+)$", markdown_text, re.M)
    title = heading.group(1).strip() if heading else ""
    body = re.sub(r"^# .+\n", "", markdown_text, count=1)
    keys: list[str] = []
    match = re.search(r"\| Driver names? \| (.+?) \|", markdown_text)
    if match:
        keys = re.findall(r"`([^`]+)`", match.group(1))
    optional = "none"
    match = re.search(r"\| Optional dependency \| (.+?) \|", markdown_text)
    if match:
        optional = match.group(1).strip().replace("\\|", "|")
    first = body.lstrip().split("\n\n", 1)[0]
    return title, keys, optional, plain(first)


def driver_links(cfg: dict, *, current: str | None = None, prefix: str = "") -> str:
    items = []
    for key, label in DRIVERS:
        current_attr = ' aria-current="page"' if key == current else ""
        items.append(
            f'<li class="toc-h2"><a href="{prefix}{key}.html"{current_attr}>{html.escape(label)}</a></li>'
        )
    return "".join(items)


def driver_sidebar(cfg: dict, key: str, tokens: list[dict]) -> str:
    return f"""    <aside class="docs-sidebar" aria-label="{cfg['driver_index_title']}">
      <p class="docs-sidebar-title">{cfg['driver_index_title']}</p>
      <ul class="docs-toc-list">
        <li class="toc-h2"><a href="../{cfg['drivers_page']}">{cfg['all_drivers_label']}</a></li>
        {driver_links(cfg, current=key)}
      </ul>
      <p class="docs-sidebar-title docs-sidebar-title--sub">{cfg['page_toc_label']}</p>
      {toc_list(tokens)}
    </aside>"""


def driver_mobile(cfg: dict, tokens: list[dict]) -> str:
    return (
        f'      <nav class="docs-mobile-nav" aria-label="{cfg["page_toc_label"]}">'
        f'<a href="../{cfg["drivers_page"]}">{cfg["all_drivers_label"]}</a>'
        f'{mobile_nav(tokens)}</nav>'
    )


def driver_hero(cfg: dict, label: str, title: str, keys: list[str], optional: str) -> str:
    keys_html = " ".join(f"<code>{html.escape(key)}</code>" for key in keys)
    optional_html = cfg["none_label"] if optional == "none" else html.escape(optional.replace("`", ""))
    return f"""      <header class="docs-hero">
        <nav class="docs-breadcrumb"><a href="../{cfg['page']}">{cfg['driver_breadcrumb_docs']}</a> / <a href="../{cfg['drivers_page']}">{cfg['driver_breadcrumb_drivers']}</a> / <span>{html.escape(label)}</span></nav>
        <h1>{html.escape(title)}</h1>
        <div class="docs-driver-meta">
          <span>{cfg['driver_keys_label']}: {keys_html}</span>
          <span>{cfg['optional_label']}: {optional_html}</span>
        </div>
      </header>"""


def build_driver_page(shell: str, cfg: dict, key: str, label: str) -> int:
    markdown_text = driver_markdown(cfg, key)
    title, keys, optional, description = parse_driver(markdown_text)
    body_md = re.sub(r"^# .+\n", "", markdown_text, count=1)
    body, tokens = render_markdown(body_md, lang_key(cfg))
    suffix = "en-" if lang_key(cfg) == "en" else ""
    index_name = f"assets/search-index-driver-{suffix}{key}.json"
    page = build_shell_page(
        shell,
        cfg,
        prefix="../",
        active="drivers",
        other_href=f"../{'en-drivers' if lang_key(cfg) == 'zh' else 'drivers'}/{key}.html",
        title=f"{title}{cfg['driver_title_suffix']}",
        description=(description or title)[:160],
        canonical=f"{SITE_URL}/{cfg['driver_dir']}/{key}.html",
        alternate_zh=f"{SITE_URL}/drivers/{key}.html",
        alternate_en=f"{SITE_URL}/en-drivers/{key}.html",
        search_index=f"../{index_name}",
        sidebar=driver_sidebar(cfg, key, tokens),
        mobile_nav_html=driver_mobile(cfg, tokens),
        hero=driver_hero(cfg, label, title, keys, optional),
        content=body,
    )
    out = SITE / cfg["driver_dir"] / f"{key}.html"
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(page, encoding="utf-8")
    entries = search_entries(
        body,
        title=title,
        lead=description,
        entry_type=cfg["driver_search_type"],
    )
    (SITE / index_name).write_text(
        json.dumps(entries, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    return len(entries)


def build_driver_index(shell: str, cfg: dict) -> int:
    cards = []
    entries = [{
        "title": cfg["driver_index_title"],
        "text": cfg["driver_index_lead"],
        "anchor": "main",
        "type": cfg["driver_search_type"],
    }]
    for key, label in DRIVERS:
        markdown_text = driver_markdown(cfg, key)
        _title, keys, optional, description = parse_driver(markdown_text)
        optional_text = cfg["none_label"] if optional == "none" else optional.replace("`", "")
        meta = " · ".join([" ".join(keys), optional_text])
        cards.append(f"""        <a class="driver-card" id="driver-{key}" href="{cfg['driver_dir']}/{key}.html">
          <span class="driver-card-name">{html.escape(label)}</span>
          <p>{html.escape(description[:180])}</p>
          <span class="driver-card-meta">{html.escape(meta)}</span>
        </a>""")
        entries.append({
            "title": label,
            "text": f"{' '.join(keys)} {description} {optional_text}".strip(),
            "anchor": f"driver-{key}",
            "type": cfg["driver_search_type"],
        })

    sidebar = f"""    <aside class="docs-sidebar" aria-label="{cfg['driver_index_title']}">
      <p class="docs-sidebar-title">{cfg['driver_index_title']}</p>
      <ul class="docs-toc-list">
        <li class="toc-h2"><a href="{cfg['drivers_page']}" aria-current="page">{cfg['all_drivers_label']}</a></li>
        {driver_links(cfg, prefix=cfg['driver_dir'] + '/')}
      </ul>
    </aside>"""
    mobile = (
        f'      <nav class="docs-mobile-nav" aria-label="{cfg["driver_index_title"]}">'
        + "".join(
            f'<a href="{cfg["driver_dir"]}/{key}.html">{html.escape(label)}</a>'
            for key, label in DRIVERS
        )
        + "</nav>"
    )
    hero = f"""      <header class="docs-hero">
        <p class="eyebrow">BIGDATA / DRIVERS</p>
        <h1>{cfg['driver_index_title']}</h1>
        <p class="docs-lead">{cfg['driver_index_lead']}</p>
        <div class="docs-hero-actions">
          <a class="btn btn-secondary" href="{cfg['page']}">{cfg['driver_breadcrumb_docs']} <svg class="icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
          <a class="btn btn-secondary" href="{cfg['home']}">{cfg['home_label']} <svg class="icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
        </div>
      </header>"""
    content = '      <div class="driver-grid">\n' + "\n".join(cards) + "\n      </div>"
    page = build_shell_page(
        shell,
        cfg,
        prefix="",
        active="drivers",
        other_href=cfg["drivers_other"],
        title=f"{cfg['driver_index_title']}{cfg['driver_title_suffix']}",
        description=cfg["driver_index_lead"],
        canonical=f"{SITE_URL}/{cfg['drivers_page']}",
        alternate_zh=f"{SITE_URL}/drivers.html",
        alternate_en=f"{SITE_URL}/en-drivers.html",
        search_index=cfg["driver_index_search"],
        sidebar=sidebar,
        mobile_nav_html=mobile,
        hero=hero,
        content=content,
    )
    (SITE / cfg["drivers_page"]).write_text(page, encoding="utf-8")
    (SITE / cfg["driver_index_search"]).write_text(
        json.dumps(entries, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    return len(entries)


def update_sitemap() -> None:
    path = SITE / "sitemap.xml"
    if not path.is_file() or "drivers.html" in path.read_text(encoding="utf-8"):
        return
    today = datetime.date.today().isoformat()
    entries = []
    for zh_url, en_url, priority in (
        ("/drivers.html", "/en-drivers.html", "0.8"),
        *[
            (f"/drivers/{key}.html", f"/en-drivers/{key}.html", "0.7")
            for key, _label in DRIVERS
        ],
    ):
        entries.append(f"""  <url>
    <loc>{SITE_URL}{zh_url}</loc>
    <lastmod>{today}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>{priority}</priority>
    <xhtml:link rel="alternate" hreflang="zh-CN" href="{SITE_URL}{zh_url}"/>
    <xhtml:link rel="alternate" hreflang="en" href="{SITE_URL}{en_url}"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="{SITE_URL}{zh_url}"/>
  </url>
  <url>
    <loc>{SITE_URL}{en_url}</loc>
    <lastmod>{today}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>{priority}</priority>
    <xhtml:link rel="alternate" hreflang="zh-CN" href="{SITE_URL}{zh_url}"/>
    <xhtml:link rel="alternate" hreflang="en" href="{SITE_URL}{en_url}"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="{SITE_URL}{zh_url}"/>
  </url>
""")
    path.write_text(
        path.read_text(encoding="utf-8").replace("</urlset>", "".join(entries) + "</urlset>"),
        encoding="utf-8",
    )


def main() -> None:
    if not (SITE / "index.html").is_file() or not (SITE / "en.html").is_file():
        raise SystemExit("error: run scripts/build-site.sh first; site/index.html and site/en.html are missing")

    for cfg in LANGS.values():
        shell = (SITE / cfg["shell"]).read_text(encoding="utf-8")
        shell = shell.replace(cfg["search_hint_old"], cfg["search_hint"])
        index_entries = build_driver_index(shell, cfg)
        total = 0
        for key, label in DRIVERS:
            total += build_driver_page(shell, cfg, key, label)
        print(f"built {cfg['drivers_page']} and {len(DRIVERS)} driver pages ({index_entries + total} search entries)")
    update_sitemap()


if __name__ == "__main__":
    main()
