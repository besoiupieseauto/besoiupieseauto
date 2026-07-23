"""Prețuri din Lista pret Autonet — lookup ArtNr → PRET UNITAR pentru QWP cross-ref."""

from __future__ import annotations

import csv
import json
import pickle
import re
import time
from pathlib import Path

from paths import STATE_DIR, SUPPLIERS_DIR

CACHE_PATH = STATE_DIR / "autonet-qwp-prices.pkl"
JSON_CACHE_PATH = STATE_DIR / "autonet-qwp-prices.json"
_PRICE_BRANDS = frozenset({"QWP"})


def normalize_code(value: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (value or "").upper())


def find_autonet_price_list() -> Path | None:
    feed = SUPPLIERS_DIR / "autonet"
    if not feed.is_dir():
        return None

    candidates: list[Path] = []
    for path in feed.iterdir():
        if not path.is_file() or path.suffix.lower() != ".csv":
            continue
        name = path.name.lower()
        if "qwp" in name:
            continue
        if "lista" in name and "pret" in name:
            candidates.append(path)

    if not candidates:
        for path in feed.iterdir():
            if path.is_file() and path.suffix.lower() == ".csv" and "qwp" not in path.name.lower():
                candidates.append(path)

    if not candidates:
        return None
    return max(candidates, key=lambda p: p.stat().st_mtime)


def _parse_price(value: str) -> float | None:
    raw = (value or "").strip().replace(" ", "").replace(",", ".")
    if not raw:
        return None
    try:
        price = float(raw)
    except ValueError:
        return None
    return price if price > 0 else None


def _source_fingerprint(source: Path) -> str:
    st = source.stat()
    return f"{source.resolve()}|{int(st.st_mtime)}|{st.st_size}|qwp"


def _load_cache() -> dict | None:
    if not CACHE_PATH.is_file():
        return None
    try:
        with CACHE_PATH.open("rb") as fh:
            data = pickle.load(fh)
    except Exception:
        return None
    if not isinstance(data, dict):
        return None
    return data


def _build_maps(source: Path) -> tuple[dict[str, float], dict[str, float]]:
    by_code: dict[str, float] = {}
    by_norm: dict[str, float] = {}
    with source.open(encoding="utf-8-sig", newline="") as fh:
        reader = csv.reader(fh, delimiter=";")
        header = next(reader, None) or []
        header_norm = [re.sub(r"[^A-Z0-9]", "", h.upper()) for h in header]
        try:
            idx_code = header_norm.index("CODARTICOL")
            idx_price = header_norm.index("PRETUNITAR")
        except ValueError as exc:
            raise ValueError(
                f"Lista pret Autonet fără coloane COD ARTICOL / PRET UNITAR: {source.name}"
            ) from exc
        idx_brand = header_norm.index("PRODUCATOR") if "PRODUCATOR" in header_norm else -1

        for row in reader:
            if len(row) <= max(idx_code, idx_price):
                continue
            brand = (
                (row[idx_brand] or "").strip().upper()
                if idx_brand >= 0 and idx_brand < len(row)
                else ""
            )
            if brand not in _PRICE_BRANDS:
                continue
            code = (row[idx_code] or "").strip()
            if not code:
                continue
            price = _parse_price(row[idx_price] if idx_price < len(row) else "")
            if price is None:
                continue
            by_code[code] = price
            by_norm[normalize_code(code)] = price
    return by_code, by_norm


class AutonetListPriceLookup:
    def __init__(self) -> None:
        self.source: Path | None = None
        self.available = False
        self._by_code: dict[str, float] = {}
        self._by_norm: dict[str, float] = {}
        self._open()

    def _open(self) -> None:
        source = find_autonet_price_list()
        if source is None:
            return
        self.source = source
        fingerprint = _source_fingerprint(source)

        cached = _load_cache()
        if cached and cached.get("fingerprint") == fingerprint:
            self._by_code = cached.get("by_code") or {}
            self._by_norm = cached.get("by_norm") or {}
            self.available = bool(self._by_code)
            return

        started = time.time()
        self._by_code, self._by_norm = _build_maps(source)
        STATE_DIR.mkdir(parents=True, exist_ok=True)
        payload = {
            "fingerprint": fingerprint,
            "built_at": time.time(),
            "build_seconds": round(time.time() - started, 2),
            "count": len(self._by_code),
            "by_code": self._by_code,
            "by_norm": self._by_norm,
        }
        tmp = CACHE_PATH.with_suffix(".tmp")
        with tmp.open("wb") as fh:
            pickle.dump(payload, fh, protocol=pickle.HIGHEST_PROTOCOL)
        tmp.replace(CACHE_PATH)

        # Sidecar JSON pentru fallback PHP (ImportFastCardPipeline)
        json_payload = {
            "fingerprint": fingerprint,
            "count": len(self._by_code),
            "by_code": self._by_code,
            "by_norm": self._by_norm,
        }
        jtmp = JSON_CACHE_PATH.with_suffix(".tmp")
        with jtmp.open("w", encoding="utf-8") as fh:
            json.dump(json_payload, fh, ensure_ascii=False, separators=(",", ":"))
        jtmp.replace(JSON_CACHE_PATH)
        self.available = bool(self._by_code)

    def lookup(self, art_nr: str) -> float | None:
        if not self.available:
            return None
        code = (art_nr or "").strip()
        if not code:
            return None
        hit = self._by_code.get(code)
        if hit is not None:
            return hit
        return self._by_norm.get(normalize_code(code))


_LOOKUP: AutonetListPriceLookup | None = None


def get_autonet_list_price_lookup() -> AutonetListPriceLookup:
    global _LOOKUP
    if _LOOKUP is None:
        _LOOKUP = AutonetListPriceLookup()
    return _LOOKUP


def lookup_autonet_list_price(art_nr: str) -> float | None:
    return get_autonet_list_price_lookup().lookup(art_nr)


def apply_autonet_list_prices(products: list[dict]) -> int:
    """Umple price / price_csv pe produse QWP fără preț. Returnează câte au fost actualizate."""
    if not products:
        return 0
    lookup = get_autonet_list_price_lookup()
    if not lookup.available:
        return 0
    filled = 0
    for product in products:
        price = product.get("price")
        if price is not None and isinstance(price, (int, float)) and float(price) > 0:
            continue
        art = str(product.get("art_nr") or product.get("sku_supplier") or "").strip()
        hit = lookup.lookup(art)
        if hit is None:
            continue
        product["price"] = hit
        product["price_csv"] = hit
        product["price_source"] = "autonet_list"
        filled += 1
    return filled
