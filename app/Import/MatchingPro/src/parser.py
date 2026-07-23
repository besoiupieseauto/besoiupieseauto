"""Citire și normalizare CSV per furnizor."""

from __future__ import annotations

import csv
import re
from pathlib import Path
from typing import Any

from code_normalizer import build_code_variants
from paths import load_mapping_overrides, load_settings, load_suppliers, merge_supplier_config


def normalize_header(value: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (value or "").upper())


def parse_price(value: Any) -> float | None:
    if value is None or value == "":
        return None
    try:
        return float(str(value).replace(" ", "").replace(",", "."))
    except ValueError:
        return None


def parse_stock(value: Any) -> int | None:
    if value is None or value == "":
        return None
    try:
        return int(float(str(value).replace(",", ".")))
    except ValueError:
        return None


def detect_delimiter(sample: str, delimiters: list[str]) -> str:
    counts = {d: sample.count(d) for d in delimiters}
    best = max(counts, key=counts.get)
    return best if counts[best] > 0 else ";"


def read_raw_rows(
    path: Path,
    max_data_rows: int | None = None,
    skip_data_rows: int = 0,
) -> tuple[list[str], list[list[str]], str, bool, bool]:
    settings = load_settings()
    # Citește doar un sample pentru detectare delimiter
    with path.open(encoding="utf-8-sig", errors="replace", newline="") as fh:
        sample_text = fh.read(8192)
    delimiter = detect_delimiter(sample_text, settings.get("delimiters", [";", ","]))

    header: list[str] = []
    data_rows: list[list[str]] = []
    has_header = False
    skipped = 0
    has_more = False

    with path.open(encoding="utf-8-sig", errors="replace", newline="") as fh:
        reader = csv.reader(fh, delimiter=delimiter)
        for row in reader:
            if not any(cell.strip() for cell in row):
                continue
            if not header:
                if _looks_like_header(row):
                    header = row
                    has_header = True
                else:
                    header = [f"col_{i}" for i in range(len(row))]
                    has_header = False
                    if skipped < skip_data_rows:
                        skipped += 1
                        continue
                    data_rows.append(row)
                    if max_data_rows is not None and len(data_rows) >= max_data_rows:
                        break
                continue
            if skipped < skip_data_rows:
                skipped += 1
                continue
            data_rows.append(row)
            if max_data_rows is not None and len(data_rows) >= max_data_rows:
                break

        if max_data_rows is not None and len(data_rows) >= max_data_rows:
            for extra in reader:
                if any(cell.strip() for cell in extra):
                    has_more = True
                    break

    if not header and not data_rows:
        return [], [], delimiter, False, False
    if not header:
        return [], [], delimiter, False, False
    return header, data_rows, delimiter, has_header, has_more


def _looks_like_header(row: list[str]) -> bool:
    keys = {normalize_header(c) for c in row}
    known = {
        "SKU", "EAN", "NUMEPRODUS", "PRET", "STOC", "ARTARTICLENR",
        "CODARTICOL", "CODNPF", "SUPPLIERCATALOGNR", "ACTIVENO",
        "INDEXTECDOC", "SKUFURNIZOR", "PRODUCATOR", "PRETUNITAR",
        "PRETUNITARTAXA", "DENUMIREARTICOL", "SUPBRAND", "BRAND",
        "PURCHASEPRICE", "INDEXAUTOPARTNER", "MANUFACTURERNAME",
        # Autonet QWP cross-ref: ArtNr;ReferenceBrand;RefNr
        "ARTNR", "REFERENCEBRAND", "REFNR",
    }
    hits = len(keys & known)
    if hits >= 2:
        return True
    # Autonet QWP: 3 coloane cross-ref (ArtNr + brand + RefNr)
    if hits >= 1 and {"ARTNR", "REFNR"} <= keys:
        return True
    # Autonet și alții: un singur câmp cunoscut + ≥4 coloane
    return hits >= 1 and len(row) >= 4


