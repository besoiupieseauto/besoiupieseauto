"""Inspectare CSV — celule brute, mapping coloane, preview matching."""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any

from catalog import build_catalog
from matcher import ProductMatcher, STATUS_EXACT, STATUS_PROBABLE, STATUS_NO_MATCH, similarity
from parser import (
    get_field,
    get_field_source,
    normalize_product_row,
    parse_price,
    positional_assoc,
    read_raw_rows,
    resolve_supplier_profile,
    row_to_assoc,
)
from normalize_issues import detect_normalization_issues, normalization_rules_summary
from paths import CONFIG_DIR, MATC_DIR, PRICE_CACHE, load_mapping_overrides, load_settings, load_suppliers, merge_supplier_config
from tecdoc_lookup import get_tecdoc_lookup

def build_data_sources(matcher: ProductMatcher, settings: dict) -> list[dict[str, Any]]:
    sources: list[dict[str, Any]] = []
    tecdoc_only = bool(settings.get("use_tecdoc_only", True))

    if tecdoc_only:
        tecdoc = get_tecdoc_lookup()
        stats = tecdoc.stats()
        sources.append({
            "id": "tecdoc_mysql",
            "label": "MySQL besoiu_tecdoc_base (TecDoc)",
            "path": f"mysql://{settings.get('tecdoc_mysql_host', '127.0.0.1')}/{settings.get('tecdoc_mysql_db', 'besoiu_tecdoc_base')}",
            "table": "products · product_codes · brands · product_compatibilities",
            "columns": ["brand", "code_norm", "art_code_1", "art_name", "art_ean", "ttc_art_id"],
            "match_field": "brand + code_norm",
            "match_how": "Producător mapat + cod OEM → product_codes → products",
            "active": tecdoc.available,
            "rows": stats.get("products"),
            "stats": stats,
            "used_in": "Matching scan — SINGURA sursă (TecDoc veridic)",
        })
        return sources

    price_rows: int | None = None
    if matcher.price_lookup and matcher.price_lookup.available:
        conn = matcher.price_lookup._connection()
        if conn:
            try:
                price_rows = int(conn.execute("SELECT COUNT(*) FROM prices").fetchone()[0])
            except Exception:
                price_rows = None

    sources.append({
        "id": "prices",
        "label": "price-index.sqlite",
        "path": str(PRICE_CACHE),
        "table": "prices",
        "columns": ["code", "price", "supplier"],
        "match_field": "code",
        "match_how": "Cod SKU normalizat din CSV → prices.code",
        "active": bool(matcher.price_lookup.available and settings.get("use_price_cache", True)),
        "rows": price_rows,
        "used_in": "Matching scan (tabel verificare) — prioritar după cod OEM",
    })

    demo_file = CONFIG_DIR / settings.get("demo_catalog_file", "demo_catalog.json")
    cat = matcher.catalog
    base_active = settings.get("use_base_catalog", False)
    matc_count = len(list(MATC_DIR.glob("UTF8-*-ro.csv"))) if MATC_DIR.is_dir() else 0

    sources.append({
        "id": "catalog",
        "label": "Catalog (memorie)",
        "path": str(demo_file) + (" + Fisierile Matc/" if base_active else ""),
        "table": "by_ean · by_code · by_internal_sku · names",
        "columns": ["internal_sku", "codes", "ean", "name", "brand", "price"],
        "match_field": "ean / codes / internal_sku / name",
        "match_how": "EAN exact, cod normalizat, SKU mapat, similaritate nume",
        "active": True,
        "rows": len(cat.by_code),
        "stats": cat.stats,
        "base_csv_files": matc_count if base_active else 0,
        "used_in": "Matching scan — demo 16 produse (nu e TecDoc MySQL)",
    })

    return sources


def match_db_label(match: dict[str, Any]) -> str:
    method = match.get("method") or ""
    mp = match.get("matched_product") or {}
    source = mp.get("source") or ""
    if method.startswith("tecdoc") or source == "tecdoc_mysql":
        if method == "tecdoc_ean":
            return "products.art_ean"
        if method == "tecdoc_code":
            return "product_codes"
        return "product_codes+brands"
    if method == "price_sqlite" or source == "price_cache":
        return "prices"
    if source == "base_csv":
        return "Matc CSV"
    if method == "ean":
        return "catalog.by_ean"
    if method in ("sku_map", "sku_code"):
        return "catalog.by_code"
    if method == "fuzzy_name":
        return "catalog.names"
    return "—"


