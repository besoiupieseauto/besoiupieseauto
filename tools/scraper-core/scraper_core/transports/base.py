"""Interfață transport HTTP."""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass, field


@dataclass
class TransportResponse:
    ok: bool
    status_code: int
    final_url: str
    body: str
    headers: dict[str, str] = field(default_factory=dict)
    error: str | None = None


class HttpTransport(ABC):
    name: str = "base"

    @abstractmethod
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
        raise NotImplementedError
