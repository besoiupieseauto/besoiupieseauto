"""Lookup prețuri din SQLite — fără încărcare în memorie."""

from __future__ import annotations

import re
import sqlite3
from functools import lru_cache

from paths import PRICE_CACHE


def normalize_code(value: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (value or "").upper())


class PriceLookup:
    def __init__(self) -> None:
        self._conn: sqlite3.Connection | None = None
        self.available = PRICE_CACHE.is_file()

    def _connection(self) -> sqlite3.Connection | None:
        if not self.available:
            return None
        if self._conn is None:
            self._conn = sqlite3.connect(f"file:{PRICE_CACHE}?mode=ro", uri=True)
            self._conn.execute("PRAGMA query_only=ON")
        return self._conn

    def lookup_code(self, code: str) -> dict | None:
        conn = self._connection()
        if conn is None:
            return None
        norm = normalize_code(code)
        if not norm:
            return None
        row = conn.execute(
            "SELECT code, price, supplier FROM prices WHERE code = ? LIMIT 1",
            (norm,),
        ).fetchone()
        if not row:
            return None
        return {
            "internal_sku": f"PRICE-{row[0]}",
            "codes": [row[0]],
            "ean": "",
            "name": f"Cod {row[0]}",
            "brand": "",
            "price": row[1],
            "stock": None,
            "source": "price_cache",
            "cached_supplier": row[2],
        }

    def lookup_codes(self, codes: list[str]) -> dict | None:
        for code in codes:
            hit = self.lookup_code(code)
            if hit:
                return hit
        return None

    def close(self) -> None:
        if self._conn is not None:
            self._conn.close()
            self._conn = None


@lru_cache(maxsize=1)
def get_price_lookup() -> PriceLookup:
    return PriceLookup()