def explain_detection(header: list[str], filename: str, supplier_key: str, supplier_cfg: dict) -> dict[str, Any]:
    suppliers = load_suppliers()
    header_set = {h.upper().replace(" ", "") for h in header}
    name_lower = filename.lower()
    reasons: list[str] = []

    for key, cfg in suppliers.items():
        det = cfg.get("detection", {})
        fn_tokens = det.get("filename_contains", [])
        if fn_tokens and any(t.lower() in name_lower for t in fn_tokens):
            reasons.append(f"nume fișier conține: {', '.join(fn_tokens)}")
            if key == supplier_key:
                return {"supplier": supplier_key, "reasons": reasons, "matched_rule": "filename_contains"}

        req_all = [re.sub(r"[^A-Z0-9]", "", h.upper()) for h in det.get("headers_all", [])]
        if req_all and all(h in header_set for h in req_all):
            reasons.append(f"antet conține toate: {', '.join(det.get('headers_all', []))}")
            if key == supplier_key:
                return {"supplier": supplier_key, "reasons": reasons, "matched_rule": "headers_all"}

        req_any = [re.sub(r"[^A-Z0-9]", "", h.upper()) for h in det.get("headers_any", [])]
        if req_any and any(h in header_set for h in req_any):
            reasons.append(f"antet conține unul din: {', '.join(det.get('headers_any', []))}")
            if key == supplier_key:
                return {"supplier": supplier_key, "reasons": reasons, "matched_rule": "headers_any"}

    if supplier_key == "generic":
        reasons.append("niciun furnizor specific — folosit profil generic")
    return {"supplier": supplier_key, "reasons": reasons, "matched_rule": "generic_fallback"}


def build_column_mapping(
    header: list[str],
    supplier_cfg: dict,
    sample_assoc: dict[str, str] | None = None,
) -> dict[str, Any]:
    cols = supplier_cfg.get("columns", {})
    pos = supplier_cfg.get("positional_fallback")
    mapping: dict[str, Any] = {}
    assoc = sample_assoc or {}

    for field in ("sku", "ean", "name", "price", "stock", "brand"):
        aliases = cols.get(field, [])
        if pos and "_sku" in (sample_assoc or {}):
            idx = pos.get(field) if field != "sku" else pos.get("sku")
            if field == "ean":
                src = "—"
                val = ""
            elif idx is not None:
                src = f"coloana #{idx} (pozițional)"
                val = assoc.get(f"_{field}", "") if field != "sku" else assoc.get("_sku", "")
            else:
                src = ""
                val = ""
            mapping[field] = {
                "configured_aliases": aliases,
                "source_column": src,
                "matched_alias": "positional_fallback",
                "sample_value": val,
            }
        else:
            src_info = get_field_source(assoc, aliases) if assoc else {"value": "", "source_column": "", "matched_alias": ""}
            mapping[field] = {
                "configured_aliases": aliases,
                "source_column": src_info["source_column"] or "(lipsă)",
                "matched_alias": src_info["matched_alias"] or "(niciun alias potrivit)",
                "sample_value": src_info["value"],
            }

    mapping["_meta"] = {
        "positional_fallback": pos,
        "extra_codes": supplier_cfg.get("extra_codes", []),
        "sku_map": supplier_cfg.get("sku_map", {}),
        "brand_code_trim": supplier_cfg.get("brand_code_trim", False),
        "has_override": supplier_cfg.get("_has_mapping_override", False),
    }
    return mapping


