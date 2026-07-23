# Conectare modul — checklist (7 puncte)

După ce copiezi sau generezi modulul, verifică aceste legături între `modules/{id}/` și sistemul Besoiu.

| # | Punct | Fișier | Ce face |
|---|-------|--------|---------|
| 1 | **Manifest** | `modules/categorii/module.json` | Identitate, `provides.slugs/paths`, permisiuni, nav |
| 2 | **Activare** | `app/Backend/storage/modules/state.json` | `"categorii": true` |
| 3 | **Gate rute** | `app/Backend/config/optional_modules.php` | Cheia `categorii` (blochează acces când modulul e oprit) |
| 4 | **Rute URL** | Auto din `provides.slugs` + `admin/config/routes_bootstrap.php` | `/admin/categorii` |
| 5 | **Shim UI** (recomandat) | `admin/Templates/admin/pages/categorii/categorii.php` | 3 linii → `OptionalModulePageShim::render()` |
| 6 | **API proxy** | `admin/public/api/categorii_endpoint.php` | Apelează `CrudcategoriiHandler::handle()` |
| 7 | **Permisiuni API** | `admin/config/api_workspace_features.php` | `'categorii_endpoint.php' => 'module.categorii'` |
| 8 | **AI Ollama** | `src/Service/CategoriiAiService.php` | `ModuleOllamaSupport` — același `.env` ca admin |

## Flux request

```
Browser /admin/categorii
  → routes_bootstrap (slug din optional_modules / module.json)
  → AdminPageResolver → shim admin/Templates SAU modules/{id}/pages/ direct
  → OptionalModulePageShim → modules/categorii/pages/categorii.php

API POST /admin/api/categorii_endpoint.php
  → CrudcategoriiHandler
  → CategoriiController / CategoriiService
```

## Generator automat

**Interfață web (recomandat):** `http://besoiupieseimport.test/module_generator/public/`

CLI din `F:\laragon\www\besoiupieseimport\module_generator\`:

```bash
php bin/generate.php --id=exemplu --name="Modul Exemplu"
php bin/verify.php --id=exemplu
```

## Reguli

- **Tot codul modulului** stă în `modules/categorii/`.
- **CORE** (`app/Backend/src/`) nu importă `use Besoiu\Modules\...` — doar hook-uri opționale.
- **Un singur nume** pentru pagină: folosește `categorii` (kebab-case), nu duplica `snake` + `kebab`.
