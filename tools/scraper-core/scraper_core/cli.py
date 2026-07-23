#!/usr/bin/env python3
"""CLI bridge — motor PHP poate apela: python -m scraper_core.cli --url ... --json"""

from __future__ import annotations

import argparse
import json
import os
import sys

from scraper_core.core import ScraperCore
from scraper_core.models import FetchOptions, TransportKind


def main() -> int:
    parser = argparse.ArgumentParser(description="ScraperCore fetch (microkernel)")
    parser.add_argument("--url", required=False, default="", help="URL țintă (sau SCRAPER_CORE_URL în env)")
    parser.add_argument("--source-id", default="", help="ID sursă (autodoc, epiesa, …)")
    parser.add_argument("--referer", default=None)
    parser.add_argument("--proxy", default=None, help="http://user:pass@host:port")
    parser.add_argument("--timeout", type=float, default=60.0)
    parser.add_argument(
        "--transport",
        choices=[t.value for t in TransportKind],
        default=None,
    )
    parser.add_argument("--no-jitter", action="store_true")
    parser.add_argument("--json", action="store_true")
    parser.add_argument("--html-file", dest="html_file", help="Salvează HTML pe disc")
    args = parser.parse_args()

    url = (args.url or "").strip() or (os.environ.get("SCRAPER_CORE_URL") or "").strip()
    if not url:
        print(json.dumps({"success": False, "error": "URL lipsă (--url sau SCRAPER_CORE_URL)"}), file=sys.stderr)
        return 1

    transport = TransportKind(args.transport) if args.transport else None
    core = ScraperCore()
    packet = core.fetch(
        FetchOptions(
            url=url,
            source_id=args.source_id,
            referer=args.referer,
            proxy=args.proxy,
            timeout_sec=args.timeout,
            transport=transport,
            apply_jitter=not args.no_jitter,
        )
    )

    payload = packet.to_dict()
    html_file = (args.html_file or "").strip()
    if html_file and packet.html:
        with open(html_file, "w", encoding="utf-8", errors="replace") as fh:
            fh.write(packet.html)
        payload = dict(payload)
        payload["html_saved"] = html_file

    if args.json:
        out = dict(payload)
        if len(out.get("html", "")) > 500_000:
            out["html"] = ""
            out["html_truncated"] = True
        print(json.dumps(out, ensure_ascii=True))
    else:
        print(f"success={packet.success} status={packet.status_code} bytes={len(packet.html)}")

    return 0 if packet.success else 1


if __name__ == "__main__":
    sys.exit(main())
