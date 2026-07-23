"""Utilitare — jitter, exponential backoff."""

from __future__ import annotations

import asyncio
import random
import time
from collections.abc import Callable, Iterator
from typing import TypeVar

from scraper_core.config import JitterPolicy, RetryPolicy

T = TypeVar("T")


def human_jitter(policy: JitterPolicy) -> float:
    """Așteptare aleatoare între cereri (comportament uman)."""
    if not policy.enabled:
        return 0.0
    lo = min(policy.min_sec, policy.max_sec)
    hi = max(policy.min_sec, policy.max_sec)
    return random.uniform(lo, hi)


def sleep_jitter(policy: JitterPolicy) -> None:
    delay = human_jitter(policy)
    if delay > 0:
        time.sleep(delay)


async def async_sleep_jitter(policy: JitterPolicy) -> None:
    delay = human_jitter(policy)
    if delay > 0:
        await asyncio.sleep(delay)


def exponential_backoff_delays(policy: RetryPolicy) -> Iterator[float]:
    """Generează delay-uri pentru retry (403, 429, 5xx)."""
    for attempt in range(1, policy.max_attempts):
        delay = min(policy.base_delay_sec * (2 ** (attempt - 1)), policy.max_delay_sec)
        jitter = random.uniform(0.0, delay * 0.25)
        yield delay + jitter


def retryable_status(status_code: int, policy: RetryPolicy) -> bool:
    return status_code in policy.retry_statuses


def with_retries(
    fn: Callable[[], T],
    *,
    policy: RetryPolicy,
    jitter: JitterPolicy,
    is_retryable: Callable[[T], bool],
    on_retry: Callable[[int, T], None] | None = None,
) -> T:
    """Execută funcția cu retry + backoff exponențial."""
    last: T | None = None
    delays = list(exponential_backoff_delays(policy))
    total_attempts = policy.max_attempts

    for attempt in range(1, total_attempts + 1):
        last = fn()
        if not is_retryable(last) or attempt >= total_attempts:
            return last
        if on_retry:
            on_retry(attempt, last)
        sleep_jitter(jitter)
        if attempt - 1 < len(delays):
            time.sleep(delays[attempt - 1])

    assert last is not None
    return last
