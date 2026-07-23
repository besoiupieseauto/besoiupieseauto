# Scraper Web — modul ERP (bridge)

Modul **plug-in** care expune UI-ul Scraper în admin ERP.

## Arhitectură (după migrare)

```
/admin/scraper-web
  → modules/scraper_web/
  → app/Import/bootstrap.php     (căi + Ollama unificat)
  → app/Import/Scraper/          (motor scraping)
  → app/Import/modulOrchestr/    (vision + orchestr LLM)

Date mari (Poze, CSV): BESOIU_IMPORT_DATA_ROOT → besoiupieseimport legacy
Ollama: un singur .env admin (OLLAMA_*) propagat la Import
```

## Configurare

| Env | Rol |
|-----|-----|
| `BESOIU_SCRAPER_ROOT` | Cale motor (implicit `app/Import/Scraper`) |
| `BESOIU_IMPORT_DATA_ROOT` | Poze, Fisierile Matc, autoparner |
| `OLLAMA_*` | Din `admin/.env` — folosit de toate modulele |

`config/scraper-root.php` — candidați căi + assets

## Verificare

```bash
php modules/scraper_web/tools/test_module_bridge.php
```
