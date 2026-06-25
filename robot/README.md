# robot/ — Sub-aplicatie portata din aibotpiese.online

Aceasta este portarea **1:1** a logicii functionale din `C:\laragon\www\aibotpiese.online` in proiectul principal `besoiupieseauto.ro`.

Logica interna a fiecarui script (intent detection, AI prompts, scraping XPaths, sesiuni, dedup, conversie preturi, etc.) este **identica** cu originalul.  
Singurele schimbari sunt:
- Cheile API mutate din cod hardcoded in `robot/.env`
- Caile de date (sesiuni, log-uri, JSON-uri) consolidate in `robot/data/`
- Cache-ul TecDoc in `robot/cache_tecdoc/`
- Toate scripturile incep cu `require_once __DIR__ . '/bootstrap.php';`
- Tool-urile UI sunt protejate cu `auth_guard.php` (cer login admin)
- Webhook-ul UltraMsg si lead-form raman publice (au propria validare)

---

## Integrare in admin EvaSystem

Toate tool-urile sunt **intr-un singur fisier**: `admin/Templates/admin/pages/bots/bots.php`.
Pagina foloseste **tabs interne** — nu mai sunt fisiere separate.

### URL principal

`/admin/bots` — pagina unica cu 8 tabs:

| Tab | Sursa | Tip |
|---|---|---|
| Configurare boti | DB `bots` table | CRUD existent (carduri + modal) |
| WhatsApp | iframe `/robot/chat.php` | Manager conversatii UltraMsg |
| Pieseauto.ro | iframe `/robot/parser_view.php` | Scanner cereri + analiza concurenta |
| TecDoc | iframe `/robot/tecdoc.php` | Selector marca/model + cache |
| VIN Allegro | iframe `/robot/vin.php` | Importa anunt + traducere AI |
| Facebook | iframe `/robot/fb_view_protected.php` | Scanner grupuri Apify + Groq |
| Lead-uri | citeste `data/leads.json` | **Nativ** — tabel cu cautare |
| Webhook & Log | citeste `data/webhook.log` | **Nativ** — status + log colorat + KPI |

### Acces direct la un tab

`/admin/bots?tab=whatsapp` — deschide tab-ul WhatsApp.
Valori valide: `config`, `whatsapp`, `parser`, `tecdoc`, `vin`, `facebook`, `leads`, `webhook`.

### Performanta

Iframe-urile au **lazy-load** prin `data-src` — se incarca doar la prima activare a tab-ului.
Tab-urile native (Lead-uri, Webhook & Log) se randeaza din PHP la load-ul paginii.

### SQL aplicat

- `admin/sql/roboti_routes.sql` — adauga 8 rute in tabela `routes` (already executed)
- `admin/sql/roboti_nav.sql` — adauga 1 parinte + 7 sub-itemi in tabela `role_nav` pentru `super_ambassador` (already executed)

Pentru a re-rula:
```bash
mysql -u pieseauto -p besoiupieseauto.ro < admin/sql/roboti_routes.sql
mysql -u pieseauto -p besoiupieseauto.ro < admin/sql/roboti_nav.sql
```

Pentru a aplica si la alte roluri (ex. `regional_ambassador`, `manager`), copiaza inserturile din `roboti_nav.sql` schimband `role_slug`.

### Autentificare

`robot/auth_guard.php` verifica `$_SESSION['role']` si redirectioneaza la `/admin/login` daca utilizatorul nu e logat. Inclus in toate tool-urile UI:

- chat.php, vin.php, parser.php, run.php, process.php, fb_parser.php, pars.php, genereaza_mesaj.php, api.php, tecdoc_proxy.php, tecdoc.php
- + wrapper-e: `parser_view.php`, `fb_view_protected.php`

NU e inclus (intentionat) in:
- `webhook.php` (apelat de UltraMsg, valideaza prin `WEBHOOK_KEY`)
- `save-lead.php` (formular contact public)
- `bootstrap.php`, `index.php`

Pentru bypass dev (testare fara login), pune in `.env`:
```
ROBOTI_GUARD_BYPASS=1
```

---

## Acces rapid (URL-uri)

Presupunand ca site-ul ruleaza pe `https://besoiupieseauto.ro`:

