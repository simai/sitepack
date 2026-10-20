"""Write redirect pages for the Docara 1.x upper-case addresses.

Docara 3 page addresses are lower case and its redirects.json accepts only
those. legacy-redirects.json maps the old addresses (SPEC, CHANGELOG,
LLM_README, ...) to the new pages. Usage, from docara/:

    python3 scripts/write-legacy-redirects.py build_production [base_path]

base_path is "" for a site at the domain root and "/sitepack" for the
GitHub Pages project path.
"""
import html
import json
import sys
from pathlib import Path

root = Path(sys.argv[1])
base = (sys.argv[2] if len(sys.argv) > 2 else "").rstrip("/")
for source, target in json.loads(Path("legacy-redirects.json").read_text()).items():
    assert (root / target / "index.html").is_file(), target
    page = root / source / "index.html"
    assert not page.exists(), source
    url = html.escape(f"{base}/{target}/", quote=True)
    page.parent.mkdir(parents=True, exist_ok=True)
    page.write_text(
        '<!doctype html><html><head><meta charset="utf-8">'
        f'<meta name="robots" content="noindex"><link rel="canonical" href="{url}">'
        f'<meta http-equiv="refresh" content="0; url={url}"><title>Page moved</title></head>'
        f'<body><a href="{url}">{url}</a></body></html>\n',
        encoding="utf-8",
    )
