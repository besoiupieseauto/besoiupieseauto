# Conectare modul — checklist (7 puncte)

După ce copiezi sau generezi modulul, verifică aceste legături între `modules/{id}/` și sistemul Besoiu.

| # | Punct | Fișier | Ce face |
|---|-------|--------|---------|
| 1 | **Manifest** | `modules/adaos_comercial/module.json` | Identitate, `provides.slugs/paths`, permisiuni, nav |
| 2 | **Activare** | `app/Backend/storage/modules/state.json` | `"adaos_comercial": true` |
| 3 | **Gate rute** | `app/Backend/config/optional_modules.php` | Cheia `adaos_comercial` (blochează acces când modulul e oprit) |
| 4 | **Rute URL** | Auto din `provides.slugs` + `admin/config/routes_bootstrap.php` | `/admin/adaoscomercial` |
| 5 | **Shim UI** (recomandat) | `admin/Templates/admin/pages/adaoscomercial/adaoscomercial.php` | 3 linii → `OptionalModulePageShim::render()` |
| 6 | **API proxy** | `admin/public/api/adaos_comercial_endpoint.php` | Apelează `Crudadaos_comercialHandler::handle()` |
| 7 | **Permisiuni API** | `admin/config/api_workspace_features.php` | `'adaos_comercial_endpoint.php' => 'module.adaos_comercial'` |
| 8 | **AI Ollama** | `src/Service/AdaosComercialAiService.php` | `ModuleOllamaSupport` — același `.env` ca admin |

## Flux request

```
Browser /admin/adaoscomercial
  → routes_bootstrap (slug din optional_modules / module.json)
  → AdminPageResolver → shim admin/Templates SAU modules/{id}/pages/ direct
  → OptionalModulePageShim → modules/adaos_comercial/pages/adaoscomercial.php

API POST /admin/api/adaos_comercial_endpoint.php
  → Crudadaos_comercialHandler
  → AdaosComercialController / AdaosComercialService
```

## Generator automat

**Interfață web (recomandat):** `http://besoiupieseimport.test/module_generator/public/`

CLI din `F:\laragon\www\besoiupieseimport\module_generator\`:

```bash
php bin/generate.php --id=exemplu --name="Modul Exemplu"
php bin/verify.php --id=exemplu
```

## Reguli

- **Tot codul modulului** stă în `modules/adaos_comercial/`.
- **CORE** (`app/Backend/src/`) nu importă `use Besoiu\Modules\...` — doar hook-uri opționale.
- **Un singur nume** pentru pagină: folosește `adaoscomercial` (kebab-case), nu duplica `snake` + `kebab`.