| URL | Ce face |
|---|---|
| `/robot/` | Dashboard navigare (vezi `robot/index.php`) |
| `/robot/webhook.php?key=WEBHOOK_KEY` | Endpoint webhook UltraMsg (PRINCIPAL) |
| `/robot/webhook.php?key=...&debug=1` | Vezi ultimele 300 de linii de log |
| `/robot/chat.php` | Manager conversatii (UI simplu, varianta veche) + webhook simplificat |
| `/robot/chat.html` | UI chat (consuma `api.php` pentru lista mesaje) |
| `/robot/api.php?action=fetch` | Lista mesaje (folosit de `chat.html`) |
| `/robot/parser.html` + `/robot/parser.php` | Scanner cereri pieseauto.ro + analiza concurenta |
| `/robot/run.php` | Pipeline TecDoc (POST: `vin`, `cerere`) — apelat de parser.html |
| `/robot/process.php` | Pipeline TecDoc standalone (debug, hardcoded VIN) |
| `/robot/vin.html` + `/robot/vin.php` | Allegro scraper + traducere AI + conversie pret |
| `/robot/tecdoc.php` | UI selector marca/model/motorizare cu cache RapidAPI |
| `/robot/tecdoc_proxy.php` | Backend proxy + cache pentru selector TecDoc |
| `/robot/fb_view.html` + `/robot/fb_parser.php` | Scanner Facebook Groups (Apify) |
| `/robot/pars.php` | Apify FB scraper run trigger (varianta async) |
| `/robot/genereaza_mesaj.php` | Generator comentariu Facebook cu Groq AI |
| `/robot/save-lead.php` | Captura lead-uri din formulare contact |
| `/robot/auto.html` | UI auto (frontend secundar) |

---

## Tabel mapping: sursa -> destinatie

| Fisier sursa (aibotpiese.online) | Fisier destinatie (robot/) | Modificari |
|---|---|---|
| `webhook.php` | `webhook.php` | secrete -> .env, restul identic |
| `chat.php` | `chat.php` | secrete -> .env, `baza_date.json` -> `data/baza_date.json` |
| `process.php` | `process.php` | secrete -> .env, JSON outputs -> `data/` |
| `run.php` | `run.php` | secrete -> .env |
| `parser.php` | `parser.php` | secrete -> .env, `status.txt` -> `data/status.txt`, `PIESEAUTO_USER` env override |
| `vin.php` | `vin.php` | secrete + curs valutar + adaos -> .env |
| `fb_parser.php` | `fb_parser.php` | secrete -> .env, `status_fb.txt` -> `data/status_fb.txt` |
| `pars.php` | `pars.php` | secrete -> .env |
| `genereaza_mesaj.php` | `genereaza_mesaj.php` | secrete -> .env |
| `save-lead.php` | `save-lead.php` | `leads.json` -> `data/leads.json` |
| `api.php` | `api.php` | secrete -> .env, `baza_date.json` -> `data/baza_date.json` |
| `tecdoc_proxy.php` | `tecdoc_proxy.php` | secrete -> .env |
| `tecdoc.php` (UI selector) | `tecdoc.php` | copiat ca-i |
| `chat.html` | `chat.html` | copiat ca-i |
| `parser.html` | `parser.html` | copiat ca-i |
| `vin.html` | `vin.html` | copiat ca-i |
| `fb_view.html` | `fb_view.html` | copiat ca-i |
| `auto.html` | `auto.html` | copiat ca-i |
| `company.json` | `company.json` | secretele scoase, lasati doar texte de business |
| `products.json` | `products.json` | copiat ca-i |
| - | `bootstrap.php` | NOU — incarca .env si expune `env(...)` |
| - | `.env` | NOU — toate cheile + DB credentials |
| - | `.env.example` | NOU — template pentru deployment |
| - | `.gitignore` | NOU — exclude .env, data/, cache_tecdoc/ |
| - | `index.php` | NOU — dashboard mic de navigatie |
| - | `data/` | NOU — sesiuni, log-uri, dedup, lead-uri, JSON-uri TecDoc |
| - | `cache_tecdoc/` | NOU — cache RapidAPI |

### Fisiere NEPORTATE (din aibotpiese.online radacina)

Le port la cerere daca le folosesti:

`a.php`, `avion.php` (127KB!), `blur.php`, `cofe.php`, `img.php`, `index.php` (landing page marketing), `poll.php`, `scaner_largon.php` (50KB!), `scanned_products_api.php`, `scanner_pieseauto.php` (gol), `test_openai.php`, `toggle.php`, `toggle2.php`, `wh.php`, `*.txt` (state files), `*.json` (date temporare ale rularilor anterioare).

---

## ⚠️ CHEI EXPUSE — DE ROTAT URGENT

Cheile de mai jos au fost folosite de codul vechi si sunt acum mutate in `robot/.env`. Sunt aceleasi chei reale, deci au aceeasi vulnerabilitate. **Roteaza-le in ordinea asta:**

1. **OpenAI** (2 chei expuse):
   - `OPENAI_KEY` (din `company.json` original): ruleaza la https://platform.openai.com/api-keys → revoke + create new
   - `OPENAI_KEY_VIN` (din `vin.php` original): la fel
2. **Groq** (`GROQ_KEY`): https://console.groq.com/keys → revoke + create new
3. **UltraMsg** (`ULTRAMSG_TOKEN`): https://app.ultramsg.com → instance → regenerate token
4. **Apify** (`APIFY_TOKEN`): https://console.apify.com/account/integrations → reset
5. **RapidAPI** (`RAPIDAPI_TECDOC_KEY` + `RAPIDAPI_AUTOPARTS_KEY`): https://rapidapi.com/developer/security → regenerate
6. **WEBHOOK_KEY**: schimba in `robot/.env` cu un string nou random (32+ caractere) si actualizeaza in UltraMsg webhook URL.

