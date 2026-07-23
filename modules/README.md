# Module admin Besoiu

Modulele admin stau **lângă** folderul `admin/`, nu în `app/`.

```
modules/
  users/            # core — autentificare + conturi operatori
  furnizori/        # optional — CRUD B2B, comparare, sync, profil
  clienti/          # optional — CRM clienți magazin
  supplier_search/  # optional — căutare live B2B + coș (dep: furnizori)
  import_pro/       # optional — Import & Matching Pro (motor principal import)
  coada_import/     # optional — coadă import staging (importreview) — după import-pro
  adaos_comercial/  # optional — reguli adaos comercial
  categorii/        # optional — taxonomie CMS (categorii, mărci, modele)
```

**Flux import:** `import_pro` (MatchingPro) → `import_produse` (staging) → `coada_import` (review) → `produse` (publish). Modulul legacy `import/` (CSV/hub vechi) este **dezactivat** — folosește `/admin/import-pro`.

Integrare: `ModuleRegistry` bootează din `modules/*/module.json`; state în `admin/storage/modules/state.json`.

**Nu sunt module plug-in** (rămân în CORE): import CSV/coadă/cron, caiet comenzi.

Ghid: [`ALGORITM_MODULE_PLUG_IN.md`](ALGORITM_MODULE_PLUG_IN.md)

## Șablon + generator

| Resursă | Locație |
|---------|---------|
| Modul gol (copiere manuală) | `modules/_template/` + `CONNECT.md` |
| **Interfață web wizard + Ollama** | `F:\laragon\www\besoiupieseimport\module_generator\public\` |
| Generator CLI | `F:\laragon\www\besoiupieseimport\module_generator\bin\` |

Interfață: `http://besoiupieseimport.test/module_generator/public/`

```bash
cd F:\laragon\www\besoiupieseimport\module_generator
php bin/generate.php --id=exemplu --name="Modul Exemplu"
php bin/verify.php --id=exemplu
```
