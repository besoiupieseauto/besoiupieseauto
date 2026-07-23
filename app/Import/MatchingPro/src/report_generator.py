"""Generare rapoarte JSON/CSV."""

from __future__ import annotations

import csv
import json
from datetime import datetime
from pathlib import Path
from typing import Any

from matcher import STATUS_CONFLICT, STATUS_EXACT, STATUS_NO_MATCH, STATUS_PROBABLE
from paths import REPORTS_DIR, load_settings


def _status_icon(status: str) -> str:
    return {
        STATUS_EXACT: "exact",
        STATUS_PROBABLE: "probable",
        STATUS_NO_MATCH: "no_match",
        STATUS_CONFLICT: "conflict",
    }.get(status, status)


def _tecdoc_tables(method: str | None) -> str:
    if method == "tecdoc_ean":
        return "products · brands"
    if method in ("tecdoc_brand_code", "tecdoc_code"):
        return "product_codes · products · brands"
    return ""


def _normalize_source_file(row_input: dict, parse_result: dict) -> str:
    """supplier/fișier.csv — obligatoriu pentru lookup-ul de staging PHP."""
    supplier = str(row_input.get("supplier") or parse_result.get("supplier") or "").strip()
    source = str(row_input.get("source_file") or "").strip()
    filename = str(parse_result.get("filename") or "").strip()
    if source and "/" in source:
        return source
    leaf = source or filename
    if supplier and leaf:
        return f"{supplier}/{leaf}"
    return leaf or (f"{supplier}/" if supplier else "")


def build_report_payload(
    parse_result: dict,
    match_results: list[dict],
    summary: dict,
    mode: str,
) -> dict[str, Any]:
    return {
        "generated_at": datetime.now().isoformat(timespec="seconds"),
        "mode": mode,
        "file": parse_result.get("filename"),
        "filename": parse_result.get("filename"),
        "supplier": parse_result.get("supplier"),
        "supplier_label": parse_result.get("supplier_label"),
        "rows_parsed": parse_result.get("rows_parsed", 0),
        "summary": summary,
        "products": [
            {
                "row": r["input"].get("row"),
                "sku_supplier": r["input"].get("sku_supplier"),
                "ean": r["input"].get("ean"),
                "name": r["input"].get("name"),
                "price": r["input"].get("price"),
                "stock": r["input"].get("stock"),
                "status": r.get("status"),
                "status_icon": _status_icon(r.get("status", "")),
                "match_method": r.get("match_method"),
                "confidence": round(float(r.get("confidence", 0)), 2),
                "brand": r["input"].get("brand"),
                # Autonet QWP: match pe ReferenceBrand+RefNr; produs final = QWP + ArtNr
                "art_nr": r["input"].get("art_nr") or "",
                "ref_nr": r["input"].get("ref_nr") or "",
                "force_brand": r["input"].get("force_brand") or "",
                "supplier_profile": r["input"].get("supplier_profile") or "",
                # Necesare la staging PHP (cardLookupKey / ImportFastCardPipeline)
                "supplier": r["input"].get("supplier") or parse_result.get("supplier"),
                "source_file": _normalize_source_file(r["input"], parse_result),
                "matched_brand": (mp := (r.get("matched_product") or {})).get("brand"),
                "matched_ttc_art_id": mp.get("ttc_art_id"),
                "matched_internal_sku": mp.get("internal_sku"),
                "matched_name": mp.get("name"),
                "matched_source": mp.get("source"),
                "matched_product_id": mp.get("product_id"),
                "matched_codes": mp.get("codes") or [],
                "matched_ean": mp.get("ean"),
                "tecdoc_db": mp.get("tecdoc_db")
                or (
                    load_settings().get("tecdoc_mysql_db_qwp", "besoiu_tecdoc_y1998")
                    if (r["input"].get("supplier_profile") == "autonet_qwp")
                    else load_settings().get("tecdoc_mysql_db", "besoiu_tecdoc_base")
                ),
                "tecdoc_tables": _tecdoc_tables(r.get("match_method")),
                "qwp_base_product_id": mp.get("base_product_id"),
                "qwp_write_status": (r.get("qwp_tecdoc_write") or {}).get("status")
                or mp.get("qwp_write_status")
                or "",
                "conflicts": r.get("conflicts", []),
                "notes": r.get("notes", []),
            }
            for r in match_results
        ],
    }


def save_report(payload: dict, base_name: str) -> dict[str, str]:
    REPORTS_DIR.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    stem = f"{ts}_{base_name}"

    json_path = REPORTS_DIR / f"{stem}.json"
    csv_path = REPORTS_DIR / f"{stem}.csv"

    json_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    _write_csv(csv_path, payload.get("products", []))

    return {"json": str(json_path), "csv": str(csv_path), "id": stem}


def _write_csv(path: Path, products: list[dict]) -> None:
    fields = [
        "row", "sku_supplier", "ean", "name", "price", "stock",
        "status", "match_method", "confidence",
        "matched_internal_sku", "matched_name", "conflicts",
    ]
    with path.open("w", encoding="utf-8-sig", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        for p in products:
            row = dict(p)
            if row.get("conflicts"):
                row["conflicts"] = json.dumps(row["conflicts"], ensure_ascii=False)
            writer.writerow(row)


def list_reports(limit: int = 20) -> list[dict]:
    if not REPORTS_DIR.is_dir():
        return []
    files = sorted(REPORTS_DIR.glob("*.json"), key=lambda p: p.stat().st_mtime, reverse=True)
    out = []
    for path in files[:limit]:
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
            out.append({
                "id": path.stem,
                "file": data.get("file"),
                "supplier": data.get("supplier_label"),
                "generated_at": data.get("generated_at"),
                "summary": data.get("summary", {}),
                "json_path": str(path),
            })
        except (json.JSONDecodeError, OSError):
            continue
    return out
