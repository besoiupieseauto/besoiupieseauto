"""
Mapează brandurile din folderul Poze după Excel:
  coloana A = TTC_ART_ID = numele fișierului din {brand}/Poze
  coloana C = ART_CODE_1 (TecDoc), curățat (spațiu - . /)
  denumire = ART_NAME din Excel
  fișier disc = BRAND-COD.jpg

  python admin/tools/import_poze_from_xlsx.py --all --reset
  python admin/tools/import_poze_from_xlsx.py AE
"""

from __future__ import annotations

import argparse
import os
import re
import subprocess
from pathlib import Path

import openpyxl

POZE_ROOT = Path(r"C:\laragon\www\besoiupieseimport\Poze")
OUT_ROOT = Path(r"C:\laragon\www\besoiupieseimport\Poze_RENUMITE")
SQL_SCHEMA = Path(__file__).resolve().parents[2] / "SQL" / "imagine_poze.sql"
MYSQL = Path(r"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe")
SKIP_DIRS = {"importpro"}


def norm_code(value: object) -> str:
    text = str(value or "").strip()
    text = text.replace(" ", "").replace("-", "").replace(".", "").replace("/", "").replace("_", "")
    return re.sub(r"[^A-Za-z0-9]", "", text).upper()


def brand_token(value: object) -> str:
    return re.sub(r"[^A-Z0-9]", "", str(value or "").upper())


