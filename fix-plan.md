# Plan de reparare Ollama + panou de control

Bazat pe: `ollama-audit.md` (2026-07-18)  
**Status:** doar planificare — fără implementare cod.

---

## PARTEA A — Plan de reparare (10 puncte)

Ordine: de la cel mai sigur/rapid → cel mai riscant/mare efort.

---

### Punct 1 — Unificarea modelului default în toate modulele

**Fișiere afectate:**
- `app/Config/.env.example`
- `admin/.env.example` (dacă există; altfel documentat în README env)
- `app/Backend/system/ollama_llm.php` (fallback L43: `qwen2.5:7b`)
- `app/Backend/src/Services/OllamaLlmClient.php` (fallback L51: `qwen2.5:3b`)
- `app/Import/MatchingPro/config/settings.json` (`ollama_model`: `qwen2.5:3b`)
- `app/Import/modulOrchestr/.env.example` (L17: `qwen2.5:3b`)
- `app/Import/Scraper/.env.example` (L41: `qwen2.5:7b`)

**Ce se schimbă concret:**
- Alegere unică documentată: **`OLLAMA_MODEL=qwen2.5:7b`** (sau `qwen2.5:3b` dacă hardware slab — decizie explicită în panou).
- Aliniere fallback-uri hardcodate:
  - `OllamaLlmClient::model()` L51: `'qwen2.5:3b'` → aceeași valoare ca `besoiu_ollama_model()`.
  - `import_ollama_model()` L2189: fallback `'qwen2.5:3b'` → citește doar env, fără fallback diferit.
  - `settings.json`: fie golit `ollama_model` (moștenește env), fie setat identic cu `.env`.
- `OLLAMA_VISION_MODEL=llava:7b` păstrat consistent în toate `.env.example`.

**Risc:** Scăzut  
**Efort:** 30–60 minute

**Ce se poate strica:** Import sau ERP folosesc modele diferite față de ce e instalat local (`ollama list`); un modul poate deveni mai lent dacă trece de la 3b la 7b fără GPU/RAM suficient.

**Test (non-tehnic):**
1. Deschide Setări → Metro LLM → apasă **Test Ollama**.
2. Mesajul trebuie să conțină „OK Metro Ollama” și același nume model ca în formular.
3. Deschide Import MatchingPro → funcție mapping AI (dacă există) → verifică în răspuns `ollama_model` = același string.
4. Rulează `php app/Import/tools/verify_ollama_stack.php` — toate secțiunile „model” identice.

**Rollback:** Restaurează valorile vechi din backup `.env` / `settings.json`; nu necesită revert cod dacă doar env s-a schimbat.

---

### Punct 2 — Healthcheck unificat la nivel de aplicație

**Fișiere afectate (noi + existente):**
- **Nou:** `app/Backend/src/Services/OllamaHealthService.php`
- **Nou:** `app/Backend/system/ollama_health.php` (helper + registry integrări)
- `app/Backend/system/metro_llm_hub.php` (refolosește health service)
- `admin/public/api/settings_endpoint.php` (action nou: `ollama_health_snapshot`)
- `app/Import/tools/verify_ollama_stack.php` (devine wrapper peste același service)

**Ce se schimbă concret:**
- Un singur service care pentru fiecare modul (ERP, Import, Scraper, modulOrchestr, Python) execută:
  - `GET {OLLAMA_BASE_URL}/api/tags` (timeout 4s)
  - verifică model text + vision instalat
  - opțional ping chat minimal: `POST /api/chat` cu `num_predict: 8`, prompt fix
- Return structurat:
  ```json
  { "module": "erp", "url": "...", "model": "...", "ready": true, "latency_ms": 120, "last_check": "..." }
  ```
- Fără modificare comportament producție — doar citire/diagnostic.

**Risc:** Scăzut  
**Efort:** 2–4 ore

