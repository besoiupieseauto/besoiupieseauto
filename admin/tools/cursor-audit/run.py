#!/usr/bin/env python3
"""Audit imagini via Cursor SDK — câte un produs pe rând (progres vizibil în admin)."""
from __future__ import annotations

import json
import os
import re
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent
PYDEPS = ROOT / "pydeps"
if PYDEPS.is_dir():
    sys.path.insert(0, str(PYDEPS))

try:
    from cursor_sdk import Agent, AgentOptions, LocalAgentOptions
except ImportError as exc:
    print(json.dumps({"ok": False, "error": "cursor-sdk lipseste. Ruleaza install.bat"}))
    raise SystemExit(1) from exc


def cursor_model() -> str:
    return (os.environ.get("CURSOR_MODEL") or "composer-2.5").strip() or "composer-2.5"


def emit(payload: dict, code: int = 0) -> None:
    sys.stdout.write(json.dumps(payload, ensure_ascii=True))
    raise SystemExit(code)


def safe_rid_filename(rid: str) -> str:
    return re.sub(r"[^a-zA-Z0-9_-]", "_", rid)


def result_path(project_root: Path, rid: str) -> Path:
    return (
        project_root
        / "admin"
        / "storage"
        / "image_audit"
        / "by_product"
        / f"{safe_rid_filename(rid)}.json"
    )


def parse_verdict_json(text: str) -> dict | None:
    text = (text or "").strip()
    if not text:
        return None

    try:
        obj = json.loads(text)
        if isinstance(obj, dict) and ("verdict" in obj or "match_score" in obj):
            return obj
    except json.JSONDecodeError:
        pass

    for match in re.finditer(r"```(?:json)?\s*(\{[\s\S]*?\})\s*```", text):
        try:
            obj = json.loads(match.group(1))
            if isinstance(obj, dict):
                return obj
        except json.JSONDecodeError:
            continue

    start = text.find("{")
    while start >= 0:
        depth = 0
        for i in range(start, len(text)):
            ch = text[i]
            if ch == "{":
                depth += 1
            elif ch == "}":
                depth -= 1
                if depth == 0:
                    snippet = text[start : i + 1]
                    try:
                        obj = json.loads(snippet)
                        if isinstance(obj, dict) and ("verdict" in obj or "match_score" in obj):
                            return obj
                    except json.JSONDecodeError:
                        break
        start = text.find("{", start + 1)

    return None


def save_product_verdict(project_root: Path, product: dict, verdict: dict) -> Path:
    rid = str(product.get("randomn_id", "")).strip()
    issues = verdict.get("issues")
    if not isinstance(issues, list):
        issues = []

    model_id = cursor_model()
    payload = {
        "randomn_id": rid,
        "title": str(product.get("title", "") or ""),
        "code": str(product.get("code", "") or ""),
        "category": str(product.get("category", "") or ""),
        "image_url": str(product.get("image_url", "") or ""),
        "verdict": str(verdict.get("verdict", "uncertain")),
        "match_score": int(verdict.get("match_score", 0) or 0),
        "image_shows": str(verdict.get("image_shows", "") or ""),
        "issues": issues,
        "recommendation": str(verdict.get("recommendation", "review")),
        "summary_ro": str(verdict.get("summary_ro", "") or ""),
        "analyzed_at": datetime.now(timezone.utc).isoformat(),
        "engine": f"cursor-{model_id}",
        "model": model_id,
    }
    path = result_path(project_root, rid)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    return path


def result_saved_for_job(path: Path, started_at: int) -> bool:
    if not path.is_file():
        return False
    if started_at <= 0:
        return True
    try:
        return int(path.stat().st_mtime) >= started_at
    except OSError:
        return False


def count_saved_since(project_root: Path, products: list, started_at: int) -> int:
    count = 0
    for product in products:
        if not isinstance(product, dict):
            continue
        rid = str(product.get("randomn_id", "")).strip()
        if rid == "":
            continue
        if result_saved_for_job(result_path(project_root, rid), started_at):
            count += 1
    return count


def job_started_at(job_path: Path | None) -> int:
    if job_path is None or not job_path.is_file():
        return int(time.time())
    try:
        data = json.loads(job_path.read_text(encoding="utf-8"))
        return int(data.get("started_at", 0) or time.time())
    except (OSError, json.JSONDecodeError):
        return int(time.time())


def job_patch(job_path: Path | None, **fields: object) -> None:
    if job_path is None or not str(job_path):
        return
    try:
        data: dict = {}
        if job_path.is_file():
            data = json.loads(job_path.read_text(encoding="utf-8"))
        data.update(fields)
        data["updated_at"] = int(time.time())
        job_path.write_text(json.dumps(data, ensure_ascii=True), encoding="utf-8")
    except OSError:
        pass


