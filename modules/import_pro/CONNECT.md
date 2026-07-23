# Conectare modul — checklist (7 puncte)

După ce copiezi sau generezi modulul, verifică aceste legături între `modules/{id}/` și sistemul Besoiu.

| # | Punct | Fișier | Ce face |
|---|-------|--------|---------|
| 1 | **Manifest** | `modules/import_pro/module.json` | Identitate, `provides.slugs/paths`, permisiuni, nav |
| 2 | **Activare** | `app/Backend/storage/modules/state.json` | `"import_pro": true` |
| 3 | **Gate rute** | `app/Backend/config/optional_modules.php` | Cheia `import_pro` (blochează acces când modulul e oprit) |
| 4 | **Rute URL** | Auto din `provides.slugs` + `admin/config/routes_bootstrap.php` | `/admin/import-pro` |
| 5 | **Shim UI** (recomandat) | `admin/Templates/admin/pages/import-pro/import-pro.php` | 3 linii → `OptionalModulePageShim::render()` |
| 6 | **API proxy** | `admin/public/api/import_pro_endpoint.php` | Apelează `Crudimport_proHandler::handle()` |
| 7 | **Permisiuni API** | `admin/config/api_workspace_features.php` | `'import_pro_endpoint.php' => 'module.import_pro'` |
| 8 | **AI Ollama** | `src/Service/ImportProAiService.php` | `ModuleOllamaSupport` — același `.env` ca admin |

## Arhitectură motor (migrat în ERP)

Motorul complet rulează din **`app/Import/MatchingPro/`** (UI + API + Python), nu din `besoiupieseimport/import/`.

| Componentă | Locație ERP |
|------------|-------------|
| UI + logică matching | `app/Import/MatchingPro/index.php` |
| API scan/upload/cron | `app/Import/MatchingPro/api/` (junction → `admin/public/import-pro/api/`) |
| Rezolvare căi | `app/Import/Support/ImportPathResolver.php` |
| Modul admin (shell) | `modules/import_pro/` → `ImportProBridge` + `ImportProPageRenderer` |
| Embed public | `admin/public/import-pro/` + `_proxy/` |
| Scraper | `app/Import/Scraper/` |
| Date mari (Poze, Matc) | `BESOIU_IMPORT_DATA_ROOT` (env) — opțional extern până la mutare date |

Test diagnostic:

```bash
php modules/import_pro/tools/test_module_bridge.php
```

## Flux request

```
Browser /admin/import-pro
  → routes_bootstrap (slug din optional_modules / module.json)
  → AdminPageResolver → shim admin/Templates SAU modules/{id}/pages/ direct
  → OptionalModulePageShim → modules/import_pro/pages/import-pro.php

API POST /admin/api/import_pro_endpoint.php
  → Crudimport_proHandler
  → ImportProController / ImportProService
```

## Generator automat

**Interfață web (recomandat):** `http://besoiupieseimport.test/module_generator/public/`

CLI din `F:\laragon\www\besoiupieseimport\module_generator\`:

```bash
php bin/generate.php --id=exemplu --name="Modul Exemplu"
php bin/verify.php --id=exemplu
```

## Reguli

- **Tot codul modulului** stă în `modules/import_pro/`.
- **CORE** (`app/Backend/src/`) nu importă `use Besoiu\Modules\...` — doar hook-uri opționale.
- **Un singur nume** pentru pagină: folosește `import-pro` (kebab-case), nu duplica `snake` + `kebab`.
