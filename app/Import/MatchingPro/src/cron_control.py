"""Stare cron_control.json — mod, batch, offset per fișier (partajat PHP + Python)."""

from __future__ import annotations

import json
from pathlib import Path

from paths import IMPORT_ROOT


def control_path() -> Path:
    return IMPORT_ROOT / "state" / "cron_control.json"


def read_control() -> dict:
    path = control_path()
    if not path.is_file():
        return {"mode": "running", "batch_size": 500, "suppliers": {}, "file_offsets": {}}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError:
        return {"mode": "running", "batch_size": 500, "suppliers": {}, "file_offsets": {}}
    if not isinstance(data, dict):
        return {"mode": "running", "batch_size": 500, "suppliers": {}, "file_offsets": {}}
    if not isinstance(data.get("file_offsets"), dict):
        data["file_offsets"] = {}
    return data


def write_control(patch: dict) -> dict:
    state = {**read_control(), **patch}
    path = control_path()
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")
    return state


def batch_size(default: int = 500) -> int:
    try:
        size = int(read_control().get("batch_size") or default)
    except (TypeError, ValueError):
        size = default
    return max(1, min(5000, size if size > 0 else default))


def offset_key(supplier: str, filename: str) -> str:
    return f"{supplier}/{filename}"


def get_file_offset(supplier: str, filename: str) -> int:
    offsets = read_control().get("file_offsets") or {}
    if not isinstance(offsets, dict):
        return 0
    try:
        return max(0, int(offsets.get(offset_key(supplier, filename), 0) or 0))
    except (TypeError, ValueError):
        return 0


def set_file_offset(supplier: str, filename: str, offset: int) -> None:
    state = read_control()
    offsets = state.get("file_offsets") if isinstance(state.get("file_offsets"), dict) else {}
    offsets[offset_key(supplier, filename)] = max(0, int(offset))
    state["file_offsets"] = offsets
    write_control(state)


def clear_file_offset(supplier: str, filename: str) -> None:
    state = read_control()
    offsets = state.get("file_offsets") if isinstance(state.get("file_offsets"), dict) else {}
    offsets.pop(offset_key(supplier, filename), None)
    state["file_offsets"] = offsets
    write_control(state)
