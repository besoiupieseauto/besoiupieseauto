"""UPSERT mapare Autonet QWP (ArtNr / RefNr / ReferenceBrand) în shop DB."""

from __future__ import annotations

from typing import Any, Iterable

from paths import load_settings

try:
    import pymysql
    from pymysql.cursors import DictCursor
except ImportError:
    pymysql = None  # type: ignore
    DictCursor = None  # type: ignore


CREATE_SQL = """
CREATE TABLE IF NOT EXISTS autonet_qwp_data (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ArtNr VARCHAR(64) NOT NULL,
  ReferenceBrand VARCHAR(128) NOT NULL DEFAULT '',
  RefNr VARCHAR(128) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_qwp_art_ref_brand (ArtNr, RefNr, ReferenceBrand),
  KEY idx_qwp_ref (RefNr),
  KEY idx_qwp_art (ArtNr),
  KEY idx_qwp_ref_brand (RefNr, ReferenceBrand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
"""


class QwpShopMap:
    def __init__(self) -> None:
        self.settings = load_settings()
        self.db = str(self.settings.get("shop_mysql_db") or "besoiupieseauto.ro")
        self._conn = None
        self.available = False
        if pymysql is None:
            return
        try:
            self._conn = pymysql.connect(
                host=self.settings.get("tecdoc_mysql_host", "127.0.0.1"),
                user=self.settings.get("tecdoc_mysql_user", "root"),
                password=self.settings.get("tecdoc_mysql_password", ""),
                database=self.db,
                charset="utf8mb4",
                autocommit=True,
                connect_timeout=10,
                cursorclass=DictCursor,
            )
            with self._conn.cursor() as cur:
                cur.execute(CREATE_SQL)
            self.available = True
        except Exception:
            self.available = False
            self.close()

    def upsert_rows(self, rows: Iterable[dict[str, str]]) -> dict[str, int]:
        stats = {"upserted": 0, "skipped": 0}
        if not self.available or not self._conn:
            return stats
        sql = (
            "INSERT INTO autonet_qwp_data (ArtNr, ReferenceBrand, RefNr) "
            "VALUES (%s, %s, %s) "
            "ON DUPLICATE KEY UPDATE "
            "ReferenceBrand = VALUES(ReferenceBrand), updated_at = CURRENT_TIMESTAMP"
        )
        batch: list[tuple[str, str, str]] = []
        with self._conn.cursor() as cur:
            for row in rows:
                art = str(row.get("ArtNr") or row.get("art_nr") or "").strip()
                brand = str(row.get("ReferenceBrand") or row.get("brand") or "").strip()
                ref = str(row.get("RefNr") or row.get("ref_nr") or "").strip()
                if not art or not ref:
                    stats["skipped"] += 1
                    continue
                batch.append((art, brand, ref))
                if len(batch) >= 500:
                    cur.executemany(sql, batch)
                    stats["upserted"] += len(batch)
                    batch.clear()
            if batch:
                cur.executemany(sql, batch)
                stats["upserted"] += len(batch)
        return stats

    def upsert_from_match_inputs(self, match_results: list[dict[str, Any]]) -> dict[str, int]:
        rows = []
        for item in match_results:
            inp = item.get("input") or {}
            if str(inp.get("supplier_profile") or "") != "autonet_qwp":
                continue
            rows.append(
                {
                    "ArtNr": str(inp.get("art_nr") or inp.get("sku_supplier") or ""),
                    "ReferenceBrand": str(inp.get("brand") or ""),
                    "RefNr": str(inp.get("ref_nr") or ""),
                }
            )
        return self.upsert_rows(rows)

    def close(self) -> None:
        if self._conn:
            try:
                self._conn.close()
            except Exception:
                pass
            self._conn = None


_map: QwpShopMap | None = None


def get_qwp_shop_map() -> QwpShopMap:
    global _map
    if _map is None:
        _map = QwpShopMap()
    return _map
