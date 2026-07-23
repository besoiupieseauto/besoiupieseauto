"""Microkernel scraper — motor HTTP rezistent la bot-detection."""

from scraper_core.core import ScraperCore
from scraper_core.models import DataPacket, FetchOptions, TransportKind
from scraper_core.header_manager import HeaderManager

__all__ = [
    "ScraperCore",
    "DataPacket",
    "FetchOptions",
    "TransportKind",
    "HeaderManager",
]
