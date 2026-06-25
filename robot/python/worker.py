#!/usr/bin/env python3
"""Worker Redis/file queue pentru joburi Besoiu (fallback PHP queue_jobs)."""

from __future__ import annotations

import json
import os
import sys
import time
from typing import Any

try:
    import redis
except ImportError:
    redis = None


QUEUE_KEY = os.getenv("BESOIU_QUEUE_KEY", "besoiu:queue:default")
REDIS_HOST = os.getenv("REDIS_HOST", "127.0.0.1")
REDIS_PORT = int(os.getenv("REDIS_PORT", "6379"))


def handle_job(job: dict[str, Any]) -> None:
    job_type = str(job.get("job_type", ""))
    payload = job.get("payload") or {}
    print(f"[worker] {job_type} -> {json.dumps(payload, ensure_ascii=False)}")


def main() -> int:
    if redis is None:
        print("redis package missing; install with: pip install redis", file=sys.stderr)
        return 1

    client = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)
    print(f"Listening on {QUEUE_KEY} ({REDIS_HOST}:{REDIS_PORT})")

    while True:
        item = client.brpop(QUEUE_KEY, timeout=5)
        if not item:
            continue

        _, raw = item
        try:
            job = json.loads(raw)
            if isinstance(job, dict):
                handle_job(job)
        except json.JSONDecodeError:
            print(f"Invalid payload: {raw}", file=sys.stderr)

        time.sleep(0.05)


if __name__ == "__main__":
    raise SystemExit(main())
