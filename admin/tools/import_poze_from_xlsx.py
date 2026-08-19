"""
Mapează un brand din folderul Poze după Excel:
  coloana A = TTC_ART_ID = numele fișierului din {brand}/Poze
  coloana C = ART_CODE_1 (TecDoc), curățat (spațiu - . /)
  denumire = ART_NAME din Excel (română)
  fișier disc = BRAND-COD.jpg

Nu folosește CSV-ul de cross-ref și nu amestecă Autopartner.

  python admin/tools/import_poze_from_xlsx.py AE
  python admin/tools/import_poze_from_xlsx.py --all
"""

from __future__ import annotations

import argparse
import os
import re
import sys
from pathlib import Path

import openpyxl

POZE_ROOT = Path(r"C:\laragon\www\besoiupieseimport\Poze")
OUT_ROOT = Path(r"C:\laragon\www\besoiupieseimport\Poze_RENUMITE")
SQL_SCHEMA = Path(__file__).resolve().parents[2] / "SQL" / "imagine_poze.sql"


def norm_code(value: object) -> str:
    text = str(value or "").strip()
    text = text.replace(" ", "").replace("-", "").replace(".", "").replace("/", "").replace("_", "")
    return re.sub(r"[^A-Za-z0-9]", "", text).upper()


def brand_token(value: object) -> str:
    return re.sub(r"[^A-Z0-9]", "", str(value or "").upper())


def find_brand_dir(wanted: str) -> Path | None:
    wanted_key = brand_token(wanted)
    exact: Path | None = None
    for child in POZE_ROOT.iterdir():
        if not child.is_dir() or child.name.startswith("."):
            continue
        name = child.name.lstrip("=")
        first = brand_token(name.split("-")[0].split(" ")[0])
        if first == wanted_key or brand_token(name) == wanted_key:
            exact = child
            break
    return exact


def find_xlsx(brand_dir: Path) -> Path | None:
    files = [p for p in brand_dir.glob("*.xlsx") if not p.name.startswith("~$")]
    if not files:
        return None
    files.sort(key=lambda p: (0 if brand_token(p.stem) == brand_token(brand_dir.name.lstrip("=")) else 1, p.name))
    return files[0]


def find_poze_dir(brand_dir: Path) -> Path | None:
    for cand in [brand_dir / "Poze", brand_dir / "poze"]:
        if cand.is_dir():
            return cand
    return None


def load_xlsx_map(xlsx: Path) -> dict[str, dict[str, str]]:
    """TTC_ART_ID (col A) -> brand, code_raw, code_norm, name."""
    wb = openpyxl.load_workbook(xlsx, read_only=True, data_only=True)
    ws = wb.active
    out: dict[str, dict[str, str]] = {}
    for i, row in enumerate(ws.iter_rows(values_only=True)):
        if not row:
            continue
        if i == 0 and str(row[0] or "").upper() in {"TTC_ART_ID", "A"}:
            continue
        art_id = str(row[0] or "").strip()
        brand = brand_token(row[1] if len(row) > 1 else "")
        code_raw = str(row[2] or "").strip() if len(row) > 2 else ""
        name = str(row[5] or "").strip() if len(row) > 5 else ""
        code = norm_code(code_raw)
        if art_id == "" or code == "":
            continue
        if art_id not in out:
            out[art_id] = {
                "brand": brand or "NECUNOSCUT",
                "code_raw": code_raw,
                "code_norm": code,
                "name": name,
            }
    wb.close()
    return out


def link_or_copy(src: Path, dest: Path) -> bool:
    dest.parent.mkdir(parents=True, exist_ok=True)
    if dest.exists():
        try:
            dest.unlink()
        except OSError:
            return dest.stat().st_size >= 512
    try:
        os.link(src, dest)
        return True
    except OSError:
        try:
            dest.write_bytes(src.read_bytes())
            return dest.stat().st_size >= 512
        except OSError:
            return False


def mysql_exec(sql: str) -> None:
    import subprocess

    mysql = Path(r"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe")
    subprocess.run([str(mysql), "-uroot", "--default-character-set=utf8mb4"], input=sql.encode("utf8"), check=True)


def reset_db() -> None:
    mysql_exec(SQL_SCHEMA.read_text(encoding="utf-8"))


