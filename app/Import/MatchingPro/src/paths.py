"""Căi proiect — motor MatchingPro integrat în app/Import."""

from __future__ import annotations

import json
import os
from pathlib import Path

IMPORT_ROOT = Path(__file__).resolve().parent.parent
IMPORT_APP_ROOT = IMPORT_ROOT.parent
SITE_ROOT = IMPORT_ROOT.parent.parent.parent

_env_data_root = (os.environ.get("BESOIU_IMPORT_DATA_ROOT") or "").strip()
if _env_data_root:
    PROJECT_ROOT = Path(_env_data_root)
else:
    _legacy = Path("F:/laragon/www/besoiupieseimport")
    PROJECT_ROOT = _legacy if _legacy.is_dir() else IMPORT_APP_ROOT

_admin_env = (os.environ.get("BESOIU_ADMIN") or "").strip()
ADMIN_ROOT = Path(_admin_env) if _admin_env else SITE_ROOT / "admin"

# Loc unic CSV furnizori — aliniat cu modulul Furnizori (admin/storage/supplier_feeds)
SUPPLIERS_DIR = ADMIN_ROOT / "storage" / "supplier_feeds"
SUPPLIERS_LEGACY_DIR = IMPORT_ROOT / "suppliers"

CONFIG_DIR = IMPORT_ROOT / "config"
UPLOADS_DIR = IMPORT_ROOT / "uploads"
UPLOADS_TEMP = UPLOADS_DIR / "temp"
PROCESSED_DIR = IMPORT_ROOT / "processed"
LOGS_DIR = IMPORT_ROOT / "logs"
REPORTS_DIR = IMPORT_ROOT / "reports"
STATE_DIR = IMPORT_ROOT / "state"

MATC_DIR = PROJECT_ROOT / "Fisierile Matc"
POZE_DIR = PROJECT_ROOT / "Poze"

_prelucrare_local = IMPORT_APP_ROOT / "Prelucrare" / "api"
_prelucrare_legacy = PROJECT_ROOT / "Prelucrare fisiere bovsoft-base" / "api"
AUX_DIR = (
    _prelucrare_local.parent
    if _prelucrare_local.is_dir()
    else (_prelucrare_legacy.parent if _prelucrare_legacy.is_dir() else PROJECT_ROOT / "Prelucrare fisiere bovsoft-base")
)
PRICE_CACHE = AUX_DIR / "api" / "cache" / "price-index.sqlite"


def load_json(path: Path) -> dict | list:
    with path.open(encoding="utf-8") as fh:
        return json.load(fh)


def load_settings() -> dict:
    return load_json(CONFIG_DIR / "settings.json")


def load_suppliers() -> dict:
    return load_json(CONFIG_DIR / "suppliers.json")


def load_mapping_overrides() -> dict:
    path = CONFIG_DIR / "mapping_overrides.json"
    if not path.is_file():
        return {}
    data = load_json(path)
    return data if isinstance(data, dict) else {}


def merge_supplier_config(supplier_key: str, base_cfg: dict, override: dict | None) -> dict:
    cfg = dict(base_cfg)
    if not override:
        return cfg
    cfg["_has_mapping_override"] = True
    if "columns" in override and isinstance(override["columns"], dict):
        merged = dict(cfg.get("columns", {}))
        merged.update(override["columns"])
        cfg["columns"] = merged
    for key in ("sku_map", "extra_codes", "brand_code_trim", "price_includes_vat", "use_demo_catalog", "match_code_only"):
        if key in override:
            cfg[key] = override[key]
    if "positional_fallback" in override:
        cfg["positional_fallback"] = override["positional_fallback"]
    return cfg
