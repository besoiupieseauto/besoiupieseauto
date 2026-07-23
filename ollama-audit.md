# Audit integrare Ollama — besoiupieseauto.ro

Data audit: 2026-07-18

---

## 1. Puncte de integrare

Integrarea Ollama este **descentralizată**: există un client central ERP (`OllamaLlmClient`), dar Import/Scraper/modulOrchestr au clienți HTTP proprii (curl/`file_get_contents`), plus un motor Python (httpx).

### 1.1 Nucleu ERP (Backend)

| Fișier | Scop | Apel exact |
|--------|------|------------|
| `app/Backend/system/ollama_llm.php` | Config central env: enable, URL, modele, profil task, router mode | Funcții `besoiu_ollama_*()`, `besoiu_llm_router_mode()`, `besoiu_llm_task_profile()` |
| `app/Backend/src/Services/OllamaLlmClient.php` | Client HTTP principal ERP: chat, complete, vision, readiness | `postJson('/api/chat', …)` L388; `fetchTags()` → `GET /api/tags` L350 |
| `app/Backend/src/Services/LlmRouterService.php` | Router Metro: Ollama → Groq → Gemini → OpenRouter | `routeChain()` L150 → `$this->ollama->complete()` / `chat()` L177-179 |
| `app/Backend/src/Services/MetroAiOrchestrator.php` | Orchestrator unificat module ERP | `dispatchComplete()` L64 → `$this->router->complete()` L74 |
| `app/Backend/src/Services/ModuleOllamaSupport.php` | Fațadă module ERP (readiness + complete) | `complete()` L54 → `MetroAiOrchestrator::dispatchComplete()` L67 |
| `app/Backend/src/Services/BesoiuOllamaModelService.php` | Model custom fine-tuned (`besoiu-llama`), Modelfile, `ollama create` | `deploy()` L93: `@exec('ollama create …')` |
| `app/Backend/src/Services/AiOllamaAgentRunnerService.php` | 4 agenți specializați admin (chat + context MySQL) | `dispatchLlm()` L271 → `$this->ollama->complete()` L289 |
| `app/Backend/src/Services/ShopChatOllamaAgentBridge.php` | Bridge chat public → agenți Ollama | `tryReply()` L59 → `$runner->chat()` L100 |
| `app/Backend/src/Services/ProductImageAuditService.php` | Audit imagini produse (vision JSON) | `analyzeProductWithOllamaVision()` L951 → `$client->visionChat()` L994 |
| `app/Backend/src/Services/CategoryMatchService.php` | Clasificare categorie/subcategorie | `matchWithOllama()` L221 → `$this->ollama->complete('categorii', …)` L257 |
| `app/Backend/src/Services/ProductNameNormalizeService.php` | Normalizare denumiri TecDoc | `suggestWithOllama()` L92 → `$this->ollama->complete('importreview', …)` L130 |
| `app/Backend/src/Services/ChatLlmService.php` | Chat widget site (cascadă Metro) | Folosește `LlmRouterService` (Ollama first) |
| `app/Backend/src/Services/SectionAssistantService.php` | Asistent secțiuni admin | Folosește `LlmRouterService` |
| `app/Backend/src/Services/AiContextAgentService.php` | Briefing context agenți | `LlmRouterService::create()` L647 |
| `app/Backend/src/Services/ComposerRepairAgentService.php` | Reparări supervizor | Verifică `ollama.ready` L543, apoi `LlmRouterService` |
| `app/Backend/src/Services/AdminOpsAlertsService.php` | Alerte ops dacă LLM indisponibil | `(new OllamaLlmClient(...))->readiness()` L109 |
| `app/Backend/src/Controllers/Produse/crudu.php` | Audit imagine din CRUD produse | `analyzeProductWithOllamaVision()` L752 |
| `app/Backend/system/metro_llm_hub.php` | Hub Metro LLM: status, test, jurnal rutare | `metro_llm_test_ollama()` L336 → `LlmRouterService::complete()` |

### 1.2 Import / MatchingPro

