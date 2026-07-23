# Algoritm: adăugare modul admin plug-in (HDD)

Ghid pas-cu-pas pentru module noi în Besoiu Admin. Model de referință: `modules/furnizori/` (10/10 — CORE nu importă clase din modul).

---

## 0. Regula de aur

| Unde | Ce pui |
|------|--------|
| **CORE** (`app/Backend/src/`) | Auth, router, produse, comenzi, contracte generice, fallback DB |
| **Modul** (`modules/{nume}/`) | UI, logică de business, API, migrări, hook-uri |
| **Nu în CORE** | `use Besoiu\Modules\{Modul}\*` sau logică specifică modulului |

> CORE vorbește cu modulele doar prin: `ModuleGate`, `OptionalModuleBridge`, `ModuleHookRegistry`, `SupplierHooks`-style helpers.

---

## 1. Decide tipul modulului

```
┌─────────────────────────────────────────────────────────────┐
│ Întrebare: site-ul/adminul trebuie să funcționeze FĂRĂ el? │
└─────────────────────────────────────────────────────────────┘
         │                              │
        DA                             NU
         │                              │
         ▼                              ▼
   type: "optional"              type: "core"
   în modules/                   în modules/ (users)
   activare în state.json        mereu pornit
   gate pe rute/API              fără dezactivare
```

**Exemple**

| Modul | Tip | Motiv |
|-------|-----|-------|
| `users` | core | Fără login nu există admin |
| `furnizori` | optional | Importul merge cu fallback DB |
| `blog` | optional | Magazinul nu depinde de blog |
| `ai` | optional | Asistent opțional |

Lista module CORE documentate: `app/Backend/config/core_modules.php`.

---

## 2. Algoritm complet (checklist)

### Pas A — Pregătire

1. Alege **id** modul: lowercase, `a-z`, `0-9`, `_` (ex: `furnizori`, `search_logs` → id `searchlogs`).
2. Alege **folder** fizic: de obicei același cu id sau StudlyCase (ex: `furnizori`, `Blog`).
3. Alege **workspace** admin: `orders` | `company` | `marketing` | `suppliers` | `ai` …
4. Notează **dependențe** (ex: `["produse"]` dacă modulul are nevoie de catalog).

### Pas B — Structură folder (obligatoriu)

```
modules/{folder}/
├── module.json          ← manifest (OBLIGATORIU)
├── src/
│   ├── {Studly}Module.php       ← entry class (implements ModuleInterface)
│   ├── {Studly}ModuleHooks.php  ← doar dacă CORE consumă capabilități
│   ├── Controller/
│   ├── Service/
│   ├── Model/
│   └── Handler/
├── pages/               ← UI PHP (mini-MVP propriu)
├── api/                 ← endpoint-uri opționale (sau admin/public/api/)
├── assets/              ← CSS/JS modul
└── migrations/          ← SQL idempotent
```

**Namespace modul:** `Besoiu\Modules\{StudlyId}\{Controller|Service|Model|Handler}\...`

Exemplu: `Besoiu\Modules\Furnizori\Service\FurnizoriService`

### Pas C — `module.json` (manifest)

Copiază șablonul de la `modules/furnizori/module.json` și completează:

| Câmp | Regulă |
|------|--------|
| `id` | unic, stabil, fără spații |
| `type` | `optional` sau `core` |
| `enabled` | `true` la prima instalare |
| `entry` | FQCN clasă Module (ex: `Besoiu\Modules\Furnizori\FurnizoriModule`) |
| `dependencies` | listă id-uri module |
| `permissions` | chei RBAC (ex: `module.furnizori`, `furnizori.manage`) |
| `workspace` | tab dashboard unde apare |
| `nav` | item meniu: `id`, `label`, `href`, `icon`, `group` |
| `provides` | **critic pentru gate** — vezi Pas D |

