"""Motor de matching — TecDoc MySQL (prioritar) sau fallback catalog/prețuri."""

from __future__ import annotations

import re
from difflib import SequenceMatcher
from typing import Any

from catalog import CatalogIndex, build_catalog, normalize_name
from paths import load_settings
from price_lookup import get_price_lookup
from tecdoc_lookup import get_tecdoc_lookup, get_tecdoc_lookup_for_product


STATUS_EXACT = "exact"
STATUS_PROBABLE = "probable"
STATUS_NO_MATCH = "no_match"
STATUS_CONFLICT = "conflict"


def similarity(a: str, b: str) -> float:
    if not a or not b:
        return 0.0
    return SequenceMatcher(None, normalize_name(a), normalize_name(b)).ratio() * 100


def price_diff_pct(new_price: float | None, ref_price: float | None) -> float | None:
    if new_price is None or ref_price is None or ref_price == 0:
        return None
    return abs(new_price - ref_price) / ref_price * 100


class ProductMatcher:
    def __init__(self, catalog: CatalogIndex | None = None) -> None:
        self.settings = load_settings()
        self.tecdoc_only = bool(self.settings.get("use_tecdoc_only", True))
        self.tecdoc = get_tecdoc_lookup() if self.tecdoc_only else None
        if catalog is not None:
            self.catalog = catalog
        elif self.tecdoc_only:
            self.catalog = CatalogIndex()
        else:
            self.catalog = build_catalog()
        self.price_lookup = get_price_lookup() if self.settings.get("use_price_cache", False) else None
        self.fuzzy_threshold = float(self.settings.get("fuzzy_threshold", 85))
        self.fuzzy_probable_min = float(self.settings.get("fuzzy_probable_min", 70))
        self.price_conflict_pct = float(self.settings.get("price_conflict_pct", 10))

    def match_product(self, product: dict[str, Any]) -> dict[str, Any]:
        result: dict[str, Any] = {
            "input": product,
            "status": STATUS_NO_MATCH,
            "match_method": None,
            "confidence": 0.0,
            "matched_product": None,
            "conflicts": [],
            "notes": [],
        }

        if self.tecdoc_only:
            tecdoc = get_tecdoc_lookup_for_product(product)
            if tecdoc and tecdoc.available:
                return self._match_tecdoc(product, result, tecdoc)

        return self._match_legacy(product, result)

    def _match_tecdoc(
        self,
        product: dict[str, Any],
        result: dict[str, Any],
        tecdoc=None,
    ) -> dict[str, Any]:
        engine = tecdoc or self.tecdoc
        if engine is None:
            result["status"] = STATUS_NO_MATCH
            result["notes"].append("TecDoc indisponibil")
            return result
        matched, method = engine.lookup_product(product)
        if matched is None:
            result["status"] = STATUS_NO_MATCH
            db_name = getattr(engine, "database", "besoiu_tecdoc_base")
            if not product.get("brand"):
                result["notes"].append("Lipsește producător — TecDoc caută brand + cod OEM")
            elif not product.get("codes"):
                result["notes"].append("Lipsește cod OEM")
            else:
                result["notes"].append(f"Cod OEM negăsit în {db_name}")
            return result

        result["matched_product"] = matched
        result["match_method"] = method
        result["confidence"] = 100.0 if method == "tecdoc_brand_code" else 95.0
        if method == "tecdoc_code":
            result["status"] = STATUS_PROBABLE
            result["notes"].append("Match doar după cod (fără brand confirmat)")
        else:
            result["status"] = STATUS_EXACT
        return result

    def _match_legacy(self, product: dict[str, Any], result: dict[str, Any]) -> dict[str, Any]:
        matched = None
        method = None
        confidence = 0.0

        ean = re.sub(r"\D", "", product.get("ean", "") or "")
        if ean and ean in self.catalog.by_ean:
            matched = self.catalog.by_ean[ean]
            method = "ean"
            confidence = 100.0

        if matched is None and product.get("sku_mapped"):
            mapped = product["sku_mapped"]
            if mapped in self.catalog.by_internal_sku:
                matched = self.catalog.by_internal_sku[mapped]
                method = "sku_map"
                confidence = 100.0

        if matched is None:
            for code in product.get("codes", []):
                if code in self.catalog.by_code:
                    matched = self.catalog.by_code[code]
                    method = "sku_code"
                    confidence = 100.0
                    break

        if matched is None and self.price_lookup and self.price_lookup.available:
            matched = self.price_lookup.lookup_codes(product.get("codes", []))
            if matched:
                method = "price_sqlite"
                confidence = 100.0

        if matched is None and not product.get("match_code_only") and product.get("name") and self.catalog.names:
            best_score = 0.0
            best_item = None
            for name, item in self.catalog.names:
                score = similarity(product["name"], name)
                if score > best_score:
                    best_score = score
                    best_item = item
            if best_score >= self.fuzzy_threshold:
                matched = best_item
                method = "fuzzy_name"
                confidence = best_score
            elif best_score >= self.fuzzy_probable_min:
                matched = best_item
                method = "fuzzy_name"
                confidence = best_score
                result["status"] = STATUS_PROBABLE
                result["notes"].append(f"Similaritate nume {best_score:.1f}% (sub prag exact)")

        if matched is None:
            result["status"] = STATUS_NO_MATCH
            result["notes"].append("Niciun produs identificat în catalog")
            return result

        result["matched_product"] = {
            "internal_sku": matched.get("internal_sku"),
            "name": matched.get("name"),
            "brand": matched.get("brand"),
            "codes": matched.get("codes", []),
            "ean": matched.get("ean", ""),
            "price": matched.get("price") or matched.get("cached_price"),
            "stock": matched.get("stock"),
            "source": matched.get("source", "catalog"),
            "cached_supplier": matched.get("cached_supplier"),
        }
        result["match_method"] = method
        result["confidence"] = confidence

        conflicts = self._detect_conflicts(product, matched)
        result["conflicts"] = conflicts

        if conflicts:
            result["status"] = STATUS_CONFLICT
        elif result["status"] != STATUS_PROBABLE:
            if method in ("ean", "sku_map", "sku_code", "price_sqlite") and confidence >= 99:
                result["status"] = STATUS_EXACT
            elif method == "fuzzy_name":
                result["status"] = STATUS_PROBABLE
            else:
                result["status"] = STATUS_EXACT

        return result

    def _detect_conflicts(self, product: dict, matched: dict) -> list[dict]:
        conflicts: list[dict] = []
        ref_price = matched.get("price")
        if ref_price is None:
            ref_price = matched.get("cached_price")
        new_price = product.get("price")
        diff = price_diff_pct(new_price, ref_price)
        if diff is not None and diff > self.price_conflict_pct:
            conflicts.append({
                "type": "price",
                "supplier_price": new_price,
                "catalog_price": ref_price,
                "diff_pct": round(diff, 2),
            })

        if self.settings.get("stock_conflict", True):
            ref_stock = matched.get("stock")
            new_stock = product.get("stock")
            if ref_stock is not None and new_stock is not None and ref_stock != new_stock:
                conflicts.append({
                    "type": "stock",
                    "supplier_stock": new_stock,
                    "catalog_stock": ref_stock,
                })

        return conflicts

    def match_batch(self, products: list[dict]) -> list[dict]:
        return [self.match_product(p) for p in products]

    def summarize(self, results: list[dict]) -> dict[str, int]:
        summary = {STATUS_EXACT: 0, STATUS_PROBABLE: 0, STATUS_NO_MATCH: 0, STATUS_CONFLICT: 0}
        for r in results:
            status = r.get("status", STATUS_NO_MATCH)
            summary[status] = summary.get(status, 0) + 1
        summary["total"] = len(results)
        return summary
