"""Eșantion match rate QWP vs y1998."""
from __future__ import annotations

import csv
import re
import sys
from pathlib import Path

import pymysql

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "src"))
from code_normalizer import normalize_code  # noqa: E402
from tecdoc_lookup import normalize_brand_key  # noqa: E402


def main() -> None:
    conn = pymysql.connect(
        host="127.0.0.1",
        user="root",
        password="",
        database="besoiu_tecdoc_y1998",
        charset="utf8mb4",
    )
    brands: dict[str, str] = {}
    with conn.cursor() as cur:
        cur.execute("SELECT name FROM brands")
        for (name,) in cur.fetchall():
            brands[normalize_brand_key(name)] = name.upper()

    path = Path(
        r"C:\laragon\www\besoiupieseauto.ro\app\Backend\storage\supplier_feeds\autonet\Autonet QWP.csv"
    )
    ok = no = 0
    with path.open(encoding="utf-8", errors="replace", newline="") as fh:
        reader = csv.DictReader(fh, delimiter=";")
        for i, row in enumerate(reader):
            if i >= 100:
                break
            brand = (row.get("ReferenceBrand") or "").strip()
            ref = (row.get("RefNr") or "").strip()
            cn = normalize_code(ref)
            bn = brands.get(normalize_brand_key(brand))
            if not bn or len(cn) < 5:
                no += 1
                continue
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT p.id FROM product_codes pc "
                    "JOIN products p ON p.id = pc.product_id "
                    "JOIN brands b ON b.id = p.brand_id "
                    "WHERE pc.code_norm = %s AND b.name = %s LIMIT 1",
                    (cn, bn),
                )
                hit = cur.fetchone()
            if hit:
                ok += 1
            else:
                no += 1
    print(f"sample100 match={ok} no_match={no}")
    conn.close()


if __name__ == "__main__":
    main()
