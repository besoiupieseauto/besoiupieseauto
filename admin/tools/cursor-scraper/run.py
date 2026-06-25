#!/usr/bin/env python3
"""Analiză HTML scraper via Cursor SDK — Composer 2.5, răspuns JSON selectori."""
from __future__ import annotations

import json
import os
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
PYDEPS = ROOT / "pydeps"
AUDIT_PYDEPS = ROOT.parent / "cursor-audit" / "pydeps"
for dep in (PYDEPS, AUDIT_PYDEPS):
    if dep.is_dir():
        sys.path.insert(0, str(dep))
        break

try:
    from cursor_sdk import Agent, AgentOptions, LocalAgentOptions
except ImportError as exc:
    print(json.dumps({"ok": False, "error": "cursor-sdk lipseste. Ruleaza admin/tools/cursor-audit/install.bat"}))
    raise SystemExit(1) from exc


def cursor_model() -> str:
    return (os.environ.get("CURSOR_MODEL") or "composer-2.5").strip() or "composer-2.5"


def emit(payload: dict, code: int = 0) -> None:
    sys.stdout.write(json.dumps(payload, ensure_ascii=False))
    raise SystemExit(code)


def parse_agent_json(text: str) -> dict | None:
    raw = (text or "").strip()
    if not raw:
        return None
    fence = re.search(r"```(?:json)?\s*([\s\S]*?)```", raw, re.I)
    if fence:
        raw = fence.group(1).strip()
    else:
        match = re.search(r"\{[\s\S]*\}", raw)
        if match:
            raw = match.group(0)
    try:
        data = json.loads(raw)
    except json.JSONDecodeError:
        return None
    return data if isinstance(data, dict) else None


def build_prompt(req: dict) -> str:
    source_id = str(req.get("source_id", "")).strip()
    title = str(req.get("page_title", "")).strip()
    goals = str(req.get("user_goals", "")).strip()
    fields = req.get("fields_needed") if isinstance(req.get("fields_needed"), list) else []
    fields_json = json.dumps(fields, ensure_ascii=False)
    heuristic = req.get("heuristic")
    heuristic_json = json.dumps(heuristic, ensure_ascii=False) if heuristic is not None else "null"

    compass_lines = []
    for row in req.get("compass") or []:
        if isinstance(row, dict):
            compass_lines.append(f"{row.get('token', '')} ({row.get('count', 0)}x)")

    snippet = str(req.get("snippet") or "")

    return "\n".join(
        [
            "Esti agent de scraping e-commerce (Romania). Analizezi HTML de lista produse.",
            "",
            f"Sursa: {source_id}",
            f"Pagina: {title}",
            f"Cerinta operatorului: {goals}",
            f"Campuri obligatorii JSON: {fields_json}",
            "",
            "Busola DOM (clase repetate in HTML):",
            ", ".join(compass_lines[:18]),
            "",
            f"Sugestie heuristica (poate fi corecta sau nu): {heuristic_json}",
            "",
            "Reguli:",
            '- Raspunde DOAR cu JSON valid (fara markdown, fara text in afara JSON).',
            '- "selectors": CSS pentru un produs din lista — chei: block (container produs), plus campurile cerute.',
            '- Pentru imagine: ".class img@src" sau "img@src"; pentru URL: "a@href".',
            '- "items": max 3 produse extrase direct din HTML (fallback daca selectori gresiti).',
            '- "explanation_ro": 1-3 propozitii in romana ce ai facut.',
            "",
            "Schema:",
            '{"selectors":{"block":"...","title":"...","image":"...","url":"...","price":"...","sku":"..."},'
            '"items":[{"title":"...","image":"...","url":"...","price":"...","sku":"..."}],'
            '"explanation_ro":"..."}',
            "",
            "HTML (fragment lista):",
            snippet,
        ]
    )


def main() -> None:
    request_path = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else None
    project_root = Path(sys.argv[2]).resolve() if len(sys.argv) > 2 else Path.cwd()

    api_key = str(os.environ.get("CURSOR_API_KEY", "")).strip()
    if not api_key and len(sys.argv) > 3:
        api_key = str(sys.argv[3]).strip()

    if not api_key:
        emit({"ok": False, "error": "CURSOR_API_KEY lipseste."}, 1)
    if request_path is None or not request_path.is_file():
        emit({"ok": False, "error": f"Fisier request invalid: {request_path}"}, 1)

    try:
        req = json.loads(request_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        emit({"ok": False, "error": f"Request JSON invalid: {exc}"}, 1)

    if not isinstance(req, dict):
        emit({"ok": False, "error": "Request JSON trebuie sa fie obiect."}, 1)

    model_id = cursor_model()

    options = AgentOptions(
        api_key=api_key,
        model=model_id,
        local=LocalAgentOptions(cwd=str(project_root)),
    )

    try:
        with Agent.create(options) as agent:
            run = agent.send(build_prompt(req))
            result = run.wait()
            text = result.result if isinstance(result.result, str) else json.dumps(result.result or "")
            if result.status == "error":
                emit(
                    {
                        "ok": False,
                        "error": text[:500] or "Agent Cursor a esuat.",
                        "provider": "cursor",
                        "model": model_id,
                    },
                    2,
                )
    except Exception as exc:  # noqa: BLE001
        message = getattr(exc, "message", None) or str(exc)
        emit(
            {
                "ok": False,
                "error": message,
                "retryable": bool(getattr(exc, "is_retryable", False)),
                "provider": "cursor",
                "model": model_id,
            },
            1,
        )

    parsed = parse_agent_json(text)
    if parsed is None:
        emit(
            {
                "ok": False,
                "error": f"{model_id} nu a returnat JSON valid.",
                "raw": text[:2000],
                "provider": "cursor",
                "model": model_id,
            },
            2,
        )

    emit(
        {
            "ok": True,
            "provider": "cursor",
            "model": model_id,
            "engine": f"cursor-{model_id}",
            "selectors": parsed.get("selectors") if isinstance(parsed.get("selectors"), dict) else {},
            "items": parsed.get("items") if isinstance(parsed.get("items"), list) else [],
            "explanation_ro": str(parsed.get("explanation_ro") or ""),
            "raw": text[:2000],
        },
        0,
    )


if __name__ == "__main__":
    main()