def single_product_prompt(batch_path: Path, product: dict, index: int, total: int) -> str:
    rid = str(product.get("randomn_id", "")).strip()
    title = str(product.get("title", "") or rid)
    code = str(product.get("code", "") or "")
    category = str(product.get("category", "") or "")
    image = str(product.get("image_url", "") or product.get("local_image_path", "") or "")

    return "\n".join(
        [
            "@product-image-audit",
            "",
            f"Analizeaza DOAR produsul {index}/{total} din lotul:",
            str(batch_path),
            "",
            f"randomn_id: {rid}",
            f"title: {title}",
            f"code: {code}",
            f"category: {category}",
            f"image: {image}",
            "",
            "Obligatoriu:",
            "1. Citeste imaginea produsului (image sau local_image_path)",
            "2. Compara cu title / code / category",
            "3. Raspunde DOAR cu un obiect JSON valid (fara markdown), chei:",
            "   verdict (match|partial|mismatch|no_image|uncertain),",
            "   match_score (0-100), image_shows, issues (array), recommendation, summary_ro",
            "4. NU procesa alte produse din lot",
            "",
            "Composer 2.5, vedere pe imagini. Python salveaza automat JSON-ul din raspuns.",
        ]
    )


def main() -> None:
    batch_path = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else None
    project_root = Path(sys.argv[2]).resolve() if len(sys.argv) > 2 else Path.cwd()
    job_arg = sys.argv[3] if len(sys.argv) > 3 else ""
    job_path = Path(job_arg).resolve() if job_arg and job_arg != "-" else None

    api_key = str(os.environ.get("CURSOR_API_KEY", "")).strip()
    if not api_key and len(sys.argv) > 4:
        api_key = str(sys.argv[4]).strip()

    if not api_key:
        job_patch(job_path, status="error", phase="CURSOR_API_KEY lipseste.", error="CURSOR_API_KEY lipseste.")
        emit({"ok": False, "error": "CURSOR_API_KEY lipseste."}, 1)
    if batch_path is None or not batch_path.is_file():
        job_patch(job_path, status="error", phase="Batch invalid.", error="Batch invalid.")
        emit({"ok": False, "error": f"Fisier batch invalid: {batch_path}"}, 1)

    try:
        batch = json.loads(batch_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        job_patch(job_path, status="error", phase="Batch JSON invalid.", error=str(exc))
        emit({"ok": False, "error": f"Batch JSON invalid: {exc}"}, 1)

    products = batch.get("products") if isinstance(batch.get("products"), list) else []
    total = len(products)
    started_at = job_started_at(job_path)
    model_id = cursor_model()

    job_patch(
        job_path,
        status="running",
        phase=f"Pornesc Cursor API ({model_id})...",
        total=total,
        current_index=0,
    )

    options = AgentOptions(
        api_key=api_key,
        model=model_id,
        local=LocalAgentOptions(cwd=str(project_root)),
    )

    errors: list[str] = []
    last_summary = ""

    try:
        with Agent.create(options) as agent:
            index = 0
            for product in products:
                if not isinstance(product, dict):
                    continue
                index += 1
                rid = str(product.get("randomn_id", "")).strip()
                title = str(product.get("title", "") or rid or f"Produs {index}")

                job_patch(
                    job_path,
                    status="running",
                    phase=f"Analizez {index}/{total}: {title}",
                    current_index=index,
                    current_product_id=rid,
                    current_product_title=title,
                )

                run = agent.send(single_product_prompt(batch_path, product, index, total))
                result = run.wait()
                text = result.result if isinstance(result.result, str) else json.dumps(result.result or "")
                last_summary = text[:500]

                if result.status == "error":
                    errors.append(f"{rid}: {text[:120]}")
                else:
                    out_path = result_path(project_root, rid)
                    if not result_saved_for_job(out_path, started_at):
                        verdict = parse_verdict_json(text)
                        if verdict:
                            save_product_verdict(project_root, product, verdict)
                        else:
                            errors.append(f"{rid}: raspuns fara JSON verdict")

                    done_count = count_saved_since(project_root, products, started_at)
                    job_patch(
                        job_path,
                        done=done_count,
                        phase=f"Salvat {done_count}/{total} — {title}",
                    )

    except Exception as exc:  # noqa: BLE001
        message = getattr(exc, "message", None) or str(exc)
        job_patch(job_path, status="error", phase="Eroare Cursor API.", error=message)
        emit({"ok": False, "error": message, "retryable": bool(getattr(exc, "is_retryable", False))}, 1)

    done_count = count_saved_since(project_root, products, started_at)
    ok = done_count >= total and len(errors) == 0

    if ok:
        job_patch(
            job_path,
            status="done",
            phase=f"Audit finalizat ({done_count}/{total} produse).",
            finished_at=int(time.time()),
            current_index=done_count,
            done=done_count,
            summary=last_summary,
        )
    elif done_count > 0:
        job_patch(
            job_path,
            status="done",
            phase=f"Partial: {done_count}/{total} verdicturi salvate.",
            finished_at=int(time.time()),
            current_index=done_count,
            done=done_count,
            errors=errors[:8],
            summary=last_summary,
        )
    else:
        job_patch(
            job_path,
            status="error",
            phase=f"Niciun verdict salvat din {total} produse.",
            finished_at=int(time.time()),
            current_index=0,
            done=0,
            error="Agentul Cursor nu a returnat JSON valid. Verifica admin/storage/logs/cursor_audit_spawn.log",
            errors=errors[:8],
            summary=last_summary,
        )

    emit(
        {
            "ok": ok,
            "status": "finished" if ok else "error",
            "saved_count": done_count,
            "engine": "cursor-local-python",
            "model": model_id,
            "product_count": total,
            "errors": errors,
            "summary": last_summary,
        },
        0 if ok else 2,
    )


if __name__ == "__main__":
    main()
