#!/usr/bin/env python3
"""Aplica patch Windows pentru cursor_sdk._bridge (WinError 10038)."""
from __future__ import annotations

from pathlib import Path

BRIDGE = Path(__file__).resolve().parent / "pydeps" / "cursor_sdk" / "_bridge.py"
MARKER = "_read_discovery_windows_polling"

if not BRIDGE.is_file():
    print("Lipseste:", BRIDGE)
    raise SystemExit(1)

text = BRIDGE.read_text(encoding="utf-8")
if MARKER in text:
    print("Patch deja aplicat.")
    raise SystemExit(0)

old = """def _read_discovery(
    process: subprocess.Popen[str], timeout: float
) -> Mapping[str, Any]:
    if process.stderr is None:
        raise CursorSDKError("Bridge process stderr is unavailable")
    stderr_fd = process.stderr.fileno()"""

new = """def _read_discovery(
    process: subprocess.Popen[str], timeout: float
) -> Mapping[str, Any]:
    if process.stderr is None:
        raise CursorSDKError("Bridge process stderr is unavailable")

    if os.name == "nt":
        return _read_discovery_windows_polling(process, timeout)

    stderr_fd = process.stderr.fileno()"""

if old not in text:
    print("Nu am gasit codul de patch (versiune SDK diferita).")
    raise SystemExit(2)

text = text.replace(old, new, 1)

insert_before = "def parse_discovery_line(line: str) -> Mapping[str, Any] | None:"
win_fn = '''

def _read_discovery_windows_polling(
    process: subprocess.Popen[str], timeout: float
) -> Mapping[str, Any]:
    stderr = process.stderr
    if stderr is None:
        raise CursorSDKError("Bridge process stderr is unavailable")

    deadline = time.monotonic() + timeout
    stderr_lines: list[str] = []

    while time.monotonic() < deadline:
        line = stderr.readline()
        if line:
            stderr_lines.append(line)
            discovery = parse_discovery_line(line)
            if discovery is not None:
                return discovery
            continue

        exit_code = process.poll()
        if exit_code is not None:
            tail = stderr.read() or ""
            if tail:
                stderr_lines.append(tail)
                discovery = parse_discovery_line(tail)
                if discovery is not None:
                    return discovery
            raise CursorSDKError(
                f"Bridge exited before discovery with status {exit_code}: "
                + "".join(stderr_lines)
            )

        time.sleep(0.05)

    raise CursorSDKError("Timed out waiting for bridge discovery")


'''

if insert_before not in text:
    print("Nu am gasit parse_discovery_line.")
    raise SystemExit(3)

text = text.replace(insert_before, win_fn + insert_before, 1)
BRIDGE.write_text(text, encoding="utf-8")
print("Patch aplicat.")