def detect_supplier(header: list[str], filename: str) -> tuple[str, dict]:
    suppliers = load_suppliers()
    header_set = {normalize_header(h) for h in header}
    name_lower = filename.lower()

    for key, cfg in suppliers.items():
        det = cfg.get("detection", {})
        fn_tokens = det.get("filename_contains", [])
        if fn_tokens and any(t.lower() in name_lower for t in fn_tokens):
            return key, cfg

        req_all = [normalize_header(h) for h in det.get("headers_all", [])]
        if req_all and all(h in header_set for h in req_all):
            return key, cfg

        req_any = [normalize_header(h) for h in det.get("headers_any", [])]
        if req_any and any(h in header_set for h in req_any):
            return key, cfg

    return "generic", suppliers.get("generic", {})


def resolve_supplier_profile(
    header: list[str],
    filename: str,
    forced_supplier: str | None = None,
) -> tuple[str, dict]:
    """Alege profilul CSV corect când PHP forțează slug-ul folderului (ex. autonet).

    Feed-urile din același folder pot avea formate diferite (lista preț vs QWP).
    Dacă detectarea găsește un profil specializat cu feed_supplier == forced,
    folosim profilul specializat (ex. autonet_qwp), nu maparea generică a folderului.
    """
    suppliers = load_suppliers()
    detected_key, detected_cfg = detect_supplier(header, filename)

    forced_key = (forced_supplier or "").strip()
    if not forced_key:
        return detected_key, detected_cfg

    feed_of_detected = str(detected_cfg.get("feed_supplier") or detected_key).strip()
    if detected_key != forced_key and feed_of_detected == forced_key:
        return detected_key, detected_cfg

    if forced_key in suppliers:
        return forced_key, suppliers[forced_key]

    return detected_key, detected_cfg


def row_to_assoc(header: list[str], row: list[str]) -> dict[str, str]:
    assoc: dict[str, str] = {}
    for idx, col in enumerate(header):
        assoc[col] = row[idx].strip() if idx < len(row) else ""
    return assoc


def get_field(assoc: dict[str, str], aliases: list[str]) -> str:
    return get_field_source(assoc, aliases)["value"]


def get_field_source(assoc: dict[str, str], aliases: list[str]) -> dict[str, str]:
    if not aliases:
        return {"value": "", "source_column": "", "matched_alias": ""}
    norm_map = {normalize_header(k): (k, v) for k, v in assoc.items()}
    for alias in aliases:
        hit = norm_map.get(normalize_header(alias))
        if hit and str(hit[1]).strip():
            return {
                "value": str(hit[1]).strip(),
                "source_column": hit[0],
                "matched_alias": alias,
            }
    return {"value": "", "source_column": "", "matched_alias": ""}


def positional_assoc(row: list[str], cfg: dict) -> dict[str, str] | None:
    pos = cfg.get("positional_fallback")
    if not pos or len(row) < pos.get("min_columns", 99):
        return None
    out = {
        "_sku": row[pos["sku"]].strip(),
        "_name": row[pos.get("name", 1)].strip(),
        "_price": row[pos.get("price", 5)].strip(),
        "_brand": row[pos.get("brand", 6)].strip(),
        "_ean": "",
        "_stock": "",
    }
    for alias, idx in (pos.get("extra_columns") or {}).items():
        if isinstance(idx, int) and 0 <= idx < len(row):
            out[str(alias)] = row[idx].strip()
    return out