```json
{
  "id": "exemplu",
  "name": "Modul Exemplu",
  "version": "1.0.0",
  "type": "optional",
  "enabled": true,
  "entry": "Besoiu\\Modules\\Exemplu\\ExempluModule",
  "dependencies": [],
  "permissions": ["module.exemplu"],
  "workspace": "company",
  "nav": [
    {
      "id": "exemplu",
      "label": "Exemplu",
      "href": "/admin/exemplu",
      "icon": "puzzle-piece",
      "group": "Setări"
    }
  ],
  "provides": {
    "folder": "exemplu",
    "slugs": ["exemplu", "addexemplu"],
    "paths": [
      "/admin/exemplu",
      "/admin/addexemplu",
      "/admin/public/exemplu",
      "/admin/public/api/exemplu_endpoint.php"
    ],
    "crud_key": "exemplu",
    "api_scripts": ["exemplu_endpoint.php"],
    "quick_actions": ["exemplu"]
  },
  "migrations": []
}
```

### Pas D — Înregistrare în CORE (gate rute)

Adaugă intrare în **`app/Backend/config/optional_modules.php`** (sursă canonică — `ModuleGate::optionalMap()` citește doar de aici). `admin/config/optional_modules.php` e strict un `require` către fișierul canonic, nu o copie separată — nu edita array-ul acolo.

```php
'exemplu' => [
    'slugs' => ['exemplu', 'addexemplu'],
    'paths' => ['/admin/exemplu', '/admin/public/exemplu', ...],
    'crud_key' => 'exemplu',
    'api_scripts' => ['exemplu_endpoint.php'],
    'quick_actions' => ['exemplu'],
    'folder' => 'exemplu',
    'workspace' => 'company',
],
```

> `ModuleGate` blochează slug/path/API când modulul e dezactivat sau folderul lipsește.

### Pas E — Clasa Module + boot

```php
// modules/exemplu/src/ExempluModule.php
final class ExempluModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        // 1) Hook-uri către CORE (dacă e cazul)
        ExempluModuleHooks::register();

        // 2) Listeners, CRUD register, etc.
    }

    // navItems(), permissions(), dependencies() — moștenite din AbstractModule / module.json
}
```

**Reguli boot:**

- Nu arunca fatal dacă lipsesc dependențe soft — `return` grațios.
- Înregistrează hook-uri în `boot()`, nu în constructori random din CORE.

### Pas F — Activare în `state.json`

Fișier: `admin/storage/modules/state.json`

```json
{
  "enabled": {
    "users": true,
    "furnizori": true,
    "exemplu": true
  }
}
```

Fără `"exemplu": true` → modul descoperit dar **oprit** (gate ascunde meniu/rute).

### Pas G — Integrare cu CORE (doar dacă e nevoie)

Folosește acest flux **numai** când alt cod din CORE (Scan, Import, Dashboard…) trebuie să apeleze modulul.

```
┌──────────────┐     boot()      ┌─────────────────────┐
│ ExempluModule│ ──────────────► │ ExempluModuleHooks  │
└──────────────┘                 │ ::register()        │
                                 └──────────┬──────────┘
                                            │
                                            ▼
                                 ┌─────────────────────┐
                                 │ ModuleHookRegistry  │
                                 │ 'exemplu.stats'     │
                                 │ 'exemplu.export'    │
                                 └──────────┬──────────┘
                                            │
                                            ▼
                                 ┌─────────────────────┐
                                 │ CORE: ExempluHooks  │
                                 │ (helper generic)    │
                                 └─────────────────────┘
```

**Pași:**

1. În modul: `{Modul}ModuleHooks.php` → `ModuleHookRegistry::registerFactory('exemplu.stats', fn() => ...)`.
2. În CORE: helper nou `app/Backend/src/Core/Exemplu/ExempluHooks.php` (sau extinde un registry pe domeniu).
3. Consumatorii CORE apelează **doar** helper-ul, nu namespace modul.
4. Dacă modulul e oprit: helper returnează `null` sau fallback DB.

**Anti-pattern (interzis):**

```php
// GREȘIT în ScanService / Import / Adaos
use Besoiu\Modules\Exemplu\Service\ExempluService;
new ExempluService();
```

**Corect:**

```php
$service = ExempluHooks::stats(); // null-safe
```

### Pas H — API endpoint

Variante acceptate:

| Variantă | Cale |
|----------|------|
| Bridge subțire | `admin/public/api/exemplu_endpoint.php` → proxy către Controller modul |
| Modul direct | `modules/exemplu/api/...` servit prin router |

Endpoint-ul trebuie listat în `provides.api_scripts` + `optional_modules.php`.

### Pas I — Pagini UI

- Paginile stau în `modules/{folder}/pages/`.
- Dacă există template legacy în `admin/Templates/`, păstrează **shim** 3 linii:

```php
<?php
// admin/Templates/admin/pages/exemplu/list.php
require dirname(__DIR__, 5) . '/modules/exemplu/pages/list.php';
```

### Pas J — Migrări

1. SQL în `modules/{folder}/migrations/NNN_descriere.sql` (idempotent: `IF NOT EXISTS`).
2. La activare modul: `AbstractModule::install()` rulează migrările automat.
3. La dezactivare: **nu** șterge date (soft uninstall).

### Pas K — Fallback CORE (când modulul e oprit)

Dacă CORE are nevoie de date și când modulul lipsește:

1. Creează `app/Backend/src/Core/{Domeniu}/{Nume}Fallback.php` — acces PDO/CRUD direct.
2. Fațadă în `app/Backend/src/Core/{Domeniu}/{Nume}Repository.php` care delegă:
   - modul activ → repository modul
   - modul oprit → fallback

Model: `SupplierCatalogRepository` + `SupplierCatalogRepositoryFallback`.

---

## 3. Flux decizional — conectare la CORE

```
Modul nou creat
      │
      ▼
Are consumatori în CORE? ──NU──► Gata: doar module.json + pages + api + state.json
      │
     DA
      ▼
Poate funcționa cu date minime fără modul? ──DA──► Fallback DB în CORE + hook opțional
      │
     NU
      ▼
Modul obligatoriu? ──DA──► reconsideră: poate trebuie type "core"
      │
     NU
      ▼
ModuleHookRegistry + {Modul}ModuleHooks + helper CORE
```

---

## 4. Verificare finală (test manual)

Rulează în ordine:

| # | Test | Așteptat |
|---|------|----------|
| 1 | `"exemplu": true`, folder prezent | Meniu vizibil, pagini OK, API 200 JSON |
| 2 | `"exemplu": false` | Fără meniu, rute blocate, API refuzat |
| 3 | Redenumește `modules/exemplu/` → `_exemplu_off` | Site/admin **fără fatal**, mesaj grațios la acces legacy |
| 4 | Restaurează folder, reactivează | Totul revine |
| 5 | CORE fără `use Besoiu\Modules\Exemplu\*` | `rg "Modules\\\\Exemplu" app/Backend/src` → 0 în consumatori |

---

## 5. Generator rapid (CRUD simplu)

### Variantă A — șablon `_sablon` (recomandat)

```bash
php modules/_sablon/scaffold-module.php <id> ["Nume afișat"] [workspace] [folder]
```

Exemplu: `php modules/_sablon/scaffold-module.php rapoarte "Rapoarte vânzări" company`

Detalii: `modules/_sablon/README.md`

### Variantă B — generator CORE

- Clasă: `Besoiu\Core\Module\ModulePackageGenerator`
- Output: `admin/modules/{Folder}/` (sau `modules/` la rădăcină proiect — verifică `ModulesPaths::modulesRoot()`)

După generare, completează manual: `optional_modules.php`, `state.json`, hook-uri dacă e cazul.

---

## 6. Convenții nume

| Element | Convenție | Exemplu |
|---------|-----------|---------|
| id manifest | snake_case | `furnizori` |
| folder | lowercase sau StudlyCase | `furnizori` |
| namespace | `Besoiu\Modules\{Studly}` | `Besoiu\Modules\Furnizori` |
| hook id | `{domeniu}.{capabilitate}` | `supplier.stats`, `exemplu.export` |
| permisiune | `module.{id}` | `module.furnizori` |
| API script | `{id}_endpoint.php` | `furnizori_endpoint.php` |

---

## 7. Greșeli frecvente

