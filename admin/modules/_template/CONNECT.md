# Conectare modul — checklist (7 puncte)

După ce copiezi sau generezi modulul, verifică aceste legături între `modules/{id}/` și sistemul Besoiu.

| # | Punct | Fișier | Ce face |
|---|-------|--------|---------|
| 1 | **Manifest** | `modules/__MODULE_ID__/module.json` | Identitate, `provides.slugs/paths`, permisiuni, nav |
| 2 | **Activare** | `app/Backend/storage/modules/state.json` | `"__MODULE_ID__": true` |
| 3 | **Gate rute** | `app/Backend/config/optional_modules.php` | Cheia `__MODULE_ID__` (blochează acces când modulul e oprit) |
| 4 | **Rute URL** | Auto din `provides.slugs` + `admin/config/routes_bootstrap.php` | `/admin/__URL_SLUG__` |
| 5 | **Shim UI** (recomandat) | `admin/Templates/admin/pages/__URL_SLUG__/__URL_SLUG__.php` | 3 linii → `OptionalModulePageShim::render()` |
| 6 | **API proxy** | `admin/public/api/__MODULE_ID___endpoint.php` | Apelează `Crud__MODULE_ID__Handler::handle()` |
| 7 | **Permisiuni API** | `admin/config/api_workspace_features.php` | `'__MODULE_ID___endpoint.php' => 'module.__MODULE_ID__'` |
| 8 | **AI Ollama** | `src/Service/__STUDLY__AiService.php` | `ModuleOllamaSupport` — același `.env` ca admin |

## Flux request

```
Browser /admin/__URL_SLUG__
  → routes_bootstrap (slug din optional_modules / module.json)
  → AdminPageResolver → shim admin/Templates SAU modules/{id}/pages/ direct
  → OptionalModulePageShim → modules/__MODULE_ID__/pages/__URL_SLUG__.php

API POST /admin/api/__MODULE_ID___endpoint.php
  → Crud__MODULE_ID__Handler
  → __STUDLY__Controller / __STUDLY__Service
```

## Generator automat

**Interfață web (recomandat):** `http://besoiupieseimport.test/module_generator/public/`

CLI din `F:\laragon\www\besoiupieseimport\module_generator\`:

```bash
php bin/generate.php --id=exemplu --name="Modul Exemplu"
php bin/verify.php --id=exemplu
```

## Reguli

- **Tot codul modulului** stă în `modules/__MODULE_ID__/`.
- **CORE** (`app/Backend/src/`) nu importă `use Besoiu\Modules\...` — doar hook-uri opționale.
- **Un singur nume** pentru pagină: folosește `__URL_SLUG__` (kebab-case), nu duplica `snake` + `kebab`.