def q(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def find_xlsx_list(brand_dir: Path) -> list[Path]:
    files = [p for p in brand_dir.glob("*.xlsx") if not p.name.startswith("~$")]
    files.sort(key=lambda p: (1 if "vechi" in p.stem.lower() else 0, p.name.lower()))
    return files


def find_poze_dir(brand_dir: Path) -> Path | None:
    for cand in [brand_dir / "Poze", brand_dir / "poze"]:
        if cand.is_dir():
            return cand
    return None


def load_xlsx_map(xlsx_files: list[Path]) -> dict[str, dict[str, str]]:
    out: dict[str, dict[str, str]] = {}
    for xlsx in xlsx_files:
        wb = openpyxl.load_workbook(xlsx, read_only=True, data_only=True)
        ws = wb.active
        for i, row in enumerate(ws.iter_rows(values_only=True)):
            if not row:
                continue
            first = str(row[0] or "").strip().upper()
            if i == 0 and first in {"TTC_ART_ID", "A", "ID"}:
                continue
            art_id = str(row[0] or "").strip()
            if art_id.endswith(".0") and art_id.replace(".", "", 1).replace("0", "").isdigit() is False:
                art_id = art_id[:-2]
            if art_id.endswith(".0"):
                try:
                    art_id = str(int(float(art_id)))
                except ValueError:
                    pass
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
    if dest.exists() and dest.stat().st_size >= 512:
        return True
    if dest.exists():
        try:
            dest.unlink()
        except OSError:
            return False
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
    subprocess.run(
        [str(MYSQL), "-uroot", "--default-character-set=utf8mb4"],
        input=sql.encode("utf-8"),
        check=True,
        stdout=subprocess.DEVNULL,
    )


def reset_db() -> None:
    mysql_exec(SQL_SCHEMA.read_text(encoding="utf-8"))


def mysql_batches(statements: list[str], batch_size: int = 250) -> None:
    header = "USE `imagine_poze`; SET NAMES utf8mb4; SET UNIQUE_CHECKS=0; SET FOREIGN_KEY_CHECKS=0;"
    footer = "SET UNIQUE_CHECKS=1; SET FOREIGN_KEY_CHECKS=1;"
    for i in range(0, len(statements), batch_size):
        mysql_exec(header + "\n" + "\n".join(statements[i : i + batch_size]) + "\n" + footer)


def list_brand_dirs() -> list[Path]:
    dirs: list[Path] = []
    for child in sorted(POZE_ROOT.iterdir(), key=lambda p: p.name.lower()):
        if not child.is_dir() or child.name.startswith("."):
            continue
        if child.name.lower() in SKIP_DIRS:
            continue
        if find_xlsx_list(child) and find_poze_dir(child):
            dirs.append(child)
    return dirs


def import_brand_dir(brand_dir: Path) -> dict[str, int]:
    xlsx_files = find_xlsx_list(brand_dir)
    poze_dir = find_poze_dir(brand_dir)
    if not xlsx_files or poze_dir is None:
        return {"matched": 0, "missing_file": 0, "orphan_file": 0, "products": 0, "images": 0}

    catalog = load_xlsx_map(xlsx_files)
    files = {
        p.stem: p
        for p in poze_dir.iterdir()
        if p.is_file() and p.suffix.lower() in {".jpg", ".jpeg", ".png", ".webp"}
    }

    products: dict[tuple[str, str], dict] = {}
    matched = 0
    missing_file = 0
    for art_id, meta in catalog.items():
        src = files.get(art_id)
        if src is None:
            missing_file += 1
            continue
        key = (meta["brand"], meta["code_norm"])
        products.setdefault(
            key,
            {"code_raw": meta["code_raw"], "name": meta["name"], "ttc": art_id, "files": []},
        )
        products[key]["files"].append((art_id, src))
        matched += 1

    orphan_file = sum(1 for stem in files if stem not in catalog)
    images: list[tuple] = []
    prod_sql: list[str] = []
    img_sql: list[str] = []

    for (brand, code), prod in products.items():
        files_for = prod["files"]
        prod_sql.append(
            "INSERT INTO products (code_norm, brand, code_raw, name, ttc_art_id, image_count) VALUES ("
            f"{q(code)}, {q(brand)}, {q(prod['code_raw'])}, {q(prod['name'])}, {q(prod['ttc'])}, {len(files_for)}"
            ") ON DUPLICATE KEY UPDATE name=IF(VALUES(name)<>'', VALUES(name), name), "
            "ttc_art_id=VALUES(ttc_art_id), code_raw=VALUES(code_raw), "
            "image_count=image_count+VALUES(image_count);"
        )
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
            img_sql.append(
                "INSERT IGNORE INTO images (brand, code_norm, code_raw, ttc_art_id, original_path, disk_name, rel_path, source) VALUES ("
                f"{q(brand)}, {q(code)}, {q(prod['code_raw'])}, {q(art_id)}, {q('Poze/' + rel_orig)}, {q(disk)}, {q(rel_new)}, 'ttc_xlsx');"
            )
            images.append((brand, code, disk))

    mysql_batches(prod_sql + img_sql)
    stats = {
        "xlsx_ids": len(catalog),
        "files": len(files),
        "matched": matched,
        "missing_file": missing_file,
        "orphan_file": orphan_file,
        "products": len(products),
        "images": len(images),
    }
    print(
        f"{brand_dir.name}: xlsx={stats['xlsx_ids']} fisiere={stats['files']} "
        f"mapate={stats['matched']} fara_poza={stats['missing_file']} "
        f"orfane={stats['orphan_file']} produse={stats['products']} imagini={stats['images']}",
        flush=True,
    )
    return stats


def import_all(reset: bool) -> None:
    dirs = list_brand_dirs()
    print(f"Branduri de importat: {len(dirs)}", flush=True)
    if reset:
        print("Recreez baza imagine_poze...", flush=True)
        reset_db()
    totals = {"matched": 0, "missing_file": 0, "orphan_file": 0, "products": 0, "images": 0}
    for brand_dir in dirs:
        st = import_brand_dir(brand_dir)
        for k in totals:
            totals[k] += st.get(k, 0)
    mysql_exec(
        "USE `imagine_poze`; "
        "UPDATE products p SET image_count = ("
        "SELECT COUNT(*) FROM images i WHERE i.brand=p.brand AND i.code_norm=p.code_norm);"
    )
    print(
        f"TOTAL mapate={totals['matched']} fara_poza={totals['missing_file']} "
        f"orfane={totals['orphan_file']} produse={totals['products']} imagini={totals['images']}",
        flush=True,
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("brand", nargs="?")
    parser.add_argument("--all", action="store_true")
    parser.add_argument("--reset", action="store_true")
    args = parser.parse_args()
    if args.all or args.brand in {None, "all", "--all"}:
        import_all(reset=args.reset or args.brand is None)
        return
    brand_dir = None
    wanted = brand_token(args.brand)
    for child in list_brand_dirs():
        name = child.name.lstrip("=")
        first = brand_token(name.split("-")[0].split(" ")[0])
        if first == wanted or brand_token(name) == wanted:
            brand_dir = child
            break
    if brand_dir is None:
        raise SystemExit(f"Nu găsesc folderul {args.brand}")
    if args.reset:
        reset_db()
    import_brand_dir(brand_dir)


if __name__ == "__main__":
    main()
