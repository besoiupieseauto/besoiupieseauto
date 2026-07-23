"""Transport httpx — fallback HTTP/2 fără TLS impersonation nativ."""

from __future__ import annotations

from scraper_core.transports.base import HttpTransport, TransportResponse


class HttpxTransport(HttpTransport):
    name = "httpx"

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
            import httpx
        except ImportError as exc:
            return TransportResponse(
                ok=False,
                status_code=0,
                final_url=url,
                body="",
                error=f"httpx lipsește: {exc}. pip install httpx[http2]",
            )

        timeout = httpx.Timeout(max(5.0, timeout_sec))
        proxy_url = proxy or None

        try:
            with httpx.Client(
                http2=True,
                follow_redirects=True,
                timeout=timeout,
                proxy=proxy_url,
                headers=headers,
            ) as client:
                response = client.request(method.upper(), url, content=body)
                text = response.text or ""
                resp_headers = {str(k): str(v) for k, v in response.headers.items()}
                return TransportResponse(
                    ok=200 <= response.status_code < 400,
                    status_code=int(response.status_code),
                    final_url=str(response.url),
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
