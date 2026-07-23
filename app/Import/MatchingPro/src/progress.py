"""Progres cron — vizibil în UI (progress bar + pași)."""

from __future__ import annotations

import json
from datetime import datetime
from typing import Any

from paths import STATE_DIR

PROGRESS_FILE = STATE_DIR / "cron_progress.json"
MAX_STEPS = 100
MAX_PROGRESS_BYTES = 512_000


def _now() -> str:
    return datetime.now().isoformat(timespec="seconds")


def default_progress() -> dict[str, Any]:
    return {
        "status": "idle",
        "phase": "",
        "percent": 0,
        "message": "Inactiv — aștept următoarea rulare cron",
        "current_file": "",
        "current_supplier": "",
        "file_index": 0,
        "total_files": 0,
        "started_at": None,
        "finished_at": None,
        "steps": [],
        "summary": {},
    }


def sanitize_summary(summary: dict[str, Any] | None) -> dict[str, Any]:
    if not isinstance(summary, dict):
        return {}

    clean: dict[str, Any] = {
        "pending": summary.get("pending", 0),
        "processed": summary.get("processed", 0),
        "partial": summary.get("partial", 0),
        "skipped": summary.get("skipped", 0),
        "errors": summary.get("errors") or [],
    }

    digests = []
    for item in summary.get("results") or []:
        if not isinstance(item, dict):
            continue
        digests.append(
            {
                "success": item.get("success"),
                "skipped": item.get("skipped"),
                "mode": item.get("mode"),
                "parse": item.get("parse"),
                "summary": item.get("summary"),
                "report": item.get("report"),
                "archived_to": item.get("archived_to"),
            }
        )
    if digests:
        clean["results"] = digests
    return clean


def normalize_progress(data: dict[str, Any]) -> dict[str, Any]:
    steps = data.get("steps") or []
    if isinstance(steps, list):
        data["steps"] = steps[-MAX_STEPS:]
    else:
        data["steps"] = []

    summary = data.get("summary")
    if isinstance(summary, dict):
        data["summary"] = sanitize_summary(summary)
    else:
        data["summary"] = {}
    return data


def _compact_oversized_progress_file(size: int) -> dict[str, Any]:
    from logger import log

    backup = PROGRESS_FILE.with_name(PROGRESS_FILE.name + ".bloated")
    try:
        if backup.exists():
            backup.unlink()
        PROGRESS_FILE.replace(backup)
    except OSError:
        try:
            PROGRESS_FILE.unlink(missing_ok=True)
        except OSError:
            pass

    log(f"Reset progres cron — fișier prea mare ({size} bytes)", "WARN")
    data = default_progress()
    data["message"] = "Progres resetat — rularea anterioară a generat un fișier prea mare"
    save_progress(data)
    return data


def load_progress() -> dict[str, Any]:
    if not PROGRESS_FILE.is_file():
        return default_progress()
    try:
        size = PROGRESS_FILE.stat().st_size
        if size > MAX_PROGRESS_BYTES:
            return _compact_oversized_progress_file(size)
        data = json.loads(PROGRESS_FILE.read_text(encoding="utf-8"))
        if isinstance(data, dict):
            return normalize_progress(data)
    except (json.JSONDecodeError, OSError):
        pass
    return default_progress()


def save_progress(data: dict[str, Any]) -> None:
    STATE_DIR.mkdir(parents=True, exist_ok=True)
    normalized = normalize_progress(dict(data))
    PROGRESS_FILE.write_text(json.dumps(normalized, ensure_ascii=False, indent=2), encoding="utf-8")


def write_progress(**kwargs: Any) -> dict[str, Any]:
    data = load_progress()
    data.update(kwargs)
    save_progress(data)
    return data


def add_step(message: str, level: str = "info", **extra: Any) -> None:
    from logger import log

    log_level = "ERROR" if level == "error" else ("WARN" if level == "warn" else "INFO")
    log(message, log_level)

    data = load_progress()
    steps = list(data.get("steps") or [])
    step = {"at": _now(), "message": message, "level": level}
    step.update(extra)
    steps.append(step)
    data["steps"] = steps[-MAX_STEPS:]
    data["message"] = message
    data.update({k: v for k, v in extra.items() if k in ("phase", "percent", "current_file", "current_supplier", "file_index", "total_files")})
    save_progress(data)


def start_cron(total_files: int) -> None:
    write_progress(
        status="running",
        phase="init",
        percent=0,
        message="Pornire cron watcher…",
        current_file="",
        current_supplier="",
        file_index=0,
        total_files=total_files,
        started_at=_now(),
        finished_at=None,
        steps=[],
        summary={},
    )
    add_step(f"Cron pornit — {total_files} fișier(e) de procesat", "info", phase="init", percent=0)


def finish_cron(summary: dict[str, Any]) -> None:
    processed = summary.get("processed", 0)
    pending = summary.get("pending", 0)
    errors = len(summary.get("errors") or [])
    write_progress(
        status="done",
        phase="done",
        percent=100,
        message=f"Finalizat: {processed}/{pending} fișiere procesate, {errors} erori",
        finished_at=_now(),
        summary=sanitize_summary(summary),
    )
    add_step(
        f"Cron finalizat — fișiere procesate: {processed}/{pending}, "
        f"sărite: {summary.get('skipped', 0)}, erori: {errors} "
        "(produse/matching: vezi «Rezultat migrare coadă import» mai jos)",
        "ok" if errors == 0 else "warn",
        phase="done",
        percent=100,
    )


def fail_cron(message: str) -> None:
    write_progress(
        status="error",
        phase="error",
        message=message,
        finished_at=_now(),
    )
    add_step(message, "error", phase="error")


def set_file_phase(
    file_index: int,
    total_files: int,
    filename: str,
    supplier: str,
    phase: str,
    phase_pct: int,
    message: str,
) -> None:
    total = max(total_files, 1)
    file_weight = 100 / total
    base = (file_index - 1) * file_weight
    percent = min(99, int(base + file_weight * (phase_pct / 100)))
    write_progress(
        file_index=file_index,
        total_files=total_files,
        current_file=filename,
        current_supplier=supplier,
        phase=phase,
        percent=percent,
        message=message,
    )
    add_step(
        message,
        "info",
        phase=phase,
        percent=percent,
        current_file=filename,
        current_supplier=supplier,
        file_index=file_index,
        total_files=total_files,
    )


def reset_idle() -> None:
    save_progress(default_progress())