**Ce se poate strica:** Healthcheck-ul agresiv (prea multe ping-uri) poate încărca Ollama dacă e apelat la fiecare page load; mitigare: cache 60s per modul.

**Test (non-tehnic):**
1. Oprește Ollama (`ollama stop` sau serviciu oprit).
2. Deschide panoul de control (Partea B) sau endpoint health — toate modulele trebuie roșu „indisponibil”.
3. Pornește Ollama — reîmprospătează — verde „OK” cu timp răspuns afișat.
4. Schimbă temporar `OLLAMA_BASE_URL` greșit în `.env` — un modul trebuie galben/roșu cu URL greșit vizibil.

**Rollback:** Șterge fișierele noi; endpoint-ul vechi `test_metro_ollama` rămâne funcțional.

---

### Punct 3 — Logging centralizat pentru eșecuri Ollama

**Fișiere afectate:**
- **Nou:** `app/Backend/system/ollama_error_log.php` (append JSONL)
- **Nou:** `admin/storage/ollama/errors.jsonl` (destinație log)
- `app/Import/MatchingPro/api/bootstrap.php` — `import_ollama_chat_json()` L2259: înainte de `throw`, apelează logger
- `app/Import/Scraper/lib/ScraperOllamaClient.php` — L218, L240: duplică în log central (păstrează ScraperLogger)
- `app/Backend/src/Services/OllamaLlmClient.php` — `postJson()` L406-408: log la null/invalid
- `app/Import/modulOrchestr/lib/OrchestrLocalRouter.php` — la eșec curl

**Ce se schimbă concret:**
- Funcție unică `ollama_error_log_append(['source'=>'import.mapping','error'=>'...','url'=>'...','model'=>'...','http_code'=>503])`.
- Rotire: max 500 linii sau 7 zile (configurabil).
- Nu schimbă fluxul de erori — doar persistă ce deja se pierde (Import).

**Risc:** Scăzut  
**Efort:** 2–3 ore

**Ce se poate strica:** Fișier log fără permisiuni write → warning PHP; disk full dacă nu există rotire.

**Test (non-tehnic):**
1. Oprește Ollama.
2. Declanșează o acțiune Import cu AI (mapping) — trebuie eșec vizibil în UI.
3. Deschide panoul → Jurnal erori — apare linie cu sursă „import.mapping” și mesaj „Ollama indisponibil”.
4. Repornește Ollama, reîncearcă — log nou cu `ok:true` opțional sau dispariția erorilor noi.

**Rollback:** Dezactivează logger via env `OLLAMA_ERROR_LOG=0`; șterge `errors.jsonl`.

---

### Punct 4 — Sincronizare profile PHP ↔ Python (orchestr)

**Fișiere afectate:**
- `app/Import/modulOrchestr/config/orchestr-ollama-profiles.php` (sursă unică)
- `app/Import/modulOrchestr/python/orchestr_engine/ollama_options.py` (devine generator sau import JSON)
- **Nou:** `app/Import/modulOrchestr/tools/sync_ollama_profiles.php` (export JSON din PHP)
- **Nou:** `app/Import/modulOrchestr/config/orchestr-ollama-profiles.json` (artefact generat)
- `app/Import/modulOrchestr/python/orchestr_engine/ollama_options.py` — citește JSON la import

**Ce se schimbă concret:**
- PHP rămâne **single source of truth**; script `sync_ollama_profiles.php` generează JSON.
- Python `ollama_options.py` încarcă `orchestr-ollama-profiles.json` în loc de dict `PROFILES` duplicat L6-119.
- CI/manual: rulezi sync după orice edit profil PHP.

**Risc:** Mediu (doar modulOrchestr Python)  
**Efort:** 3–5 ore

**Ce se poate strica:** Motor Python pornește fără JSON generat → fallback greșit sau crash la startup FastAPI.

