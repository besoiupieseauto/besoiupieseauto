"""HeaderManager — rotație UA + Client Hints corelate."""

from __future__ import annotations

import hashlib
import random
from dataclasses import dataclass
from typing import Any
from urllib.parse import urlparse

from scraper_core.config import BROWSER_PROFILES, SOURCE_PROFILE_HINTS, ScraperSettings


@dataclass(frozen=True)
class BrowserProfile:
    id: str
    label: str
    user_agent: str
    sec_ch_ua: str | None
    sec_ch_ua_mobile: str | None
    sec_ch_ua_platform: str | None
    impersonate: str


class HeaderManager:
    """Construiește headere HTTP realiste, corelate cu profilul browser."""

    def __init__(self, settings: ScraperSettings | None = None) -> None:
        self._settings = settings or ScraperSettings.from_env()
        self._profiles = [self._to_profile(p) for p in BROWSER_PROFILES]

    @staticmethod
    def _to_profile(raw: dict[str, Any]) -> BrowserProfile:
        return BrowserProfile(
            id=str(raw["id"]),
            label=str(raw.get("label") or raw["id"]),
            user_agent=str(raw["user_agent"]),
            sec_ch_ua=raw.get("sec_ch_ua"),
            sec_ch_ua_mobile=raw.get("sec_ch_ua_mobile"),
            sec_ch_ua_platform=raw.get("sec_ch_ua_platform"),
            impersonate=str(raw.get("impersonate") or "chrome131"),
        )

    def pick_profile(self, source_id: str = "", url: str = "") -> BrowserProfile:
        """Alege profil — preferință per sursă, altfel hash stabil pe zi."""
        env_profile = self._profile_from_env()
        if env_profile is not None:
            return env_profile

        hint = SOURCE_PROFILE_HINTS.get(source_id.strip().lower(), "")
        if hint:
            for profile in self._profiles:
                if profile.id == hint:
                    return profile

        rotate = (self._settings.transport == "httpx")  # rotație agresivă doar fără TLS bind
        if rotate:
            return random.choice(self._profiles)

        seed = f"{source_id}|{urlparse(url).netloc}|{self._day_bucket()}"
        idx = int(hashlib.sha256(seed.encode()).hexdigest(), 16) % len(self._profiles)
        return self._profiles[idx]

    def build_headers(
        self,
        *,
        url: str,
        profile: BrowserProfile,
        referer: str | None = None,
        extra: dict[str, str] | None = None,
    ) -> dict[str, str]:
        parsed = urlparse(url)
        origin = f"{parsed.scheme}://{parsed.netloc}" if parsed.scheme and parsed.netloc else ""

        referer_val = (referer or self._settings.default_referer or origin or "").strip()

        headers: dict[str, str] = {
            "User-Agent": profile.user_agent,
            "Accept": (
                "text/html,application/xhtml+xml,application/xml;q=0.9,"
                "image/avif,image/webp,image/apng,*/*;q=0.8"
            ),
            "Accept-Language": self._settings.accept_language,
            "Accept-Encoding": "gzip, deflate, br, zstd",
            "Cache-Control": "no-cache",
            "Pragma": "no-cache",
            "Upgrade-Insecure-Requests": "1",
            "Sec-Fetch-Dest": "document",
            "Sec-Fetch-Mode": "navigate",
            "Sec-Fetch-Site": "none" if not referer_val else "same-origin",
            "Sec-Fetch-User": "?1",
        }

        if referer_val:
            headers["Referer"] = referer_val

        if profile.sec_ch_ua:
            headers["Sec-CH-UA"] = profile.sec_ch_ua
        if profile.sec_ch_ua_mobile:
            headers["Sec-CH-UA-Mobile"] = profile.sec_ch_ua_mobile
        if profile.sec_ch_ua_platform:
            headers["Sec-CH-UA-Platform"] = profile.sec_ch_ua_platform

        if extra:
            headers.update(extra)

        return headers

    def _profile_from_env(self) -> BrowserProfile | None:
        import json
        import os

        raw = (os.environ.get("STEALTH_BROWSER_PROFILE") or "").strip()
        if not raw:
            return None
        try:
            data = json.loads(raw)
        except Exception:
            return None
        if not isinstance(data, dict):
            return None
        ua = str(data.get("user_agent") or "").strip()
        if not ua:
            return None
        return BrowserProfile(
            id=str(data.get("source") or "env"),
            label=str(data.get("label") or "env"),
            user_agent=ua,
            sec_ch_ua=None,
            sec_ch_ua_mobile="?0",
            sec_ch_ua_platform='"Windows"',
            impersonate="chrome131",
        )

    @staticmethod
    def _day_bucket() -> str:
        from datetime import date

        return date.today().isoformat()
