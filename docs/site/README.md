# bigdata project site

Source and deployment files for <https://package.erik.xyz/bigdata/>. The generated
`site/` directory is a local, git-ignored build artifact; never edit it by hand.

| Path | Purpose |
|------|---------|
| `docs/site/content.json` | Committed copy of the site content source |
| `docs/site/nginx.conf` | Static-server sample (caching, compression, security headers) |
| `scripts/build-site.sh` | Reproducible build into `site/` |
| `scripts/site-check.py` | Playwright audit: LCP, CLS, overflow, search/lightbox/pet tiles |

## Build

Requirements: Python 3 and the project site builder, which lives next to this
package as `../.site-builder` (or point `SITE_BUILDER` at it). From the package
root:

```bash
./scripts/build-site.sh
```

The script validates `docs/site/content.json`, installs it as the builder's
`data/bigdata-php.json` temporarily, runs `python3 .site-builder/build.py
bigdata-php`, and restores the previous data file on exit. It fails with a clear
message when `.site-builder` is missing. The build takes about one second and
writes `site/index.html`, `site/en.html`, `site/404.html`, SEO files and local
assets.

`docs/site/content.json` is the source of truth. Refresh it when the external
content source changes, then rebuild:

```bash
cp ../.site-builder/data/bigdata-php.json docs/site/content.json
./scripts/build-site.sh
```

Current snapshot: 2026-10-30, SHA-256
`6da5eda0fd525c8791083fd08d4049916ca5244c4e75e8f21d06d1902047bac3`.

## Audit

```bash
python3 scripts/site-check.py
```

The script starts its own loopback HTTP server, so no external server is needed.
It loads the built site in headless Chrome through Playwright at mobile
(390x844) and desktop (1440x900) viewports, then checks:

- LCP `<= 2500 ms`
- CLS `<= 0.1`
- horizontal overflow `<= 1 px`
- search dialog returns results
- gallery lightbox opens and closes
- 12 pet tiles render and their lightbox opens and closes

Results from 2026-10-30 (Chrome 156, local server):

```
[PASS] mobile  390x844  LCP 1432 ms (<= 2500)  CLS 0.000 (<= 0.1)  overflow 0 px (<= 1)
[PASS] search: 8 results for 版本
[PASS] gallery lightbox: opens and closes
[PASS] pet tiles: 12 tiles, lightbox opens and closes
[PASS] desktop 1440x900  LCP 348 ms (<= 2500)  CLS 0.000 (<= 0.1)  overflow 0 px (<= 1)
RESULT PASS
```

Mobile LCP varies on a cold local server: one run measured 2556 ms, the next
passed at 1432 ms.

The builder's broader interaction test also passes:
`python3 .site-builder/test_ui.py bigdata-php` (start a static server on
`127.0.0.1:8765` rooted at the parent directory first).

## Deploy

Copy the generated tree to a web root and use `docs/site/nginx.conf` as a
starting point:

```bash
rsync -a --delete site/ /var/www/bigdata-php/
nginx -t && systemctl reload nginx
```

The sample serves `site/` as the document root, maps extensionless URLs to
`.html`, serves `404.html`, enables gzip (brotli is included but commented,
because it requires `ngx_brotli`), and adds `X-Content-Type-Options`,
`X-Frame-Options`, `Referrer-Policy` and `Permissions-Policy`. A CSP example is
commented out; enable it only after confirming every page still works.

Cache policy:

| Request | Header | Notes |
|---------|--------|-------|
| `/assets/*` | `public, max-age=31536000, immutable` | Asset filenames are not content-hashed, so purge the CDN/proxy cache after deploying changed files. |
| HTML and other paths | `public, max-age=300` | Short cache so content updates appear quickly. |
| 404 | no `Cache-Control` | Security headers still apply. |

Measured with `nginx -t` and a live `curl` check: HTML returns
`Cache-Control: public, max-age=300`, assets return the one-year immutable
header, gzip is applied to HTML, `/en` resolves to `en.html`, and unknown paths
return the generated 404 page with status 404.

For a subpath deployment (the canonical URL is `/bigdata/`), put the site in a
directory matching the URL prefix and use `root` rather than `alias`:

```nginx
location /bigdata/ {
    root /var/www;                 # serves /var/www/bigdata/...
    try_files $uri $uri/ $uri.html =404;
    error_page 404 /bigdata/404.html;
    # repeat the add_header lines from docs/site/nginx.conf
}
```

`add_header` is not inherited by a location that declares its own headers, so
keep the security and cache headers together in each `location` block.

## Optional busuanzi counter

The footer includes the busuanzi page-view counter, the only external request on
the page (`//busuanzi.ibruce.info/busuanzi/2.3/busuanzi.pure.mini.js`). It is
async and does not block rendering. To remove it after a build, strip the whole
footer-info block (script plus placeholder):

```bash
perl -0pi -e 's#\s*<div class="footer-info" id="busuanzi">.*?</div>##s' \
  site/index.html site/en.html
```

Alternatively block `busuanzi.ibruce.info` at the edge or with the CSP
`script-src`; the placeholder will then stay at zero, so the strip above gives a
cleaner footer. Do not edit the builder for this; keep it a deploy-time step.

## Performance notes

- 36 files, 2.2 MB total (the two 1200x630 social previews account for about
  0.6 MB and are not loaded by the page). The 670 KB three.js bundle loads after the page load
  event; HTML gzips to 23 KB and the hero script to 2.3 KB.
- Static-server benchmark (`python3 .site-builder/bench.py`): 245 responses for
  bigdata-php, median 0.58 ms, max 2.21 ms. nginx is faster than the Python
  server.
- Builder audit (`python3 .site-builder/audit.py`): `OK bigdata-php`.