**Test (non-tehnic):**
1. Rulează script sync (buton în panou sau CLI).
2. Deschide modulOrchestr → health Python (`/health`) — `ollama` URL corect.
3. Rulează un task import quality din modulOrchestr — răspuns JSON valid, fără eroare „profile unknown”.
4. Modifică intenționat `temperature` în PHP, regenerează JSON, verifică în panou că Python vede noua valoare.

**Rollback:** Restaurează `ollama_options.py` vechi cu dict inline; șterge JSON generat.

---

### Punct 5 — Eliminarea `.env` duplicate → sursă unică de config

**Fișiere afectate:**
- `app/Config/.env` (sursă canonică)
- `admin/.env` (symlink sau redirect documentat către `app/Config/.env`)
- `app/Import/Scraper/.env` → **șters**, înlocuit cu pointer în `.env.example`: „folosește OllamaEnvBridge”
- `app/Import/modulOrchestr/.env` → **șters**
- `app/Import/Support/OllamaEnvBridge.php` — extins: citește doar `BESOIU_ROOT/app/Config/.env`
- `app/Import/Scraper/lib/ScraperLlmConfig.php` — elimină `envFileCandidates()` deprecated L200
- `app/Backend/system/env_settings.php` — aliniere path unic

**Ce se schimbă concret:**
- Un singur fișier `.env` activ: `app/Config/.env`.
- `admin/.env` fie symlink, fie 3 linii `# include ../app/Config/.env` + require în bootstrap admin.
- Module Import: la boot, `OllamaEnvBridge::apply()` propagă din sursa canonică; fișierele locale devin `.env.example` only.
- Documentare în `app/Config/.env.example` a tuturor cheilor `OLLAMA_*`.

**Risc:** Mediu  
**Efort:** 4–6 ore

**Ce se poate strica:** Scraper/modulOrchestr rulate standalone (fără ERP bootstrap) nu mai găsesc env; cron-uri CLI care depindeau de `.env` local.

**Test (non-tehnic):**
1. Panoul → **Diff configurare** — zero diferențe pe chei `OLLAMA_*` (toate modulele aceeași valoare).
2. Test Scraper din admin — relevanță produs funcționează.
3. Test Import mapping Ollama — funcționează.
4. Test Setări → Metro LLM — același URL/model ca panoul ERP.

**Rollback:** Restaurează copiile `.env` locale din backup; revert `OllamaEnvBridge.php`.

---

### Punct 6 — Alinierea parametrilor (temperature, num_ctx, format json)

**Fișiere afectate:**
- `app/Backend/src/Services/OllamaLlmClient.php` — `chat()`/`visionChat()`: acceptă `$profileName` sau `$options` extins
- `app/Import/MatchingPro/api/bootstrap.php` — `import_ollama_chat_json()`: înlocuiește payload minimal L2231-2236 cu `OrchestrOllamaOptions::buildChatPayload(..., 'import_json')`
- `app/Import/modulOrchestr/lib/OrchestrOllamaOptions.php` (deja complet)
- `app/Backend/system/ollama_llm.php` — mapare task → profil (ex. `category_match` → `import_json`)

**Ce se schimbă concret:**
- Payload Import devine:
  ```php
  OrchestrOllamaOptions::buildChatPayload(
      [['role' => 'user', 'content' => $prompt]],
      'import_json'
  );
  ```
  în loc de doar `'format' => 'json'`.
- `OllamaLlmClient` primește opțional `options` din profil (temperature, num_ctx, num_predict, keep_alive).
- ERP chat liber rămâne profil `chat`; vision profil `vision`.

**Risc:** Mediu  
**Efort:** 4–8 ore

**Ce se poate strica:** Răspunsuri JSON mai lente (num_ctx 8192); timeout-uri dacă `OLLAMA_TIMEOUT_SEC` prea mic; regresie calitate dacă temperature diferă.

**Test (non-tehnic):**
1. Import mapping — răspuns JSON structurat ca înainte, dar timp ≤ timeout setat.
2. Audit imagine produs — verdict JSON, fără markdown în răspuns.
3. Panou → compară parametri efectivi ERP vs Import — trebuie identici pentru același profil `import_json`.

