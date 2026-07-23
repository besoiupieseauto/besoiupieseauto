"""Detectare erori de normalizare OEM (înainte de Ollama)."""

from __future__ import annotations

import re
from typing import Any


def _brand_compact(brand: str) -> str:
    return re.sub(r"\s+", "", (brand or "").upper())


def detect_normalization_issues(
    rows_inspected: list[dict[str, Any]],
    column_mapping: dict[str, Any],
    supplier_key: str,
    supplier_cfg: dict[str, Any],
) -> list[dict[str, Any]]:
    issues: list[dict[str, Any]] = []
    sk = (supplier_key or "generic").lower()

    sku_map = column_mapping.get("sku") or {}
    if sku_map.get("source_column") in ("(lipsă)", "", None):
        issues.append({
            "row_num": 0,
            "severity": "error",
            "type": "mapping_missing_sku",
            "message_ro": "Cod OEM (sku) nemapat — matching TecDoc imposibil.",
            "suggestion_ro": "Activează legătura Cod OEM și alege coloana corectă (ex: COD ARTICOL).",
        })

    brand_map = column_mapping.get("brand") or {}
    brand_missing = brand_map.get("source_column") in ("(lipsă)", "", None)
    if brand_missing:
        issues.append({
            "row_num": 0,
            "severity": "warn",
            "type": "mapping_missing_brand",
            "message_ro": "Producător nemapat — se folosește doar fallback code-only în TecDoc.",
            "suggestion_ro": "Mapează Producător (PRODUCATOR / SUP_BRAND) pentru match brand+cod.",
        })

    matched = 0
    no_match = 0
    for row in rows_inspected:
        n = row.get("normalized") or {}
        match = row.get("match") or {}
        status = match.get("status") or "no_match"
        sku = str(n.get("sku_supplier") or "").strip()
        brand = str(n.get("brand") or "").strip()
        codes = n.get("code_variants") or n.get("codes") or []
        row_num = int(row.get("row_num") or 0)

        if status in ("exact", "probable") or match.get("matched_product"):
            matched += 1
        elif sku:
            no_match += 1

        if status == "no_match" and sku:
            issues.append({
                "row_num": row_num,
                "severity": "warn",
                "type": "no_tecdoc_match",
                "message_ro": f"Rând {row_num}: «{sku}» ({brand or 'fără brand'}) — niciun match TecDoc.",
                "suggestion_ro": "Verifică normalizarea (separatori, prefix brand) sau dacă codul există în TecDoc.",
                "detail": {"codes_tried": codes[:8], "brand": brand},
            })

        if match.get("method") == "tecdoc_code" and match.get("matched_product"):
            mp = match["matched_product"] or {}
            tec_brand = str(mp.get("brand") or "").upper()
            sup_brand = brand.upper()
            if sup_brand and tec_brand and sup_brand not in tec_brand and tec_brand not in sup_brand:
                issues.append({
                    "row_num": row_num,
                    "severity": "info",
                    "type": "code_only_match",
                    "message_ro": f"Rând {row_num}: match fără brand — furnizor «{brand}», TecDoc «{mp.get('brand')}».",
                    "suggestion_ro": "Posibil alias brand lipsă în tecdoc_brand_aliases.json.",
                    "detail": {"codes_tried": codes[:6]},
                })

        if sku and re.search(r"[\s\-\/_.]", sku) and status == "no_match":
            norm_primary = codes[0] if codes else ""
            issues.append({
                "row_num": row_num,
                "severity": "info",
                "type": "special_chars_normalized",
                "message_ro": f"Rând {row_num}: «{sku}» normalizat la «{norm_primary}» — tot fără match.",
                "suggestion_ro": "Codul poate avea prefix/sufix brand sau variantă echivalentă (CODE_ECHIV).",
            })

        if sk == "autonet" and brand and sku and len(codes) <= 2:
            short = _brand_compact(brand)[:3]
            norm = codes[0] if codes else ""
            if short and norm and not norm.startswith(short) and not norm.endswith(short):
                issues.append({
                    "row_num": row_num,
                    "severity": "info",
                    "type": "autonet_no_brand_trim",
                    "message_ro": f"Rând {row_num}: Autonet — codul nu conține prefix/sufix «{short}».",
                    "suggestion_ro": "Normal pentru coduri fără brand în SKU; verifică TecDoc direct pe cod.",
                })

        if sk == "autototal" and status == "no_match" and sku:
            if len(codes) <= 1:
                issues.append({
                    "row_num": row_num,
                    "severity": "info",
                    "type": "autototal_no_equiv",
                    "message_ro": f"Rând {row_num}: Autototal — doar cod principal, fără CODE_ECHIV.",
                    "suggestion_ro": "Adaugă CODE_ECHIV la coduri extra sau verifică maparea EAN.",
                })

    total = len(rows_inspected)
    if total and no_match > matched:
        issues.insert(0, {
            "row_num": 0,
            "severity": "warn",
            "type": "low_match_rate",
            "message_ro": f"Rată match scăzută: {matched}/{total} găsite, {no_match} fără match în eșantion.",
            "suggestion_ro": "Rulează Ollama pentru analiză mapping + normalizare sau verifică furnizorul detectat.",
            "detail": {"matched": matched, "no_match": no_match, "total": total},
        })

    return issues


def normalization_rules_summary(supplier_key: str) -> str:
    sk = (supplier_key or "generic").upper()
    rules = (
        "normalizeCode: UPPER, elimină spații - / _ ., apoi non-alfanumeric.\n"
        "Autonet: cod + variante fără primele/ultimele 3 litere brand + brand+cod.\n"
        "Autototal: ART_ARTICLE_NR + CODE_ECHIV.\n"
        "Materom: COD NPF + EAN.\n"
        "Intercars: P2 Code + Active No.\n"
        "Autopartner: INDEX TECDOC + INDEX AUTOPARTNER.\n"
        "Lookup TecDoc: brand mapat → product_codes.code_norm, fallback code-only.\n"
    )
    return f"Furnizor: {sk}\n{rules}"
