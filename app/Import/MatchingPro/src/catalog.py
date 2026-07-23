"""Index catalog intern — demo + Base (opțional), fără price-index în memorie."""

from __future__ import annotations

import csv
import json
import re
import time
from pathlib import Path

from paths import CONFIG_DIR, MATC_DIR, STATE_DIR, load_settings

_CATALOG_CACHE = STATE_DIR / "catalog_cache.json"
_catalog_memory: CatalogIndex | None = None


def normalize_code(value: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (value or "").upper())


def normalize_name(value: str) -> str:
    return re.sub(r"\s+", " ", (value or "").lower().strip())


class CatalogIndex:
    def __init__(self) -> None:
        self.by_ean: dict[str, dict] = {}
        self.by_code: dict[str, dict] = {}
        self.by_internal_sku: dict[str, dict] = {}
        self.names: list[tuple[str, dict]] = []
        self.stats: dict[str, int] = {}

    def to_dict(self) -> dict:
        return {
            "by_ean": self.by_ean,
            "by_code": self.by_code,
            "by_internal_sku": self.by_internal_sku,
            "names": self.names,
            "stats": self.stats,
        }

    @classmethod
    def from_dict(cls, data: dict) -> "CatalogIndex":
        cat = cls()
        cat.by_ean = data.get("by_ean", {})
        cat.by_code = data.get("by_code", {})
        cat.by_internal_sku = data.get("by_internal_sku", {})
        cat.names = [tuple(x) for x in data.get("names", [])]
        cat.stats = data.get("stats", {})
        return cat

    def add_product(self, product: dict, *, fuzzy: bool = True) -> None:
        internal = product.get("internal_sku") or product.get("id") or ""
        if internal:
            self.by_internal_sku[internal] = product

        for code in product.get("codes", []):
            norm = normalize_code(code)
            if norm:
                self.by_code[norm] = product

        ean = re.sub(r"\D", "", product.get("ean", "") or "")
        if ean:
            self.by_ean[ean] = product

        if fuzzy:
            name = normalize_name(product.get("name", ""))
            if name and not name.startswith("cod "):
                self.names.append((name, product))

    def load_demo_catalog(self) -> None:
        settings = load_settings()
        demo_file = CONFIG_DIR / settings.get("demo_catalog_file", "demo_catalog.json")
        if not demo_file.is_file():
            return
        items = json.loads(demo_file.read_text(encoding="utf-8"))
        for item in items:
            self.add_product(item, fuzzy=True)
        self.stats["demo"] = len(items)

    def load_base_csv(self, max_files: int | None = None) -> None:
        if not MATC_DIR.is_dir():
            return
        files = sorted(MATC_DIR.glob("UTF8-*-ro.csv"))
        if max_files:
            files = files[:max_files]

        seen = 0
        for path in files:
            brand = _extract_brand(path.name)
            with path.open(encoding="utf-8-sig", errors="replace", newline="") as fh:
                reader = csv.DictReader(fh, delimiter=";")
                for row in reader:
                    code1 = normalize_code(row.get("ART_CODE_1", ""))
                    code2 = normalize_code(row.get("ART_CODE_2", ""))
                    if not code1 or code1 in self.by_code:
                        continue
                    self.add_product({
                        "internal_sku": f"{brand}-{code1}",
                        "codes": [c for c in [code1, code2] if c],
                        "ean": "",
                        "name": (row.get("ART_NAME") or "").strip(),
                        "brand": (row.get("ART_BRAND") or brand).strip(),
                        "price": None,
                        "stock": None,
                        "source": "base_csv",
                    }, fuzzy=True)
                    seen += 1
        self.stats["base_csv"] = seen

    def build(self, use_base: bool = False, use_demo: bool = True) -> "CatalogIndex":
        if use_demo:
            self.load_demo_catalog()
        if use_base:
            self.load_base_csv()
        return self


def _extract_brand(filename: str) -> str:
    m = re.search(r"-([A-Z0-9_]+)-ro\.csv$", filename, re.I)
    return m.group(1).upper() if m else "UNKNOWN"


def _cache_sources_mtime() -> float:
    mtimes = [0.0]
    if MATC_DIR.is_dir():
        for p in MATC_DIR.glob("UTF8-*-ro.csv"):
            mtimes.append(p.stat().st_mtime)
    return max(mtimes)


def _load_disk_cache() -> CatalogIndex | None:
    if not _CATALOG_CACHE.is_file():
        return None
    try:
        data = json.loads(_CATALOG_CACHE.read_text(encoding="utf-8"))
        if data.get("sources_mtime", 0) < _cache_sources_mtime():
            return None
        return CatalogIndex.from_dict(data["catalog"])
    except (json.JSONDecodeError, KeyError, OSError):
        return None


def _save_disk_cache(catalog: CatalogIndex) -> None:
    STATE_DIR.mkdir(parents=True, exist_ok=True)
    payload = {
        "built_at": time.time(),
        "sources_mtime": _cache_sources_mtime(),
        "catalog": catalog.to_dict(),
    }
    _CATALOG_CACHE.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")


def build_catalog(force_demo: bool = False) -> CatalogIndex:
    global _catalog_memory

    if force_demo:
        catalog = CatalogIndex()
        catalog.load_demo_catalog()
        return catalog

    if _catalog_memory is not None:
        return _catalog_memory

    cached = _load_disk_cache()
    if cached is not None:
        _catalog_memory = cached
        return cached

    settings = load_settings()
    catalog = CatalogIndex()
    catalog.build(
        use_base=settings.get("use_base_catalog", False),
        use_demo=True,
    )
    if settings.get("use_base_catalog", False):
        _save_disk_cache(catalog)
    _catalog_memory = catalog
    return catalog
