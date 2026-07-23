"""Rezolvă PHP CLI pe Windows/Linux — Laragon, PATH, env BESOIU_PHP_CLI."""

from __future__ import annotations

import glob
import os
import shutil
import subprocess
from pathlib import Path


def resolve_php_cli() -> str:
    """Returnează calea absolută către php.exe / php (CLI)."""
    env_bin = (os.environ.get("BESOIU_PHP_CLI") or os.environ.get("PHP_CLI") or "").strip()
    if env_bin:
        path = Path(env_bin)
        if path.is_file():
            return str(path.resolve())

    for name in ("php", "php.exe"):
        found = shutil.which(name)
        if found:
            low = found.lower()
            if "httpd" not in low and "apache" not in low:
                return found

    for laragon in (
        "D:/laragon", "F:/laragon", "E:/laragon", "C:/laragon",
        "d:/laragon", "f:/laragon", "e:/laragon",
    ):
        pattern = laragon + "/bin/php/php-*/php.exe"
        matches = sorted(glob.glob(pattern), reverse=True)
        for candidate in matches:
            if Path(candidate).is_file():
                return candidate

    raise FileNotFoundError(
        "PHP CLI negăsit. Setează BESOIU_PHP_CLI sau adaugă php în PATH (Laragon)."
    )


def php_cli_cmd() -> list[str]:
    """Argumente subprocess: [php.exe] sau [php] — fără script."""
    return [resolve_php_cli()]


def probe_php_cli(timeout_sec: int = 8) -> tuple[bool, str]:
    """Verifică dacă PHP CLI răspunde la --version."""
    try:
        proc = subprocess.run(
            php_cli_cmd() + ["--version"],
            capture_output=True,
            text=True,
            timeout=timeout_sec,
            check=False,
        )
        if proc.returncode != 0:
            return False, (proc.stderr or proc.stdout or "exit " + str(proc.returncode)).strip()
        first = (proc.stdout or "").splitlines()[0] if proc.stdout else "OK"
        return True, first.strip()
    except Exception as exc:
        return False, str(exc)
