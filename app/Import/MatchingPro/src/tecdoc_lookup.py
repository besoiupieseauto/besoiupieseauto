"""Lookup produse în MySQL besoiu_tecdoc_base (TecDoc indexat)."""

from __future__ import annotations

import json
import re
from typing import Any

from code_normalizer import normalize_code
from paths import CONFIG_DIR, load_settings, load_suppliers

try:
    import pymysql
    from pymysql.cursors import DictCursor
except ImportError:
    pymysql = None  # type: ignore
    DictCursor = None  # type: ignore

# Coduri prea scurte (ex. "627") declanșează scanări lente pe product_codes fără index util.
MIN_CODE_NORM_LEN = 5
MIN_EAN_DIGITS = 8
MYSQL_READ_TIMEOUT_SEC = 12


def normalize_brand_key(value: str) -> str:
    value = (value or "").upper().strip()
    for a, b in [
        ("Ă", "A"), ("Â", "A"), ("Î", "I"), ("Ș", "S"), ("Ț", "T"),
        ("Ö", "O"), ("Ü", "U"), ("Ä", "A"), ("Ë", "E"), ("É", "E"),
    ]:
        value = value.replace(a, b)
    return re.sub(r"[^A-Z0-9]", "", value)


class TecDocLookup:
    def __init__(self, database: str | None = None) -> None:
        self.settings = load_settings()
        self.database = (
            (database or "").strip()
            or str(self.settings.get("tecdoc_mysql_db") or "besoiu_tecdoc_base")
        )
        self._conn = None
        self._brands_by_norm: dict[str, str] = {}
        self._aliases: dict[str, str] = {}
        self._skip_brands: set[str] = set()
        self._load_aliases()
        self.available = False
        if pymysql is None:
            return
        try:
            self._conn = pymysql.connect(
                host=self.settings.get("tecdoc_mysql_host", "127.0.0.1"),
                user=self.settings.get("tecdoc_mysql_user", "root"),
                password=self.settings.get("tecdoc_mysql_password", ""),
                database=self.database,
                charset="utf8mb4",
                connect_timeout=5,
                read_timeout=MYSQL_READ_TIMEOUT_SEC,
                write_timeout=MYSQL_READ_TIMEOUT_SEC,
                cursorclass=DictCursor,
            )
            self._load_brands()
            self.available = len(self._brands_by_norm) > 0
        except Exception:
            self.available = False
            if self._conn:
                try:
                    self._conn.close()
                except Exception:
                    pass
            self._conn = None

    def _load_aliases(self) -> None:
        path = CONFIG_DIR / "tecdoc_brand_aliases.json"
        if path.is_file():
            try:
                data = json.loads(path.read_text(encoding="utf-8"))
                self._aliases = {normalize_brand_key(k): str(v).upper() for k, v in (data.get("aliases") or {}).items()}
                self._skip_brands = {normalize_brand_key(s) for s in (data.get("skip_supplier_brands") or [])}
            except (json.JSONDecodeError, OSError):
                pass
        for cfg in load_suppliers().values():
            for key, value in (cfg.get("brand_aliases") or {}).items():
                norm = normalize_brand_key(str(key))
                if norm:
                    self._aliases[norm] = str(value).upper()

    def _load_brands(self) -> None:
        if not self._conn:
            return
        with self._conn.cursor() as cur:
            cur.execute("SELECT name FROM brands")
            for row in cur.fetchall() or []:
                name = str(row.get("name") or "").strip()
                if name:
                    self._brands_by_norm[normalize_brand_key(name)] = name.upper()

    def resolve_brand(self, supplier_brand: str) -> str | None:
        raw = (supplier_brand or "").strip()
        if not raw:
            return None
        norm = normalize_brand_key(raw)
        if not norm or norm in self._skip_brands:
            return None
        if norm in self._aliases:
            target = normalize_brand_key(self._aliases[norm])
            if target in self._brands_by_norm:
                return self._brands_by_norm[target]
            return str(self._aliases[norm]).upper()
        if norm in self._brands_by_norm:
            return self._brands_by_norm[norm]
        best_name = None
        best_score = 0
        for key, name in self._brands_by_norm.items():
            if key.startswith(norm) or norm.startswith(key):
                score = 90 - abs(len(key) - len(norm))
            else:
                score = 0
            if score > best_score:
                best_score = score
                best_name = name
        return best_name if best_score >= 72 else None

    @staticmethod
    def _filter_lookup_norms(norms: list[str]) -> list[str]:
        seen: set[str] = set()
        out: list[str] = []
        for norm in norms:
            if len(norm) < MIN_CODE_NORM_LEN:
                continue
            if norm in seen:
                continue
            seen.add(norm)
            out.append(norm)
        return out

    def _fetch_code_hits(self, norms: list[str], brand: str | None = None) -> list[dict[str, Any]]:
        norms = self._filter_lookup_norms(norms)
        if not self._conn or not norms:
            return []
        ph = ",".join(["%s"] * len(norms))
        sql = (
            "SELECT p.id AS product_id, p.art_code_1, p.art_code_2, p.art_name, "
            "p.art_ean, p.ttc_art_id, b.name AS art_brand "
            "FROM product_codes pc "
            "JOIN products p ON p.id = pc.product_id "
            "JOIN brands b ON b.id = p.brand_id "
            f"WHERE pc.code_norm IN ({ph})"
        )
        params: list[Any] = list(norms)
        if brand:
            sql += " AND b.name = %s"
            params.append(brand)
        sql += " LIMIT 20"
        try:
            with self._conn.cursor() as cur:
                cur.execute(sql, params)
                return list(cur.fetchall() or [])
        except Exception:
            return []

    def _pick_best_code_hit(
        self,
        rows: list[dict[str, Any]],
        norms: list[str],
        preferred_brand: str | None = None,
    ) -> dict[str, Any] | None:
        if not rows:
            return None
        norm_set = set(norms)
        pref = (preferred_brand or "").strip().upper()

        for row in rows:
            for field in ("art_code_1", "art_code_2"):
                if normalize_code(str(row.get(field) or "")) in norm_set:
                    if pref and str(row.get("art_brand") or "").upper() != pref:
                        continue
                    return row

        if pref:
            for row in rows:
                if str(row.get("art_brand") or "").upper() == pref:
                    return row

        if len(rows) == 1:
            return rows[0]
        product_ids = {row.get("product_id") for row in rows}
        if len(product_ids) == 1:
            return rows[0]
        # Cod ambiguu (mai multe produse) — preferă primul hit decât no_match total.
        return rows[0]

    def lookup_brand_codes(self, brand: str, codes: list[str]) -> dict[str, Any] | None:
        if not self._conn or not codes:
            return None
        tecdoc_brand = self.resolve_brand(brand)
        if not tecdoc_brand:
            return None
        norms = list(dict.fromkeys(normalize_code(c) for c in codes if normalize_code(c)))
        norms = self._filter_lookup_norms(norms)
        if not norms:
            return None
        rows = self._fetch_code_hits(norms, tecdoc_brand)
        row = self._pick_best_code_hit(rows, norms, tecdoc_brand)
        return self._row_to_product(row) if row else None

    def lookup_codes_any_brand(self, codes: list[str], preferred_brand: str | None = None) -> dict[str, Any] | None:
        if not self._conn or not codes:
            return None
        norms = list(dict.fromkeys(normalize_code(c) for c in codes if normalize_code(c)))
        norms = self._filter_lookup_norms(norms)
        if not norms:
            return None
        resolved = self.resolve_brand(preferred_brand) if preferred_brand else None
        if resolved:
            rows = self._fetch_code_hits(norms, resolved)
            row = self._pick_best_code_hit(rows, norms, resolved)
            if row:
                return self._row_to_product(row)
        rows = self._fetch_code_hits(norms)
        row = self._pick_best_code_hit(rows, norms, resolved)
        return self._row_to_product(row) if row else None

    def _row_to_product(self, row: dict[str, Any]) -> dict[str, Any]:
        return {
            "internal_sku": f"TEC-{row.get('product_id')}",
            "name": row.get("art_name") or "",
            "brand": row.get("art_brand") or "",
            "codes": [c for c in [row.get("art_code_1"), row.get("art_code_2")] if c],
            "ean": row.get("art_ean") or "",
            "price": None,
            "stock": None,
            "source": "tecdoc_mysql",
            "tecdoc_db": self.database,
            "ttc_art_id": row.get("ttc_art_id") or "",
            "product_id": row.get("product_id"),
        }

    def lookup_ean(self, ean: str) -> dict[str, Any] | None:
        if not self._conn or not ean:
            return None
        ean_digits = re.sub(r"\D", "", ean)
        if len(ean_digits) < MIN_EAN_DIGITS:
            return None
        sql = (
            "SELECT p.id AS product_id, p.art_code_1, p.art_code_2, p.art_name, "
            "p.art_ean, p.ttc_art_id, b.name AS art_brand "
            "FROM products p JOIN brands b ON b.id = p.brand_id "
            "WHERE p.art_ean = %s LIMIT 1"
        )
        try:
            with self._conn.cursor() as cur:
                cur.execute(sql, [ean_digits])
                row = cur.fetchone()
        except Exception:
            return None
        return self._row_to_product(row) if row else None

    def lookup_product(self, product: dict[str, Any]) -> tuple[dict[str, Any] | None, str | None]:
        """Returnează (matched, method)."""
        codes = product.get("codes") or []
        brand = product.get("brand") or ""

        hit = self.lookup_brand_codes(brand, codes)
        if hit:
            return hit, "tecdoc_brand_code"

        ean = product.get("ean") or ""
        if ean:
            hit = self.lookup_ean(ean)
            if hit:
                return hit, "tecdoc_ean"

        allow_code_only = bool(product.get("match_code_only", True))
        resolved_brand = self.resolve_brand(brand)
        if not allow_code_only and resolved_brand:
            return None, None

        hit = self.lookup_codes_any_brand(codes, brand if resolved_brand else None)
        if hit:
            # Match doar după cod: brandul TecDoc din hit este sursa de adevăr pentru enrichment.
            return hit, "tecdoc_code"

        return None, None

    def stats(self) -> dict[str, Any]:
        if not self._conn:
            return {"available": False}
        try:
            with self._conn.cursor() as cur:
                # COUNT(*) pe tabele mari TecDoc poate dura minute — folosim estimări rapide.
                cur.execute(
                    "SELECT table_name, table_rows FROM information_schema.tables "
                    "WHERE table_schema = DATABASE() "
                    "AND table_name IN ('products', 'product_codes', 'brands')"
                )
                rows = {str(r.get("table_name") or ""): int(r.get("table_rows") or 0) for r in (cur.fetchall() or [])}
                products = rows.get("products", 0)
                codes = rows.get("product_codes", 0)
                brands = rows.get("brands", 0)
                if products <= 0 and codes <= 0 and brands <= 0:
                    cur.execute("SELECT COUNT(*) AS c FROM brands")
                    brands = int((cur.fetchone() or {}).get("c", 0))
            return {
                "available": True,
                "products": products,
                "product_codes": codes,
                "brands": brands,
                "approximate": True,
            }
        except Exception:
            return {"available": self.available, "brands": len(self._brands_by_norm)}

    def close(self) -> None:
        if self._conn:
            try:
                self._conn.close()
            except Exception:
                pass
            self._conn = None


_LOOKUPS: dict[str, TecDocLookup] = {}


def get_tecdoc_lookup(database: str | None = None) -> TecDocLookup:
    settings = load_settings()
    db = (database or "").strip() or str(settings.get("tecdoc_mysql_db") or "besoiu_tecdoc_base")
    hit = _LOOKUPS.get(db)
    if hit is None:
        hit = TecDocLookup(database=db)
        _LOOKUPS[db] = hit
    return hit


def get_tecdoc_lookup_for_product(product: dict[str, Any] | None = None) -> TecDocLookup:
    settings = load_settings()
    profile = str((product or {}).get("supplier_profile") or "").strip()
    if profile == "autonet_qwp":
        db = str(settings.get("tecdoc_mysql_db_qwp") or "besoiu_tecdoc_y1998")
        return get_tecdoc_lookup(db)
    return get_tecdoc_lookup()
