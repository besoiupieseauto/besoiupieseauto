# Modul Import & Matching Produse

Modul unificat în `import/` — înlocuiește/logică comună pentru:
- import manual CSV (buton **Scanează**)
- import automat din foldere per furnizor (cron)
- matching produse: EAN → SKU → fuzzy nume → fără match + detectare conflicte

## Acces web

```
http://besoiupieseimport.test/import/
```

## Structură

```
import/
├── config/           # suppliers.json, settings.json, demo_catalog.json
├── uploads/temp/     # fișiere încărcate manual (Mod 1)
├── suppliers/        # foldere per furnizor (Mod 2)
├── processed/        # arhivă fișiere procesate
├── reports/          # rapoarte JSON + CSV
├── logs/             # import_YYYY-MM-DD.log
├── state/            # hash-uri procesate (idempotență)
├── src/              # Python: parser, matcher, scanner, cron
├── api/              # PHP endpoints
└── index.php         # interfață web
```

## Fluxuri

### Mod 1 — Manual
1. Încarcă CSV în UI sau copiază în `uploads/temp/`
2. Apasă **Scanează**
3. Rezultat în UI + `reports/*.json` + `reports/*.csv`
4. Fișier mutat în `processed/<furnizor>/`

### Mod 2 — Cron
1. Pune CSV în `suppliers/<furnizor>/` (ex: `suppliers/autototal/lista.csv`)
2. Rulează:
   ```bat
   import\cron_watcher.bat
   ```
   sau din UI: **Rulează cron watcher**
3. Fișierele deja procesate (același hash) sunt sărite

## CLI Python

```bash
cd import/src

# Scan uploads/temp
python run_import.py scan --uploads

# Scan fișier specific (demo 10 produse, fără arhivare)
python run_import.py scan --file ../suppliers/furnizor_demo/test_10_produse.csv --supplier furnizor_demo --no-archive

# Cron watcher
python cron_watcher.py

# Rapoarte recente
python run_import.py reports --limit 10
```

## Matching (4 niveluri)

| Prioritate | Metodă | Status |
|------------|--------|--------|
| 1 | EAN exact | `exact` |
| 2 | SKU mapat / cod normalizat | `exact` |
| 3 | Fuzzy nume (≥85% = exact, 70–85% = probabil) | `probable` |
| 4 | Sub prag | `no_match` |
| + | Preț/stoc diferit semnificativ | `conflict` |

Configurare în `config/settings.json`:
- `fuzzy_threshold`, `price_conflict_pct`, `stock_conflict`

## Furnizori configurați

`config/suppliers.json` — Autototal, Autonet, Materom, Elit, Intercars, Autopartner, generic, `furnizor_demo`.

Catalog intern:
- **Producție**: Base CSV (`Fisierile Matc/`) + `price-index.sqlite`
- **Demo**: `config/demo_catalog.json` (10 produse test)

## Test 10 produse

Fișier: `suppliers/furnizor_demo/test_10_produse.csv`

| Rând | Caz testat |
|------|------------|
| F001 | Match exact EAN |
| F002 | Match exact EAN + stoc 0 |
| F003 | Match fuzzy nume (fără EAN) |
| F004 | Match exact cod |
| F005 | Conflict preț (15 vs 18 în catalog) |
| F006 | Match exact |
| F007 | Match exact |
| F008 | Fără match (produs necunoscut) |
| F009 | Match exact |
| F010 | Match fuzzy / nume extins |

În UI: buton **Test 10 produse (demo)**.

## API PHP

| Endpoint | Metodă | Rol |
|----------|--------|-----|
| `api/upload.php` | POST `files[]` | Upload în temp |
| `api/scan.php` | POST `mode=uploads\|demo\|cron` | Declanșează scan |
| `api/reports.php` | GET `?id=` sau `?limit=` | Rapoarte |

## Cron Windows (Task Scheduler)

Program: `f:\laragon\www\besoiupieseimport\import\cron_watcher.bat`  
Interval recomandat: 15–30 min (vezi `config/settings.json` → `cron_interval_minutes`).

## Integrare cu restul proiectului

Motorul reutilizează aceleași surse ca `ProductMatcher.php`:
- `Fisierile Matc/` — catalog Base
- `Prelucrare fisiere bovsoft-base/api/cache/price-index.sqlite` — prețuri
- Reguli furnizori aliniate cu `.cursor/rules/fisiere-pret-furnizori.mdc`

Modulele existente (`Prelucrare fisiere bovsoft-base/`, `fetch product/`) rămân funcționale; `import/` este punctul unificat nou.
