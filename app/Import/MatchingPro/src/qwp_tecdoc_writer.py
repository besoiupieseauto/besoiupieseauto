"""Clonează cartele QWP din TecDoc y1998 în besoiu_tecdoc_base (brand QWP + ArtNr).

Compatibilitățile se copiază pe bucăți (fetch+insert), nu cu INSERT…SELECT cross-DB
(care a crash-uit MySQL 8.4 pe volum mare).
"""

from __future__ import annotations

from typing import Any

from code_normalizer import normalize_code
from paths import load_settings

try:
    import pymysql
    from pymysql.cursors import DictCursor
except ImportError:
    pymysql = None  # type: ignore
    DictCursor = None  # type: ignore

COMPAT_CHUNK = 1000


class QwpTecdocWriter:
    def __init__(self) -> None:
        self.settings = load_settings()
        self.source_db = str(self.settings.get("tecdoc_mysql_db_qwp") or "besoiu_tecdoc_y1998")
        self.target_db = str(self.settings.get("tecdoc_mysql_db_qwp_write") or "besoiu_tecdoc_base")
        self._conn = None
        self._qwp_brand_id: int | None = None
        self.available = False
        if pymysql is None:
            return
        try:
            self._connect()
            self._qwp_brand_id = self._ensure_qwp_brand()
            self.available = self._qwp_brand_id is not None
            if self.available and self._conn:
                self._conn.commit()
        except Exception:
            self.available = False
            self._rollback_quiet()
            self.close()

    def _connect(self) -> None:
        self._conn = pymysql.connect(
            host=self.settings.get("tecdoc_mysql_host", "127.0.0.1"),
            user=self.settings.get("tecdoc_mysql_user", "root"),
            password=self.settings.get("tecdoc_mysql_password", ""),
            charset="utf8mb4",
            autocommit=False,
            connect_timeout=10,
            read_timeout=300,
            write_timeout=300,
            cursorclass=DictCursor,
        )

    def _ensure_alive(self) -> bool:
        if not self._conn:
            try:
                self._connect()
            except Exception:
                return False
        try:
            self._conn.ping(reconnect=True)
            return True
        except Exception:
            try:
                self.close()
                self._connect()
                return True
            except Exception:
                return False

    def _rollback_quiet(self) -> None:
        if not self._conn:
            return
        try:
            self._conn.rollback()
        except Exception:
            pass

    def _ensure_qwp_brand(self) -> int | None:
        if not self._conn:
            return None
        with self._conn.cursor() as cur:
            cur.execute(f"SELECT id FROM `{self.target_db}`.brands WHERE name = %s LIMIT 1", ("QWP",))
            row = cur.fetchone()
            if row:
                return int(row["id"])
            cur.execute(
                f"INSERT INTO `{self.target_db}`.brands (name, source_file) VALUES (%s, %s)",
                ("QWP", "autonet_qwp"),
            )
            return int(cur.lastrowid)

    def find_existing_product_id(self, art_nr: str) -> int | None:
        if not self._ensure_alive() or not self._qwp_brand_id:
            return None
        art = (art_nr or "").strip()
        code_norm = normalize_code(art)
        if not art and not code_norm:
            return None
        assert self._conn is not None
        with self._conn.cursor() as cur:
            if art:
                cur.execute(
                    f"SELECT id FROM `{self.target_db}`.products "
                    "WHERE brand_id = %s AND art_code_1 = %s LIMIT 1",
                    (self._qwp_brand_id, art),
                )
                row = cur.fetchone()
                if row:
                    return int(row["id"])
            if code_norm:
                cur.execute(
                    f"SELECT p.id FROM `{self.target_db}`.product_codes pc "
                    f"JOIN `{self.target_db}`.products p ON p.id = pc.product_id "
                    "WHERE pc.brand_id = %s AND pc.code_norm = %s LIMIT 1",
                    (self._qwp_brand_id, code_norm),
                )
                row = cur.fetchone()
                if row:
                    return int(row["id"])
        return None

    def _copy_compat_chunked(self, cur, new_id: int, template_product_id: int) -> int:
        """Copiază compat pe bucăți — evită INSERT…SELECT cross-DB care crash-uiește MySQL."""
        cur.execute(
            f"SELECT ttc_typ_id, car_brand, car_model, car_typ, car_body, "
            f"car_of_year, car_to_year, car_kw, car_pm, car_cc "
            f"FROM `{self.source_db}`.product_compatibilities WHERE product_id = %s",
            (template_product_id,),
        )
        total = 0
        insert_sql = (
            f"INSERT INTO `{self.target_db}`.product_compatibilities ("
            "product_id, ttc_typ_id, car_brand, car_model, car_typ, car_body, "
            "car_of_year, car_to_year, car_kw, car_pm, car_cc"
            ") VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)"
        )
        while True:
            rows = cur.fetchmany(COMPAT_CHUNK)
            if not rows:
                break
            payload = [
                (
                    new_id,
                    r.get("ttc_typ_id"),
                    r.get("car_brand"),
                    r.get("car_model"),
                    r.get("car_typ"),
                    r.get("car_body"),
                    r.get("car_of_year"),
                    r.get("car_to_year"),
                    r.get("car_kw"),
                    r.get("car_pm"),
                    r.get("car_cc"),
                )
                for r in rows
            ]
            cur.executemany(insert_sql, payload)
            total += len(payload)
        return total

    def clone_card(self, template_product_id: int, art_nr: str) -> dict[str, Any]:
        result: dict[str, Any] = {
            "status": "error",
            "product_id": None,
            "art_nr": (art_nr or "").strip(),
            "template_product_id": int(template_product_id or 0),
        }
        if not self.available or not self._ensure_alive() or not self._qwp_brand_id:
            result["status"] = "unavailable"
            return result

        art = (art_nr or "").strip()
        if not art or template_product_id <= 0:
            result["status"] = "invalid"
            return result

        existing = self.find_existing_product_id(art)
        if existing:
            result["status"] = "exists"
            result["product_id"] = existing
            return result

        assert self._conn is not None
        try:
            with self._conn.cursor() as cur:
                cur.execute(
                    f"SELECT id, art_code_1, art_code_2, art_name, art_ean, ttc_art_id, "
                    f"parts_info, terms_of_use, art_cross, compat_count "
                    f"FROM `{self.source_db}`.products WHERE id = %s LIMIT 1",
                    (template_product_id,),
                )
                src = cur.fetchone()
                if not src:
                    result["status"] = "no_template"
                    return result

                code_norm = normalize_code(art)
                code2 = src.get("art_code_2") or None
                code2_norm = normalize_code(str(code2)) if code2 else ""

                cur.execute(
                    f"INSERT INTO `{self.target_db}`.products ("
                    "brand_id, art_code_1, art_code_2, art_name, art_ean, ttc_art_id, "
                    "parts_info, terms_of_use, art_cross, compat_count"
                    ") VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
                    (
                        self._qwp_brand_id,
                        art,
                        code2,
                        src.get("art_name"),
                        src.get("art_ean"),
                        src.get("ttc_art_id"),
                        src.get("parts_info"),
                        src.get("terms_of_use"),
                        src.get("art_cross"),
                        0,
                    ),
                )
                new_id = int(cur.lastrowid)

                if code_norm:
                    cur.execute(
                        f"INSERT IGNORE INTO `{self.target_db}`.product_codes "
                        "(product_id, brand_id, code_norm, code_type) VALUES (%s,%s,%s,%s)",
                        (new_id, self._qwp_brand_id, code_norm, "code1"),
                    )
                if code2_norm and code2_norm != code_norm:
                    cur.execute(
                        f"INSERT IGNORE INTO `{self.target_db}`.product_codes "
                        "(product_id, brand_id, code_norm, code_type) VALUES (%s,%s,%s,%s)",
                        (new_id, self._qwp_brand_id, code2_norm, "code2"),
                    )

                # Commit produs+coduri înainte de compat (ca să nu pierdem cartela dacă compat eșuează)
                self._conn.commit()

                compat_count = 0
                try:
                    with self._conn.cursor() as cur2:
                        compat_count = self._copy_compat_chunked(cur2, new_id, template_product_id)
                        if compat_count > 0:
                            cur2.execute(
                                f"UPDATE `{self.target_db}`.products SET compat_count = %s WHERE id = %s",
                                (compat_count, new_id),
                            )
                        try:
                            cur2.execute(
                                f"INSERT INTO `{self.target_db}`.product_images ("
                                "product_id, brand_id, source, ttc_art_id, ap_code, file_path, priority"
                                ") "
                                f"SELECT %s, %s, source, ttc_art_id, ap_code, file_path, priority "
                                f"FROM `{self.source_db}`.product_images WHERE product_id = %s",
                                (new_id, self._qwp_brand_id, template_product_id),
                            )
                        except Exception:
                            pass
                    self._conn.commit()
                except Exception as compat_exc:
                    self._rollback_quiet()
                    result["status"] = "written_no_compat"
                    result["product_id"] = new_id
                    result["compat_error"] = str(compat_exc)
                    return result

            result["status"] = "written"
            result["product_id"] = new_id
            result["compat_count"] = compat_count
            return result
        except Exception as exc:
            self._rollback_quiet()
            existing = self.find_existing_product_id(art)
            if existing:
                result["status"] = "exists"
                result["product_id"] = existing
                return result
            result["status"] = "error"
            result["error"] = str(exc)
            return result

    def write_from_match_results(self, match_results: list[dict[str, Any]]) -> dict[str, int]:
        stats = {
            "candidates": 0,
            "written": 0,
            "written_no_compat": 0,
            "exists": 0,
            "no_template": 0,
            "skipped": 0,
            "error": 0,
        }
        if not self.available:
            return stats

        for row in match_results:
            inp = row.get("input") or {}
            if str(inp.get("supplier_profile") or "") != "autonet_qwp":
                continue
            if row.get("status") not in ("exact", "probable"):
                stats["skipped"] += 1
                continue
            art_nr = str(inp.get("art_nr") or inp.get("sku_supplier") or "").strip()
            matched = row.get("matched_product") or {}
            template_id = int(matched.get("product_id") or 0)
            if not art_nr or template_id <= 0:
                stats["skipped"] += 1
                continue
            stats["candidates"] += 1
            out = self.clone_card(template_id, art_nr)
            status = out.get("status") or "error"
            if status in stats:
                stats[status] += 1
            else:
                stats["error"] += 1
            row["qwp_tecdoc_write"] = out
            if out.get("product_id"):
                matched["base_product_id"] = out["product_id"]
                matched["qwp_write_status"] = status
        return stats

    def close(self) -> None:
        if self._conn:
            try:
                self._conn.close()
            except Exception:
                pass
            self._conn = None


_writer: QwpTecdocWriter | None = None


def get_qwp_tecdoc_writer() -> QwpTecdocWriter:
    global _writer
    if _writer is None:
        _writer = QwpTecdocWriter()
    return _writer