def trace_match_steps(matcher: ProductMatcher, product: dict[str, Any]) -> list[dict[str, Any]]:
    settings = matcher.settings
    fuzzy_threshold = float(settings.get("fuzzy_threshold", 85))
    fuzzy_probable_min = float(settings.get("fuzzy_probable_min", 70))
    steps: list[dict[str, Any]] = []

    ean = re.sub(r"\D", "", product.get("ean", "") or "")
    if ean:
        hit = ean in matcher.catalog.by_ean
        steps.append({
            "step": "ean",
            "tried": f"EAN={ean}",
            "result": "găsit" if hit else "negăsit",
            "matched": hit,
            "detail": "catalog.by_ean" if hit else "EAN absent din catalog",
        })
    else:
        steps.append({"step": "ean", "tried": "(gol)", "result": "sărit", "matched": False, "detail": "fără EAN în CSV"})

    mapped = product.get("sku_mapped") or ""
    if mapped:
        hit = mapped in matcher.catalog.by_internal_sku
        steps.append({
            "step": "sku_map",
            "tried": f"sku_map[{product.get('sku_supplier')}]={mapped}",
            "result": "găsit" if hit else "negăsit",
            "matched": hit,
            "detail": "catalog.by_internal_sku" if hit else "mapare configurată dar cod intern lipsă",
        })
    else:
        steps.append({"step": "sku_map", "tried": "(fără mapare)", "result": "sărit", "matched": False, "detail": "sku_map neconfigurat sau SKU nemapat"})

    codes = product.get("codes", [])
    code_hit = None
    for code in codes:
        if code in matcher.catalog.by_code:
            code_hit = code
            break
    steps.append({
        "step": "sku_code",
        "tried": ", ".join(codes[:6]) + ("…" if len(codes) > 6 else "") if codes else "(fără coduri)",
        "result": "găsit" if code_hit else "negăsit",
        "matched": code_hit is not None,
        "detail": f"catalog.by_code[{code_hit}]" if code_hit else "niciun cod în catalog.by_code",
    })

    price_hit = None
    if matcher.price_lookup.available:
        price_hit = matcher.price_lookup.lookup_codes(codes)
        steps.append({
            "step": "price_sqlite",
            "tried": "lookup_codes în price-index.sqlite",
            "result": "găsit" if price_hit else "negăsit",
            "matched": price_hit is not None,
            "detail": "price-index.sqlite" if price_hit else "coduri absente din SQLite prețuri",
        })
    else:
        steps.append({
            "step": "price_sqlite",
            "tried": "—",
            "result": "dezactivat",
            "matched": False,
            "detail": "use_price_cache=false sau fișier SQLite indisponibil",
        })

    name = product.get("name") or ""
    if name and matcher.catalog.names:
        best_score = 0.0
        best_name = ""
        for cat_name, _item in matcher.catalog.names[:5000]:
            score = similarity(name, cat_name)
            if score > best_score:
                best_score = score
                best_name = cat_name
        if best_score >= fuzzy_threshold:
            verdict = "exact fuzzy"
        elif best_score >= fuzzy_probable_min:
            verdict = "probabil fuzzy"
        else:
            verdict = "sub prag"
        steps.append({
            "step": "fuzzy_name",
            "tried": name[:80] + ("…" if len(name) > 80 else ""),
            "result": f"{best_score:.1f}% — {verdict}",
            "matched": best_score >= fuzzy_probable_min,
            "detail": f"cel mai apropiat: «{best_name[:60]}»" if best_name else "fără candidat",
        })
    else:
        steps.append({
            "step": "fuzzy_name",
            "tried": name or "(gol)",
            "result": "sărit",
            "matched": False,
            "detail": "fără nume sau catalog gol",
        })

    return steps


