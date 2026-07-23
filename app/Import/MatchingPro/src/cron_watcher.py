"""Cron watcher — verifică foldere furnizori pentru fișiere noi/modificate."""

from __future__ import annotations

import argparse
import json
import os
import subprocess
from datetime import datetime, timezone
from pathlib import Path

from cron_control import batch_size, read_control
from logger import log
from paths import ADMIN_ROOT, IMPORT_ROOT, SITE_ROOT, SUPPLIERS_DIR
from php_cli import php_cli_cmd, probe_php_cli, resolve_php_cli
from progress import add_step, fail_cron, finish_cron, set_file_phase, start_cron
from scanner import scan_file
from state_store import is_processed


def control_path() -> Path:
    return IMPORT_ROOT / "state" / "cron_control.json"


def assert_runnable() -> None:
    mode = str(read_control().get("mode") or "running")
    if mode == "stopped":
        raise RuntimeError("Cron oprit definitiv.")
    if mode == "paused":
        raise RuntimeError("Cron pe pauză.")


def supplier_due(supplier_key: str, interval_minutes: int) -> bool:
    if interval_minutes <= 0:
        return True
    suppliers = read_control().get("suppliers") or {}
    entry = suppliers.get(supplier_key) if isinstance(suppliers, dict) else None
    last = ""
    if isinstance(entry, dict):
        last = str(entry.get("last_run_at") or "")
    if not last:
        return True
    try:
        last_dt = datetime.fromisoformat(last.replace("Z", "+00:00"))
    except ValueError:
        return True
    if last_dt.tzinfo is None:
        last_dt = last_dt.replace(tzinfo=timezone.utc)
    delta = datetime.now(timezone.utc) - last_dt.astimezone(timezone.utc)
    return delta.total_seconds() >= interval_minutes * 60


def mark_supplier_run(supplier_key: str) -> None:
    path = control_path()
    state = read_control()
    suppliers = state.get("suppliers") if isinstance(state.get("suppliers"), dict) else {}
    suppliers[supplier_key] = {"last_run_at": datetime.now().isoformat(timespec="seconds")}
    state["suppliers"] = suppliers
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")


def stage_subprocess_env() -> dict[str, str]:
    env = os.environ.copy()
    env.setdefault("BESOIU_ROOT", str(SITE_ROOT))
    env.setdefault("BESOIU_ADMIN", str(ADMIN_ROOT))
    # PHP scrie JSON UTF-8; pe Windows subprocess text=True folosește cp125x altfel → crash.
    env["PYTHONIOENCODING"] = "utf-8"
    env["PYTHONUTF8"] = "1"
    return env


def stage_report_batch(
    report_id: str,
    *,
    file_index: int = 0,
    total_files: int = 0,
    supplier: str = "",
    filename: str = "",
) -> None:
    if not report_id:
        return
    script = IMPORT_ROOT / "tools" / "stage_cron_batch.php"
    if not script.is_file():
        log(f"Lipsește stage_cron_batch.php — skip staging pentru {report_id}", "WARN")
        return
    try:
        php_bin = resolve_php_cli()
    except FileNotFoundError as exc:
        log(f"Staging batch — {exc}", "WARN")
        return
    prefix = f"[{file_index}/{total_files}] " if file_index and total_files else ""
    label = f"{supplier}/{filename}" if supplier and filename else report_id
    add_step(
        f"{prefix}Formare carduri + migrare coadă import ({label})…",
        "info",
        phase="staging",
    )
    try:
        cmd = php_cli_cmd() + [str(script), f"--report={report_id}"]
        env = stage_subprocess_env()
        for attempt in range(1, 51):
            proc = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                timeout=600,
                check=False,
                cwd=str(SITE_ROOT),
                env=env,
            )
            if proc.returncode != 0:
                log(
                    f"Staging batch eșuat ({report_id}, încercare {attempt}, php={php_bin}): "
                    f"{proc.stderr or proc.stdout}",
                    "WARN",
                )
                break
            try:
                payload = json.loads(proc.stdout.strip() or "{}")
            except json.JSONDecodeError:
                log(f"Staging răspuns invalid ({report_id}): {proc.stdout}", "WARN")
                break
            queued = int(payload.get("queued") or 0)
            complete = bool(payload.get("complete"))
            stats = payload.get("stats") if isinstance(payload.get("stats"), dict) else {}
            log(
                f"Staging batch OK ({report_id}): queued={queued} "
                f"staged={payload.get('staged_count')}/{payload.get('total_products')} "
                f"complete={complete} stats={stats}",
                "INFO",
            )
            add_step(
                f"{prefix}Coadă import: +{queued} produse · "
                f"match={stats.get('matched', 0)} · "
                f"vitrină={stats.get('showcase_candidates', 0)} · "
                f"standard={stats.get('standard_candidates', 0)} · "
                f"fără match={stats.get('skipped_no_match', 0)} · "
                f"fără imagine={stats.get('skipped_no_image', 0)}",
                "ok" if queued > 0 else "warn",
                phase="staging",
            )
            if complete or queued <= 0:
                break
    except Exception as exc:
        log(f"Staging batch excepție: {exc}", "WARN")


