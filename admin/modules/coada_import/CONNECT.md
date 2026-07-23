# Conectare modul — checklist (7 puncte)

După ce copiezi sau generezi modulul, verifică aceste legături între `modules/{id}/` și sistemul Besoiu.

| # | Punct | Fișier | Ce face |
|---|-------|--------|---------|
| 1 | **Manifest** | `modules/coada_import/module.json` | Identitate, `provides.slugs/paths`, permisiuni, nav |
| 2 | **Activare** | `app/Backend/storage/modules/state.json` | `"coada_import": true` |
| 3 | **Gate rute** | `app/Backend/config/optional_modules.php` | Cheia `coada_import` (blochează acces când modulul e oprit) |
| 4 | **Rute URL** | Auto din `provides.slugs` + `admin/config/routes_bootstrap.php` | `/admin/importreview` |
| 5 | **Shim UI** (recomandat) | `admin/Templates/admin/pages/importreview/importreview.php` | 3 linii → `OptionalModulePageShim::render()` |
| 6 | **API proxy** | `admin/public/api/coada_import_endpoint.php` | Apelează `Crudcoada_importHandler::handle()` |
| 7 | **Permisiuni API** | `admin/config/api_workspace_features.php` | `'coada_import_endpoint.php' => 'module.coada_import'` |
| 8 | **AI Ollama** | `src/Service/CoadaImportAiService.php` | `ModuleOllamaSupport` — același `.env` ca admin |

## Flux request

```
Browser /admin/importreview
  → routes_bootstrap (slug din optional_modules / module.json)
  → AdminPageResolver → shim admin/Templates SAU modules/{id}/pages/ direct
  → OptionalModulePageShim → modules/coada_import/pages/importreview.php

API POST /admin/api/coada_import_endpoint.php
  → Crudcoada_importHandler
  → CoadaImportController / CoadaImportService
```

## Generator automat

**Interfață web (recomandat):** `http://besoiupieseimport.test/module_generator/public/`

CLI din `F:\laragon\www\besoiupieseimport\module_generator\`:

```bash
php bin/generate.php --id=exemplu --name="Modul Exemplu"
php bin/verify.php --id=exemplu
```

## Reguli

- **Tot codul modulului** stă în `modules/coada_import/`.
- **CORE** (`app/Backend/src/`) nu importă `use Besoiu\Modules\...` — doar hook-uri opționale.
- **Un singur nume** pentru pagină: folosește `importreview` (kebab-case), nu duplica `snake` + `kebab`.
