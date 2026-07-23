"""Scanare manuală — procesează fișiere CSV și generează raport."""

from __future__ import annotations

import shutil
from datetime import datetime
from pathlib import Path
from typing import Any

from catalog import build_catalog
from cron_control import clear_file_offset, get_file_offset, set_file_offset
from logger import log
from matcher import ProductMatcher
from parser import parse_csv_file
from paths import PROCESSED_DIR, UPLOADS_TEMP, load_settings, load_suppliers
from progress import add_step, set_file_phase
from report_generator import build_report_payload, save_report
from state_store import is_processed, mark_processed


def archive_file(source: Path, supplier: str, max_mb: int = 20) -> Path | None:
    size_mb = source.stat().st_size / (1024 * 1024)
    if size_mb > max_mb:
        log(f"Sărit arhivare ({size_mb:.1f} MB > {max_mb} MB): {source.name}", "SKIP")
        return None

    day = datetime.now().strftime("%Y-%m-%d")
    dest_dir = PROCESSED_DIR / supplier / day
    dest_dir.mkdir(parents=True, exist_ok=True)
    dest = dest_dir / source.name
    if dest.exists():
        dest = dest_dir / f"{datetime.now().strftime('%H%M%S')}_{source.name}"
    shutil.move(str(source), str(dest))
    return dest