def import_brand(wanted: str, reset: bool = False) -> dict[str, int]:
    brand_dir = find_brand_dir(wanted)
    if brand_dir is None:
        raise SystemExit(f"Nu găsesc folderul brandului {wanted} în {POZE_ROOT}")
    xlsx = find_xlsx(brand_dir)
    poze_dir = find_poze_dir(brand_dir)
    if xlsx is None or poze_dir is None:
        raise SystemExit(f"Lipsește xlsx sau folder Poze în {brand_dir}")

    catalog = load_xlsx_map(xlsx)
    files = {
        p.stem: p
        for p in poze_dir.iterdir()
        if p.is_file() and p.suffix.lower() in {".jpg", ".jpeg", ".png", ".webp"}
    }

    if reset:
        reset_db()

    products: dict[tuple[str, str], dict] = {}
    images: list[tuple] = []
    matched = 0
    missing_file = 0
    orphan_file = 0

    for art_id, meta in catalog.items():
        src = files.get(art_id)
        if src is None:
            missing_file += 1
            continue
        brand = meta["brand"]
        code = meta["code_norm"]
        key = (brand, code)
        products.setdefault(
            key,
            {"code_raw": meta["code_raw"], "name": meta["name"], "ttc": art_id, "count": 0, "files": []},
        )
        products[key]["files"].append((art_id, src))
        products[key]["count"] += 1
        matched += 1

    for stem, src in files.items():
        if stem not in catalog:
            orphan_file += 1

    seqs: dict[tuple[str, str], int] = {}
    for (brand, code), prod in products.items():
        files_for = prod["files"]
        for idx, (art_id, src) in enumerate(files_for, start=1):
            if len(files_for) == 1:
                disk = f"{brand}-{code}{src.suffix.lower()}"
            else:
                disk = f"{brand}-{code}_{idx}{src.suffix.lower()}"
            dest = OUT_ROOT / brand / disk
            if not link_or_copy(src, dest):
                continue
            rel_orig = src.relative_to(POZE_ROOT).as_posix()
            rel_new = f"Poze_RENUMITE/{brand}/{disk}"
            images.append((brand, code, prod["code_raw"], art_id, f"Poze/{rel_orig}", disk, rel_new))

    # write SQL inserts
    def q(v: str) -> str:
        return "'" + v.replace("\\", "\\\\").replace("'", "\\'") + "'"

    chunks = ["USE `imagine_poze`; SET NAMES utf8mb4;"]
    for (brand, code), prod in products.items():
        chunks.append(
            "INSERT INTO products (code_norm, brand, code_raw, name, ttc_art_id, image_count) VALUES ("
            f"{q(code)}, {q(brand)}, {q(prod['code_raw'])}, {q(prod['name'])}, {q(prod['ttc'])}, {prod['count']}"
            ") ON DUPLICATE KEY UPDATE name=VALUES(name), ttc_art_id=VALUES(ttc_art_id), "
            "image_count=VALUES(image_count), code_raw=VALUES(code_raw);"
        )
    for row in images:
        brand, code, raw, ttc, orig, disk, rel = row
        chunks.append(
            "INSERT IGNORE INTO images (brand, code_norm, code_raw, ttc_art_id, original_path, disk_name, rel_path, source) VALUES ("
            f"{q(brand)}, {q(code)}, {q(raw)}, {q(ttc)}, {q(orig)}, {q(disk)}, {q(rel)}, 'ttc_xlsx');"
        )
    mysql_exec("\n".join(chunks))

    stats = {
        "xlsx_ids": len(catalog),
        "files": len(files),
        "matched": matched,
        "missing_file": missing_file,
        "orphan_file": orphan_file,
        "products": len(products),
        "images": len(images),
    }
    print(f"{wanted}: xlsx={stats['xlsx_ids']} fisiere={stats['files']} mapate={stats['matched']} "
          f"fara_poza={stats['missing_file']} orfane={stats['orphan_file']} "
          f"produse={stats['products']} imagini={stats['images']}")
    return stats


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("brand", nargs="?", default="AE")
    parser.add_argument("--reset", action="store_true", help="Recreează baza imagine_poze înainte de import")
    args = parser.parse_args()
    import_brand(args.brand, reset=args.reset)


if __name__ == "__main__":
    main()