**Rollback:** Revert la payload minimal în `import_ollama_chat_json`; ERP client fără profile.

---

### Punct 7 — Robustețea parsării JSON din răspunsurile Ollama

**Fișiere afectate:**
- **Nou:** `app/Backend/src/Support/OllamaJsonParser.php` (sau `app/Import/Support/OllamaJsonParser.php` partajat)
- `app/Import/Scraper/lib/ScraperOllamaClient.php` — `parseJsonResponse()` L619
- `app/Import/modulOrchestr/lib/OrchestrOllamaVisionClient.php` — `parseJsonResponse()` L325
- `app/Import/MatchingPro/api/bootstrap.php` — L2269-2274
- `app/Backend/src/Services/ProductImageAuditService.php` — L1014-1018
- `app/Backend/src/Services/CategoryMatchService.php` — `parseJsonObject()`
- `app/Import/modulOrchestr/python/orchestr_engine/nodes/llm.py` — `_extract_json()`

**Ce se schimbă concret:**
- Parser unic cu pași ordonați:
  1. `json_decode` direct pe string trim
  2. extrage bloc ```json … ```
  3. scanare balanced-brace (nu regex `{[\s\S]*}`)
  4. opțional: re-prompt Ollama cu „fix JSON” (1 retry logic, nu HTTP retry)
- Validare schema minimă per task (ex. mapping: chei `columns`, `normalization`).

**Risc:** Mediu  
**Efort:** 6–10 ore

**Ce se poate strica:** Parser prea strict respinge răspunsuri acceptate anterior; retry JSON dublează latenta.

**Test (non-tehnic):**
1. Rulează teste existente scraper orchestr audit (`test_ollama_orchestrate_audit.php`).
2. Import mapping cu fișier CSV real — suggestion JSON complet, fără „JSON invalid”.
3. Simulează răspuns cu markdown (manual în panou test) — parser extrage JSON corect.

**Rollback:** Revert la `preg_match` vechi per fișier.

---

### Punct 8 — Decizie + acțiune pentru cod mort/rupt (tab Ollama admin, ShopChatOllamaAgentBridge)

**Fișiere implicate:**
- `admin/public/assets/js/admin-ai-agent-ollama.js` (actions: `agent_ollama_status`, `agent_ollama_chat`, `agent_ollama_daily_stats`)
- `admin/public/assets/js/admin-ai-agent.js` (tab `ollama`)
- `app/Backend/src/Services/AiOllamaAgentRunnerService.php`
- `app/Backend/src/Services/ShopChatOllamaAgentBridge.php`
- `app/Backend/src/Services/PublicShopChatOrchestrator.php` (consumator potențial bridge)
- `app/Backend/src/Services/ChatLlmService.php`
- Endpoint lipsă (de creat sau refolosit din `settings_endpoint` / hub)

#### Opțiunea A — Reconectare (recomandată dacă vrei agenți locali în admin + chat)

| Aspect | Detaliu |
|--------|---------|
| **Ce se face** | Recreezi handler API (ex. `admin/public/api/ai_agent_endpoint.php` sau acțiuni în hub existent) cu `agent_ollama_status` → `AiOllamaAgentRunnerService::status()`, `agent_ollama_chat` → `chat()`, `agent_ollama_daily_stats` → `runDailyStatsReport()`. Wire `ShopChatOllamaAgentBridge::tryReply()` în `PublicShopChatOrchestrator::handleMessage()` înainte de `ChatLlmService`. |
| **Fișiere noi/modificate** | +1 endpoint PHP, `PublicShopChatOrchestrator.php`, eventual restaurare template `admin/Templates/admin/pages/ai-agent/` |
| **Efort** | **1–2 zile** (endpoint + test chat admin + test widget) |
| **Risc** | Mediu |

#### Opțiunea B — Eliminare (recomandată dacă nu folosești tab-ul)

