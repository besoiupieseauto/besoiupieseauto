"""Transport Playwright stealth — pagini JS / Cloudflare complex."""

from __future__ import annotations

from scraper_core.transports.base import HttpTransport, TransportResponse


class PlaywrightStealthTransport(HttpTransport):
    name = "playwright"

    def fetch(
        self,
        *,
        url: str,
        method: str,
        headers: dict[str, str],
        proxy: str | None,
        timeout_sec: float,
        impersonate: str,
        body: str | bytes | None = None,
    ) -> TransportResponse:
        if method.upper() != "GET":
            return TransportResponse(
                ok=False,
                status_code=0,
                final_url=url,
                body="",
                error="PlaywrightTransport suportă doar GET în această versiune.",
            )

        try:
            from playwright.sync_api import sync_playwright
        except ImportError as exc:
            return TransportResponse(
                ok=False,
                status_code=0,
                final_url=url,
                body="",
                error=f"playwright lipsește: {exc}. pip install playwright && playwright install chromium",
            )

        timeout_ms = int(max(5.0, timeout_sec) * 1000)
        proxy_cfg = None
        if proxy:
            proxy_cfg = {"server": proxy}

        try:
            with sync_playwright() as pw:
                browser = pw.chromium.launch(headless=True, proxy=proxy_cfg)
                context = browser.new_context(
                    user_agent=headers.get("User-Agent"),
                    extra_http_headers={
                        k: v
                        for k, v in headers.items()
                        if k.lower() not in ("user-agent",)
                    },
                    locale="ro-RO",
                )
                page = context.new_page()
                try:
                    from playwright_stealth import stealth_sync

                    stealth_sync(page)
                except ImportError:
                    pass

                response = page.goto(url, wait_until="domcontentloaded", timeout=timeout_ms)
                html = page.content()
                final_url = page.url
                status = int(response.status) if response else 0
                browser.close()

                return TransportResponse(
                    ok=200 <= status < 400,
                    status_code=status,
                    final_url=final_url,
                    body=html,
                )
        except Exception as exc:
            return TransportResponse(
                ok=False,
                status_code=0,
                final_url=url,
                body="",
                error=f"{type(exc).__name__}: {exc}",
            )
