"""Normalizare coduri OEM — aceeași logică ca Procesare fisier import Base.html."""



from __future__ import annotations



import re

from typing import Any



# Aliniat cu normalizeHeaderKey / normalize_header din parser

def normalize_header(value: str) -> str:

    return re.sub(r"[^A-Z0-9]", "", (value or "").upper())





def normalize_code(value: str) -> str:

    """UPPER → elimină ghilimele → elimină [\\s\\-_/.] → elimină non-alfanumeric."""

    if not value:

        return ""

    s = str(value).strip().replace('"', "").replace("'", "")

    s = s.upper()

    s = re.sub(r"[\s\-\/_.]", "", s)

    s = re.sub(r"[^A-Z0-9]", "", s)

    return s





def normalize_brand(value: str) -> str:

    """normalizeBrand din Base.html (fără normalizeSpecialChars — doar cleanup de bază)."""

    if not value:

        return ""

    s = str(value).strip().replace('"', "").replace("'", "")

    s = s.upper()

    s = s.replace("-", " ")

    s = re.sub(r"\s*-\s*$", "", s)

    s = re.sub(r"\s+", " ", s)

    return s.strip()





def get_row_value(assoc: dict[str, str], aliases: list[str]) -> str:

    if not aliases:

        return ""

    norm_map = {normalize_header(k): str(v).strip() for k, v in assoc.items()}

    for alias in aliases:

        hit = norm_map.get(normalize_header(alias), "")

        if hit:

            return hit

    return ""





def _append_code_variants(variants: list[str], value: str) -> None:

    if not value:

        return

    raw = str(value).strip()

    if raw:

        variants.append(raw)

    norm = normalize_code(raw)

    if norm:

        variants.append(norm)

    trim_zeros = norm.lstrip("0")

    if trim_zeros and trim_zeros != norm and len(trim_zeros) >= 5:

        variants.append(trim_zeros)





def brand_trim_variants(code_norm: str, brand: str) -> list[str]:
    """Prefix/sufix brand (Autonet) + brand+code (getPriceForProduct fallback)."""

    if not code_norm:
        return []

    brand_norm = normalize_brand(brand)
    brand_key = re.sub(r"[^A-Z0-9]", "", brand_norm)
    if not brand_key or brand_key.isdigit():
        return []

    out: list[str] = []
    brand_compact = re.sub(r"\s+", "", brand_norm)

    if brand_compact:
        merged = normalize_code(brand_compact + code_norm)
        if merged:
            out.append(merged)

        short = brand_compact[:3]
        if short:
            if code_norm.startswith(short):
                out.append(code_norm[len(short) :])
            if code_norm.endswith(short):
                out.append(code_norm[: -len(short)])

    return out





def build_code_variants(

    supplier_key: str,

    sku: str,

    brand: str,

    assoc: dict[str, str],

    supplier_cfg: dict[str, Any] | None = None,

) -> list[str]:

    """

    Construiește toate variantele de cod pentru lookup TecDoc.

    Ordinea: SKU principal → variante brand → coduri suplimentare per furnizor.

    """

    cfg = supplier_cfg or {}

    variants: list[str] = []

    sk = (supplier_key or "generic").lower()



    _append_code_variants(variants, sku)



    primary_norm = normalize_code(sku)

    for v in brand_trim_variants(primary_norm, brand):

        variants.append(v)



    if sk == "autototal":

        _append_code_variants(variants, get_row_value(assoc, ["CODE_ECHIV", "CODE ECHIV"]))

    elif sk == "materom":

        _append_code_variants(variants, get_row_value(assoc, ["EAN"]))

    elif sk == "intercars":

        _append_code_variants(variants, get_row_value(assoc, ["P2 Code", "P2 CODE"]))

        _append_code_variants(variants, get_row_value(assoc, ["Active No.", "Active No", "ACTIVE NO"]))

    elif sk == "autopartner":

        _append_code_variants(variants, get_row_value(assoc, ["INDEX TECDOC"]))

        _append_code_variants(variants, get_row_value(assoc, ["INDEX AUTOPARTNER"]))

        _append_code_variants(variants, get_row_value(assoc, ["OEM DISPLAY"]))

    if cfg.get("brand_code_trim") and primary_norm:

        brand_key = re.sub(r"[^A-Z0-9]", "", normalize_brand(brand))

        short = brand_key[:3] if brand_key else ""

        if short and primary_norm.startswith(short):

            trimmed = primary_norm[len(short):]

            if trimmed:

                variants.append(trimmed)



    for extra in cfg.get("extra_codes", []):

        if "_sku" in assoc:

            val = assoc.get("_ean", "") if extra.upper() == "EAN" else ""

        else:

            val = get_row_value(assoc, [extra])

        _append_code_variants(variants, val)



    seen: set[str] = set()

    result: list[str] = []

    for v in variants:

        norm = normalize_code(v)

        if norm and norm not in seen:

            seen.add(norm)

            result.append(norm)

    return result