| Aspect | Detaliu |
|--------|---------|
| **Ce se face** | Ștergi tab Ollama din `admin-ai-agent.js`, fișier `admin-ai-agent-ollama.js`, CSS asociat; marchezi `ShopChatOllamaAgentBridge` `@deprecated` sau ștergi clasa; păstrezi `AiOllamaAgentRunnerService` doar dacă e apelat din cron. |
| **Fișiere** | JS/CSS admin, eventual `ShopChatOllamaAgentBridge.php` |
| **Efort** | **2–4 ore** |
| **Risc** | Scăzut |

**Ce se poate strica (Opțiunea A):** Chat public răspunde dublu (bridge + LLM); agenți fără model vision blocant; lock sesiune admin (deja menționat în `admin_hub_endpoint.php`).

**Test Opțiunea A (non-tehnic):**
1. Admin → AI Agent → tab Ollama → status verde pentru agenți instalați.
2. Trimite mesaj test agent-produse — răspuns în română cu date catalog.
3. Site → chat widget → întrebare despre stoc — răspuns de la agent (badge „ollama_agent” în debug panou).

**Test Opțiunea B:** Tab Ollama dispare; restul AI Agent funcționează; chat site neschimbat.

**Rollback A:** Dezactivează endpoint; `SHOP_CHAT_OLLAMA_AGENTS=0`.  
**Rollback B:** Restaurează fișiere JS din git.

---

### Punct 9 — Retry / fallback uniform unde lipsește

**Fișiere afectate:**
- `app/Backend/src/Services/OllamaLlmClient.php` — `postJson()`: max 2 retry cu backoff 500ms/1500ms pe timeout/connection fail (nu pe 4xx JSON invalid)
- `app/Backend/src/Services/LlmRouterService.php` — documentează lanț: retry Ollama → cloud (deja parțial)
- `app/Import/MatchingPro/api/bootstrap.php` — `import_ollama_chat_json`: 1 retry apoi exception
- Policy centrală în `app/Backend/system/ollama_llm.php`:
  ```php
  function besoiu_ollama_retry_policy(): array {
      return ['max_attempts' => 2, 'backoff_ms' => [500, 1500], 'retry_on' => ['timeout', 'connection']];
  }
  ```
- Scraper: păstrează fallback heuristic ca ultim nivel (deja există L217-255)

**Ce se schimbă concret:**
- Retry doar pentru erori tranzitorii (Ollama busy, connection reset).
- Fără retry pe răspuns gol/JSON invalid → trece direct la fallback module-specific (router cloud, heuristic, humanizer).

**Risc:** Mediu–Ridicat  
**Efort:** 1–2 zile

**Ce se poate strica:** Dublarea timpului de așteptare la Ollama oprit (2× timeout); load spike pe Ollama la erori intermittente.

**Test (non-tehnic):**
1. Panou → simulează timeout (URL valid, model greu) — vezi în log „attempt 1/2”.
2. Ollama oprit — eșec după max 2 încercări, apoi fallback cloud (Setări → Groq configurat) sau mesaj clar.
3. Scraper cu Ollama oprit — încă returnează scor heuristic, nu blocare totală.

**Rollback:** `OLLAMA_RETRY_MAX=0` în env dezactivează retry.

---

### Punct 10 — Unificarea clienților HTTP sub `OllamaLlmClient`

**Fișiere afectate:**
- `app/Backend/src/Services/OllamaLlmClient.php` — extins: transport curl (unificat), metode `chatWithProfile()`, `chatRaw()`
- `app/Import/Scraper/lib/ScraperOllamaClient.php` — devine wrapper subțire: cache + heuristic + apelează client central
- `app/Import/modulOrchestr/lib/OrchestrOllamaVisionClient.php` — delegare sau șters
- `app/Import/modulOrchestr/lib/OrchestrLocalRouter.php` — `ollamaComplete()` → client central
- `app/Import/MatchingPro/api/bootstrap.php` — `import_ollama_chat_json()` → client central
- Autoload: Import trebuie să încarce `OllamaLlmClient` via bridge (deja parțial în `OllamaEnvBridge`)

