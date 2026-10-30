#!/usr/bin/env python3
"""Build the standalone documentation pages and the homepage install CTA."""
from __future__ import annotations

import datetime
import json
import re

from site_pages import (
    GITHUB,
    LANGS,
    SITE,
    SITE_URL,
    build_shell_page,
    mobile_nav,
    render_markdown,
    search_entries,
    toc_list,
)

def readme_body(cfg: dict) -> str:
    text = cfg["readme"].read_text(encoding="utf-8")
    begin = text.index(cfg["start"])
    stop = text.index(cfg["end"], begin)
    text = text[begin:stop]
    return re.sub(r"^(#{2,3}) \d+\.\s*", r"\1 ", text, flags=re.M)


def docs_sidebar(cfg: dict, toc: str) -> str:
    return f"""    <aside class="docs-sidebar" aria-label="{cfg['toc_title']}">
      <p class="docs-sidebar-title">{cfg['driver_index_title']}</p>
      <ul class="docs-toc-list"><li class="toc-h2"><a href="{cfg['drivers_page']}">{cfg['all_drivers_label']}</a></li></ul>
      <p class="docs-sidebar-title docs-sidebar-title--sub">{cfg['toc_title']}</p>
      {toc}
    </aside>"""


def docs_hero(cfg: dict) -> str:
    return f"""      <header class="docs-hero">
        <p class="eyebrow">{cfg['hero_kicker']}</p>
        <h1>{cfg['hero_title']}</h1>
        <p class="docs-lead">{cfg['hero_lead']}</p>
        <div class="docs-hero-actions">
          <a class="btn btn-primary" href="{GITHUB}#readme" target="_blank" rel="noopener">{cfg['readme_label']} <svg class="icon" aria-hidden="true"><use href="#i-external"></use></svg></a>
          <a class="btn btn-secondary" href="{cfg['drivers_page']}">{cfg['driver_index_title']} <svg class="icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
          <a class="btn btn-secondary" href="{cfg['home']}">{cfg['home_label']} <svg class="icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
        </div>
      </header>"""


def build_page(shell: str, cfg: dict) -> tuple[str, list[dict]]:
    body, tokens = render_markdown(
        readme_body(cfg),
        "zh" if cfg["html_lang"] == "zh-CN" else "en",
        localize_drivers=True,
    )
    sidebar = docs_sidebar(cfg, toc_list(tokens))
    mobile = f'      <nav class="docs-mobile-nav" aria-label="{cfg["toc_title"]}">{mobile_nav(tokens)}</nav>'
    page = build_shell_page(
        shell,
        cfg,
        prefix="",
        active="docs",
        other_href=cfg["other"],
        title=cfg["title"],
        description=cfg["description"],
        canonical=cfg["canonical"],
        alternate_zh=f"{SITE_URL}/docs.html",
        alternate_en=f"{SITE_URL}/en-docs.html",
        search_index=cfg["search_index"],
        sidebar=sidebar,
        mobile_nav_html=mobile,
        hero=docs_hero(cfg),
        content=body,
    )
    return page, tokens


def cta_section(cfg: dict) -> str:
    return f"""  <section class="section docs-cta" id="docs" aria-labelledby="docs-title">
    <div class="wrap docs-cta-grid">
      <div class="docs-cta-copy reveal">
        <p class="eyebrow">GET STARTED</p>
        <h2 id="docs-title">{cfg['cta_title']}</h2>
        <p>{cfg['cta_sub']}</p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="{cfg['page']}">{cfg['cta_button']} <svg class="icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
          <a class="btn btn-secondary" href="{cfg['drivers_page']}">{cfg['driver_index_title']} <svg class="icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
          <a class="btn btn-secondary" href="{GITHUB}#readme" target="_blank" rel="noopener">{cfg['readme_label']} <svg class="icon" aria-hidden="true"><use href="#i-external"></use></svg></a>
        </div>
      </div>
      <div class="docs-cta-code reveal" style="--delay:80ms">
        <div class="code-block code-block--install"><span class="code-label">{cfg['cta_install']}</span><pre><code>composer require erikwang2013/bigdata-php</code></pre></div>
        <div class="code-block"><span class="code-label">{cfg['cta_quick']}</span><pre><code>use Erikwang2013\\BigData\\BigData;

$trino = BigData::driver('trino', [
    'host' =&gt; '127.0.0.1',
    'port' =&gt; 8080,
    'user' =&gt; 'php',
    'catalog' =&gt; 'hive',
    'schema' =&gt; 'default',
]);

$rows = $trino-&gt;query('SELECT 1 AS id')-&gt;rows();</code></pre></div>
      </div>
    </div>
  </section>"""


