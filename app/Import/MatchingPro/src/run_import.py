#!/usr/bin/env python3
"""CLI unificat — scan manual, cron, listă rapoarte."""

from __future__ import annotations

import argparse
import io
import json
import sys
from pathlib import Path

# Permite import din src/
SRC = Path(__file__).resolve().parent
if str(SRC) not in sys.path:
    sys.path.insert(0, str(SRC))

from cron_watcher import run_watcher
from inspect_file import inspect_csv_file
from report_generator import list_reports
from scanner import scan_file, scan_paths, scan_uploads_temp


def _stdout_utf8() -> None:
    if hasattr(sys.stdout, "buffer"):
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")


def main() -> int:
    _stdout_utf8()
    parser = argparse.ArgumentParser(description="Modul import unificat — scan & matching")
    sub = parser.add_subparsers(dest="command", required=True)

    p_scan = sub.add_parser("scan", help="Scanează fișiere (manual)")
    p_scan.add_argument("--uploads", action="store_true", help="Scanează import/uploads/temp/")
    p_scan.add_argument("--file", action="append", default=[], help="Fișier CSV specific")
    p_scan.add_argument("--supplier", default=None, help="Forțează tip furnizor")
    p_scan.add_argument("--force", action="store_true", help="Ignoră idempotența")
    p_scan.add_argument("--sample", type=int, default=None, help="Limitează la N produse")
    p_scan.add_argument("--skip-rows", type=int, default=0, help="Sare peste N rânduri CSV (date)")
    p_scan.add_argument("--no-archive", action="store_true", help="Nu muta în processed/")

    p_cron = sub.add_parser("cron", help="Rulează cron watcher pe import/suppliers/")
    p_cron.add_argument("--force", action="store_true", help="Reprocesează fișiere cunoscute")
    p_cron.add_argument("--sample", type=int, default=None, help="Limitează la N produse/fișier")
    p_cron.add_argument(
        "--total-sample",
        type=int,
        default=None,
        help="Limitează la N produse TOTAL (agregat pe toate fișierele), oprește scanarea când e atins",
    )

    p_reports = sub.add_parser("reports", help="Listează rapoarte recente")
    p_reports.add_argument("--limit", type=int, default=10)

    p_inspect = sub.add_parser("inspect", help="Inspectează mapping CSV (celule + match preview)")
    p_inspect.add_argument("--file", required=True, help="Fișier CSV")
    p_inspect.add_argument("--supplier", default=None, help="Forțează tip furnizor")
    p_inspect.add_argument("--sample", type=int, default=8, help="Nr. rânduri eșantion")

    args = parser.parse_args()

    if args.command == "scan":
        if args.uploads:
            results = scan_uploads_temp(force_reprocess=args.force, sample_limit=args.sample)
        elif args.file:
            results = scan_paths(
                args.file,
                forced_supplier=args.supplier,
                force_reprocess=args.force,
                sample_limit=args.sample,
                skip_rows=max(0, int(args.skip_rows or 0)),
                move_to_processed=not args.no_archive,
            )
        else:
            print("Specifică --uploads sau --file", file=sys.stderr)
            return 1
        print(json.dumps(results, ensure_ascii=False, indent=2))
        return 0

    if args.command == "cron":
        summary = run_watcher(
            force=args.force,
            sample_limit=args.sample,
            total_limit=args.total_sample,
        )
        print(json.dumps(summary, ensure_ascii=False, indent=2))
        return 0

    if args.command == "reports":
        print(json.dumps(list_reports(args.limit), ensure_ascii=False, indent=2))
        return 0

    if args.command == "inspect":
        payload = inspect_csv_file(
            Path(args.file),
            forced_supplier=args.supplier,
            sample_rows=args.sample,
        )
        print(json.dumps(payload, ensure_ascii=False, indent=2))
        return 0

    return 1


if __name__ == "__main__":
    raise SystemExit(main())
