"""Modele date — contract cu motorul PHP principal."""

from __future__ import annotations

from dataclasses import asdict, dataclass, field
from enum import Enum
from typing import Any


class TransportKind(str, Enum):
    CURL_CFFI = "curl_cffi"
    HTTPX = "httpx"
    PLAYWRIGHT = "playwright"


@dataclass(frozen=True)
class FetchOptions:
    url: str
    method: str = "GET"
    source_id: str = ""
    referer: str | None = None
    proxy: str | None = None
    timeout_sec: float = 60.0
    transport: TransportKind | None = None
    extra_headers: dict[str, str] = field(default_factory=dict)
    apply_jitter: bool = True
    body: str | bytes | None = None


@dataclass
class DataPacket:
    """Rezultat fetch — fără persistare locală; consumat de motorul principal."""

    success: bool
    url: str
    final_url: str
    status_code: int
    html: str
    duration_ms: int
    transport: str
    profile_id: str = ""
    profile_label: str = ""
    headers_sent: dict[str, str] = field(default_factory=dict)
    response_headers: dict[str, str] = field(default_factory=dict)
    error: str | None = None
    attempts: int = 1
    metadata: dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)

    @classmethod
    def failure(
        cls,
        *,
        url: str,
        error: str,
        transport: str = "",
        duration_ms: int = 0,
        status_code: int = 0,
        attempts: int = 0,
        metadata: dict[str, Any] | None = None,
    ) -> DataPacket:
        return cls(
            success=False,
            url=url,
            final_url=url,
            status_code=status_code,
            html="",
            duration_ms=duration_ms,
            transport=transport,
            error=error,
            attempts=attempts,
            metadata=metadata or {},
        )
