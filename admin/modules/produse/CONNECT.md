# Conectare modul — checklist (7 puncte)

După ce copiezi sau generezi modulul, verifică aceste legături între `modules/{id}/` și sistemul Besoiu.

| # | Punct | Fișier | Ce face |
|---|-------|--------|---------|
| 1 | **Manifest** | `modules/produse/module.json` | Identitate, `provides.slugs/paths`, permisiuni, nav |
| 2 | **Activare** | `app/Backend/storage/modules/state.json` | `"produse": true` |
| 3 | **Gate rute** | `app/Backend/config/optional_modules.php` | Cheia `produse` (blochează acces când modulul e oprit) |
| 4 | **Rute URL** | Auto din `provides.slugs` + `admin/config/routes_bootstrap.php` | `/admin/product` |
| 5 | **Shim UI** (recomandat) | `admin/Templates/admin/pages/product/product.php` | 3 linii → `OptionalModulePageShim::render()` |
| 6 | **API proxy** | `admin/public/api/produse_endpoint.php` | Apelează `CrudproduseHandler::handle()` |
| 7 | **Permisiuni API** | `admin/config/api_workspace_features.php` | `'produse_endpoint.php' => 'module.produse'` |
| 8 | **AI Ollama** | `src/Service/ProduseAiService.php` | `ModuleOllamaSupport` — același `.env` ca admin |

## Flux request

```
Browser /admin/product
  → routes_bootstrap (slug din optional_modules / module.json)
  → AdminPageResolver → shim admin/Templates SAU modules/{id}/pages/ direct
  → OptionalModulePageShim → modules/produse/pages/product.php

API POST /admin/api/produse_endpoint.php
  → CrudproduseHandler
  → ProduseController / ProduseService
```

## Generator automat

**Interfață web (recomandat):** `http://besoiupieseimport.test/module_generator/public/`

CLI din `F:\laragon\www\besoiupieseimport\module_generator\`:

```bash
php bin/generate.php --id=exemplu --name="Modul Exemplu"
php bin/verify.php --id=exemplu
```

## Reguli

- **Tot codul modulului** stă în `modules/produse/`.
- **CORE** (`app/Backend/src/`) nu importă `use Besoiu\Modules\...` — doar hook-uri opționale.
- **Un singur nume** pentru pagină: folosește `product` (kebab-case), nu duplica `snake` + `kebab`.
