"""Transport curl_cffi — TLS fingerprint Chrome/Safari (recomandat)."""

from __future__ import annotations

from scraper_core.transports.base import HttpTransport, TransportResponse


class CurlCffiTransport(HttpTransport):
    name = "curl_cffi"

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
        try:
            from curl_cffi import requests as curl_requests
        except ImportError as exc:
            return TransportResponse(
                ok=False,
                status_code=0,
                final_url=url,
                body="",
                error=f"curl_cffi lipsește: {exc}. pip install curl_cffi",
            )

        proxies = {"http": proxy, "https": proxy} if proxy else None
        timeout = max(5.0, timeout_sec)

        try:
            response = curl_requests.request(
                method=method.upper(),
                url=url,
                headers=headers,
                data=body,
                proxies=proxies,
                timeout=timeout,
                impersonate=impersonate or "chrome131",
                allow_redirects=True,
            )
            text = response.text if response.text is not None else ""
            resp_headers = {str(k): str(v) for k, v in response.headers.items()}
            return TransportResponse(
                ok=200 <= response.status_code < 400,
                status_code=int(response.status_code),
                final_url=str(response.url or url),
                body=text,
                headers=resp_headers,
            )
        except Exception as exc:
            return TransportResponse(
                ok=False,
                status_code=0,
                final_url=url,
                body="",
                error=f"{type(exc).__name__}: {exc}",
            )
