#!/usr/bin/env node
/**
 * Rulează audit imagini via Cursor SDK (local agent, Composer 2.5).
 * Usage: CURSOR_API_KEY=... node run.mjs <batch.json> <projectRoot>
 */
import { readFileSync, existsSync } from "node:fs";
import { resolve } from "node:path";
import { Agent } from "@cursor/sdk";

const batchPath = resolve(process.argv[2] || "");
const projectRoot = resolve(process.argv[3] || process.cwd());
const apiKey = String(process.env.CURSOR_API_KEY || "").trim();
const cursorModel = String(process.env.CURSOR_MODEL || "composer-2.5").trim() || "composer-2.5";

function emit(payload, code = 0) {
  process.stdout.write(JSON.stringify(payload));
  process.exit(code);
}

if (!apiKey) {
  emit({ ok: false, error: "CURSOR_API_KEY lipsește." }, 1);
}
if (!batchPath || !existsSync(batchPath)) {
  emit({ ok: false, error: "Fișier batch invalid: " + batchPath }, 1);
}

let batch;
try {
  batch = JSON.parse(readFileSync(batchPath, "utf8"));
} catch (e) {
  emit({ ok: false, error: "Batch JSON invalid: " + e.message }, 1);
}

const products = Array.isArray(batch.products) ? batch.products : [];
const ids = products.map((p) => p.randomn_id).filter(Boolean);

const prompt = [
  "@product-image-audit",
  "",
  "Rulează audit imagini pentru lotul:",
  batchPath,
  "",
  "Produse (" + products.length + "): " + ids.join(", "),
  "",
  "Obligatoriu pentru fiecare produs:",
  "1. Citește imaginea (local_image_path sau image_url din batch)",
  "2. Compară cu title, category, description_excerpt",
  "3. Salvează verdict în admin/storage/image_audit/by_product/{randomn_id}.json",
  "4. Scrie raport scurt în admin/storage/image_audit/reports/",
  "",
  `Fără OpenAI. Folosești Cursor (${cursorModel}) cu vedere pe imagini.`,
].join("\n");

try {
  const result = await Agent.prompt(prompt, {
    apiKey,
    model: { id: cursorModel },
    local: { cwd: projectRoot },
  });

  const text =
    typeof result.result === "string"
      ? result.result
      : JSON.stringify(result.result ?? "");

  emit({
    ok: result.status !== "error",
    status: result.status,
    engine: "cursor-local",
    model: cursorModel,
    product_count: products.length,
    summary: text.slice(0, 4000),
  }, result.status === "error" ? 2 : 0);
} catch (err) {
  const message = err && err.message ? err.message : String(err);
  emit({ ok: false, error: message, retryable: Boolean(err?.isRetryable) }, 1);
}