**Ce se schimbă concret:**
- Un singur `postJson()` cu curl, timeout configurabil, logging (Punct 3), profile (Punct 6).
- `ScraperOllamaClient::chat()` L566-616 dispare ca implementare duplicată.
- `file_get_contents` din `OllamaLlmClient` L396-416 înlocuit cu curl pentru paritate timeout/connect.

**Risc:** Ridicat  
**Efort:** 3–5 zile (+ testare regresie)

**Ce se poate strica:** Toate fluxurile Ollama (import, scraper, admin, chat, audit imagini) simultan; autoload PHP între module Import vs Backend; cache scraper invalidat greșit.

**Test (non-tehnic) — checklist complet:**
1. Setări → Test Ollama ✓
2. Import mapping CSV ✓
3. Scraper → validare relevanță produs ✓
4. Admin produse → audit imagine ✓
5. Categorii → match cu Ollama ✓
6. modulOrchestr import assistant ✓
7. Panou health — toate module verzi ✓

**Rollback:** Branch git separat; flag env `OLLAMA_LEGACY_CLIENTS=1` păstrează implementările vechi behind if (recomandat pe durata migrării).

---

## PARTEA B — Panou de control UI/UX (verificare înainte de acțiune)

### Unde se construiește

**Recomandare: extensie a paginii Setări existente**, nu pagină nouă standalone.

| Motiv | Detaliu |
|-------|---------|
| Infrastructură existentă | `admin/Templates/admin/pages/settings/settings.php` are deja tab-uri; `_settings-app.js.php` are panel **Metro LLM** (`renderMetroLlmPanel`, L1138+) cu test Ollama, chip status, route log. |
| API existent | `settings_endpoint.php` expune deja `test_metro_ollama`, `metro_ai_orchestra_stats`, `hubPayload`. |
| Permisiuni | Setări = super_ambassador/admin — potrivit pentru acțiuni destructive cu confirmare. |

**Implementare propusă:** tab nou **„Ollama Control”** (sau sub-secțiune expandabilă în tab **Tokeni API** / Metro LLM) cu 6 zone.

### Zone panou (specificație)

#### B1. Status live pe modul

Carduri pentru: **ERP**, **Import**, **Scraper**, **modulOrchestr**, **Python engine**.

Fiecare card afișează:
- URL efectiv (nu doar env — ce a rezolvat runtime)
- Model text + vision
- Stare: 🟢 OK / 🟡 parțial (reachable, model lipsă) / 🔴 offline
- Ultimul apel: timestamp, durata ms, ok/eșec
- Media ultimelor 10 apeluri (ms)

Sursă date: `OllamaHealthService` (Punct 2) + ring buffer în `admin/storage/ollama/telemetry.json`.

#### B2. Diff configurare

Tabel:
| Cheie | Sursă canonică (`app/Config/.env`) | admin/.env | Scraper/.env | modulOrchestr/.env | settings.json |
|-------|-------------------------------------|------------|--------------|---------------------|---------------|
| OLLAMA_MODEL | qwen2.5:7b | … | … | … | qwen2.5:3b ⚠️ |

Celule diferite: fundal galben; lipsă fișier: gri „—”.

Acțiune: buton **„Export raport diff”** (PDF/CSV) — fără aplicare automată.

#### B3. Hartă integrări

Listă grupată (din audit §1), fiecare rând:
- Cale fișier + funcție (`ScraperOllamaClient::analyzeProductRelevance`)
- Badge conectare: 🟢 activ / 🟡 parțial / 🔴 mort
  - **Roșu exemplu:** `ShopChatOllamaAgentBridge` (0 consumatori), `admin-ai-agent-ollama.js` (fără backend)
- Link „Test acest apel”