def supplier_interval_minutes(supplier_key: str) -> int:
    path = IMPORT_ROOT / "state" / "supplier_intervals.json"
    if not path.is_file():
        return 0
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError:
        return 0
    if not isinstance(data, dict):
        return 0
    return int(data.get(supplier_key) or data.get(supplier_key.lower()) or 0)


def collect_pending_files(force: bool = False) -> list[tuple[Path, str]]:
    pending: list[tuple[Path, str]] = []
    if not SUPPLIERS_DIR.is_dir():
        return pending

    for supplier_dir in sorted(SUPPLIERS_DIR.iterdir()):
        if not supplier_dir.is_dir():
            continue
        supplier_key = supplier_dir.name
        for path in sorted(supplier_dir.iterdir()):
            if path.suffix.lower() not in {".csv", ".txt"}:
                continue
            if path.name.startswith("."):
                continue
            if not path.is_file():
                continue
            if not force and is_processed(path):
                continue
            pending.append((path, supplier_key))
    return pending


def run_watcher(
    force: bool = False,
    sample_limit: int | None = None,
    total_limit: int | None = None,
) -> dict:
    assert_runnable()
    php_ok, php_info = probe_php_cli()
    if php_ok:
        log(f"PHP CLI: {php_info}", "INFO")
    else:
        log(f"PHP CLI indisponibil — staging va eșua: {php_info}", "WARN")
    per_file_default = sample_limit if sample_limit is not None else batch_size()
    # IMPORTANT: `total_limit` (folosit la „Testează cron”) înseamnă produse CU MATCH
    # găsite, NU rânduri citite din CSV. Un fișier poate avea 10 rânduri citite și 0
    # match-uri — vechea logică se opriea acolo, "consumând" limita pe rânduri fără
    # match, în loc să continue căutarea. Acum numărăm doar produsele cu status
    # exact/probable/conflict și continuăm (mai multe treceri pe același fișier,
    # apoi pe următoarele) până găsim `match_target` produse cu match sau se epuizează
    # toate fișierele.
    match_target = total_limit if total_limit is not None and total_limit > 0 else None
    matched_found = 0
    MAX_PASSES_PER_FILE = 40  # plasă de siguranță — evită citirea la infinit a unui fișier fără match-uri
    pending = collect_pending_files(force=force)
    total = len(pending)
    start_cron(total)
    add_step(
        f"Scanare admin/storage/supplier_feeds/ — {total} fișier(e) în coadă, "
        + (
            f"caut {match_target} produse CU MATCH (agregat pe toate fișierele)"
            if match_target is not None
            else f"batch {per_file_default} produse/fișier"
        ),
        "info",
        phase="scan",
        percent=2,
    )

    total_found = 0
    results = []
    errors = []
    for idx, (path, supplier_key) in enumerate(pending, start=1):
        assert_runnable()
        if match_target is not None and matched_found >= match_target:
            msg = f"Limită de {match_target} produse CU MATCH atinsă — opresc scanarea (test)"
            log(msg, "INFO")
            add_step(f"[{idx}/{total}] {msg}", "ok")
            break
        interval = supplier_interval_minutes(supplier_key)
        if not force and not supplier_due(supplier_key, interval):
            msg = f"Sărit {supplier_key}/{path.name} — interval furnizor neexpirat"
            log(msg, "SKIP")
            add_step(f"[{idx}/{total}] {msg}", "warn")
            continue
        if not path.is_file():
            msg = f"Sărit {path.name} — fișier inexistent (probabil arhivat/deplasat)"
            log(msg, "WARN")
            add_step(f"[{idx}/{total}] {msg}", "warn")
            errors.append({"file": str(path), "error": "file_not_found"})
            continue

        file_scanned = 0
        file_matched = 0
        passes = 0
        last_result: dict | None = None
        try:
            while True:
                passes += 1
                result = scan_file(
                    path,
                    mode="cron",
                    forced_supplier=supplier_key if supplier_key != "furnizor_demo" else "furnizor_demo",
                    force_reprocess=force,
                    sample_limit=per_file_default,
                    move_to_processed=supplier_key != "furnizor_demo",
                    file_index=idx,
                    total_files=total,
                )
                last_result = result
                mark_supplier_run(supplier_key)

                if result.get("skipped"):
                    break  # deja procesat (hash cunoscut) — nimic de căutat aici, trecem la următorul fișier

                summary = result.get("summary") or {}
                found_here = int(summary.get("total") or 0)
                matched_here = (
                    int(summary.get("exact", 0))
                    + int(summary.get("probable", 0))
                    + int(summary.get("conflict", 0))
                )
                total_found += found_here
                file_scanned += found_here
                file_matched += matched_here
                if match_target is not None:
                    matched_found += matched_here

                report = result.get("report") if isinstance(result, dict) else None
                report_id = report.get("id") if isinstance(report, dict) else None
                if report_id:
                    stage_report_batch(
                        str(report_id),
                        file_index=idx,
                        total_files=total,
                        supplier=supplier_key,
                        filename=path.name,
                    )

                if match_target is None:
                    break  # comportament normal (cron real, fără limită) — o singură trecere/fișier/rulare
                if matched_found >= match_target:
                    break
                if not result.get("partial"):
                    break  # fișierul a fost citit complet — trecem la următorul
                if passes >= MAX_PASSES_PER_FILE:
                    add_step(
                        f"[{idx}/{total}] Prag de siguranță atins ({MAX_PASSES_PER_FILE} treceri, "
                        f"{file_scanned} rânduri citite, {file_matched} cu match) pe {path.name} — "
                        "trec la fișierul următor",
                        "warn",
                    )
                    break

            results.append(last_result)
            if match_target is not None and file_scanned > 0:
                add_step(
                    f"[{idx}/{total}] {path.name} — {file_scanned} rânduri citite ({passes} trecere(i)), "
                    f"{file_matched} produse CU MATCH găsite (total test: {matched_found}/{match_target})",
                    "ok" if file_matched > 0 else "warn",
                )
            # Abia acum, DUPĂ ce migrarea în coada de import s-a terminat efectiv
            # pentru acest fișier, marcăm 100% pe fișierul curent — altfel bara de
            # progres ajungea la 100% cât încă rulau pașii de matching/migrare.
            set_file_phase(
                idx, total, path.name, supplier_key,
                "staged", 100,
                f"[{idx}/{total}] Fișier complet — {path.name} migrat în coada de import",
            )
        except FileNotFoundError as exc:
            msg = f"Fișier dispărut în timpul procesării: {path.name}"
            log(msg, "WARN")
            add_step(f"[{idx}/{total}] {msg}", "warn")
            errors.append({"file": str(path), "error": str(exc)})
        except Exception as exc:
            log(f"Eroare la {path.name}: {exc}", "ERROR")
            add_step(f"Eroare la {path.name}: {exc}", "error")
            errors.append({"file": str(path), "error": str(exc)})

    summary = {
        "pending": total,
        "processed": len([r for r in results if r.get("success")]),
        "partial": len([r for r in results if r.get("partial")]),
        "skipped": len([r for r in results if r.get("skipped")]),
        "batch_size": per_file_default,
        "total_limit": total_limit,
        "total_found": total_found,
        "matched_found": matched_found if match_target is not None else None,
        "errors": errors,
        "results": [
            {
                "success": r.get("success"),
                "skipped": r.get("skipped"),
                "partial": r.get("partial"),
                "mode": r.get("mode"),
                "parse": r.get("parse"),
                "summary": r.get("summary"),
                "report": r.get("report"),
                "archived_to": r.get("archived_to"),
            }
            for r in results
        ],
    }
    finish_cron(summary)
    return summary


def main() -> None:
    parser = argparse.ArgumentParser(description="Cron watcher import furnizori")
    parser.add_argument("--force", action="store_true", help="Reprocesează chiar dacă hash-ul e cunoscut")
    parser.add_argument(
        "--sample",
        type=int,
        default=None,
        help="Override produse/fișier (demo); implicit = batch_size din cron_control.json",
    )
    parser.add_argument(
        "--total-sample",
        type=int,
        default=None,
        help="Limitează la N produse TOTAL (agregat pe toate fișierele)",
    )
    args = parser.parse_args()
    try:
        summary = run_watcher(
            force=args.force,
            sample_limit=args.sample,
            total_limit=args.total_sample,
        )
        log(
            f"Cron finalizat: pending={summary['pending']} processed={summary['processed']} "
            f"partial={summary.get('partial', 0)} skipped={summary['skipped']} "
            f"errors={len(summary['errors'])} batch={summary.get('batch_size')}"
        )
        print(json.dumps(summary, ensure_ascii=False, indent=2))
    except Exception as exc:
        fail_cron(str(exc))
        raise


if __name__ == "__main__":
    main()