def inspect_csv_file(
    path: Path,
    *,
    forced_supplier: str | None = None,
    sample_rows: int = 8,
) -> dict[str, Any]:
    path = Path(path)
    settings = load_settings()
    max_sample = max(1, min(int(sample_rows), 30))

    header, data_rows, delimiter, has_header, _ = read_raw_rows(path, max_data_rows=max_sample)
    if not header and not data_rows:
        raise ValueError(f"Fișier gol sau invalid: {path.name}")

    overrides = load_mapping_overrides()
    supplier_key, base_cfg = resolve_supplier_profile(header, path.name, forced_supplier)
    supplier_cfg = merge_supplier_config(supplier_key, base_cfg, overrides.get(supplier_key))
    detection = explain_detection(header, path.name, supplier_key, base_cfg)

    raw_sample = {
        "header": header,
        "rows": data_rows[:max_sample],
        "row_count_shown": min(len(data_rows), max_sample),
        "row_count_total_hint": len(data_rows),
    }

    first_row = data_rows[0] if data_rows else []
    pos_assoc = positional_assoc(first_row, supplier_cfg)
    first_assoc = pos_assoc if pos_assoc else row_to_assoc(header, first_row)
    column_mapping = build_column_mapping(header, supplier_cfg, first_assoc)

    use_demo_only = supplier_cfg.get("use_demo_catalog", False)
    settings = load_settings()
    if settings.get("use_tecdoc_only", True):
        matcher = ProductMatcher()
    else:
        catalog = build_catalog(force_demo=use_demo_only)
        matcher = ProductMatcher(catalog)

    rows_inspected: list[dict[str, Any]] = []
    for idx, row in enumerate(data_rows[:max_sample], start=2 if has_header else 1):
        pos = positional_assoc(row, supplier_cfg)
        assoc = pos if pos else row_to_assoc(header, row)
        product = normalize_product_row(assoc, supplier_key, supplier_cfg, idx, path.name)
        if not product["sku_supplier"] and not product["ean"] and not product["name"]:
            continue

        field_sources = {}
        cols = supplier_cfg.get("columns", {})
        if pos:
            field_sources = {
                "sku": {"source_column": f"col #{supplier_cfg.get('positional_fallback', {}).get('sku', '?')}", "value": product["sku_supplier"]},
                "name": {"source_column": f"col #{supplier_cfg.get('positional_fallback', {}).get('name', '?')}", "value": product["name"]},
                "price": {"source_column": f"col #{supplier_cfg.get('positional_fallback', {}).get('price', '?')}", "value": str(product.get("price") or "")},
                "brand": {"source_column": f"col #{supplier_cfg.get('positional_fallback', {}).get('brand', '?')}", "value": product["brand"]},
            }
        else:
            for field in ("sku", "ean", "name", "price", "stock", "brand"):
                info = get_field_source(assoc, cols.get(field, []))
                field_sources[field] = {
                    "source_column": info["source_column"],
                    "matched_alias": info["matched_alias"],
                    "value": info["value"],
                }

        match_result = matcher.match_product(product)
        matched = match_result.get("matched_product") or {}
        rows_inspected.append({
            "row_num": idx,
            "raw_cells": assoc if not pos else {k: v for k, v in zip(header, row) if k},
            "raw_row": row,
            "field_sources": field_sources,
            "normalized": {
                "sku_supplier": product["sku_supplier"],
                "sku_mapped": product.get("sku_mapped"),
                "ean": product["ean"],
                "name": product["name"],
                "price": product.get("price"),
                "stock": product.get("stock"),
                "brand": product["brand"],
                "codes": product.get("codes", []),
                "code_variants": product.get("code_variants", product.get("codes", [])),
            },
            "match": {
                "status": match_result.get("status"),
                "method": match_result.get("match_method"),
                "confidence": match_result.get("confidence"),
                "db_table": match_db_label({
                    "method": match_result.get("match_method"),
                    "matched_product": match_result.get("matched_product"),
                }),
                "matched_product": {
                    "internal_sku": matched.get("internal_sku"),
                    "name": matched.get("name"),
                    "brand": matched.get("brand"),
                    "ean": matched.get("ean"),
                    "price": matched.get("price"),
                    "source": matched.get("source"),
                } if matched else None,
            },
        })

    normalization_issues = detect_normalization_issues(
        rows_inspected, column_mapping, supplier_key, supplier_cfg
    )

    return {
        "file": str(path),
        "filename": path.name,
        "supplier": supplier_key,
        "supplier_label": supplier_cfg.get("label", supplier_key),
        "forced_supplier": forced_supplier,
        "delimiter": delimiter,
        "has_header": has_header,
        "detection": detection,
        "column_mapping": column_mapping,
        "extra_codes": supplier_cfg.get("extra_codes", []),
        "match_code_only": bool(supplier_cfg.get("match_code_only", False)),
        "normalization_issues": normalization_issues,
        "normalization_rules": normalization_rules_summary(supplier_key),
        "data_sources": build_data_sources(matcher, settings),
        "raw_sample": raw_sample,
        "rows_inspected": rows_inspected,
        "settings": {
            "fuzzy_threshold": settings.get("fuzzy_threshold", 85),
            "fuzzy_probable_min": settings.get("fuzzy_probable_min", 70),
            "use_price_cache": settings.get("use_price_cache", True),
            "use_base_catalog": settings.get("use_base_catalog", False),
            "use_demo_catalog": use_demo_only,
        },
        "mapping_override": overrides.get(supplier_key),
        "data_sources_note": (
            "Matching folosește DOAR MySQL besoiu_tecdoc_base (~8.5 GB). "
            "Legăturile obligatorii: Cod OEM + Producător (mapat la brands)."
            if settings.get("use_tecdoc_only", True)
            else None
        ),
    }