| Greșeală | Consecință | Fix |
|----------|------------|-----|
| Ui doar în Templates, fără `modules/` | Nu e plug-in, nu se poate dezactiva | Mută în `modules/{id}/pages/` |
| Lipsește `optional_modules.php` | Rute accesibile și când modulul e oprit | Adaugă `provides` + config |
| `use` direct modul în CORE | Nu e 10/10, fatal la ștergere folder | Hook + helper CORE |
| `extends` clasă `final` din modul | Fatal PHP | Proxy `__call` sau factory |
| Credențiale hardcodate | Risc securitate | `.env` + `$_ENV` |
| Migrări non-idempotente | Eșec la re-activare | `IF NOT EXISTS`, `INSERT IGNORE` |
| Două pagini pentru același modul, denumire diferită (`supplier-search.php` **și** `supplier_search.php`) | Una e „fantomă", nelegată la nicio rută, dar rămâne în cod și confundă | O singură convenție de denumire per modul (recomandat: kebab-case pentru fișiere pagină); șterge orice pagină neconectată la `admin_nav_routes.php`/`optional_modules.php` |

---

## 8. Șablon minimal modul fără hook CORE

Pentru module „izolate” (doar UI + API propriu, fără legătură Scan/Import):

1. `modules/{id}/module.json`
2. `modules/{id}/src/{Studly}Module.php` (boot gol sau minimal)
3. `modules/{id}/pages/`
4. `optional_modules.php` + `state.json`
5. **Stop** — nu adăuga nimic în `app/Backend/src/Services/`.

---

## 9. Referințe în proiect

| Fișier | Rol |
|--------|-----|
| `modules/furnizori/` | Model complet (hooks, fallback, shim) |
| `modules/users/` | Model modul core |
| `app/Backend/src/Core/Module/ModuleInterface.php` | Contract |
| `app/Backend/src/Core/Module/ModuleRegistry.php` | Descoperire + boot |
| `app/Backend/src/Core/Module/ModuleGate.php` | Blocare rute |
| `app/Backend/src/Core/Module/OptionalModuleBridge.php` | `resolveClass()` fără hardcodare |
| `app/Backend/src/Core/Module/ModuleHookRegistry.php` | Registry hook-uri |
| `app/Backend/src/Core/Supplier/SupplierHooks.php` | Exemplu helper CORE |
| `modules/furnizori/src/FurnizoriModuleHooks.php` | Exemplu înregistrare hook |

---

## 10. Rezumat one-liner

> **Creezi folder în `modules/`, completezi `module.json`, înregistrezi în `optional_modules.php` + `state.json`, pui logica în modul; CORE atinge modulul doar prin gate și hook-uri, cu fallback DB dacă modulul e oprit.**

---

## 11. Lifecycle request API (obligatoriu)

Orice modul cu endpoint AJAX lung (căutare, import, scan) **trebuie să termine** request-ul PHP — altfel blochează worker-ul și întreg adminul pare înghețat.

### Checklist modul

| Pas | Unde | Ce faci |
|-----|------|---------|
| 1 | `Handler/*ApiHandler.php` | `registerJsonFatalGuard()` după `bootJsonApi()`; `beginBoundedJsonWork(45)` **după** `requireAuthenticatedSession()` |
| 2 | `Handler` | `try/catch Throwable` → `respondInternalError()` |
| 3 | `Service` cu HTTP | `CurlMultiPool::run()` cu deadline, nu buclă infinită `curl_multi_select` |
| 4 | `Service` | `finally` + `curl_close` pe fiecare handle |
| 5 | `pages/*.php` JS | `AbortSignal.timeout(ms)` + mesaje timeout vs rețea |
| 6 | `api_automation_guard.php` | Adaugă fragment endpoint în allowlist dacă e acțiune manuală operator |

### Referință implementare

- CORE: `app/Backend/src/Core/Bootstrap/ApiBootstrap.php` (`beginBoundedJsonWork`, `registerJsonFatalGuard`)
- CORE: `app/Backend/src/Core/Http/CurlMultiPool.php`
- Modul exemplu: `modules/supplier_search/src/Handler/SupplierSearchApiHandler.php`
- Reguli Cursor: `.cursor/rules/php-api-lifecycle-core.mdc`, `php-api-lifecycle-modules.mdc`

