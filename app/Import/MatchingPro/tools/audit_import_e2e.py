#!/usr/bin/env python3
"""Audit E2E Import Pro — scan TecDoc + simulare scenarii (Python layer)."""
from __future__ import annotations

import json
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[4]
MP = ROOT / "app" / "Import" / "MatchingPro"
SRC = MP / "src"
FEEDS = ROOT / "app" / "Backend" / "storage" / "supplier_feeds"
SAMPLE = 10

sys.path.insert(0, str(SRC))

SUPPLIERS = ["autototal", "autonet", "materom", "elit", "autopartner", "intercars"]


def run_scan(supplier: str, path: Path, sample: int) -> dict:
    t0 = time.time()
    cmd = [
        sys.executable,
        str(SRC / "run_import.py"),
        "scan",
        "--file",
        str(path),
        "--supplier",
        supplier,
        "--no-archive",
        "--force",
        "--sample",
        str(sample),
    ]
    proc = subprocess.run(cmd, capture_output=True, text=True, timeout=120, cwd=str(SRC))
    ms = round((time.time() - t0) * 1000, 1)
    if proc.returncode != 0:
        return {"ok": False, "ms": ms, "error": (proc.stderr or proc.stdout)[:300], "summary": {}}
    try:
        data = json.loads(proc.stdout)
        last = data[-1] if isinstance(data, list) and data else {}
        payload = last.get("payload") or {}
        summary = payload.get("summary") or {}
        products = payload.get("products") or []
        no_match_samples = [
            {
                "sku": p.get("sku_supplier"),
                "brand": p.get("brand"),
                "notes": p.get("notes", []),
            }
            for p in products
            if p.get("status") == "no_match"
        ][:3]
        return {
            "ok": True,
            "ms": ms,
            "summary": summary,
            "no_match_samples": no_match_samples,
            "products": len(products),
        }
    except json.JSONDecodeError as e:
        return {"ok": False, "ms": ms, "error": f"JSON: {e}", "summary": {}}


def run_inspect(path: Path) -> dict:
    cmd = [
        sys.executable,
        str(SRC / "run_import.py"),
        "inspect",
        "--file",
        str(path),
        "--sample",
        "3",
    ]
    proc = subprocess.run(cmd, capture_output=True, text=True, timeout=60, cwd=str(SRC))
    if proc.returncode != 0:
        return {"ok": False, "mapping_issues": []}
    try:
        data = json.loads(proc.stdout)
        cm = data.get("column_mapping") or {}
        missing = [
            f
            for f in ("sku", "name", "brand", "price")
            if cm.get(f, {}).get("source_column") in ("(lipsă)", "", None)
        ]
        issues = data.get("normalization_issues") or []
        return {"ok": True, "missing_fields": missing, "issues": issues[:5]}
    except json.JSONDecodeError:
        return {"ok": False, "mapping_issues": []}


def tecdoc_available() -> dict:
    try:
        from tecdoc_lookup import get_tecdoc_lookup

        return get_tecdoc_lookup().stats()
    except Exception as e:
        return {"available": False, "error": str(e)}


def main() -> int:
    print(f"=== AUDIT E2E IMPORT PRO (Python) sample={SAMPLE} ===\n")
    tecdoc = tecdoc_available()
    print(f"TecDoc MySQL: {'OK' if tecdoc.get('available') else 'OFF'}")
    if tecdoc.get("products"):
        print(f"  ~{tecdoc.get('products')} produse indexate\n")

    report = []
    for slug in SUPPLIERS:
        feed_dir = FEEDS / slug
        files = sorted(feed_dir.glob("*.csv"), key=lambda p: p.stat().st_mtime, reverse=True) if feed_dir.is_dir() else []
        path = files[0] if files else None
        print(f"--- {slug} ---")
        if path is None:
            row = {
                "supplier": slug,
                "feed": None,
                "tecdoc_match": "0/10",
                "manual_no_img": "❌",
                "manual_with_img": "❌",
                "cron_no_img": "❌",
                "cron_with_img": "❌",
                "errors": ["Feed CSV lipsă în supplier_feeds"],
                "status": "❌ FAIL",
            }
            report.append(row)
            print("  SKIP — feed lipsă\n")
            continue

        inspect = run_inspect(path)
        scan = run_scan(slug, path, SAMPLE)
        s = scan.get("summary") or {}
        matched = int(s.get("exact", 0)) + int(s.get("probable", 0)) + int(s.get("conflict", 0))
        total = int(s.get("total", SAMPLE))
        match_label = f"{matched}/{total}"

        errors = []
        if inspect.get("missing_fields"):
            errors.append(f"Mapare lipsă: {', '.join(inspect['missing_fields'])}")
        if not scan.get("ok"):
            errors.append(f"Scan: {scan.get('error', 'fail')}")
        elif matched == 0:
            errors.append("0 match TecDoc în eșantion")

        # Manual fără img = scan + match OK (carduri generate din match)
        pass_manual_no = scan.get("ok") and matched > 0
        # Cu img = depinde de Poze/Autopartner — marcat separat
        pass_manual_img = "⚠️"  # necesită PHP ProductMatcher.attachImageToCard
        pass_cron = pass_manual_no  # același motor Python

        status = "✅ PASS" if pass_manual_no and not inspect.get("missing_fields") else "❌ FAIL"
        if pass_manual_no and inspect.get("missing_fields"):
            status = "⚠️ PARTIAL"

        row = {
            "supplier": slug,
            "feed": path.name,
            "tecdoc_match": match_label,
            "tecdoc_detail": s,
            "scan_ms": scan.get("ms"),
            "missing_mapping": inspect.get("missing_fields", []),
            "no_match_samples": scan.get("no_match_samples", []),
            "manual_no_img": "✅" if pass_manual_no else "❌",
            "manual_with_img": "⚠️",
            "cron_no_img": "✅" if pass_cron else "❌",
            "cron_with_img": "⚠️",
            "errors": errors,
            "status": status,
        }
        report.append(row)

        print(f"  feed: {path.name}")
        print(f"  mapare lipsa: {inspect.get('missing_fields') or '-'}")
        print(f"  TecDoc: exact={s.get('exact',0)} probable={s.get('probable',0)} no_match={s.get('no_match',0)} → {match_label}")
        print(f"  scan: {scan.get('ms')}ms")
        if errors:
            print(f"  erori: {' | '.join(errors)}")
        print(f"  status: {status}\n")

    out = MP / "reports" / f"audit_e2e_{time.strftime('%Y%m%d_%H%M%S')}.json"
    out.write_text(
        json.dumps(
            {"generated_at": time.strftime("%Y-%m-%dT%H:%M:%S"), "sample": SAMPLE, "tecdoc": tecdoc, "suppliers": report},
            ensure_ascii=False,
            indent=2,
        ),
        encoding="utf-8",
    )

    print("=== TABEL REZUMAT ===")
    print(f"{'Furnizor':<12} | {'Man-no':<6} | {'Man+img':<7} | {'Cron-no':<7} | {'Cron+img':<7} | {'TecDoc':<8} | Status")
    for r in report:
        print(
            f"{r['supplier']:<12} | {r['manual_no_img']:<6} | {r['manual_with_img']:<7} | "
            f"{r['cron_no_img']:<7} | {r['cron_with_img']:<7} | {r['tecdoc_match']:<8} | {r['status']}"
        )
    print(f"\nRaport: {out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