Dupa fiecare rotatie, actualizezi valoarea in `robot/.env` si robotul continua sa mearga **fara modificari de cod**.

---

## Pornire / Schimbarea webhook UltraMsg

Logica Robot WhatsApp ramane functionala identic. Singurul lucru de schimbat dupa portare e **URL-ul webhook in UltraMsg**:

1. Deschide https://app.ultramsg.com → instance `instance162465` → Settings → Webhook
2. Schimba URL-ul vechi (era pe `aibotpiese.online/webhook.php?key=...`) cu:  
   `https://besoiupieseauto.ro/robot/webhook.php?key=Windowsxp92@radu`
3. Salveaza. Trimite un mesaj de test din WhatsApp catre numarul instance — robotul raspunde din noua locatie.
4. Verifica logul: `https://besoiupieseauto.ro/robot/webhook.php?key=Windowsxp92@radu&debug=1`

---

## Integrare cu admin EvaSystem (besoiupieseauto.ro/admin)

Robotul este **independent** de admin (lucreaza pe fisiere JSON, nu pe baza de date). Conexiunea la admin se face acum doar prin link de meniu.

**Optional — adauga link in nav-ul admin:**

In tabela `route_nav` (sau echivalentul pentru navigatia admin) adauga o ruta noua:

```sql
INSERT INTO routes (path, controller, action, load_type, dir, is_active)
VALUES ('/robot', NULL, NULL, 'redirect', '/robot/index.php', 1);
```

Sau in `admin/config/roles.php`, adauga la nav-ul rolelor `super_ambassador`/`manager`:

```php
['label' => 'Robot WhatsApp', 'href' => '/robot/', 'icon' => 'whatsapp']
```

(verifica structura exacta a array-ului de nav din roles.php).

---

## Faza urmatoare (cand ai timp)

Aceasta portare e **stratul 1 (proof-of-work)**: scripturile merg, dar in regim "fisiere". Ce se poate face dupa:

1. **Migrare JSON -> DB**:
   - `robot/data/baza_date.json` -> tabel `roboti_messages`
   - `robot/data/leads.json` -> tabel `roboti_leads`
   - `robot/data/sessions/` -> tabel `roboti_bot_sessions`
   - `robot/products.json` -> tabel `roboti_products`
2. **RBAC** — protejeaza `/robot/chat.php`, `/robot/parser.html` etc. cu sesiunea EvaSystem (cere login admin)
3. **Modul "Roboti" in admin** — lista mesaje, lead-uri, conversatii in UI nativ EvaSystem
4. **Integrare agent-v2** — adopta `App\AI\ClaudeClient` din agent-v2 ca alternativa la Groq (Claude Sonnet pentru analiza mesajelor lungi, Groq pentru raspunsuri rapide)
5. **Python bridge** — daca vrei roboti in Python (ca in agent-v2), adaugi `c:\laragon\www\besoiupieseauto.ro\python-bots\` cu FastAPI si pun PHP-ul sa-l cheme
6. **Deduplica codul TecDoc** — pipeline-ul VIN->categorii->AI exista in 4 forme (process.php, run.php, tecdoc.php, tecdoc_proxy.php). Le pot consolida intr-un `TecDocService.php` partajat.

---

## Troubleshooting

**"Missing UltraMsg instance_id/token"** la `/robot/webhook.php` — `robot/.env` nu se citeste. Verifica:
- Fisierul exista la `c:\laragon\www\besoiupieseauto.ro\robot\.env`
- Permisiunile Apache pot citi fisierul
- Apache nu blocheaza accesul fisierelor `.env` (vezi `robot/.htaccess` daca trebuie adaugat)

**"Forbidden"** la webhook — `?key=` din URL nu coincide cu `WEBHOOK_KEY` din `.env`. Reseteaza ambele.

**RapidAPI rate_limit_exceeded** — TecDoc/AutoParts au limita lunara. Verifica plan pe https://rapidapi.com/dashboard.

**Apify return 401** — token expirat sau resetat. Genereaza nou si pune in `.env`.

**Sesiunile nu se salveaza** — verifica `robot/data/sessions/` are permisiuni write pentru Apache (Laragon ruleaza ca user curent, deci de obicei merge).

---

## Comanda de verificare rapida (PowerShell)

```powershell
# Verifica ca .env e citibil si toate cheile sunt prezente
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -r "require 'c:\laragon\www\besoiupieseauto.ro\robot\bootstrap.php'; foreach (['ULTRAMSG_INSTANCE','ULTRAMSG_TOKEN','OPENAI_KEY','GROQ_KEY','APIFY_TOKEN','RAPIDAPI_TECDOC_KEY','RAPIDAPI_AUTOPARTS_KEY','WEBHOOK_KEY'] as `$k) echo str_pad(`$k,30) . (env(`$k) ? '[OK len=' . strlen(env(`$k)) . ']' : '[MISSING]') . PHP_EOL;"
```

---

Generat automat la portare. Logica scripturilor = identica cu aibotpiese.online. Modificarile sunt limitate la sursele de configurare (env vs hardcoded).
