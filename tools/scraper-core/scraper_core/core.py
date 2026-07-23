"""ScraperCore — nucleu microkernel pentru fetch rezistent la bot-detection."""

from __future__ import annotations

import time
from typing import Callable

from scraper_core.config import ScraperSettings
from scraper_core.header_manager import BrowserProfile, HeaderManager
from scraper_core.models import DataPacket, FetchOptions, TransportKind
from scraper_core.transports.base import HttpTransport, TransportResponse
from scraper_core.transports.curl_transport import CurlCffiTransport
from scraper_core.transports.httpx_transport import HttpxTransport
from scraper_core.transports.playwright_transport import PlaywrightStealthTransport
from scraper_core.utils import exponential_backoff_delays, retryable_status, sleep_jitter


class ScraperCore:
    """
    Nucleu fetch — compune HeaderManager + transport TLS + retry/backoff.

    Nu persistă date; returnează DataPacket pentru motorul PHP (ScraperModule).
    """

    def __init__(
        self,
        settings: ScraperSettings | None = None,
        header_manager: HeaderManager | None = None,
        transports: dict[str, HttpTransport] | None = None,
    ) -> None:
        self.settings = settings or ScraperSettings.from_env()
        self.headers = header_manager or HeaderManager(self.settings)
        self._transports: dict[str, HttpTransport] = transports or self._default_transports()

    @staticmethod
    def _default_transports() -> dict[str, HttpTransport]:
        return {
            TransportKind.CURL_CFFI.value: CurlCffiTransport(),
            TransportKind.HTTPX.value: HttpxTransport(),
            TransportKind.PLAYWRIGHT.value: PlaywrightStealthTransport(),
        }

    def resolve_transport(self, kind: TransportKind | None = None) -> HttpTransport:
        key = (kind.value if kind else self.settings.transport).lower()
        transport = self._transports.get(key)
        if transport is None:
            raise ValueError(f"Transport necunoscut: {key}")
        return transport

    def fetch(self, options: FetchOptions) -> DataPacket:
        """Fetch principal — jitter opțional, retry pe 403/429/5xx."""
        started = time.monotonic()
        url = options.url.strip()
        if not url:
            return DataPacket.failure(url="", error="URL gol")

        if options.apply_jitter and self.settings.jitter.enabled:
            sleep_jitter(self.settings.jitter)

        profile = self.headers.pick_profile(options.source_id, url)
        transport = self.resolve_transport(options.transport)
        proxy = options.proxy or self.settings.proxy
        sent_headers = self.headers.build_headers(
            url=url,
            profile=profile,
            referer=options.referer,
            extra=options.extra_headers,
        )

        policy = self.settings.retry
        delays = list(exponential_backoff_delays(policy))
        last_response: TransportResponse | None = None
        attempts = 0

        for attempt in range(1, policy.max_attempts + 1):
            attempts = attempt
            last_response = self._execute_once(
                transport=transport,
                options=options,
                profile=profile,
                headers=sent_headers,
                proxy=proxy,
            )

            if last_response.error:
                if attempt >= policy.max_attempts:
                    break
                if attempt - 1 < len(delays):
                    time.sleep(delays[attempt - 1])
                continue

            if not retryable_status(last_response.status_code, policy):
                break

            if attempt >= policy.max_attempts:
                break

            if attempt - 1 < len(delays):
                time.sleep(delays[attempt - 1])
            sleep_jitter(self.settings.jitter)

        assert last_response is not None
        duration_ms = int((time.monotonic() - started) * 1000)

        if last_response.error:
            return DataPacket.failure(
                url=url,
                error=last_response.error,
                transport=transport.name,
                duration_ms=duration_ms,
                attempts=attempts,
                metadata={"profile_id": profile.id},
            )

        success = 200 <= last_response.status_code < 400
        return DataPacket(
            success=success,
            url=url,
            final_url=last_response.final_url,
            status_code=last_response.status_code,
            html=last_response.body,
            duration_ms=duration_ms,
            transport=transport.name,
            profile_id=profile.id,
            profile_label=profile.label,
            headers_sent=sent_headers,
            response_headers=last_response.headers,
            error=None if success else f"HTTP {last_response.status_code}",
            attempts=attempts,
            metadata={
                "impersonate": profile.impersonate,
                "source_id": options.source_id,
            },
        )

    def _execute_once(
        self,
        *,
        transport: HttpTransport,
        options: FetchOptions,
        profile: BrowserProfile,
        headers: dict[str, str],
        proxy: str | None,
    ) -> TransportResponse:
        impersonate = profile.impersonate
        if transport.name == TransportKind.HTTPX.value and "Firefox" in profile.user_agent:
            impersonate = ""  # httpx nu folosește impersonate; UA din headers e suficient

        return transport.fetch(
            url=options.url,
            method=options.method,
            headers=headers,
            proxy=proxy,
            timeout_sec=options.timeout_sec or self.settings.timeout_sec,
            impersonate=impersonate,
            body=options.body,
        )

    def fetch_many(
        self,
        urls: list[str],
        *,
        base_options: FetchOptions | None = None,
        on_progress: Callable[[int, int, DataPacket], None] | None = None,
    ) -> list[DataPacket]:
        """Fetch secvențial cu jitter între URL-uri."""
        results: list[DataPacket] = []
        total = len(urls)
        for idx, url in enumerate(urls, start=1):
            opts = base_options or FetchOptions(url=url)
            packet = self.fetch(
                FetchOptions(
                    url=url,
                    method=opts.method,
                    source_id=opts.source_id,
                    referer=opts.referer,
                    proxy=opts.proxy,
                    timeout_sec=opts.timeout_sec,
                    transport=opts.transport,
                    extra_headers=opts.extra_headers,
                    apply_jitter=True,
                    body=opts.body,
                )
            )
            results.append(packet)
            if on_progress:
                on_progress(idx, total, packet)
        return results