Vizualizare alternativă: diagramă simplă Mermaid renderizată în UI (4 coloane module, săgeți către Ollama).

Registry backend: array în `ollama_health.php` menținut manual la început, generat automat pe termen lung.

#### B4. Test manual pe loc

Lângă fiecare integrare din hartă:
- Buton **„Test apel”**
- Prompt fix per tip (ex. ERP: „Spune OK”; Scraper: query+fictiv titlu; Vision: imagine sample base64 mică)
- Rezultat: JSON expandabil — `raw_content`, `latency_ms`, `model`, `error`, `parsed_ok`

Endpoint: `settings_endpoint.php?action=ollama_integration_test&integration_id=scraper.relevance`

**Important:** testele rulează serial (coadă), max 1 concurrent — protecție Ollama.

#### B5. Jurnal ultimele erori

Tabel ultimele 50 din `admin/storage/ollama/errors.jsonl`:
- Coloane: dată, modul/sursă, model, mesaj scurt, HTTP code
- Filtru: modul, doar eșecuri, ultimele 24h
- Buton „Copiază pentru support”

#### B6. Confirmare explicită per acțiune (Partea A)

Secțiune **„Plan reparare”** — 10 carduri (Punct 1–10), fiecare cu:
- Titlu + rezumat risc/efort
- Stare: ⏸ neaplicat / ✅ aplicat / ⚠️ eșuat
- Buton **„Previzualizează modificarea X”** → modal cu diff (fișiere + linii afectate, generat server-side fără scriere)
- Buton **„Aplică modificarea X”** → cere confirmare tastând `APLICA-X` (ex. `APLICA-1`)
- **Fără** buton global „Aplică tot”

Backend: `action=ollama_fix_preview&fix_id=1` (read-only diff) și `action=ollama_fix_apply&fix_id=1` (execută script migrare asociat).

---

### Efort estimat Partea B

| Componentă | Efort |
|------------|-------|
| Tab UI + layout (settings) | 1 zi |
| API health + diff + registry | 1–1.5 zile |
| Test per integrare + telemetry | 1.5 zile |
| Jurnal erori UI | 0.5 zi |
| Preview/apply per fix (MVP doar fix 1–3) | 1–2 zile |
| **Total MVP** (status + diff + hartă + test + jurnal, fără apply automat) | **3–4 zile** |
| **Total complet** (cu apply per punct) | **5–7 zile** |

**Pagină nouă separată** ar costa +1 zi (routing admin, nav, permisiuni duplicate) — **nerecomandat**.

---

## PARTEA C — Rezumat pentru decizie

**Fă PRIMELE (raport siguranță/beneficiu maxim):**
1. **Punct 2 — Healthcheck unificat** + **Partea B MVP** (status live + diff config + hartă integrări): îți arată starea reală și inconsistențele *înainte* de orice atingere cod; risc minim, valoare imediată pentru decizii.
2. **Punct 1 — Unificare model default**: schimbare simplă env/fallback, elimină confuzia 7b vs 3b care afectează toate modulele acum.
3. **Punct 3 — Logging centralizat**: Import e orb la erori; fără asta nu poți valida că reparațiile ulterioare funcționează.

**Panoul (Partea B) ar trebui construit ÎNAINTE de Punctele 5–10** (env duplicate, parametri, JSON, retry, unificare HTTP). MVP-ul panoului (3–4 zile) poate rula paralel cu Punctele 1–3 (1–2 zile total). **Nu aplica Punct 10 (unificare clienți)** până nu vezi în panou toate integrările verzi/galben și diff config zero. **Punct 8 (cod mort)** necesită decizia ta explicită (reconectare 1–2 zile vs eliminare 2–4 ore) — panoul o evidențiază cu badge roșu până alegi.

**Ordine recomandată execuție:** B MVP → 1 → 3 → 2 → 5 → 4 → 6 → 7 → 8 (decizie) → 9 → 10.
