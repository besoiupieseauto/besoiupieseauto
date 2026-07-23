"""Configurare scraper — variabile mediu, pool-uri UA, retry."""

from __future__ import annotations

import os
from dataclasses import dataclass
from typing import Any


def env_bool(key: str, default: bool = False) -> bool:
    raw = (os.environ.get(key) or "").strip().lower()
    if raw in ("1", "true", "yes", "on"):
        return True
    if raw in ("0", "false", "no", "off"):
        return False
    return default


def env_float(key: str, default: float) -> float:
    try:
        return float(os.environ.get(key, default))
    except (TypeError, ValueError):
        return default


def env_int(key: str, default: int) -> int:
    try:
        return int(os.environ.get(key, default))
    except (TypeError, ValueError):
        return default


@dataclass(frozen=True)
class RetryPolicy:
    max_attempts: int = 4
    base_delay_sec: float = 1.5
    max_delay_sec: float = 45.0
    retry_statuses: tuple[int, ...] = (403, 429, 502, 503, 504)


@dataclass(frozen=True)
class JitterPolicy:
    min_sec: float = 2.0
    max_sec: float = 5.0
    enabled: bool = True


@dataclass(frozen=True)
class ScraperSettings:
    proxy: str | None
    transport: str
    timeout_sec: float
    http2: bool
    retry: RetryPolicy
    jitter: JitterPolicy
    default_referer: str | None
    accept_language: str

    @classmethod
    def from_env(cls) -> ScraperSettings:
        return cls(
            proxy=_normalize_proxy(os.environ.get("SCRAPER_PROXY") or os.environ.get("HTTP_PROXY")),
            transport=(os.environ.get("SCRAPER_TRANSPORT") or "curl_cffi").strip().lower(),
            timeout_sec=env_float("SCRAPER_TIMEOUT_SEC", 60.0),
            http2=env_bool("SCRAPER_HTTP2", True),
            retry=RetryPolicy(
                max_attempts=env_int("SCRAPER_RETRY_MAX", 4),
                base_delay_sec=env_float("SCRAPER_RETRY_BASE_SEC", 1.5),
                max_delay_sec=env_float("SCRAPER_RETRY_MAX_SEC", 45.0),
            ),
            jitter=JitterPolicy(
                min_sec=env_float("SCRAPER_JITTER_MIN_SEC", 2.0),
                max_sec=env_float("SCRAPER_JITTER_MAX_SEC", 5.0),
                enabled=env_bool("SCRAPER_JITTER", True),
            ),
            default_referer=(os.environ.get("SCRAPER_DEFAULT_REFERER") or "").strip() or None,
            accept_language=(
                os.environ.get("SCRAPER_ACCEPT_LANGUAGE")
                or "ro-RO,ro;q=0.9,en-US;q=0.8,en;q=0.7"
            ).strip(),
        )


def _normalize_proxy(value: str | None) -> str | None:
    value = (value or "").strip()
    return value if value else None


# Pool browsere reale — Client Hints corelate cu impersonate curl_cffi.
# Folosim doar profiluri Chromium/Safari compatibile cu TLS impersonation.
BROWSER_PROFILES: list[dict[str, Any]] = [
    {
        "id": "chrome_131_win",
        "label": "Chrome 131 / Windows",
        "user_agent": (
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
            "(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
        ),
        "sec_ch_ua": '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        "sec_ch_ua_mobile": "?0",
        "sec_ch_ua_platform": '"Windows"',
        "impersonate": "chrome131",
    },
    {
        "id": "chrome_124_win",
        "label": "Chrome 124 / Windows",
        "user_agent": (
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
            "(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36"
        ),
        "sec_ch_ua": '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
        "sec_ch_ua_mobile": "?0",
        "sec_ch_ua_platform": '"Windows"',
        "impersonate": "chrome124",
    },
    {
        "id": "edge_131_win",
        "label": "Edge 131 / Windows",
        "user_agent": (
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
            "(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0"
        ),
        "sec_ch_ua": '"Microsoft Edge";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        "sec_ch_ua_mobile": "?0",
        "sec_ch_ua_platform": '"Windows"',
        "impersonate": "chrome131",
    },
    {
        "id": "safari_17_mac",
        "label": "Safari 17 / macOS",
        "user_agent": (
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 "
            "(KHTML, like Gecko) Version/17.4 Safari/605.1.15"
        ),
        "sec_ch_ua": None,
        "sec_ch_ua_mobile": None,
        "sec_ch_ua_platform": None,
        "impersonate": "safari17_0",
    },
    {
        "id": "firefox_133_win",
        "label": "Firefox 133 / Windows",
        "user_agent": (
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) "
            "Gecko/20100101 Firefox/133.0"
        ),
        "sec_ch_ua": None,
        "sec_ch_ua_mobile": None,
        "sec_ch_ua_platform": None,
        "impersonate": "chrome131",  # TLS Chrome; UA Firefox doar dacă transport=httpx
    },
]

# Override per sursă — index în pool sau ID profil preferat
SOURCE_PROFILE_HINTS: dict[str, str] = {
    "autodoc": "chrome_131_win",
    "epiesa": "edge_131_win",
    "emag": "chrome_124_win",
    "pieseauto": "chrome_124_win",
}