def normalize_product_row(
    assoc: dict[str, str],
    supplier_key: str,
    supplier_cfg: dict,
    row_num: int,
    source_file: str,
) -> dict[str, Any]:
    cols = supplier_cfg.get("columns", {})

    if "_sku" in assoc:
        sku = assoc["_sku"]
        ean = assoc.get("_ean", "")
        name = assoc.get("_name", "")
        price = parse_price(assoc.get("_price"))
        stock = parse_stock(assoc.get("_stock"))
        brand = assoc.get("_brand", "")
        art_nr = assoc.get("_art_nr", "") or assoc.get("ArtNr", "")
    else:
        sku = get_field(assoc, cols.get("sku", []))
        ean = get_field(assoc, cols.get("ean", []))
        name = get_field(assoc, cols.get("name", []))
        price = parse_price(get_field(assoc, cols.get("price", [])))
        stock = parse_stock(get_field(assoc, cols.get("stock", [])))
        brand = get_field(assoc, cols.get("brand", []))
        art_nr = get_field(assoc, cols.get("art_nr", []))

    # Match TecDoc pe RefNr + ReferenceBrand; produsul final poate fi QWP/ArtNr.
    match_sku = sku
    match_brand = brand
    force_brand = str(supplier_cfg.get("force_brand") or "").strip()
    feed_supplier = str(supplier_cfg.get("feed_supplier") or supplier_key).strip() or supplier_key

    codes = build_code_variants(supplier_key, match_sku, match_brand, assoc, supplier_cfg)

    sku_map = supplier_cfg.get("sku_map", {})
    mapped_sku = sku_map.get(match_sku, "")

    output_sku = match_sku
    if supplier_cfg.get("output_sku_from_art_nr") and art_nr:
        output_sku = art_nr

    return {
        "row": row_num,
        "source_file": source_file,
        "supplier": feed_supplier,
        "supplier_profile": supplier_key,
        "supplier_label": supplier_cfg.get("label", supplier_key),
        "sku_supplier": output_sku,
        "sku_mapped": mapped_sku,
        "ref_nr": match_sku,
        "art_nr": art_nr,
        "force_brand": force_brand,
        "ean": re.sub(r"\D", "", ean) if ean else "",
        "name": name,
        "price": price,
        "stock": stock,
        # brand folosit la lookup TecDoc (= ReferenceBrand pentru QWP)
        "brand": match_brand,
        "codes": codes,
        "code_variants": codes,
        "match_code_only": bool(supplier_cfg.get("match_code_only", False)),
    }


def parse_csv_file(
    path: Path,
    forced_supplier: str | None = None,
    max_rows: int | None = None,
    skip_rows: int = 0,
) -> dict[str, Any]:
    header, data_rows, delimiter, _, has_more = read_raw_rows(
        path,
        max_data_rows=max_rows,
        skip_data_rows=max(0, skip_rows),
    )
    if not data_rows and not header:
        raise ValueError(f"Fișier gol sau invalid: {path.name}")

    overrides = load_mapping_overrides()
    supplier_key, base_cfg = resolve_supplier_profile(header, path.name, forced_supplier)
    supplier_cfg = merge_supplier_config(supplier_key, base_cfg, overrides.get(supplier_key))

    products: list[dict[str, Any]] = []
    row_base = skip_rows + 2
    feed_supplier = str(supplier_cfg.get("feed_supplier") or supplier_key).strip() or supplier_key
    for offset, row in enumerate(data_rows):
        idx = row_base + offset
        if max_rows is not None and len(products) >= max_rows:
            break
        pos_assoc = positional_assoc(row, supplier_cfg)
        assoc = pos_assoc if pos_assoc else row_to_assoc(header, row)
        product = normalize_product_row(assoc, supplier_key, supplier_cfg, idx, path.name)
        # QWP cross-ref: identitate = ArtNr; match = RefNr (poate lipsi name/ean/preț)
        has_identity = bool(product["sku_supplier"] or product.get("art_nr") or product.get("ref_nr"))
        if not has_identity and not product["ean"] and not product["name"]:
            continue
        products.append(product)

    # QWP: prețul e în Lista pret Autonet (COD ARTICOL = ArtNr), nu în fișierul cross-ref
    if products and (
        supplier_key == "autonet_qwp"
        or bool(supplier_cfg.get("price_from_feed_list"))
    ):
        from autonet_list_prices import apply_autonet_list_prices

        apply_autonet_list_prices(products)

    return {
        "file": str(path),
        "filename": path.name,
        "supplier": feed_supplier,
        "supplier_profile": supplier_key,
        "supplier_label": supplier_cfg.get("label", supplier_key),
        "delimiter": delimiter,
        "rows_total": len(data_rows),
        "rows_parsed": len(products),
        "rows_truncated": has_more or (max_rows is not None and len(data_rows) >= max_rows),
        "has_more": has_more,
        "skip_rows": skip_rows,
        "max_rows": max_rows,
        "products": products,
    }