| Fișier | Scop | Apel exact |
|--------|------|------------|
| `app/Import/bootstrap.php` | Propagare env Ollama din ERP către Import | `OllamaEnvBridge::apply()` L21 |
| `app/Import/Support/OllamaEnvBridge.php` | Bridge env `OLLAMA_*` | Listează 15 chei env, le propagă via `putenv()` |
| `app/Import/MatchingPro/api/bootstrap.php` | Helper import Ollama (curl direct) | `import_ollama_chat_json()` L2218 → `POST …/api/chat` L2242 |
| `app/Import/MatchingPro/api/mapping-ollama.php` | Sugestie mapping CSV + normalizare OEM | `import_ollama_chat_json($prompt, …)` L281 |
| `app/Import/MatchingPro/api/lib/ImportShowcaseTypeMatcher.php` | Match tip produs showcase | `import_ollama_chat_json()` L451 |
| `app/Import/MatchingPro/config/settings.json` | Override model/timeout import | `"ollama_model": "qwen2.5:3b"`, `"ollama_timeout_sec": 150` |

### 1.3 Scraper

| Fișier | Scop | Apel exact |
|--------|------|------------|
| `app/Import/Scraper/lib/ScraperLlmConfig.php` | Citire env Ollama pentru scraper | `ollamaEnabled()` L130, `ollamaBaseUrl()` L137 |
| `app/Import/Scraper/lib/ScraperOllamaClient.php` | Relevanță produs (vision + text + heuristic) | `chat()` L566 → `curl …/api/chat` L588; `analyzeProductRelevance()` L159 |
| `app/Import/Scraper/lib/OrchestratorImageQualityGate.php` | Poartă calitate imagini import | `ScraperOllamaClient::analyzeProductRelevance()` L128 |
| `app/Import/Scraper/lib/ProductSearchRelevance.php` | Construire query Ollama din text | `buildOllamaQueryFromText()` L87 |

### 1.4 modulOrchestr

| Fișier | Scop | Apel exact |
|--------|------|------------|
| `app/Import/modulOrchestr/config/orchestr-ollama-profiles.php` | Profile parametri per task (import, vision, chat…) | Array PHP returnat, consumat de `OrchestrOllamaOptions` |
| `app/Import/modulOrchestr/lib/OrchestrOllamaOptions.php` | Rezolvare profile + override env | `buildChatPayload()` L108 → payload `/api/chat` |
| `app/Import/modulOrchestr/lib/OrchestrOllamaVisionClient.php` | Vision standalone modulOrchestr | `chat()` L292 → `curl …/api/chat` L304 |
| `app/Import/modulOrchestr/lib/OrchestrLocalRouter.php` | Router local Import (Ollama → cloud) | `ollamaComplete()` L148 → `OrchestrOllamaOptions::buildChatPayload()` + curl L162 |
| `app/Import/modulOrchestr/lib/OrchestrOllamaLab.php` | Benchmark/laborator parametri Ollama | `runBench()` L57 → `OrchestrOllamaOptions::buildChatPayload()` |
| `app/Import/modulOrchestr/lib/OrchestrImportOpsAssistant.php` | Asistent import uman | `OrchestrLocalRouter::ollamaReadiness()` L81, apoi router LLM |
| `app/Import/modulOrchestr/python/orchestr_engine/ollama_options.py` | Profile Ollama (mirror PHP) | `build_chat_payload()`, `ollama_url()` L233 |
| `app/Import/modulOrchestr/python/orchestr_engine/nodes/llm.py` | Nod LangGraph import quality | `client.post(ollama_url(), json=payload)` L87 |

### 1.5 Admin UI / API

| Fișier | Scop | Apel exact |
|--------|------|------------|
| `admin/public/api/settings_endpoint.php` | Test Ollama din Setări | `action=test_metro_ollama` L390 → `MetroLlmHubService::testOllama()` |
| `admin/public/api/categorii_endpoint.php` | Status Ollama categorii | `action=ollama_category_status` L50 |
| `admin/Templates/admin/pages/settings/_settings-app.js.php` | UI Metro LLM (enable, model, URL, test) | `data-metro-test="test_metro_ollama"` L1237 |
| `admin/public/assets/js/admin-ai-agent-ollama.js` | Tab Ollama agenți (frontend) | `action: 'agent_ollama_status'` L157, `agent_ollama_chat` L314 |
| `admin/public/assets/js/admin-ai-agent.js` | Tab-uri AI Agent, chip Metro Ollama | Referințe UI `metro.ollama` L339+ |

