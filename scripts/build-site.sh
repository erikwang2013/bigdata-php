#!/usr/bin/env bash
# Build site/ from docs/site/content.json using the project site builder,
# then inject the project theme over the shared builder CSS.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG=bigdata-php
CONTENT="$ROOT/docs/site/content.json"
THEME="$ROOT/docs/site/theme.css"

BUILDER="${SITE_BUILDER:-}"
if [ -z "$BUILDER" ]; then
  for candidate in "$ROOT/../.site-builder" "$ROOT/.site-builder"; do
    if [ -f "$candidate/build.py" ]; then
      BUILDER="$(cd "$candidate" && pwd)"
      break
    fi
  done
fi
if [ -z "$BUILDER" ] || [ ! -f "$BUILDER/build.py" ]; then
  echo "error: .site-builder/build.py not found." >&2
  echo "Looked in $ROOT/../.site-builder and $ROOT/.site-builder." >&2
  echo "Set SITE_BUILDER=/path/to/.site-builder if it lives elsewhere." >&2
  exit 1
fi

if [ ! -f "$CONTENT" ]; then
  echo "error: content source missing: $CONTENT" >&2
  exit 1
fi
if [ ! -f "$THEME" ]; then
  echo "error: theme source missing: $THEME" >&2
  exit 1
fi
if ! python3 -c 'import json, sys; json.load(open(sys.argv[1]))' "$CONTENT"; then
  echo "error: content source is not valid JSON: $CONTENT" >&2
  exit 1
fi

# The builder reads data/<slug>.json; swap in the committed source and restore
# the previous file on exit so the read-only builder tree stays untouched.
DATA="$BUILDER/data/$SLUG.json"
BACKUP=""
if [ -f "$DATA" ]; then
  BACKUP="$(mktemp)"
  cp -p "$DATA" "$BACKUP"
fi
restore_data() {
  if [ -n "$BACKUP" ]; then
    cp -p "$BACKUP" "$DATA"
    rm -f "$BACKUP"
  else
    rm -f "$DATA"
  fi
}
trap restore_data EXIT INT TERM

mkdir -p "$BUILDER/data"
cp "$CONTENT" "$DATA"
python3 "$BUILDER/build.py" "$SLUG"

for page in index.html en.html 404.html; do
  if [ ! -f "$ROOT/site/$page" ]; then
    echo "error: build did not produce site/$page" >&2
    exit 1
  fi
done

python3 - "$ROOT/site" "$THEME" <<'PY'
import sys
from pathlib import Path

site = Path(sys.argv[1])
theme = Path(sys.argv[2]).read_text(encoding="utf-8")
marker = "<!-- project theme: docs/site/theme.css -->"
tag = f'{marker}\n<style id="project-theme">\n{theme}\n</style>\n</head>'

for name in ("index.html", "en.html", "404.html"):
    page = site / name
    html = page.read_text(encoding="utf-8")
    if marker in html:
        raise SystemExit(f"error: theme already injected in {page}")
    if "</head>" not in html:
        raise SystemExit(f"error: no </head> in {page}")
    page.write_text(html.replace("</head>", tag, 1), encoding="utf-8")
print("Injected docs/site/theme.css")
PY

python3 "$ROOT/scripts/build-docs-page.py"
python3 "$ROOT/scripts/build-driver-pages.py"

echo "Built $ROOT/site from $CONTENT (site/ is git-ignored)"
