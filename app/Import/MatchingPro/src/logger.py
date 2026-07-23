"""Logging pentru rulări import — doar fișier (fără stderr/stdout)."""

from __future__ import annotations

from datetime import datetime
from pathlib import Path

from paths import LOGS_DIR


def log(message: str, level: str = "INFO") -> None:
    LOGS_DIR.mkdir(parents=True, exist_ok=True)
    day = datetime.now().strftime("%Y-%m-%d")
    line = f"[{datetime.now().isoformat(timespec='seconds')}] [{level}] {message}\n"
    path = LOGS_DIR / f"import_{day}.log"
    with path.open("a", encoding="utf-8") as fh:
        fh.write(line)