### 1.6 Utilitare / teste

| Fișier | Scop |
|--------|------|
| `app/Import/tools/verify_ollama_stack.php` | Verificare stack: ping `POST /api/generate` |
| `app/Import/tools/test_scraper_web_ollama.php` | Test scraper + Ollama |
| `app/Import/Scraper/tests/test_ollama_orchestrate_audit.php` | Audit orchestrare Ollama scraper |

### 1.7 Ce NU folosește Ollama

- **Embeddings**: niciun apel la `/api/embeddings` găsit în proiect.
- **SDK JS browser**: frontend-ul nu vorbește direct cu Ollama; doar cu API-uri PHP admin.
- **`ShopChatOllamaAgentBridge`**: definit, dar **niciun consumator** găsit în restul codebase-ului (cod mort / neconectat).

---

## 2. Configurare

### 2.1 URL / host

| Sursă | Variabilă | Default | Tip |
|-------|-----------|---------|-----|
| `app/Backend/system/ollama_llm.php` L31-38 | `OLLAMA_BASE_URL` | `http://127.0.0.1:11434` | env (central) |
| `app/Config/.env`, `admin/.env` | `OLLAMA_BASE_URL` | `http://127.0.0.1:11434` | env fișier |
| `app/Import/Scraper/.env`, `modulOrchestr/.env` | duplicate locale | același default | env per modul |
| `OllamaLlmClient::baseUrl()` L39 | fallback hardcodat | `http://127.0.0.1:11434` | hardcodat dacă lipsește helper |
| `ScraperOllamaClient::baseUrl()` L36 | fallback | `http://127.0.0.1:11434` | hardcodat |
| Python `ollama_options.py` L234 | `OLLAMA_BASE_URL` | `http://127.0.0.1:11434` | env |

**Observație:** există 4+ copii `.env` cu aceleași chei (`app/Config/.env`, `admin/.env`, `Import/Scraper/.env`, `Import/modulOrchestr/.env`). `OllamaEnvBridge` încearcă unificarea la boot Import, dar ERP admin citește separat.

### 2.2 Modele

| Model | Unde e definit | Utilizare |
|-------|----------------|-----------|
| `qwen2.5:7b` | `ollama_llm.php` L43 (default `OLLAMA_MODEL`) | ERP principal, `.env` proiect |
| `qwen2.5:3b` | fallback în `OllamaLlmClient::model()` L51, Import `settings.json`, modulOrchestr | Import, scraper, orchestr |
| `llava:7b` | `OLLAMA_VISION_MODEL` default L50 | Vision audit, scraper |
| `besoiu-llama` | `OLLAMA_BESOIU_MODEL_NAME` | Model fine-tuned custom (deploy GGUF) |
| Per agent | `OLLAMA_MODEL_PRODUSE`, `_CLIENTI`, `_STATS` L60-62 | `besoiu_ollama_agent_model()` |
| Fallback vision scraper | listă hardcodată L43-51 | `llava:7b`, `moondream`, `llama3.2-vision`, `qwen2-vl:7b`… |

**Inconsistență:** default ERP = `qwen2.5:7b`, dar Import/Scraper/Orchestr default = `qwen2.5:3b`.

### 2.3 Parametri

| Parametru | OllamaLlmClient | ScraperOllamaClient | OrchestrOllamaOptions (profile) | import_ollama_chat_json |
|-----------|-----------------|---------------------|----------------------------------|-------------------------|
| `temperature` | 0.4 default chat L187; vision 0.2 L277 | 0.08 default L771 | 0.08–0.48 per profil | *(nespecificat — default Ollama)* |
| `num_ctx` | — | 4096 vision / 6144 text L775 | 4096–8192 per profil | — |
| `num_predict` | — | 320 vision / 512 text L776 | 320–1024 per profil | — |
| `format` | — | — | `json` pe import/vision/scraper | `'format' => 'json'` L2235 |
| `stream` | `false` L219 | `false` L579 | `false` L114 | `false` L2234 |
| `timeout` | 90s chat, 120s vision L187/277 | 20–120s via `OLLAMA_TIMEOUT_SEC` L587 | 60–120s în router/lab | 150s default (`settings.json`) |
| `keep_alive` | — | — | `5m`–`15m` per profil | — |
| `top_p`, `top_k`, `repeat_penalty` | doar temperature în ERP client | da L772-774 | da, per profil | — |