def scan_file(
    path: Path,
    *,
    mode: str = "manual",
    forced_supplier: str | None = None,
    force_reprocess: bool = False,
    sample_limit: int | None = None,
    skip_rows: int = 0,
    move_to_processed: bool = True,
    file_index: int = 0,
    total_files: int = 0,
) -> dict[str, Any]:
    path = Path(path)
    if not path.is_file():
        raise FileNotFoundError(f"Fișier inexistent: {path}")

    supplier_hint = forced_supplier or path.parent.name
    if mode == "cron" and skip_rows <= 0:
        skip_rows = get_file_offset(supplier_hint, path.name)
    if skip_rows > 0:
        log(f"Reluare de la rândul CSV {skip_rows + 1}: {path.name}", "INFO")

    if is_processed(path, force=force_reprocess):
        log(f"Sărit (deja procesat): {path.name}", "SKIP")
        if mode == "cron" and file_index:
            add_step(f"Sărit {path.name} (deja procesat)", "warn")
        return {"skipped": True, "filename": path.name, "reason": "already_processed"}

    if mode == "cron" and file_index:
        set_file_phase(
            file_index, total_files, path.name, supplier_hint,
            "start", 5, f"[{file_index}/{total_files}] Start {supplier_hint}/{path.name}",
        )

    log(f"Start scan [{mode}]: {path} ")
    settings = load_settings()
    max_rows = sample_limit
    if max_rows is None and mode == "manual":
        max_rows = settings.get("max_rows_per_scan")

    if mode == "cron" and file_index:
        set_file_phase(
            file_index, total_files, path.name, supplier_hint,
            "parse", 20, f"[{file_index}/{total_files}] Parsare CSV…",
        )
    parse_result = parse_csv_file(
        path,
        forced_supplier=forced_supplier,
        max_rows=max_rows,
        skip_rows=skip_rows,
    )
    products = parse_result.get("products", [])
    if sample_limit:
        products = products[:sample_limit]

    has_more = bool(parse_result.get("has_more"))
    file_complete = not has_more and not parse_result.get("rows_truncated", False)

    if mode == "cron" and file_index:
        set_file_phase(
            file_index, total_files, path.name, parse_result["supplier"],
            "match", 55, f"[{file_index}/{total_files}] Matching {len(products)} produse…",
        )

    suppliers = load_suppliers()
    supplier_cfg = suppliers.get(parse_result["supplier"], {})
    use_demo_only = supplier_cfg.get("use_demo_catalog", False)
    settings = load_settings()
    if settings.get("use_tecdoc_only", True):
        matcher = ProductMatcher()
    else:
        catalog = build_catalog(force_demo=use_demo_only)
        matcher = ProductMatcher(catalog)
    results = matcher.match_batch(products)
    summary = matcher.summarize(results)

    qwp_write_stats = None
    qwp_map_stats = None
    if any(str((p.get("supplier_profile") or "")) == "autonet_qwp" for p in products):
        settings = load_settings()
        if settings.get("qwp_upsert_shop_map", True):
            try:
                from qwp_shop_map import get_qwp_shop_map

                qwp_map_stats = get_qwp_shop_map().upsert_from_match_inputs(results)
                log(f"QWP shop map: {qwp_map_stats}", "INFO")
            except Exception as exc:
                log(f"QWP shop map eșuat: {exc}", "WARN")
        if settings.get("qwp_write_tecdoc_cards", True):
            try:
                from qwp_tecdoc_writer import get_qwp_tecdoc_writer

                qwp_write_stats = get_qwp_tecdoc_writer().write_from_match_results(results)
                log(f"QWP TecDoc write→base: {qwp_write_stats}", "INFO")
            except Exception as exc:
                log(f"QWP TecDoc write eșuat: {exc}", "WARN")

    if mode == "cron" and file_index:
        add_step(
            f"[{file_index}/{total_files}] Match {path.name}: "
            f"exact={summary.get('exact', 0)} probable={summary.get('probable', 0)} "
            f"conflict={summary.get('conflict', 0)} fără_match={summary.get('no_match', 0)}",
            "info",
            phase="match",
            current_file=path.name,
            current_supplier=parse_result["supplier"],
            file_index=file_index,
            total_files=total_files,
        )
        set_file_phase(
            file_index, total_files, path.name, parse_result["supplier"],
            "report", 80, f"[{file_index}/{total_files}] Salvare raport…",
        )

    payload = build_report_payload(parse_result, results, summary, mode)
    report_paths = save_report(payload, Path(path.stem).name)

    archived_to = None
    if not file_complete:
        # IMPORTANT: indiferent de `mode`, un fișier NU e complet scanat (are `has_more`
        # sau rânduri trunchiate) nu trebuie NICIODATĂ arhivat/marcat ca procesat — altfel
        # fișierul e scos din supplier_feeds cu doar o fracțiune din rânduri citite, iar
        # restul produselor se pierd silențios (nu mai apar în nicio rulare viitoare).
        # Anterior condiția era `mode == "cron" and not file_complete`, deci scanările
        # "manual" (ex. butonul Scanează/Doar matching cu eșantion limitat) arhivau
        # fișierul oricum, provocând exact acest scenariu de pierdere de date.
        if mode == "cron":
            next_offset = skip_rows + int(parse_result.get("rows_total") or 0)
            set_file_offset(parse_result["supplier"], path.name, next_offset)
            log(
                f"Batch parțial {path.name}: {len(products)} produse, offset următor={next_offset}",
                "INFO",
            )
            if file_index:
                add_step(
                    f"[{file_index}/{total_files}] Batch parțial {path.name} — "
                    f"{len(products)} produse (continuă la următoarea rulare)",
                    "info",
                )
        else:
            log(
                f"Scanare manuală parțială {path.name}: {len(products)} produse citite din "
                f"{parse_result.get('rows_total', '?')} — fișierul rămâne în supplier_feeds "
                "(nearhivat, nemarcat procesat).",
                "INFO",
            )
    else:
        mark_processed(path, report_paths["id"], parse_result["supplier"])
        if mode == "cron":
            clear_file_offset(parse_result["supplier"], path.name)
        if move_to_processed:
            if mode == "cron" and file_index:
                set_file_phase(
                    file_index, total_files, path.name, parse_result["supplier"],
                    "archive", 92, f"[{file_index}/{total_files}] Arhivare (dacă e cazul)…",
                )
            archived = archive_file(path, parse_result["supplier"])
            archived_to = str(archived) if archived else None

    log(
        f"Finalizat {path.name}: total={summary['total']} "
        f"exact={summary.get('exact', 0)} probable={summary.get('probable', 0)} "
        f"no_match={summary.get('no_match', 0)} conflict={summary.get('conflict', 0)}"
        + ("" if file_complete else " [batch parțial — fișier rămâne activ]")
    )

    if mode == "cron" and file_index:
        # NU marcăm încă 100% aici — fișierul e doar SCANAT; migrarea produselor în
        # coada de import (staging, per produs) se face imediat după, în cron_watcher,
        # care marchează finalul real la 100% după ce migrarea s-a terminat efectiv.
        set_file_phase(
            file_index, total_files, path.name, parse_result["supplier"],
            "scanned", 88,
            f"[{file_index}/{total_files}] Scanare {path.name} finalizată — total {summary['total']}, "
            f"exact {summary.get('exact', 0)}, conflict {summary.get('conflict', 0)}"
            + ("" if file_complete else " · continuă la următoarea rulare")
            + " · migrez în coada de import…",
        )

    result = {
        "success": True,
        "skipped": False,
        "mode": mode,
        "partial": not file_complete,
        "file_offset": skip_rows,
        "next_offset": skip_rows + len(products) if not file_complete else None,
        "parse": {
            "filename": parse_result["filename"],
            "supplier": parse_result["supplier"],
            "supplier_label": parse_result["supplier_label"],
            "rows_parsed": parse_result["rows_parsed"],
            "rows_total": parse_result.get("rows_total", 0),
            "has_more": has_more,
        },
        "summary": summary,
        "report": report_paths,
        "archived_to": archived_to,
        "qwp_write_stats": qwp_write_stats,
        "qwp_map_stats": qwp_map_stats,
    }
    if mode != "cron":
        result["payload"] = payload
    return result


def scan_uploads_temp(
    *,
    force_reprocess: bool = False,
    sample_limit: int | None = None,
) -> list[dict]:
    UPLOADS_TEMP.mkdir(parents=True, exist_ok=True)
    results = []
    for path in sorted(UPLOADS_TEMP.glob("*.csv")):
        results.append(
            scan_file(
                path,
                mode="manual",
                force_reprocess=force_reprocess,
                sample_limit=sample_limit,
            )
        )
    for path in sorted(UPLOADS_TEMP.glob("*.txt")):
        results.append(
            scan_file(
                path,
                mode="manual",
                force_reprocess=force_reprocess,
                sample_limit=sample_limit,
            )
        )
    return results


def scan_paths(
    paths: list[str | Path],
    *,
    forced_supplier: str | None = None,
    force_reprocess: bool = False,
    sample_limit: int | None = None,
    skip_rows: int = 0,
    move_to_processed: bool = True,
) -> list[dict]:
    out = []
    for raw in paths:
        out.append(
            scan_file(
                Path(raw),
                mode="manual",
                forced_supplier=forced_supplier,
                force_reprocess=force_reprocess,
                sample_limit=sample_limit,
                skip_rows=skip_rows,
                move_to_processed=move_to_processed,
            )
        )
    return out
