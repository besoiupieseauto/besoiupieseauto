# Matc DB Tools — Laragon

Toolkit PHP simplu pentru match TecDoc din baza **besoiu_tecdoc_matc** (import full Matc).

## Baze

| Baza | Rol |
|------|-----|
| `besoiu_tecdoc_matc_20260826` | Snapshot full import (~2.153.801 produse) |
| `besoiu_tecdoc_matc` | Alias scurt / filtrare (partiala) |

Baza activa se seteaza in `active_db.json` sau env `TECDOC_MATC_DB`.

## Comenzi CLI

```bash
# Status toate bazele Matc
php admin/tools/matc/status.php

# Activeaza snapshot-ul full + creeaza indexuri lookup
php admin/tools/matc/activate.php --db=besoiu_tecdoc_matc_20260826 --indexes

# Lookup simplu
php admin/tools/matc/lookup.php --brand=DAYCO --code=KTBWP1230

# Match mii de coduri din fisier (brand;cod per linie)
php admin/tools/matc/batch_match.php --file=coduri.txt --out=rezultat.csv

# Doar indexuri (match rapid pe 3M coduri)
php admin/tools/matc/ensure_indexes.php --db=besoiu_tecdoc_matc_20260826
```

## UI browser

`http://besoiupieseauto.ro.test/admin/tools/matc/`

## Format fisier batch

```
DAYCO;KTBWP1230
BOSCH;0451103316
# comentariu
MAHLE;KX338/22D
```

Suporta separator `;`, `,` sau TAB. Proceseaza in chunk-uri de 1000 linii via tabela temporara MEMORY.

## Env optional (.env)

```
TECDOC_MATC_DB=besoiu_tecdoc_matc_20260826
TECDOC_MATC_HOST=127.0.0.1
TECDOC_MATC_USER=root
TECDOC_MATC_PASS=
```