**Enable flags:** `OLLAMA_ENABLED` (default true), `OLLAMA_LOCAL_CYCLE`, `SHOP_CHAT_OLLAMA_AGENTS`, `IMPORT_OLLAMA_QUALITY_CHECK`, `OLLAMA_USE_BESOIU_MODEL`.

**Router:** `LLM_ROUTER_MODE` = `ollama_first` | `ollama_only` (L69-80 `ollama_llm.php`).

---

## 3. Flux de date

### 3.1 Chat ERP (OllamaLlmClient)

```
Input: messages[] + systemPrompt + temperature + model opțional
  → POST /api/chat { model, messages, stream:false, options:{temperature} }
Output: { ok, content, model, usage_tokens }
Validare: content non-gol L240-242; error din response L233-237
Destinație: returnat caller-ului (agenți, router, audit)
```

```php
// app/Backend/src/Services/OllamaLlmClient.php L216-224
$payload = [
    'model' => $model ?? $this->model(),
    'messages' => $ollamaMessages,
    'stream' => false,
    'options' => ['temperature' => max(0.0, min(1.5, $temperature))],
];
$response = $this->postJson('/api/chat', $payload, $timeoutSec);
```

### 3.2 Router Metro (LlmRouterService)

```
Input: taskContext (ex: agent_produse, chat_widget)
  → besoiu_llm_task_profile() decide local|hybrid|cloud
  → încearcă Ollama dacă ready && profile≠cloud
  → fallback Groq → Gemini → OpenRouter
Output: { ok, content, routed_via, model }
Validare: lanț erori concatenate L220-225
Logging: metro_llm_route_log_append() L275; api_token_budget_log_llm_call() pentru Ollama L258
```

### 3.3 Agenți Ollama (AiOllamaAgentRunnerService)

```
Input: slug agent + mesaj + options (randomn_id, phone, preset)
  → gatherLiveContext() din MySQL (produse, comenzi, RAG catalog)
  → buildSystemPrompt() cu runtime agent + date live
  → dispatchLlm(): Ollama direct sau LlmRouterService fallback
Output: { ok, content, provider, model, mode, slug }
Salvare: runDailyStatsReport() → robot/data/ai_agents/agent-statistici/daily_report.md L136
```

### 3.4 Audit imagini (ProductImageAuditService)

```
Input: produs (titlu, cod, URL imagine) → base64
  → visionChat() cu prompt JSON structurat
Output: verdict, match_score, issues[], summary_ro
Validare: regex extrage JSON L1015-1017; non-JSON → verdict review L1020-1028
Destinație: admin CRUD, agent-imagini, rapoarte admin/storage/image_audit/reports/
```

### 3.5 Scraper relevanță (ScraperOllamaClient)

```
Input: query căutare + titlu produs + URL imagine
  → prefilter heuristic ProductSearchRelevance
  → visionAnalyze() sau textAnalyze() → prompt JSON strict
  → parseJsonResponse(): score 0-100, verdict match|partial|mismatch
Output: { score, verdict, relevant, reason_ro, source, model }
Cache: storage/scraper/ollama_cache/{md5}.json TTL 86400s L741-764
Fallback: vision eșuat → text → text_heuristic (scor titlu) L217-255
```

### 3.6 Import mapping OEM (mapping-ollama.php)

```
Input: headers CSV, mapping curent, rânduri inspectate, issues normalizare
  → prompt mare JSON (columns, extra_codes, normalization.issues…)
  → import_ollama_chat_json()
Output: JSON parsat returnat ca suggestion
Validare: json_decode strict; excepție dacă invalid L2273-2274
Destinație: răspuns API către UI MatchingPro
```

### 3.7 modulOrchestr Python (llm_node)

```
Input: state produs + RAG + vision din pași anteriori
  → build_chat_payload(profile=import_json)
  → httpx POST /api/chat
Output: parsed JSON verdict/confidence/issues → state["parsed"]
Validare: _extract_json() cu fallback regex L46-66
```

---

## 4. Gestionarea erorilor

### 4.1 Ollama oprit / indisponibil

