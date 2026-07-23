"""Transport layer — abstractizare fetch HTTP."""

from scraper_core.transports.base import HttpTransport, TransportResponse
from scraper_core.transports.curl_transport import CurlCffiTransport
from scraper_core.transports.httpx_transport import HttpxTransport

__all__ = [
    "HttpTransport",
    "TransportResponse",
    "CurlCffiTransport",
    "HttpxTransport",
]
