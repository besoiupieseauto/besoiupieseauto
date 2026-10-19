# -*- coding: utf-8 -*-
"""Verifică sau reînnoiește sesiunea Intercars e-Cat. Fără secrete pe stdout."""
from __future__ import annotations

import importlib.util
import json
import shutil
import subprocess
import sys
import time
from pathlib import Path

MAPAREA = Path(r"D:\laragon\www\maparea\intercars\intercars_scan_coduri.py")
HELPER = Path(r"D:\laragon\www\scraper\intercars_login_helper.py")
SCRAPER_CREDS = Path(r"D:\laragon\www\scraper\state\ic_creds.json")
ADMIN_SCRIPTS_CREDS = Path(__file__).resolve().parent / "ic_creds.json"


def load_ic():
    spec = importlib.util.spec_from_file_location("ic_mod", str(MAPAREA))
    if spec is None or spec.loader is None:
        raise RuntimeError("Nu pot încărca intercars_scan_coduri.py")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def copy_creds(src: Path) -> list[str]:
    copied: list[str] = []
    if not src.is_file():
        return copied
    for dest in (SCRAPER_CREDS, ADMIN_SCRIPTS_CREDS):
        dest.parent.mkdir(parents=True, exist_ok=True)
        if dest.resolve() == src.resolve():
            continue
        shutil.copy2(src, dest)
        copied.append(str(dest))
    return copied


def main() -> int:
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    ic = load_ic()
    before = ic.load_creds()
    if ic.creds_valid(before):
        copied = copy_creds(ic.CREDS_FILE)
        age = int(time.time()) - int(before.get("saved_at") or 0)
        print(json.dumps({
            "ok": True,
            "refreshed": False,
            "age_sec": max(0, age),
            "copied": copied,
        }, ensure_ascii=False))
        return 0

    if not HELPER.is_file():
        print(json.dumps({
            "ok": False,
            "refreshed": False,
            "error": "Lipsește intercars_login_helper.py",
        }, ensure_ascii=False))
        return 1

    code = subprocess.call([sys.executable, str(HELPER)])
    creds = ic.load_creds()
    ok = ic.creds_valid(creds)
    copied = copy_creds(ic.CREDS_FILE) if ok else []
    print(json.dumps({
        "ok": ok,
        "refreshed": True,
        "helper_code": code,
        "copied": copied,
        "error": "" if ok else "Sesiunea Intercars nu a putut fi reînnoită.",
    }, ensure_ascii=False))
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