| Layer | Comportament |
|-------|--------------|
| `OllamaLlmClient` | `postJson()` returnează `null` → `{ ok:false, error:'Ollama indisponibil…' }` L226-230; **fără retry HTTP** |
| `LlmRouterService` | Dacă profile=`hybrid`: trece la Groq/Gemini/OpenRouter L191-209; dacă `local`/`ollama_only`: returnează eroare L184-186 |
| `ScraperOllamaClient` | Catch Throwable → log warn → fallback heuristic titlu L217-255 |
| `OrchestrOllamaVisionClient` | `requireVision=true` → score 0 unchecked L113-124; altfel `analyzeTitleOnly()` |
| `OrchestrImportOpsAssistant` | Ollama offline → răspuns `humanizer` fără LLM L82-96 |
| `ShopChatOllamaAgentBridge` | Eșec → `null` + escalare opțională L112-124 |
| `import_ollama_chat_json` | Exception → HTTP 503 cu mesaj L283-296 |
| Admin ops alerts | Alertă dacă nici Ollama nici cloud L115-121 |

### 4.2 Retry

- **Nu există retry HTTP** explicit pe același request Ollama (nicăieri în `OllamaLlmClient`, `ScraperOllamaClient`, `import_ollama_chat_json`).
- **Fallback logic** (nu retry): router cloud, heuristic scraper, humanizer import, catalog RAG direct pentru agent-produse.

### 4.3 Timeout

- Readiness `/api/tags`: 3–4s (`OllamaLlmClient` L356, Scraper L101).
- Chat: 45–150s configurabil; ERP default 90s, vision 120s.
- Connect timeout curl: 2–5s.

### 4.4 Logging erori

| Mecanism | Loc |
|----------|-----|
| `ScraperLogger::log('warn', …)` | ScraperOllamaClient L218, L240 |
| `metro_llm_route_log_append()` | LlmRouterService, metro_llm_hub test |
| `api_token_budget_log_llm_call('ollama', …)` | LlmRouterService L258 |
| `metro_ai_orchestra_log_module()` | MetroAiOrchestrator |
| Excepții returnate ca `{ ok:false, error }` | Peste tot (pattern uniform) |

**Lipsă:** logging centralizat pentru eșecuri Ollama din Import (`import_ollama_chat_json` aruncă excepții fără log persistent).

---

## 5. Puncte slabe / vagi / incomplete

### 5.1 Cod duplicat / integrări inconsistente

1. **5+ implementări HTTP separate** pentru același API Ollama:
   - `OllamaLlmClient` (`file_get_contents`)
   - `ScraperOllamaClient` (curl + cache + heuristic)
   - `OrchestrOllamaVisionClient` (curl, duplicat ~70% din scraper)
   - `OrchestrLocalRouter::ollamaComplete` (curl)
   - `import_ollama_chat_json` (curl, fără profile OrchestrOllamaOptions)

2. **Profile Ollama duplicate PHP + Python** — `orchestr-ollama-profiles.php` și `ollama_options.py` trebuie menținute manual sincron.

3. **Default model diferit** — ERP `qwen2.5:7b` vs Import `qwen2.5:3b` (vezi secțiunea 2.2).

4. **Parametri minimali în ERP client** — doar `temperature`; Scraper/Orchestr trimit `num_ctx`, `format`, `keep_alive`, `stop`.

```php
// OllamaLlmClient — doar temperature
'options' => ['temperature' => max(0.0, min(1.5, $temperature))],

// OrchestrOllamaOptions vision — parametri compleți
'temperature' => 0.08, 'num_ctx' => 4096, 'num_predict' => 320, 'format' => 'json'
```

### 5.2 Validare răspuns incompletă

- `OrchestrOllamaVisionClient::parseJsonResponse()` L325-332: JSON invalid → array cu score 0, **fără excepție** (mai permisiv decât Scraper).
- `ProductImageAuditService`: non-JSON → `verdict: review` (acceptabil, dar nu reîncearcă).
- `CategoryMatchService` / `ProductNameNormalizeService`: verifică JSON, dar **nu validează** că categoria returnată există în taxonomie înainte de `ok:true` (doar string non-gol L286).

### 5.3 Parsare JSON fragilă

Pattern comun: `preg_match('/\{[\s\S]*\}/', $raw)` — poate captura JSON parțial/incorect din răspunsuri lungi.

### 5.4 Config necentralizat

