# Baze de date — export

Dump-uri MySQL pentru instalare locală / staging.

| Fișier | Bază de date | Dimensiune aprox. |
|--------|--------------|-------------------|
| `besoiupieseauto.sql` | Site + EvaSystem (`besoiupieseauto.ro`) | ~17 MB |
| `caietcom_comenzilv.sql.gz` | ERP Laravel legacy (`caietcom_comenzilv`) | ~143 MB (comprimat) |

## Restaurare

### Baza principală (site + admin)

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS \`besoiupieseauto.ro\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p besoiupieseauto.ro < SQL/besoiupieseauto.sql
```

### Baza legacy (caiet comenzi)

```bash
# Windows PowerShell
gzip -d SQL/caietcom_comenzilv.sql.gz
# sau: 7z x SQL/caietcom_comenzilv.sql.gz

mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS caietcom_comenzilv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p caietcom_comenzilv < SQL/caietcom_comenzilv.sql
```

## Configurare `.env`

După import, aliniază `admin/.env`:

```
DB_NAME=besoiupieseauto.ro
LEGACY_DB_NAME=caietcom_comenzilv
```

## Notă

`caietcom_comenzilv.sql.gz` este stocat prin **Git LFS** (fișier mare). La clone:

```bash
git lfs install
git lfs pull
```
