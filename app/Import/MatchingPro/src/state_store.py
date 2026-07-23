"""Stare procesare — idempotență pe mtime+size (rapid, fără hash fișier mare)."""

from __future__ import annotations

import json
from datetime import datetime
from pathlib import Path

from paths import STATE_DIR


def file_signature(path: Path) -> str:
    stat = path.stat()
    return f"{stat.st_size}:{int(stat.st_mtime)}:{path.name}"


def state_path() -> Path:
    STATE_DIR.mkdir(parents=True, exist_ok=True)
    return STATE_DIR / "processed_files.json"


def load_state() -> dict:
    path = state_path()
    if not path.is_file():
        return {"files": {}}
    try:
        state = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError:
        return {"files": {}}
    if not isinstance(state, dict):
        return {"files": {}}
    files = state.get("files")
    if not isinstance(files, dict):
        state["files"] = {}
    return state


def save_state(state: dict) -> None:
    state_path().write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")


def is_processed(path: Path, force: bool = False) -> bool:
    if force:
        return False
    if not path.is_file():
        return True
    state = load_state()
    key = str(path.resolve())
    entry = state.get("files", {}).get(key)
    if not entry:
        return False
    return entry.get("signature") == file_signature(path)


def mark_processed(path: Path, report_id: str, supplier: str) -> None:
    state = load_state()
    key = str(path.resolve())
    state.setdefault("files", {})[key] = {
        "signature": file_signature(path),
        "processed_at": datetime.now().isoformat(timespec="seconds"),
        "report_id": report_id,
        "supplier": supplier,
        "filename": path.name,
    }
    save_state(state)