- `Import/MatchingPro/config/settings.json` poate suprascrie model/timeout independent de `.env`.
- `.env` duplicate în 4 directoare.
- Fallback-uri hardcodate `127.0.0.1:11434` în fiecare client.

### 5.5 Cod neconectat / UI rupt

- **`ShopChatOllamaAgentBridge`**: zero referințe în alte fișiere — bridge-ul chat public → agenți Ollama **nu e wired**.
- **Tab Ollama admin** (`admin-ai-agent-ollama.js`): apelează `agent_ollama_status`, `agent_ollama_chat`, `agent_ollama_daily_stats` — **niciun handler backend găsit** în repo (pagina `admin/Templates/admin/pages/ai-agent/` apare ștearsă în git status). Integrare UI **incompletă/ruptă**.

### 5.6 TODO / FIXME / deprecated

- `OrchestrOllamaVisionClient::analyzeProductRelevanceLegacy()` — marcat `@deprecated` L176.
- `ScraperLlmConfig::envFileCandidates()` — `@deprecated` L198.
- Nu există `TODO`/`FIXME` explicite legate de Ollama în fișierele sursă.

### 5.7 Alte gap-uri

- **Fără embeddings** — RAG-ul catalog (`CatalogRagService`) e SQL/heuristic, nu vector Ollama.
- **Fără healthcheck unificat** la startup (doar script manual `verify_ollama_stack.php`).
- **`BesoiuOllamaModelService::deploy()`** rulează `ollama create` via shell fără verificare că binarul `ollama` e în PATH pe serverul web.

---

## 6. Dependințe și versiuni

| Layer | Librărie | Versiune | Notă |
|-------|----------|----------|------|
| PHP ERP | **Niciuna** — HTTP nativ | `file_get_contents` + stream context | Fără `ollama-php`, Guzzle dedicat Ollama |
| PHP Import/Scraper | **Niciuna** — extensia **curl** | — | Apeluri directe REST |
| Python modulOrchestr | **httpx** | `>=0.27.0` (`requirements.txt`) | POST `/api/chat` |
| Python modulOrchestr | **langgraph**, **langchain-core** | `>=0.2.0`, `>=0.3.0` | Orchestrare graf, **nu** langchain-ollama |
| JavaScript admin | **Niciuna** | — | Fetch către API PHP, nu ollama-js |
| Composer (root) | **Fără pachet ollama** | — | Confirmat: zero match `ollama` în `composer.json` |

**API Ollama folosit:** `GET /api/tags`, `POST /api/chat`, `POST /api/generate` (doar în scriptul de verify).

---

## 7. Rezumat stare generală

Integrarea Ollama este **funcțională dar fragmentată**. Există un nucleu rezonabil (`ollama_llm.php` + `OllamaLlmClient` + `LlmRouterService` + `ModuleOllamaSupport`) care acoperă ERP-ul admin, dar modulele Import/Scraper/modulOrchestr au reimplementat clientul HTTP de 3–4 ori, cu parametri, default-uri de model și strategii de fallback diferite. `OllamaEnvBridge` reduce parțial divergența env-ului, însă rămân `.env` duplicate și override-uri locale (`settings.json`).

Principalul risc operațional este **inconsistența comportamentului**: același task (ex. clasificare JSON) poate folosi profile Orchestr cu `format:json` și `num_ctx:8192` în modulOrchestr, dar `import_ollama_chat_json` trimite doar `format:json` fără opțiuni — calitatea și latenta răspunsurilor variază imprevizibil. Al doilea risc este **integrarea UI/admin ruptă**: tab-ul Ollama din AI Agent și bridge-ul chat public par neconectate la backend în starea curentă a repo-ului.

**Prim pas recomandat de curățare:** consolidați toate apelurile HTTP Ollama în `OllamaLlmClient` (sau un trait comun curl) și mutați profilele `OrchestrOllamaOptions` ca singura sursă de parametri per task; apoi reconectați `ShopChatOllamaAgentBridge` + endpoint-urile `agent_ollama_*` sau eliminați JS-ul mort. Pe termen scurt, aliniați default-ul `OLLAMA_MODEL` la o singură valoare în toate `.env.example` și eliminați duplicatele `.env` locale în favoarea `app/Config/.env` + bridge.