def patch_home(cfg: dict) -> None:
    page = SITE / cfg["shell"]
    text = page.read_text(encoding="utf-8").replace(cfg["search_hint_old"], cfg["search_hint"])
    header = re.search(r'<header class="site-header">.*?</header>', text, re.S)
    if header is None:
        raise SystemExit(f"error: header not found in {page}")
    header_html = header.group(0)
    if 'href="#docs" data-section="docs"' in header_html:
        header_html = header_html.replace('href="#docs" data-section="docs"', f'href="{cfg["page"]}"')
    if f'href="{cfg["drivers_page"]}"' not in header_html:
        pattern = re.compile(rf'(<a [^>]*href="{re.escape(cfg["page"])}"[^>]*>[^<]*</a>)')
        header_html, _ = pattern.subn(
            lambda match: match.group(1) + f'\n      <a href="{cfg["drivers_page"]}">{cfg["driver_nav"]}</a>',
            header_html,
            count=1,
        )
    text = text.replace(header.group(0), header_html, 1)
    text = re.sub(
        r'  <section class="section docs(?:-cta)?" id="docs".*?</section>',
        lambda _match: cta_section(cfg),
        text,
        count=1,
        flags=re.S,
    )
    if ".docs-cta-grid {" not in text:
        text = text.replace("</head>", f'<style id="docs-cta-style">\n{cta_css()}\n</style>\n</head>', 1)
    page.write_text(text, encoding="utf-8")

    index_path = SITE / cfg["home_index"]
    entries = json.loads(index_path.read_text(encoding="utf-8"))
    entries = [entry for entry in entries if entry.get("anchor") != "docs"]
    insert_at = next((i for i, entry in enumerate(entries) if entry.get("anchor") == "pets"), len(entries))
    entries.insert(insert_at, {
        "title": cfg["cta_title"],
        "text": cfg["cta_home_search"],
        "anchor": "docs",
        "type": cfg["home_search_type"],
    })
    index_path.write_text(json.dumps(entries, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def cta_css() -> str:
    theme = (ROOT / "docs/site/theme.css").read_text(encoding="utf-8")
    match = re.search(r"/\* docs-cta:start \*/(.*?)/\* docs-cta:end \*/", theme, re.S)
    return match.group(1).strip() if match else ""


def update_sitemap() -> None:
    path = SITE / "sitemap.xml"
    if not path.is_file() or "docs.html" in path.read_text(encoding="utf-8"):
        return
    today = datetime.date.today().isoformat()
    entries = f"""  <url>
    <loc>{SITE_URL}/docs.html</loc>
    <lastmod>{today}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
    <xhtml:link rel="alternate" hreflang="zh-CN" href="{SITE_URL}/docs.html"/>
    <xhtml:link rel="alternate" hreflang="en" href="{SITE_URL}/en-docs.html"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="{SITE_URL}/docs.html"/>
  </url>
  <url>
    <loc>{SITE_URL}/en-docs.html</loc>
    <lastmod>{today}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
    <xhtml:link rel="alternate" hreflang="zh-CN" href="{SITE_URL}/docs.html"/>
    <xhtml:link rel="alternate" hreflang="en" href="{SITE_URL}/en-docs.html"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="{SITE_URL}/docs.html"/>
  </url>
"""
    path.write_text(path.read_text(encoding="utf-8").replace("</urlset>", entries + "</urlset>"), encoding="utf-8")


def main() -> None:
    if not (SITE / "index.html").is_file() or not (SITE / "en.html").is_file():
        raise SystemExit("error: run scripts/build-site.sh first; site/index.html and site/en.html are missing")

    for lang, cfg in LANGS.items():
        shell = (SITE / cfg["shell"]).read_text(encoding="utf-8")
        shell = shell.replace(cfg["search_hint_old"], cfg["search_hint"])
        page, tokens = build_page(shell, cfg)
        (SITE / cfg["page"]).write_text(page, encoding="utf-8")
        body = re.search(r'<article class="docs-content">(.*?)</article>', page, re.S)
        if body is None:
            raise SystemExit(f"error: docs content missing for {lang}")
        index = search_entries(
            body.group(1),
            title=cfg["hero_title"],
            lead=cfg["hero_lead"],
            entry_type=cfg["search_type"],
        )
        (SITE / cfg["search_index"]).write_text(
            json.dumps(index, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )
        patch_home(cfg)
        print(f"built {cfg['page']} ({len(tokens)} headings, {len(index)} search entries)")
    update_sitemap()



if __name__ == "__main__":
    main()
