#!/usr/bin/env python3
"""Audit the built site with Chrome: LCP, CLS, overflow, search, lightbox, pets."""
from __future__ import annotations

import argparse
import functools
import http.server
import sys
import threading
from pathlib import Path

from playwright.sync_api import Page, sync_playwright

ROOT = Path(__file__).resolve().parents[1]
LCP_LIMIT_MS = 2500.0
CLS_LIMIT = 0.1
OVERFLOW_LIMIT_PX = 1

PERF_INIT = """
window.__perf = { lcp: 0, cls: 0 };
new PerformanceObserver((list) => {
  for (const entry of list.getEntries()) {
    window.__perf.lcp = Math.max(window.__perf.lcp, entry.startTime);
  }
}).observe({ type: 'largest-contentful-paint', buffered: true });
let cls = 0, session = 0, sessionStart = 0, last = 0;
new PerformanceObserver((list) => {
  for (const entry of list.getEntries()) {
    if (entry.hadRecentInput) continue;
    if (entry.startTime - last > 1000 || entry.startTime - sessionStart > 5000) {
      session = 0;
      sessionStart = entry.startTime;
    }
    session += entry.value;
    last = entry.startTime;
    cls = Math.max(cls, session);
  }
  window.__perf.cls = cls;
}).observe({ type: 'layout-shift', buffered: true });
"""


def serve(site: Path) -> tuple[http.server.ThreadingHTTPServer, str]:
    class QuietHandler(http.server.SimpleHTTPRequestHandler):
        def log_message(self, *args: object) -> None:
            pass

    handler = functools.partial(QuietHandler, directory=str(site))
    httpd = http.server.ThreadingHTTPServer(("127.0.0.1", 0), handler)
    threading.Thread(target=httpd.serve_forever, daemon=True).start()
    return httpd, f"http://127.0.0.1:{httpd.server_address[1]}"


def measure(page: Page, base: str, path: str) -> tuple[dict[str, float], list[str]]:
    errors: list[str] = []
    page.on("pageerror", lambda exc: errors.append(f"page error: {exc}"))
    page.route(
        "**/*",
        lambda route: route.continue_()
        if route.request.url.startswith(base + "/")
        else route.abort(),
    )
    page.goto(f"{base}/{path}", wait_until="load", timeout=30000)
    page.wait_for_timeout(2500)
    metrics = page.evaluate(
        """() => ({
            lcp: window.__perf ? window.__perf.lcp : 0,
            cls: window.__perf ? window.__perf.cls : 0,
            overflow: Math.max(0, document.documentElement.scrollWidth -
                                  document.documentElement.clientWidth),
        })"""
    )
    return metrics, errors


def check_ui(page: Page, errors: list[str]) -> list[str]:
    notes: list[str] = []

    try:
        page.locator("[data-search-open]").first.click()
        page.wait_for_function("document.getElementById('search-dialog').open", timeout=5000)
        page.locator("#search-input").fill("版本")
        page.wait_for_selector(".search-result", timeout=5000)
        notes.append(f"search: {page.locator('.search-result').count()} results for 版本")
        page.evaluate("document.getElementById('search-dialog').close()")
        page.wait_for_function("!document.getElementById('search-dialog').open", timeout=2000)
    except Exception:
        errors.append("search: dialog or results did not open")

    gallery = page.locator(".gallery-item")
    if not gallery.count():
        errors.append("gallery: no items")
    else:
        gallery.first.scroll_into_view_if_needed()
        page.wait_for_timeout(300)
        gallery.first.click()
        try:
            page.wait_for_function("document.getElementById('lightbox').open", timeout=3000)
            page.locator(".lightbox-close").click()
            page.wait_for_function("!document.getElementById('lightbox').open", timeout=3000)
            notes.append("gallery lightbox: opens and closes")
        except Exception:
            errors.append("gallery lightbox: did not open or close")

    pets = page.locator(".pet-tile")
    pet_count = pets.count()
    if pet_count < 12:
        errors.append(f"pet tiles: {pet_count} found, expected at least 12")
    else:
        pets.first.scroll_into_view_if_needed()
        page.wait_for_timeout(300)
        pets.first.click()
        try:
            page.wait_for_function("document.getElementById('lightbox').open", timeout=3000)
            src = page.locator("#lightbox img").get_attribute("src") or ""
            if "pet/expressions/" not in src:
                errors.append(f"pet lightbox: unexpected image {src}")
            page.locator(".lightbox-close").click()
            page.wait_for_function("!document.getElementById('lightbox').open", timeout=3000)
            notes.append(f"pet tiles: {pet_count} tiles, lightbox opens and closes")
        except Exception:
            errors.append("pet lightbox: did not open or close")

    return notes


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--site", type=Path, default=ROOT / "site", help="built site directory"
    )
    parser.add_argument(
        "--chrome", default=None, help="Chrome executable (default: /usr/bin/google-chrome)"
    )
    args = parser.parse_args()

    if not (args.site / "index.html").is_file():
        print(f"FAIL site not built: {args.site}/index.html is missing", file=sys.stderr)
        return 1
    chrome = args.chrome or "/usr/bin/google-chrome"
    if not Path(chrome).exists():
        chrome = None  # fall back to Playwright's bundled Chromium

    httpd, base = serve(args.site)
    failures: list[str] = []
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(executable_path=chrome, headless=True)
            try:
                for name, width, height in (
                    ("mobile", 390, 844),
                    ("desktop", 1440, 900),
                ):
                    context = browser.new_context(
                        viewport={"width": width, "height": height},
                        device_scale_factor=1,
                    )
                    context.add_init_script(PERF_INIT)
                    page = context.new_page()
                    metrics, errors = measure(page, base, "index.html")
                    failures.extend(errors)
                    lcp_ok = metrics["lcp"] <= LCP_LIMIT_MS
                    cls_ok = metrics["cls"] <= CLS_LIMIT
                    overflow_ok = metrics["overflow"] <= OVERFLOW_LIMIT_PX
                    status = "PASS" if lcp_ok and cls_ok and overflow_ok else "FAIL"
                    print(
                        f"[{status}] {name:7} {width}x{height}  "
                        f"LCP {metrics['lcp']:.0f} ms (<= {LCP_LIMIT_MS:.0f})  "
                        f"CLS {metrics['cls']:.3f} (<= {CLS_LIMIT})  "
                        f"overflow {metrics['overflow']:.0f} px (<= {OVERFLOW_LIMIT_PX})"
                    )
                    if status == "FAIL":
                        failures.append(f"{name}: LCP/CLS/overflow above threshold")
                    if name == "mobile":
                        for note in check_ui(page, failures):
                            print(f"[PASS] {note}")
                    context.close()
            finally:
                browser.close()
    finally:
        httpd.shutdown()
        httpd.server_close()

    for failure in failures:
        print(f"[FAIL] {failure}", file=sys.stderr)
    print("RESULT " + ("FAIL" if failures else "PASS"))
    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())
